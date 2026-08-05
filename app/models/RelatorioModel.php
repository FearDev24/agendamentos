<?php
// app/models/RelatorioModel.php - Consultas consolidadas para o painel e relatórios
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Consultas agregadas do painel administrativo (docs/FSD.md seção 22).
 *
 * Concentra o que é apenas leitura consolidada — métricas do dia, faturamento,
 * desempenho por profissional e a base das exportações — mantendo os demais
 * Models focados nas suas próprias entidades.
 *
 * Regra financeira adotada: **receita é dinheiro efetivamente recebido**, ou
 * seja, pagamentos com status `aprovado`. Agendamento confirmado sem pagamento
 * compensado não entra no faturamento.
 */
class RelatorioModel extends BaseModel {

    // =================================================================
    // Dashboard
    // =================================================================

    /**
     * Números do dia para os cartões de métrica do painel.
     */
    public function metricasDoDia(string $data): array {
        $linha = $this->buscarUm(
            "SELECT
                COUNT(*)                                              AS total,
                SUM(situacao = 'confirmado')                          AS confirmados,
                SUM(situacao = 'concluido')                           AS concluidos,
                SUM(situacao = 'aguardando_pagamento')                AS aguardando,
                SUM(situacao = 'cancelado')                           AS cancelados,
                SUM(situacao = 'falta')                               AS faltas
             FROM agendamentos
             WHERE data_agendamento = :data AND deleted_at IS NULL",
            [':data' => $data]
        );

        return [
            'total'       => (int)($linha['total'] ?? 0),
            'confirmados' => (int)($linha['confirmados'] ?? 0),
            'concluidos'  => (int)($linha['concluidos'] ?? 0),
            'aguardando'  => (int)($linha['aguardando'] ?? 0),
            'cancelados'  => (int)($linha['cancelados'] ?? 0),
            'faltas'      => (int)($linha['faltas'] ?? 0),
            'faturamento' => $this->receitaNoPeriodo($data, $data),
        ];
    }

    /**
     * Atendimentos concluídos que ainda não foram avaliados nem encerrados,
     * usados nos alertas do painel.
     */
    public function pendentesDeConclusao(string $data): array {
        return $this->buscarTodos(
            "SELECT a.codigo_reserva, a.hora_inicio, a.hora_fim,
                    u.nome AS cliente_nome, pu.nome AS profissional_nome
             FROM agendamentos a
             INNER JOIN usuarios u ON u.id = a.cliente_id
             INNER JOIN profissionais p ON p.id = a.profissional_id
             INNER JOIN usuarios pu ON pu.id = p.usuario_id
             WHERE a.data_agendamento <= :data
               AND a.situacao = 'confirmado'
               AND a.deleted_at IS NULL
               AND CONCAT(a.data_agendamento, ' ', a.hora_fim) < NOW()
             ORDER BY a.data_agendamento DESC, a.hora_inicio ASC
             LIMIT 20",
            [':data' => $data]
        );
    }

    // =================================================================
    // Financeiro
    // =================================================================

    /**
     * Receita líquida: dinheiro que entrou e permaneceu no caixa.
     *
     * Um pagamento estornado deixa de ter status `aprovado`, portanto já sai
     * naturalmente desta conta. É o número usado no faturamento do dia.
     */
    public function receitaNoPeriodo(string $de, string $ate): float {
        return (float)$this->buscarValor(
            "SELECT COALESCE(SUM(valor), 0) FROM pagamentos
             WHERE status_pagamento = 'aprovado' AND DATE(pago_em) BETWEEN :de AND :ate",
            [':de' => $de, ':ate' => $ate]
        );
    }

    /**
     * Receita bruta: tudo que chegou a ser compensado no período, inclusive o
     * que foi devolvido depois.
     *
     * Sem separar bruta de líquida, o estorno seria descontado duas vezes do
     * lucro: uma ao sair de `aprovado` e outra ao ser subtraído como estorno.
     */
    public function receitaBrutaNoPeriodo(string $de, string $ate): float {
        return (float)$this->buscarValor(
            "SELECT COALESCE(SUM(valor), 0) FROM pagamentos
             WHERE status_pagamento IN ('aprovado', 'estornado')
               AND DATE(pago_em) BETWEEN :de AND :ate",
            [':de' => $de, ':ate' => $ate]
        );
    }

    /**
     * Valores devolvidos aos clientes no período.
     */
    public function estornosNoPeriodo(string $de, string $ate): float {
        return (float)$this->buscarValor(
            "SELECT COALESCE(SUM(valor), 0) FROM pagamentos
             WHERE status_pagamento = 'estornado' AND DATE(pago_em) BETWEEN :de AND :ate",
            [':de' => $de, ':ate' => $ate]
        );
    }

