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
