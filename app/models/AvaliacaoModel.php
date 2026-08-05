<?php
// app/models/AvaliacaoModel.php - Avaliações dos atendimentos
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Avaliações de 1 a 5 estrelas deixadas pelo cliente (docs/FSD.md Módulo 5).
 *
 * Só é possível avaliar um atendimento efetivamente concluído, e uma única vez
 * por agendamento (garantido também pela chave UNIQUE em `agendamento_id`).
 */
class AvaliacaoModel extends BaseModel {

    public function buscarPorAgendamento(int $agendamentoId): ?array {
        return $this->buscarUm(
            'SELECT * FROM avaliacoes WHERE agendamento_id = :id',
            [':id' => $agendamentoId]
        );
    }

    public function jaAvaliado(int $agendamentoId): bool {
        return $this->buscarPorAgendamento($agendamentoId) !== null;
    }

    /**
     * Registra a avaliação.
     *
     * @throws RuntimeException se o atendimento não estiver concluído ou já avaliado
     */
    public function registrar(int $agendamentoId, int $clienteId, int $nota, ?string $comentario = null): int {
        if ($nota < 1 || $nota > 5) {
            throw new InvalidArgumentException('A nota deve estar entre 1 e 5.');
        }

        $agendamento = $this->buscarUm(
            'SELECT id, cliente_id, profissional_id, situacao
             FROM agendamentos WHERE id = :id AND deleted_at IS NULL',
            [':id' => $agendamentoId]
        );

        if ($agendamento === null || (int)$agendamento['cliente_id'] !== $clienteId) {
            throw new RuntimeException('Agendamento não encontrado.');
        }

        if ($agendamento['situacao'] !== 'concluido') {
            throw new RuntimeException('Só é possível avaliar atendimentos concluídos.');
        }

        if ($this->jaAvaliado($agendamentoId)) {
            throw new RuntimeException('Este atendimento já foi avaliado.');
        }

        $comentario = $comentario !== null ? trim($comentario) : '';

        $this->consultar(
            'INSERT INTO avaliacoes (agendamento_id, cliente_id, profissional_id, nota, comentario)
             VALUES (:agendamento, :cliente, :profissional, :nota, :comentario)',
            [
                ':agendamento'  => $agendamentoId,
                ':cliente'      => $clienteId,
                ':profissional' => (int)$agendamento['profissional_id'],
                ':nota'         => $nota,
                ':comentario'   => $comentario !== '' ? $comentario : null,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Média e total de avaliações de um profissional.
     */
    public function resumoDoProfissional(int $profissionalId): array {
        $linha = $this->buscarUm(
            'SELECT COUNT(*) AS total, COALESCE(AVG(nota), 0) AS media
             FROM avaliacoes WHERE profissional_id = :id',
            [':id' => $profissionalId]
        );

        return [
            'total' => (int)($linha['total'] ?? 0),
            'media' => round((float)($linha['media'] ?? 0), 1),
        ];
    }

    public function listarDoProfissional(int $profissionalId, int $limite = 20): array {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.nome AS cliente_nome
             FROM avaliacoes a
             INNER JOIN usuarios u ON u.id = a.cliente_id
             WHERE a.profissional_id = :id
             ORDER BY a.id DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':id', $profissionalId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