    /**
     * Resumo financeiro do período: receita, despesas e lucro líquido.
     *
     * Vale sempre: receita_liquida = receita_bruta − estornos.
     */
    public function resumoFinanceiro(string $de, string $ate): array {
        $bruta = $this->receitaBrutaNoPeriodo($de, $ate);
        $estornos = $this->estornosNoPeriodo($de, $ate);
        $despesas = (new DespesaModel())->totalNoPeriodo($de, $ate);

        $receitaLiquida = round($bruta - $estornos, 2);
        $lucro = round($receitaLiquida - $despesas, 2);

        return [
            'receita_bruta'   => round($bruta, 2),
            'estornos'        => round($estornos, 2),
            'receita_liquida' => $receitaLiquida,
            'despesas'        => round($despesas, 2),
            'lucro_liquido'   => $lucro,
            'margem'          => $receitaLiquida > 0 ? round(($lucro / $receitaLiquida) * 100, 1) : 0.0,
        ];
    }

    /**
     * Faturamento dia a dia, base do gráfico de evolução.
     */
    public function receitaPorDia(string $de, string $ate): array {
        return $this->buscarTodos(
            "SELECT DATE(pago_em) AS dia, COALESCE(SUM(valor), 0) AS total
             FROM pagamentos
             WHERE status_pagamento = 'aprovado' AND DATE(pago_em) BETWEEN :de AND :ate
             GROUP BY DATE(pago_em)
             ORDER BY dia ASC",
            [':de' => $de, ':ate' => $ate]
        );
    }

    /**
     * Receita separada por forma de pagamento.
     */
    public function receitaPorFormaDePagamento(string $de, string $ate): array {
        return $this->buscarTodos(
            "SELECT forma_pagamento, COUNT(*) AS transacoes, COALESCE(SUM(valor), 0) AS total
             FROM pagamentos
             WHERE status_pagamento = 'aprovado' AND DATE(pago_em) BETWEEN :de AND :ate
             GROUP BY forma_pagamento
             ORDER BY total DESC",
            [':de' => $de, ':ate' => $ate]
        );
    }

    // =================================================================
    // Relatórios operacionais
    // =================================================================

    /**
     * Listagem de agendamentos com todos os filtros da tela de relatórios.
     * É também a fonte da exportação em planilha.
     */
    public function agendamentos(array $filtros): array {
        $sql = "SELECT a.codigo_reserva, a.data_agendamento, a.hora_inicio, a.hora_fim,
                       a.valor_total, a.valor_desconto, a.credito_utilizado, a.situacao,
                       a.justificativa_desconto,
                       u.nome AS cliente_nome, u.telefone AS cliente_telefone,
                       pu.nome AS profissional_nome,
                       (SELECT GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ')
                        FROM agendamento_servicos ags
                        INNER JOIN servicos s ON s.id = ags.servico_id
                        WHERE ags.agendamento_id = a.id) AS servicos,
                       (SELECT pg.forma_pagamento FROM pagamentos pg
                        WHERE pg.agendamento_id = a.id AND pg.status_pagamento = 'aprovado'
                        ORDER BY pg.id DESC LIMIT 1) AS forma_pagamento
                FROM agendamentos a
                INNER JOIN usuarios u ON u.id = a.cliente_id
                INNER JOIN profissionais p ON p.id = a.profissional_id
                INNER JOIN usuarios pu ON pu.id = p.usuario_id
                WHERE a.deleted_at IS NULL
                  AND a.data_agendamento BETWEEN :de AND :ate";

        $parametros = [':de' => $filtros['de'], ':ate' => $filtros['ate']];

        if (!empty($filtros['profissional_id'])) {
            $sql .= ' AND a.profissional_id = :profissional';
            $parametros[':profissional'] = (int)$filtros['profissional_id'];
        }

        if (!empty($filtros['situacao'])) {
            $sql .= ' AND a.situacao = :situacao';
            $parametros[':situacao'] = $filtros['situacao'];
        }

        if (!empty($filtros['servico_id'])) {
            $sql .= ' AND EXISTS (SELECT 1 FROM agendamento_servicos ags2
                                  WHERE ags2.agendamento_id = a.id AND ags2.servico_id = :servico)';
            $parametros[':servico'] = (int)$filtros['servico_id'];
        }

        $sql .= ' ORDER BY a.data_agendamento DESC, a.hora_inicio DESC';

