<?php
// app/models/NotaClienteModel.php - Observações privadas da equipe sobre clientes
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Notas internas sobre clientes (docs/FSD.md Módulo 6 e entidade 16).
 *
 * Visíveis apenas para profissionais e administradores. O cliente nunca tem
 * acesso a estes registros por nenhuma tela do sistema.
 */
class NotaClienteModel extends BaseModel {

    public function listarDoCliente(int $clienteId): array {
        return $this->buscarTodos(
            'SELECT n.*, u.nome AS autor_nome
             FROM notas_clientes n
             INNER JOIN usuarios u ON u.id = n.autor_id
             WHERE n.cliente_id = :id
             ORDER BY n.id DESC',
            [':id' => $clienteId]
        );
    }

    public function criar(int $clienteId, int $autorId, string $observacao): int {
        $observacao = trim($observacao);

        if (mb_strlen($observacao) < 3) {
            throw new InvalidArgumentException('A observação deve ter no mínimo 3 caracteres.');
        }

        $this->consultar(
            'INSERT INTO notas_clientes (cliente_id, autor_id, observacao)
             VALUES (:cliente, :autor, :observacao)',
            [':cliente' => $clienteId, ':autor' => $autorId, ':observacao' => $observacao]
        );

        return (int)$this->db->lastInsertId();
    }

    public function totalDoCliente(int $clienteId): int {
        return (int)$this->buscarValor(
            'SELECT COUNT(*) FROM notas_clientes WHERE cliente_id = :id',
            [':id' => $clienteId]
        );
    }
}
