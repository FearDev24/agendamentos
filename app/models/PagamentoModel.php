<?php
// app/models/PagamentoModel.php - Transações financeiras dos agendamentos
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Pagamentos dos agendamentos (docs/FSD.md seções 10.9 e 23).
 *
 * A confirmação do pagamento e do agendamento acontece em `aprovar()`, que é
 * **idempotente**: o Mercado Pago pode reenviar a mesma notificação várias
 * vezes, e reprocessá-la não pode confirmar duas vezes nem duplicar e-mails.
 */
class PagamentoModel extends BaseModel {

    public const FORMAS = ['pix', 'cartao_credito', 'dinheiro'];
    public const STATUS = ['pendente', 'aprovado', 'recusado', 'estornado'];

    /**
     * Formas recebidas presencialmente, sem passar pelo gateway.
     * Nunca são oferecidas ao cliente no checkout online.
     */
    public const FORMAS_BALCAO = [
        'dinheiro'       => 'Dinheiro',
        'cartao_credito' => 'Cartão (maquininha)',
        'pix'            => 'Pix (transferência direta)',
    ];

    public const ROTULOS_FORMA = [
        'pix'            => 'Pix',
        'cartao_credito' => 'Cartão de Crédito',
        'dinheiro'       => 'Dinheiro',
    ];

    // -----------------------------------------------------------------
    // Consultas
    // -----------------------------------------------------------------

    public function buscarPorId(int $id): ?array {
        return $this->buscarUm('SELECT * FROM pagamentos WHERE id = :id', [':id' => $id]);
    }

    public function buscarPorTransacao(string $transacaoId): ?array {
        return $this->buscarUm(
            'SELECT * FROM pagamentos WHERE transacao_id = :t ORDER BY id DESC LIMIT 1',
            [':t' => $transacaoId]
        );
    }

    /**
     * Pagamento mais recente de um agendamento.
     */
    public function buscarPorAgendamento(int $agendamentoId): ?array {
        return $this->buscarUm(
            'SELECT * FROM pagamentos WHERE agendamento_id = :id ORDER BY id DESC LIMIT 1',
            [':id' => $agendamentoId]
        );
    }

    public function buscarAprovadoDoAgendamento(int $agendamentoId): ?array {
        return $this->buscarUm(
            "SELECT * FROM pagamentos
             WHERE agendamento_id = :id AND status_pagamento = 'aprovado'
             ORDER BY id DESC LIMIT 1",
            [':id' => $agendamentoId]
        );
    }

    public function listarDoAgendamento(int $agendamentoId): array {
        return $this->buscarTodos(
            'SELECT * FROM pagamentos WHERE agendamento_id = :id ORDER BY id ASC',
            [':id' => $agendamentoId]
        );
    }

    // -----------------------------------------------------------------
    // Escrita
    // -----------------------------------------------------------------

