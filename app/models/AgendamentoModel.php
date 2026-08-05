<?php
// app/models/AgendamentoModel.php - Motor de disponibilidade e criação de reservas
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Motor de disponibilidade de horários (docs/FSD.md seções 12.2 e 14).
 *
 * Regras aplicadas no cálculo:
 *  - Soma das durações de todos os serviços selecionados;
 *  - Intervalo obrigatório de 10 minutos entre atendimentos;
 *  - Jornada semanal do profissional, descontando o intervalo de almoço;
 *  - Bloqueios individuais e do estabelecimento;
 *  - Agendamentos já ocupando a agenda (inclusive reservas ainda no prazo);
 *  - Limite diário de atendimentos do profissional;
 *  - Antecedência mínima de 2 horas e máxima de 30 dias.
 *
 * Todo o cálculo interno é feito em minutos desde a meia-noite, evitando
 * imprecisões de manipulação de strings de horário.
 */
class AgendamentoModel extends BaseModel {

    /** Situações que efetivamente ocupam um horário na agenda */
    public const SITUACOES_ATIVAS = ['aguardando_pagamento', 'confirmado'];

    private SettingModel $settings;

    public function __construct() {
        parent::__construct();
        $this->settings = new SettingModel();
    }

    // =================================================================
    // MOTOR DE DISPONIBILIDADE
    // =================================================================

    /**
     * Calcula os horários livres para uma data e um conjunto de serviços.
     *
     * @param string   $data           Data no formato Y-m-d
     * @param int[]    $servicoIds     Serviços selecionados pelo cliente
     * @param int|null $profissionalId null = "qualquer profissional disponível"
     *
     * @return array Lista de ['hora' => 'HH:MM', 'hora_fim' => 'HH:MM',
     *                         'profissional_id' => int, 'profissional_nome' => string]
     */
    public function horariosDisponiveis(string $data, array $servicoIds, ?int $profissionalId = null): array {
        $servicos = $this->carregarServicos($servicoIds);

        if (empty($servicos)) {
            return [];
        }

        $duracaoTotal = array_sum(array_column($servicos, 'duracao_minutos'));
        $intervalo = $this->settings->getInt('agendamento_intervalo_minutos');
        $passo = max(5, $this->settings->getInt('agendamento_passo_minutos'));

        if (!$this->dataDentroDaJanelaPermitida($data)) {
            return [];
        }

        $diaSemana = (int)date('w', strtotime($data));
        $agora = $this->minutosDeAgoraSeHoje($data);

        $horarios = [];

        foreach ($this->profissionaisAptos($servicoIds, $profissionalId) as $profissional) {
            $idProf = (int)$profissional['id'];

            // Limite diário de atendimentos
            $limite = $profissional['limite_diario_atendimentos'];
            if ($limite !== null && $this->totalAtendimentosNoDia($idProf, $data) >= (int)$limite) {
                continue;
            }

            $jornada = $this->jornadaDoDia($idProf, $diaSemana);
            if ($jornada === null) {
                continue; // folga
            }

            $janelas = $this->janelasDeTrabalho($jornada);
            $janelas = $this->descontarBloqueios($janelas, $idProf, $data);

            $ocupados = $this->intervalosOcupados($idProf, $data, $intervalo);

            foreach ($janelas as [$inicioJanela, $fimJanela]) {
                // Alinha o primeiro horário candidato ao passo da grade
                for ($inicio = $inicioJanela; $inicio + $duracaoTotal <= $fimJanela; $inicio += $passo) {
                    // Antecedência mínima em relação ao horário atual
                    if ($agora !== null && $inicio < $agora) {
                        continue;
                    }

                    $fim = $inicio + $duracaoTotal;

                    if ($this->conflita($inicio, $fim + $intervalo, $ocupados)) {
                        continue;
                    }

                    $chave = $this->paraHora($inicio);

                    // Com "qualquer profissional", o primeiro livre fica com o horário
                    if (!isset($horarios[$chave])) {
                        $horarios[$chave] = [
                            'hora'              => $chave,
                            'hora_fim'          => $this->paraHora($fim),
                            'profissional_id'   => $idProf,
                            'profissional_nome' => $profissional['nome'],
                        ];
                    }
                }
            }
        }

        ksort($horarios);

        return array_values($horarios);
    }

