<?php
// app/models/CreditoModel.php - Créditos internos do cliente
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Créditos internos gerados por reagendamentos de menor valor
 * (docs/FSD.md Módulo 5 e seção 14.7).
 *
 * Cada crédito tem validade de 90 dias corridos e pode ser consumido
 * parcialmente em novos agendamentos. O consumo segue a ordem de vencimento
 * mais próximo, para que o cliente não perca saldo por expiração.
 */
class CreditoModel extends BaseModel {

    private SettingModel $settings;

    public function __construct() {
        parent::__construct();
        $this->settings = new SettingModel();
    }

    /**
     * Gera um crédito para o cliente, com validade contada a partir de hoje.
     */
    public function gerar(int $clienteId, float $valor, ?int $agendamentoOrigemId = null): int {
        if ($valor <= 0) {
            throw new InvalidArgumentException('O valor do crédito deve ser positivo.');
        }

        $dias = $this->settings->getInt('credito_validade_dias');

        $this->consultar(
            'INSERT INTO creditos_internos
                (cliente_id, agendamento_origem_id, valor_original, saldo_restante, data_expiracao)
             VALUES
                (:cliente, :origem, :valor, :valor2, DATE_ADD(CURDATE(), INTERVAL :dias DAY))',
            [
                ':cliente' => $clienteId,
                ':origem'  => $agendamentoOrigemId,
                ':valor'   => $valor,
                ':valor2'  => $valor,
                ':dias'    => $dias,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Saldo total disponível: soma dos créditos não expirados.
     */
    public function saldoDisponivel(int $clienteId): float {
        return (float)$this->buscarValor(
            'SELECT COALESCE(SUM(saldo_restante), 0)
             FROM creditos_internos
             WHERE cliente_id = :id AND saldo_restante > 0 AND data_expiracao >= CURDATE()',
            [':id' => $clienteId]
        );
    }

    /**
     * Créditos válidos, do vencimento mais próximo ao mais distante.
     */
    public function listarValidos(int $clienteId): array {
        return $this->buscarTodos(
            'SELECT * FROM creditos_internos
             WHERE cliente_id = :id AND saldo_restante > 0 AND data_expiracao >= CURDATE()
             ORDER BY data_expiracao ASC',
            [':id' => $clienteId]
        );
    }

    /**
     * Histórico completo, incluindo créditos zerados e expirados.
     */
    public function listarTodos(int $clienteId): array {
        return $this->buscarTodos(
            'SELECT * FROM creditos_internos WHERE cliente_id = :id ORDER BY created_at DESC',
            [':id' => $clienteId]
        );
    }

    /**
     * Consome até $valor do saldo do cliente, abatendo dos créditos que
     * vencem primeiro.
     *
     * @return float Valor efetivamente consumido (pode ser menor que o pedido)
     */
    public function consumir(int $clienteId, float $valor): float {
        if ($valor <= 0) {
            return 0.0;
        }

        $this->db->beginTransaction();

        try {
            $creditos = $this->buscarTodos(
                'SELECT * FROM creditos_internos
                 WHERE cliente_id = :id AND saldo_restante > 0 AND data_expiracao >= CURDATE()
                 ORDER BY data_expiracao ASC
                 FOR UPDATE',
                [':id' => $clienteId]
            );

            $restante = round($valor, 2);
            $consumido = 0.0;

            $stmt = $this->db->prepare(
                'UPDATE creditos_internos SET saldo_restante = :saldo WHERE id = :id'
            );

            foreach ($creditos as $credito) {
                if ($restante <= 0) {
                    break;
                }

                $saldo = (float)$credito['saldo_restante'];
                $abater = min($saldo, $restante);

                $stmt->execute([
                    ':saldo' => round($saldo - $abater, 2),
                    ':id'    => (int)$credito['id'],
                ]);

                $restante = round($restante - $abater, 2);
                $consumido = round($consumido + $abater, 2);
            }

            $this->db->commit();

            return $consumido;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Devolve ao cliente um crédito consumido, usado quando o agendamento
     * que o utilizou é cancelado.
     */
    public function devolver(int $clienteId, float $valor, ?int $agendamentoOrigemId = null): void {
        if ($valor > 0) {
            $this->gerar($clienteId, $valor, $agendamentoOrigemId);
        }
    }
}