    /**
     * Registra a tentativa de pagamento retornada pelo gateway.
     */
    public function registrar(int $agendamentoId, string $forma, float $valor, array $retornoGateway): int {
        if (!in_array($forma, self::FORMAS, true)) {
            throw new InvalidArgumentException('Forma de pagamento inválida.');
        }

        $status = $retornoGateway['status'] ?? 'pendente';

        if (!in_array($status, self::STATUS, true)) {
            $status = 'pendente';
        }

        $this->consultar(
            'INSERT INTO pagamentos
                (agendamento_id, forma_pagamento, valor, status_pagamento, transacao_id,
                 qr_code_pix, qr_code_pix_url, pago_em)
             VALUES
                (:agendamento, :forma, :valor, :status, :transacao, :qr, :qr_url, :pago_em)',
            [
                ':agendamento' => $agendamentoId,
                ':forma'       => $forma,
                ':valor'       => $valor,
                ':status'      => $status,
                ':transacao'   => $retornoGateway['transacao_id'] ?? null,
                ':qr'          => $retornoGateway['qr_code'] ?? null,
                ':qr_url'      => $retornoGateway['qr_code_base64'] ?? null,
                ':pago_em'     => $status === 'aprovado' ? date('Y-m-d H:i:s') : null,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Registra um recebimento presencial, já compensado.
     *
     * Usado nos atendimentos de balcão, quitados em dinheiro, maquininha ou
     * Pix direto. Diferente do fluxo online, não há gateway envolvido: o valor
     * entra no caixa no ato, então o pagamento nasce `aprovado` e passa a
     * contar no faturamento.
     *
     * @throws RuntimeException se o agendamento já estiver quitado
     */
    public function registrarRecebimentoPresencial(
        int $agendamentoId, string $forma, float $valor, int $registradoPor
    ): int {
        if (!array_key_exists($forma, self::FORMAS_BALCAO)) {
            throw new InvalidArgumentException('Forma de recebimento inválida.');
        }

        if ($valor <= 0) {
            throw new InvalidArgumentException('O valor recebido deve ser maior que zero.');
        }

        if ($this->buscarAprovadoDoAgendamento($agendamentoId) !== null) {
            throw new RuntimeException('Este atendimento já possui pagamento registrado.');
        }

        // O identificador deixa claro na conciliação que não veio do gateway
        $referencia = 'BALCAO-' . $agendamentoId . '-' . strtoupper(bin2hex(random_bytes(4)));

        $id = $this->registrar($agendamentoId, $forma, $valor, [
            'transacao_id' => $referencia,
            'status'       => 'aprovado',
        ]);

        (new AuditModel())->registrar(
            'recebimento_presencial', 'pagamentos', $id, null,
            [
                'agendamento_id' => $agendamentoId,
                'forma'          => $forma,
                'valor'          => $valor,
                'referencia'     => $referencia,
                'registrado_por' => $registradoPor,
            ]
        );

        return $id;
    }

    /**
     * Aprova o pagamento e confirma o agendamento, em uma única transação.
     *
     * Idempotente: reprocessar a mesma notificação não altera nada e devolve
     * 'ja_processado'. Nenhum e-mail é reenviado nesse caso.
     *
     * @return string Resultado: 'confirmado' | 'ja_processado' | 'pago_apos_expiracao'
     *                          | 'pagamento_nao_encontrado'
     */
    public function aprovar(string $transacaoId): string {
        $this->db->beginTransaction();

        try {
            // Trava a linha para impedir que duas notificações simultâneas
            // confirmem o mesmo agendamento duas vezes.
            $pagamento = $this->buscarUm(
                'SELECT * FROM pagamentos WHERE transacao_id = :t ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [':t' => $transacaoId]
            );

            if ($pagamento === null) {
                $this->db->commit();
                return 'pagamento_nao_encontrado';
            }

            $agendamento = $this->buscarUm(
                'SELECT * FROM agendamentos WHERE id = :id AND deleted_at IS NULL FOR UPDATE',
                [':id' => (int)$pagamento['agendamento_id']]
            );

            // A idempotência é decidida pelo estado do AGENDAMENTO, não do
            // pagamento: no cartão aprovado na hora o pagamento já nasce
            // 'aprovado', e ainda assim o agendamento precisa ser confirmado.
            if ($agendamento !== null && $agendamento['situacao'] === 'confirmado') {
                $this->db->commit();
                return 'ja_processado';
            }

            // Garante o pagamento aprovado mesmo que já tenha nascido assim
            if ($pagamento['status_pagamento'] !== 'aprovado' || $pagamento['pago_em'] === null) {
                $this->consultar(
                    "UPDATE pagamentos SET status_pagamento = 'aprovado', pago_em = COALESCE(pago_em, NOW())
                     WHERE id = :id",
                    [':id' => (int)$pagamento['id']]
                );
            }

            // Pagamento chegou depois de a reserva expirar: o dinheiro entrou,
            // mas o horário já foi liberado. Fica pendente de reembolso manual.
            if ($agendamento === null || $agendamento['situacao'] !== 'aguardando_pagamento') {
                $this->db->commit();
                return 'pago_apos_expiracao';
            }

            $this->consultar(
                "UPDATE agendamentos
                 SET situacao = 'confirmado', reserva_expira_em = NULL
                 WHERE id = :id",
                [':id' => (int)$agendamento['id']]
            );

            $this->db->commit();

            return 'confirmado';

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Garante que a transação esteja marcada como recusada. O agendamento
     * continua aguardando pagamento até o fim do prazo de reserva, permitindo
     * nova tentativa pelo cliente.
     *
     * Idempotente: devolve true sempre que a transação termina recusada, tenha
     * ela chegado assim do gateway (cartão negado na hora) ou sido recusada
     * depois pelo webhook. Um pagamento já aprovado nunca é rebaixado.
     */
    public function recusar(string $transacaoId): bool {
        $this->consultar(
            "UPDATE pagamentos SET status_pagamento = 'recusado'
             WHERE transacao_id = :t AND status_pagamento = 'pendente'",
            [':t' => $transacaoId]
        );

        $pagamento = $this->buscarPorTransacao($transacaoId);

        return $pagamento !== null && $pagamento['status_pagamento'] === 'recusado';
    }

    /**
     * Registra o estorno e marca o agendamento como reembolsado.
     */
    public function estornar(string $transacaoId): bool {
        $this->db->beginTransaction();

        try {
            $pagamento = $this->buscarUm(
                'SELECT * FROM pagamentos WHERE transacao_id = :t ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [':t' => $transacaoId]
            );

            if ($pagamento === null || $pagamento['status_pagamento'] === 'estornado') {
                $this->db->commit();
                return false;
            }

            $this->consultar(
                "UPDATE pagamentos SET status_pagamento = 'estornado' WHERE id = :id",
                [':id' => (int)$pagamento['id']]
            );

            $this->consultar(
                "UPDATE agendamentos SET situacao = 'reembolsado'
                 WHERE id = :id AND deleted_at IS NULL AND situacao <> 'concluido'",
                [':id' => (int)$pagamento['agendamento_id']]
            );

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Total recebido em um período, base do módulo financeiro (Fase 8).
     */
    public function totalRecebido(string $de, string $ate): float {
        return (float)$this->buscarValor(
            "SELECT COALESCE(SUM(valor), 0) FROM pagamentos
             WHERE status_pagamento = 'aprovado' AND DATE(pago_em) BETWEEN :de AND :ate",
            [':de' => $de, ':ate' => $ate]
        );
    }
}