    /**
     * Verifica se um horário específico continua livre.
     * Reexecutado no momento da reserva para evitar corrida entre clientes.
     */
    public function horarioEstaLivre(string $data, string $hora, array $servicoIds, int $profissionalId): bool {
        foreach ($this->horariosDisponiveis($data, $servicoIds, $profissionalId) as $slot) {
            if ($slot['hora'] === substr($hora, 0, 5)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dias com pelo menos um horário livre, usado para montar o calendário.
     */
    public function diasComVaga(array $servicoIds, ?int $profissionalId, int $quantidadeDias = 30): array {
        $dias = [];
        $minHoras = $this->settings->getInt('agendamento_antecedencia_min_horas');
        $inicio = new DateTime('+' . $minHoras . ' hours');

        for ($i = 0; $i <= $quantidadeDias; $i++) {
            $data = (clone $inicio)->modify("+{$i} day")->format('Y-m-d');

            if (!$this->dataDentroDaJanelaPermitida($data)) {
                continue;
            }

            if (!empty($this->horariosDisponiveis($data, $servicoIds, $profissionalId))) {
                $dias[] = $data;
            }
        }

        return $dias;
    }

    // =================================================================
    // CRIAÇÃO DA RESERVA TEMPORÁRIA
    // =================================================================

    /**
     * Cria o agendamento com situação `aguardando_pagamento` e prazo de
     * retenção do horário (padrão: 10 minutos).
     *
     * Os preços e durações são congelados em `agendamento_servicos`, para que
     * alterações futuras no catálogo não afetem agendamentos já feitos.
     *
     * @return array ['codigo' => string, 'id' => int]
     * @throws RuntimeException quando alguma regra de negócio impede a reserva
     */
    public function criarReserva(int $clienteId, int $profissionalId, string $data, string $hora, array $servicoIds): array {
        $servicos = $this->carregarServicos($servicoIds);

        if (empty($servicos)) {
            throw new RuntimeException('Selecione ao menos um serviço válido.');
        }

        if ($this->totalAtivosDoCliente($clienteId) >= $this->settings->getInt('agendamento_max_ativos_por_cliente')) {
            throw new RuntimeException(
                'Você atingiu o limite de ' . $this->settings->getInt('agendamento_max_ativos_por_cliente')
                . ' agendamentos ativos simultâneos. Conclua ou cancele um deles para agendar novamente.'
            );
        }

        // Revalidação: o horário pode ter sido tomado enquanto o cliente decidia
        if (!$this->horarioEstaLivre($data, $hora, $servicoIds, $profissionalId)) {
            throw new RuntimeException('Este horário acabou de ser ocupado. Escolha outro horário.');
        }

        $duracaoTotal = array_sum(array_column($servicos, 'duracao_minutos'));
        $valorTotal = array_sum(array_column($servicos, 'preco'));
        $minutosReserva = $this->settings->getInt('checkout_reserva_minutos');

        $inicio = $this->paraMinutos($hora);
        $horaFim = $this->paraHora($inicio + $duracaoTotal);

        $this->db->beginTransaction();

        try {
            $codigo = $this->gerarCodigoReserva();

            $this->consultar(
                'INSERT INTO agendamentos
                    (codigo_reserva, cliente_id, profissional_id, data_agendamento, hora_inicio, hora_fim,
                     duracao_total_minutos, valor_total, situacao, reserva_expira_em)
                 VALUES
                    (:codigo, :cliente, :profissional, :data, :inicio, :fim,
                     :duracao, :valor, \'aguardando_pagamento\', DATE_ADD(NOW(), INTERVAL :minutos MINUTE))',
                [
                    ':codigo'       => $codigo,
                    ':cliente'      => $clienteId,
                    ':profissional' => $profissionalId,
                    ':data'         => $data,
                    ':inicio'       => substr($hora, 0, 5) . ':00',
                    ':fim'          => $horaFim . ':00',
                    ':duracao'      => $duracaoTotal,
                    ':valor'        => $valorTotal,
                    ':minutos'      => $minutosReserva,
                ]
            );

            $id = (int)$this->db->lastInsertId();

            // Congela preço e duração vigentes na contratação
            $stmt = $this->db->prepare(
                'INSERT INTO agendamento_servicos (agendamento_id, servico_id, preco_aplicado, duracao_aplicada_minutos)
                 VALUES (:agendamento, :servico, :preco, :duracao)'
            );

            foreach ($servicos as $servico) {
                $stmt->execute([
                    ':agendamento' => $id,
                    ':servico'     => (int)$servico['id'],
                    ':preco'       => $servico['preco'],
                    ':duracao'     => (int)$servico['duracao_minutos'],
                ]);
            }

            $this->db->commit();

            return ['codigo' => $codigo, 'id' => $id];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cancela reservas cujo prazo de pagamento expirou, liberando os horários.
     * Chamado antes de cada cálculo de disponibilidade e pelo Cron da Fase 9.
     */
    public function expirarReservasVencidas(): int {
        return $this->consultar(
            "UPDATE agendamentos
             SET situacao = 'cancelado',
                 motivo_cancelamento = 'Reserva expirada: pagamento não concluído no prazo.'
             WHERE situacao = 'aguardando_pagamento'
               AND reserva_expira_em IS NOT NULL
               AND reserva_expira_em < NOW()
               AND deleted_at IS NULL"
        )->rowCount();
    }

    // =================================================================
    // OPERAÇÕES DO CLIENTE (Fase 7)
    // =================================================================

    /**
     * Agendamentos do cliente separados em próximos e histórico.
     */
    public function listarDoCliente(int $clienteId): array {
        $this->expirarReservasVencidas();

        $linhas = $this->buscarTodos(
            "SELECT a.*, pu.nome AS profissional_nome,
                    (SELECT GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ')
                     FROM agendamento_servicos ags
                     INNER JOIN servicos s ON s.id = ags.servico_id
                     WHERE ags.agendamento_id = a.id) AS servicos,
                    (SELECT COUNT(*) FROM avaliacoes av WHERE av.agendamento_id = a.id) AS avaliado
             FROM agendamentos a
             INNER JOIN profissionais p ON p.id = a.profissional_id
             INNER JOIN usuarios pu ON pu.id = p.usuario_id
             WHERE a.cliente_id = :id AND a.deleted_at IS NULL
             ORDER BY a.data_agendamento DESC, a.hora_inicio DESC",
            [':id' => $clienteId]
        );

        $proximos = [];
        $historico = [];
        $agora = date('Y-m-d H:i:s');

        foreach ($linhas as $linha) {
            $momento = $linha['data_agendamento'] . ' ' . $linha['hora_inicio'];
            $ativo = in_array($linha['situacao'], self::SITUACOES_ATIVAS, true);

            if ($ativo && $momento > $agora) {
                $proximos[] = $linha;
            } else {
                $historico[] = $linha;
            }
        }

        // Próximos em ordem cronológica crescente
        usort($proximos, fn($a, $b) => strcmp(
            $a['data_agendamento'] . $a['hora_inicio'],
            $b['data_agendamento'] . $b['hora_inicio']
        ));

        return ['proximos' => $proximos, 'historico' => $historico];
    }

    /**
     * Horas que faltam para o atendimento. Negativo se já passou.
     */
    public function horasAteAtendimento(array $agendamento): float {
        $momento = strtotime($agendamento['data_agendamento'] . ' ' . $agendamento['hora_inicio']);
        return ($momento - time()) / 3600;
    }

    /**
     * Regra das 24 horas (docs/FSD.md seção 14.5).
     */
    public function dentroDaJanelaDe24h(array $agendamento): bool {
        return $this->horasAteAtendimento($agendamento)
            >= $this->settings->getInt('cancelamento_janela_horas');
    }

    /**
     * Indica se o cliente ainda pode reagendar este atendimento.
     */
    public function podeReagendar(array $agendamento): bool {
        return $agendamento['situacao'] === 'confirmado'
            && $this->dentroDaJanelaDe24h($agendamento)
            && (int)$agendamento['reagendamentos_realizados'] < $this->settings->getInt('reagendamento_limite');
    }

    public function podeCancelar(array $agendamento): bool {
        return in_array($agendamento['situacao'], self::SITUACOES_ATIVAS, true)
            && $this->horasAteAtendimento($agendamento) > 0;
    }

    /**
     * Total efetivamente pago em um agendamento (pagamentos aprovados).
     */
    public function valorJaPago(int $agendamentoId): float {
        return (float)$this->buscarValor(
            "SELECT COALESCE(SUM(valor), 0) FROM pagamentos
             WHERE agendamento_id = :id AND status_pagamento = 'aprovado'",
            [':id' => $agendamentoId]
        );
    }

    /**
     * Quanto ainda falta pagar, descontando o crédito interno aplicado.
     */
    public function valorDevido(array $agendamento): float {
        $total = (float)$agendamento['valor_total']
               - (float)$agendamento['valor_desconto']
               - (float)$agendamento['credito_utilizado'];

        return max(0.0, round($total - $this->valorJaPago((int)$agendamento['id']), 2));
    }

    /**
     * Cancela o agendamento aplicando a regra de 24 horas.
     *
     * Com 24h ou mais de antecedência o valor pago é devolvido; com menos,
     * não há reembolso. O crédito interno eventualmente utilizado sempre
     * retorna para o cliente, pois não é dinheiro do estabelecimento.
     *
     * @return array ['reembolso' => float, 'credito_devolvido' => float, 'no_prazo' => bool]
     */
    public function cancelar(int $agendamentoId, int $clienteId, string $motivo, bool $peloEstabelecimento = false): array {
        $this->db->beginTransaction();

        try {
            $agendamento = $this->buscarUm(
                'SELECT * FROM agendamentos WHERE id = :id AND deleted_at IS NULL FOR UPDATE',
                [':id' => $agendamentoId]
            );

            if ($agendamento === null) {
                throw new RuntimeException('Agendamento não encontrado.');
            }

            if (!$peloEstabelecimento && (int)$agendamento['cliente_id'] !== $clienteId) {
                throw new RuntimeException('Este agendamento não pertence a você.');
            }

            if (!in_array($agendamento['situacao'], self::SITUACOES_ATIVAS, true)) {
                throw new RuntimeException('Este agendamento não pode mais ser cancelado.');
            }

            // O estabelecimento sempre reembolsa; o cliente, só dentro do prazo
            $noPrazo = $peloEstabelecimento || $this->dentroDaJanelaDe24h($agendamento);
            $jaPago = $this->valorJaPago($agendamentoId);
            $reembolso = $noPrazo ? $jaPago : 0.0;

            $this->consultar(
                "UPDATE agendamentos
                 SET situacao = 'cancelado', motivo_cancelamento = :motivo, reserva_expira_em = NULL
                 WHERE id = :id",
                [':motivo' => trim($motivo), ':id' => $agendamentoId]
            );

            // Crédito interno usado volta para o cliente em qualquer cenário
            $creditoUsado = (float)$agendamento['credito_utilizado'];

            if ($creditoUsado > 0) {
                (new CreditoModel())->devolver((int)$agendamento['cliente_id'], $creditoUsado, $agendamentoId);
            }

            $this->db->commit();

            return [
                'reembolso'         => $reembolso,
                'credito_devolvido' => $creditoUsado,
                'no_prazo'          => $noPrazo,
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Reagenda o atendimento para nova data/horário, podendo trocar os serviços
     * (docs/FSD.md Fluxo 3).
     *
     * O valor já pago é reaproveitado: se o novo total for menor, a diferença
     * vira crédito interno; se for maior, o agendamento volta a aguardar o
     * pagamento da diferença.
     *
     * @return array ['diferenca' => float, 'credito_gerado' => float, 'a_pagar' => float]
     */
    public function reagendar(
        int $agendamentoId,
        int $clienteId,
        string $novaData,
        string $novaHora,
        int $novoProfissionalId,
        array $novosServicoIds
    ): array {
        $agendamento = $this->buscarUm(
            'SELECT * FROM agendamentos WHERE id = :id AND deleted_at IS NULL',
            [':id' => $agendamentoId]
        );

        if ($agendamento === null || (int)$agendamento['cliente_id'] !== $clienteId) {
            throw new RuntimeException('Agendamento não encontrado.');
        }

        if ($agendamento['situacao'] !== 'confirmado') {
            throw new RuntimeException('Apenas agendamentos confirmados podem ser reagendados.');
        }

        if (!$this->dentroDaJanelaDe24h($agendamento)) {
            throw new RuntimeException(
                'O reagendamento exige no mínimo '
                . $this->settings->getInt('cancelamento_janela_horas')
                . ' horas de antecedência.'
            );
        }

        $limite = $this->settings->getInt('reagendamento_limite');

        if ((int)$agendamento['reagendamentos_realizados'] >= $limite) {
            throw new RuntimeException("Este agendamento já foi reagendado {$limite} vezes, o máximo permitido.");
        }

        $servicos = $this->carregarServicos($novosServicoIds);

        if (empty($servicos)) {
            throw new RuntimeException('Selecione ao menos um serviço válido.');
        }

        // O horário precisa estar livre desconsiderando a própria reserva atual
        if (!$this->horarioEstaLivreIgnorando($novaData, $novaHora, $novosServicoIds, $novoProfissionalId, $agendamentoId)) {
            throw new RuntimeException('Este horário não está disponível. Escolha outro.');
        }

        $duracaoTotal = array_sum(array_column($servicos, 'duracao_minutos'));
        $novoValor = round((float)array_sum(array_column($servicos, 'preco')), 2);
        $jaPago = $this->valorJaPago($agendamentoId);
        $creditoAplicado = (float)$agendamento['credito_utilizado'];

        // O crédito já aplicado continua valendo no novo total
        $diferenca = round($novoValor - $creditoAplicado - $jaPago, 2);

        $inicio = $this->paraMinutos($novaHora);
        $horaFim = $this->paraHora($inicio + $duracaoTotal);

        $this->db->beginTransaction();

        try {
            $creditoGerado = 0.0;
            $aPagar = 0.0;
            $novaSituacao = 'confirmado';
            $expiraEm = null;

            if ($diferenca < 0) {
                // Novo valor menor: sobra vira crédito de 90 dias
                $creditoGerado = abs($diferenca);
                (new CreditoModel())->gerar($clienteId, $creditoGerado, $agendamentoId);

            } elseif ($diferenca > 0) {
                // Novo valor maior: cliente paga a diferença
                $aPagar = $diferenca;
                $novaSituacao = 'aguardando_pagamento';
                $expiraEm = date(
                    'Y-m-d H:i:s',
                    strtotime('+' . $this->settings->getInt('checkout_reserva_minutos') . ' minutes')
                );
            }

            $this->consultar(
                'UPDATE agendamentos
                 SET profissional_id = :profissional,
                     data_agendamento = :data,
                     hora_inicio = :inicio,
                     hora_fim = :fim,
                     duracao_total_minutos = :duracao,
                     valor_total = :valor,
                     situacao = :situacao,
                     reserva_expira_em = :expira,
                     reagendamentos_realizados = reagendamentos_realizados + 1
                 WHERE id = :id',
                [
                    ':profissional' => $novoProfissionalId,
                    ':data'         => $novaData,
                    ':inicio'       => substr($novaHora, 0, 5) . ':00',
                    ':fim'          => $horaFim . ':00',
                    ':duracao'      => $duracaoTotal,
                    ':valor'        => $novoValor,
                    ':situacao'     => $novaSituacao,
                    ':expira'       => $expiraEm,
                    ':id'           => $agendamentoId,
                ]
            );

            // Congela novamente os preços dos serviços contratados
            $this->consultar(
                'DELETE FROM agendamento_servicos WHERE agendamento_id = :id',
                [':id' => $agendamentoId]
            );

            $stmt = $this->db->prepare(
                'INSERT INTO agendamento_servicos (agendamento_id, servico_id, preco_aplicado, duracao_aplicada_minutos)
                 VALUES (:agendamento, :servico, :preco, :duracao)'
            );

            foreach ($servicos as $servico) {
                $stmt->execute([
                    ':agendamento' => $agendamentoId,
                    ':servico'     => (int)$servico['id'],
                    ':preco'       => $servico['preco'],
                    ':duracao'     => (int)$servico['duracao_minutos'],
                ]);
            }

            $this->db->commit();

            return [
                'diferenca'      => $diferenca,
                'credito_gerado' => $creditoGerado,
                'a_pagar'        => $aPagar,
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Aplica crédito interno a uma reserva ainda não paga.
     */
    public function aplicarCredito(int $agendamentoId, int $clienteId, float $valorDesejado): float {
        $agendamento = $this->buscarUm(
            'SELECT * FROM agendamentos WHERE id = :id AND cliente_id = :cliente AND deleted_at IS NULL',
            [':id' => $agendamentoId, ':cliente' => $clienteId]
        );

        if ($agendamento === null || $agendamento['situacao'] !== 'aguardando_pagamento') {
            throw new RuntimeException('Só é possível aplicar crédito em reservas aguardando pagamento.');
        }

        $creditos = new CreditoModel();
        $maximo = min(
            $creditos->saldoDisponivel($clienteId),
            (float)$agendamento['valor_total'] - (float)$agendamento['credito_utilizado'],
            $valorDesejado
        );

        if ($maximo <= 0) {
            return 0.0;
        }

        $consumido = $creditos->consumir($clienteId, $maximo);

        $this->consultar(
            'UPDATE agendamentos SET credito_utilizado = credito_utilizado + :valor WHERE id = :id',
            [':valor' => $consumido, ':id' => $agendamentoId]
        );

        return $consumido;
    }

    /**
     * Verifica disponibilidade ignorando um agendamento específico.
     * Necessário no reagendamento: o horário atual do próprio cliente não
     * pode contar como conflito.
     */
    public function horarioEstaLivreIgnorando(
        string $data, string $hora, array $servicoIds, int $profissionalId, int $ignorarId
    ): bool {
        $situacaoOriginal = $this->buscarValor(
            'SELECT situacao FROM agendamentos WHERE id = :id', [':id' => $ignorarId]
        );

        // Neutraliza temporariamente o próprio agendamento durante a checagem
        $this->consultar(
            "UPDATE agendamentos SET situacao = 'cancelado' WHERE id = :id", [':id' => $ignorarId]
        );

        try {
            $livre = $this->horarioEstaLivre($data, $hora, $servicoIds, $profissionalId);
        } finally {
            $this->consultar(
                'UPDATE agendamentos SET situacao = :s WHERE id = :id',
                [':s' => $situacaoOriginal, ':id' => $ignorarId]
            );
        }

        return $livre;
    }

    /**
     * Nível de fidelidade pelo total de atendimentos concluídos
     * (docs/FSD.md Módulo 5: Bronze 0-4, Prata 5-9, Ouro 10+).
     */
    public function fidelidade(int $clienteId): array {
        $concluidos = (int)$this->buscarValor(
            "SELECT COUNT(*) FROM agendamentos
             WHERE cliente_id = :id AND situacao = 'concluido' AND deleted_at IS NULL",
            [':id' => $clienteId]
        );

        if ($concluidos >= 10) {
            $nivel = 'ouro';
            $rotulo = 'Ouro';
            $proximo = null;
            $faltam = 0;
        } elseif ($concluidos >= 5) {
            $nivel = 'prata';
            $rotulo = 'Prata';
            $proximo = 'Ouro';
            $faltam = 10 - $concluidos;
        } else {
            $nivel = 'bronze';
            $rotulo = 'Bronze';
            $proximo = 'Prata';
            $faltam = 5 - $concluidos;
        }

        return [
            'concluidos' => $concluidos,
            'nivel'      => $nivel,
            'rotulo'     => $rotulo,
            'proximo'    => $proximo,
            'faltam'     => $faltam,
            'insignia'   => 'insignia' . $nivel . '.png',
        ];
    }

    // =================================================================
    // OPERAÇÕES DO PROFISSIONAL (Fase 7)
    // =================================================================

    /**
     * Atendimentos de um profissional em uma data.
     */
    public function agendaDoProfissional(int $profissionalId, string $data): array {
        $this->expirarReservasVencidas();

        return $this->buscarTodos(
            "SELECT a.*, u.nome AS cliente_nome, u.telefone AS cliente_telefone, u.id AS cliente_usuario_id,
                    (SELECT GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ')
                     FROM agendamento_servicos ags
                     INNER JOIN servicos s ON s.id = ags.servico_id
                     WHERE ags.agendamento_id = a.id) AS servicos
             FROM agendamentos a
             INNER JOIN usuarios u ON u.id = a.cliente_id
             WHERE a.profissional_id = :prof
               AND a.data_agendamento = :data
               AND a.deleted_at IS NULL
               AND a.situacao <> 'cancelado'
             ORDER BY a.hora_inicio ASC",
            [':prof' => $profissionalId, ':data' => $data]
        );
    }

    /**
     * Resumo da semana do profissional, para a visão semanal.
     */
    public function resumoSemanalDoProfissional(int $profissionalId, string $dataInicio): array {
        return $this->buscarTodos(
            "SELECT data_agendamento, COUNT(*) AS total,
                    SUM(situacao = 'concluido') AS concluidos
             FROM agendamentos
             WHERE profissional_id = :prof
               AND data_agendamento BETWEEN :inicio AND DATE_ADD(:inicio2, INTERVAL 6 DAY)
               AND deleted_at IS NULL
               AND situacao <> 'cancelado'
             GROUP BY data_agendamento
             ORDER BY data_agendamento ASC",
            [':prof' => $profissionalId, ':inicio' => $dataInicio, ':inicio2' => $dataInicio]
        );
    }

    /**
     * Marca a presença do cliente e registra o horário real de início.
     */
    public function confirmarPresenca(int $agendamentoId, int $profissionalId, ?string $horaInicioReal = null): bool {
        $hora = $horaInicioReal !== null && $horaInicioReal !== '' ? $horaInicioReal . ':00' : date('H:i:s');

        return $this->consultar(
            "UPDATE agendamentos
             SET confirmado_presenca = 1, hora_inicio_real = :hora
             WHERE id = :id AND profissional_id = :prof AND situacao = 'confirmado' AND deleted_at IS NULL",
            [':hora' => $hora, ':id' => $agendamentoId, ':prof' => $profissionalId]
        )->rowCount() > 0;
    }

    /**
     * Conclui o atendimento registrando os horários reais.
     */
    public function concluir(int $agendamentoId, int $profissionalId, ?string $inicioReal, ?string $fimReal): bool {
        $agendamento = $this->buscarUm(
            'SELECT * FROM agendamentos WHERE id = :id AND profissional_id = :prof AND deleted_at IS NULL',
            [':id' => $agendamentoId, ':prof' => $profissionalId]
        );

        if ($agendamento === null) {
            throw new RuntimeException('Atendimento não encontrado na sua agenda.');
        }

        if ($agendamento['situacao'] !== 'confirmado') {
            throw new RuntimeException('Apenas atendimentos confirmados podem ser concluídos.');
        }

        $inicio = $inicioReal !== null && $inicioReal !== ''
            ? $inicioReal . ':00'
            : ($agendamento['hora_inicio_real'] ?? $agendamento['hora_inicio']);
        $fim = $fimReal !== null && $fimReal !== '' ? $fimReal . ':00' : date('H:i:s');

        if ($this->paraMinutos($fim) <= $this->paraMinutos($inicio)) {
            throw new RuntimeException('O horário de término deve ser posterior ao de início.');
        }

        return $this->consultar(
            "UPDATE agendamentos
             SET situacao = 'concluido', hora_inicio_real = :inicio, hora_fim_real = :fim, confirmado_presenca = 1
             WHERE id = :id",
            [':inicio' => $inicio, ':fim' => $fim, ':id' => $agendamentoId]
        )->rowCount() > 0;
    }

    /**
     * Registra falta do cliente, respeitada a tolerância de atraso.
     */
    public function registrarFalta(int $agendamentoId, int $profissionalId): bool {
        return $this->consultar(
            "UPDATE agendamentos
             SET situacao = 'falta', motivo_cancelamento = 'Cliente não compareceu.'
             WHERE id = :id AND profissional_id = :prof AND situacao = 'confirmado' AND deleted_at IS NULL",
            [':id' => $agendamentoId, ':prof' => $profissionalId]
        )->rowCount() > 0;
    }

    /**
     * Quantos atendimentos existem entre um profissional e um cliente.
     * Base da autorização de acesso às notas privadas.
     */
    public function totalEntreProfissionalECliente(int $profissionalId, int $clienteId): int {
        return (int)$this->buscarValor(
            'SELECT COUNT(*) FROM agendamentos
             WHERE profissional_id = :prof AND cliente_id = :cliente AND deleted_at IS NULL',
            [':prof' => $profissionalId, ':cliente' => $clienteId]
        );
    }

    public function buscarPorIdDoProfissional(int $agendamentoId, int $profissionalId): ?array {
        return $this->buscarUm(
            'SELECT a.*, u.nome AS cliente_nome, u.id AS cliente_usuario_id
             FROM agendamentos a
             INNER JOIN usuarios u ON u.id = a.cliente_id
             WHERE a.id = :id AND a.profissional_id = :prof AND a.deleted_at IS NULL',
            [':id' => $agendamentoId, ':prof' => $profissionalId]
        );
    }

    // =================================================================
    // Consultas de apoio
    // =================================================================

    public function buscarPorCodigo(string $codigo): ?array {
        return $this->buscarUm(
            'SELECT a.*, u.nome AS cliente_nome, u.email AS cliente_email,
                    pu.nome AS profissional_nome
             FROM agendamentos a
             INNER JOIN usuarios u ON u.id = a.cliente_id
             INNER JOIN profissionais p ON p.id = a.profissional_id
             INNER JOIN usuarios pu ON pu.id = p.usuario_id
             WHERE a.codigo_reserva = :codigo AND a.deleted_at IS NULL',
            [':codigo' => $codigo]
        );
    }

    public function servicosDoAgendamento(int $agendamentoId): array {
        return $this->buscarTodos(
            'SELECT ags.*, s.nome
             FROM agendamento_servicos ags
             INNER JOIN servicos s ON s.id = ags.servico_id
             WHERE ags.agendamento_id = :id
             ORDER BY s.nome ASC',
            [':id' => $agendamentoId]
        );
    }

    public function totalAtivosDoCliente(int $clienteId): int {
        $this->expirarReservasVencidas();

        return (int)$this->buscarValor(
            "SELECT COUNT(*) FROM agendamentos
             WHERE cliente_id = :id
               AND deleted_at IS NULL
               AND situacao IN ('aguardando_pagamento', 'confirmado')
               AND CONCAT(data_agendamento, ' ', hora_inicio) > NOW()",
            [':id' => $clienteId]
        );
    }

    // =================================================================
    // Internos do motor
    // =================================================================

    /**
     * Serviços válidos e disponíveis para contratação.
     */
    private function carregarServicos(array $servicoIds): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $servicoIds))));

        if (empty($ids)) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));

        return $this->buscarTodos(
            "SELECT id, nome, preco, duracao_minutos
             FROM servicos
             WHERE id IN ({$marcadores}) AND status = 'ativo' AND deleted_at IS NULL",
            $ids
        );
    }

    /**
     * Profissionais ativos que executam TODOS os serviços selecionados.
     */
    private function profissionaisAptos(array $servicoIds, ?int $profissionalId): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $servicoIds))));

        if (empty($ids)) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $parametros = $ids;

        $sql = "SELECT p.id, p.limite_diario_atendimentos, u.nome
                FROM profissionais p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                INNER JOIN profissional_servicos ps ON ps.profissional_id = p.id
                WHERE p.status = 'ativo'
                  AND p.deleted_at IS NULL
                  AND u.deleted_at IS NULL
                  AND u.status = 'ativo'
                  AND ps.servico_id IN ({$marcadores})";

        if ($profissionalId !== null) {
            $sql .= ' AND p.id = ?';
            $parametros[] = $profissionalId;
        }

        // HAVING garante que o profissional execute todos os serviços, não apenas um
        $sql .= ' GROUP BY p.id, p.limite_diario_atendimentos, u.nome
                  HAVING COUNT(DISTINCT ps.servico_id) = ?
                  ORDER BY u.nome ASC';
        $parametros[] = count($ids);

        return $this->buscarTodos($sql, $parametros);
    }