        return $this->buscarTodos($sql, $parametros);
    }

    /**
     * Desempenho por profissional: atendimentos, faturamento e nota média.
     */
    public function desempenhoDosProfissionais(string $de, string $ate): array {
        return $this->buscarTodos(
            "SELECT pu.nome AS profissional_nome,
                    COUNT(a.id)                                   AS total,
                    SUM(a.situacao = 'concluido')                 AS concluidos,
                    SUM(a.situacao = 'falta')                     AS faltas,
                    SUM(a.situacao = 'cancelado')                 AS cancelados,
                    COALESCE(SUM(CASE WHEN a.situacao = 'concluido' THEN a.valor_total ELSE 0 END), 0) AS faturamento,
                    COALESCE(SUM(CASE WHEN a.situacao = 'concluido' THEN a.duracao_total_minutos ELSE 0 END), 0) AS minutos_ocupados,
                    (SELECT ROUND(AVG(av.nota), 1) FROM avaliacoes av WHERE av.profissional_id = p.id) AS nota_media
             FROM profissionais p
             INNER JOIN usuarios pu ON pu.id = p.usuario_id
             LEFT JOIN agendamentos a
                    ON a.profissional_id = p.id
                   AND a.deleted_at IS NULL
                   AND a.data_agendamento BETWEEN :de AND :ate
             WHERE p.deleted_at IS NULL AND pu.deleted_at IS NULL
             GROUP BY p.id, pu.nome
             ORDER BY faturamento DESC, pu.nome ASC",
            [':de' => $de, ':ate' => $ate]
        );
    }

    /**
     * Serviços mais contratados no período.
     */
    public function servicosMaisContratados(string $de, string $ate): array {
        return $this->buscarTodos(
            "SELECT s.nome, COUNT(ags.id) AS contratacoes,
                    COALESCE(SUM(ags.preco_aplicado), 0) AS total
             FROM agendamento_servicos ags
             INNER JOIN servicos s ON s.id = ags.servico_id
             INNER JOIN agendamentos a ON a.id = ags.agendamento_id
             WHERE a.deleted_at IS NULL
               AND a.situacao IN ('confirmado', 'concluido')
               AND a.data_agendamento BETWEEN :de AND :ate
             GROUP BY s.id, s.nome
             ORDER BY contratacoes DESC, total DESC",
            [':de' => $de, ':ate' => $ate]
        );
    }

    // =================================================================
    // Agenda administrativa
    // =================================================================

    /**
     * Agendamentos de um intervalo, para as visões diária, semanal e mensal.
     */
    public function agendaDoPeriodo(string $de, string $ate, ?int $profissionalId = null, string $situacao = ''): array {
        $sql = "SELECT a.*, u.nome AS cliente_nome, u.telefone AS cliente_telefone,
                       pu.nome AS profissional_nome,
                       (SELECT GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ')
                        FROM agendamento_servicos ags
                        INNER JOIN servicos s ON s.id = ags.servico_id
                        WHERE ags.agendamento_id = a.id) AS servicos
                FROM agendamentos a
                INNER JOIN usuarios u ON u.id = a.cliente_id
                INNER JOIN profissionais p ON p.id = a.profissional_id
                INNER JOIN usuarios pu ON pu.id = p.usuario_id
                WHERE a.deleted_at IS NULL
                  AND a.data_agendamento BETWEEN :de AND :ate";

        $parametros = [':de' => $de, ':ate' => $ate];

        if ($profissionalId !== null) {
            $sql .= ' AND a.profissional_id = :profissional';
            $parametros[':profissional'] = $profissionalId;
        }

        if ($situacao !== '') {
            $sql .= ' AND a.situacao = :situacao';
            $parametros[':situacao'] = $situacao;
        }

        $sql .= ' ORDER BY a.data_agendamento ASC, a.hora_inicio ASC';

        return $this->buscarTodos($sql, $parametros);
    }

    /**
     * Contagem por dia, usada para pintar o calendário da visão mensal.
     */
    public function contagemPorDia(string $de, string $ate, ?int $profissionalId = null): array {
        $sql = "SELECT data_agendamento,
                       COUNT(*) AS total,
                       SUM(situacao IN ('confirmado', 'concluido')) AS ativos
                FROM agendamentos
                WHERE deleted_at IS NULL
                  AND situacao <> 'cancelado'
                  AND data_agendamento BETWEEN :de AND :ate";

        $parametros = [':de' => $de, ':ate' => $ate];

        if ($profissionalId !== null) {
            $sql .= ' AND profissional_id = :profissional';
            $parametros[':profissional'] = $profissionalId;
        }

        $sql .= ' GROUP BY data_agendamento';

        $mapa = [];
        foreach ($this->buscarTodos($sql, $parametros) as $linha) {
            $mapa[$linha['data_agendamento']] = [
                'total'  => (int)$linha['total'],
                'ativos' => (int)$linha['ativos'],
            ];
        }

        return $mapa;
    }
}