    private function jornadaDoDia(int $profissionalId, int $diaSemana): ?array {
        return $this->buscarUm(
            'SELECT * FROM horarios_trabalho WHERE profissional_id = :id AND dia_semana = :dia',
            [':id' => $profissionalId, ':dia' => $diaSemana]
        );
    }

    /**
     * Converte a jornada em janelas de atendimento, separando o almoço.
     *
     * @return array Lista de pares [inicioEmMinutos, fimEmMinutos]
     */
    private function janelasDeTrabalho(array $jornada): array {
        $inicio = $this->paraMinutos($jornada['hora_inicio']);
        $fim = $this->paraMinutos($jornada['hora_fim']);

        if ($jornada['almoco_inicio'] === null || $jornada['almoco_fim'] === null) {
            return [[$inicio, $fim]];
        }

        $almocoInicio = $this->paraMinutos($jornada['almoco_inicio']);
        $almocoFim = $this->paraMinutos($jornada['almoco_fim']);

        $janelas = [];

        if ($almocoInicio > $inicio) {
            $janelas[] = [$inicio, $almocoInicio];
        }

        if ($fim > $almocoFim) {
            $janelas[] = [$almocoFim, $fim];
        }

        return $janelas;
    }

    /**
     * Remove das janelas os trechos cobertos por bloqueios do profissional
     * ou do estabelecimento inteiro.
     */
    private function descontarBloqueios(array $janelas, int $profissionalId, string $data): array {
        $bloqueios = $this->buscarTodos(
            "SELECT data_inicio, data_fim
             FROM indisponibilidades_bloqueios
             WHERE (profissional_id = :prof OR profissional_id IS NULL)
               AND data_inicio < :fimDia
               AND data_fim > :inicioDia",
            [
                ':prof'      => $profissionalId,
                ':fimDia'    => $data . ' 23:59:59',
                ':inicioDia' => $data . ' 00:00:00',
            ]
        );

        foreach ($bloqueios as $bloqueio) {
            // Recorta o bloqueio ao dia consultado
            $inicioBloqueio = max(0, $this->minutosNoDia($bloqueio['data_inicio'], $data, 0));
            $fimBloqueio = min(1440, $this->minutosNoDia($bloqueio['data_fim'], $data, 1440));

            $janelas = $this->subtrairIntervalo($janelas, $inicioBloqueio, $fimBloqueio);
        }

        return $janelas;
    }

    /**
     * Subtrai [inicio, fim) de uma lista de janelas, podendo dividi-las.
     */
    private function subtrairIntervalo(array $janelas, int $inicio, int $fim): array {
        $resultado = [];

        foreach ($janelas as [$a, $b]) {
            // Sem interseção
            if ($fim <= $a || $inicio >= $b) {
                $resultado[] = [$a, $b];
                continue;
            }

            // Sobra um trecho antes do bloqueio
            if ($inicio > $a) {
                $resultado[] = [$a, min($inicio, $b)];
            }

            // Sobra um trecho depois do bloqueio
            if ($fim < $b) {
                $resultado[] = [max($fim, $a), $b];
            }
        }

        return $resultado;
    }

    /**
     * Intervalos já ocupados na agenda do profissional, cada um acrescido do
     * intervalo obrigatório entre atendimentos.
     */
    private function intervalosOcupados(int $profissionalId, string $data, int $intervalo): array {
        $this->expirarReservasVencidas();

        $linhas = $this->buscarTodos(
            "SELECT hora_inicio, hora_fim
             FROM agendamentos
             WHERE profissional_id = :prof
               AND data_agendamento = :data
               AND deleted_at IS NULL
               AND situacao IN ('aguardando_pagamento', 'confirmado')",
            [':prof' => $profissionalId, ':data' => $data]
        );

        $ocupados = [];

        foreach ($linhas as $linha) {
            $ocupados[] = [
                $this->paraMinutos($linha['hora_inicio']),
                $this->paraMinutos($linha['hora_fim']) + $intervalo,
            ];
        }

        return $ocupados;
    }

    /**
     * Regra de sobreposição do FSD seção 14.8:
     * conflita quando inicio < fim_existente E fim > inicio_existente.
     */
    private function conflita(int $inicio, int $fim, array $ocupados): bool {
        foreach ($ocupados as [$ocupadoInicio, $ocupadoFim]) {
            if ($inicio < $ocupadoFim && $fim > $ocupadoInicio) {
                return true;
            }
        }

        return false;
    }

    private function totalAtendimentosNoDia(int $profissionalId, string $data): int {
        return (int)$this->buscarValor(
            "SELECT COUNT(*) FROM agendamentos
             WHERE profissional_id = :prof
               AND data_agendamento = :data
               AND deleted_at IS NULL
               AND situacao IN ('aguardando_pagamento', 'confirmado', 'concluido')",
            [':prof' => $profissionalId, ':data' => $data]
        );
    }

    /**
     * Valida a antecedência mínima (horas) e máxima (dias) da data escolhida.
     */
    public function dataDentroDaJanelaPermitida(string $data): bool {
        $d = DateTime::createFromFormat('Y-m-d', $data);

        if ($d === false || $d->format('Y-m-d') !== $data) {
            return false;
        }

        $hoje = new DateTime('today');
        $maxDias = $this->settings->getInt('agendamento_antecedencia_max_dias');
        $limite = (clone $hoje)->modify("+{$maxDias} day");

        $alvo = new DateTime($data . ' 00:00:00');

        return $alvo >= $hoje && $alvo <= $limite;
    }

    /**
     * Menor horário aceitável quando a data consultada é hoje, considerando a
     * antecedência mínima. Retorna null para datas futuras.
     */
    private function minutosDeAgoraSeHoje(string $data): ?int {
        if ($data !== date('Y-m-d')) {
            return null;
        }

        $minHoras = $this->settings->getInt('agendamento_antecedencia_min_horas');
        $limite = new DateTime('+' . $minHoras . ' hours');

        // Se o limite ultrapassa a meia-noite, nenhum horário de hoje serve
        if ($limite->format('Y-m-d') !== $data) {
            return 1441;
        }

        return (int)$limite->format('H') * 60 + (int)$limite->format('i');
    }

    /**
     * Posição de um DATETIME dentro do dia consultado, em minutos.
     * Datas anteriores/posteriores são recortadas para o padrão informado.
     */
    private function minutosNoDia(string $dataHora, string $dataReferencia, int $padrao): int {
        $dia = substr($dataHora, 0, 10);

        if ($dia < $dataReferencia) {
            return $padrao === 0 ? 0 : 0;
        }

        if ($dia > $dataReferencia) {
            return 1440;
        }

        return $this->paraMinutos(substr($dataHora, 11));
    }

    private function gerarCodigoReserva(): string {
        do {
            $codigo = 'AGB-' . strtoupper(bin2hex(random_bytes(4)));
            $existe = (int)$this->buscarValor(
                'SELECT COUNT(*) FROM agendamentos WHERE codigo_reserva = :c',
                [':c' => $codigo]
            );
        } while ($existe > 0);

        return $codigo;
    }

    // -----------------------------------------------------------------
    // Conversão de horários
    // -----------------------------------------------------------------

    public function paraMinutos(string $hora): int {
        [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)));
        return $h * 60 + $m;
    }

    public function paraHora(int $minutos): string {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }
}
