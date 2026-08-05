<?php
// app/models/DespesaModel.php - Despesas do estabelecimento
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Lançamentos de despesa e suas categorias (docs/FSD.md Módulo 7).
 *
 * As despesas alimentam o cálculo de lucro líquido do painel financeiro.
 * A exclusão é lógica (`deleted_at`), preservando o histórico contábil.
 */
class DespesaModel extends BaseModel {

    // -----------------------------------------------------------------
    // Categorias
    // -----------------------------------------------------------------

    public function categorias(): array {
        return $this->buscarTodos('SELECT * FROM categorias_despesas ORDER BY nome ASC');
    }

    public function criarCategoria(string $nome): int {
        $nome = trim($nome);

        if (mb_strlen($nome) < 3) {
            throw new InvalidArgumentException('O nome da categoria deve ter no mínimo 3 caracteres.');
        }

        $this->consultar('INSERT INTO categorias_despesas (nome) VALUES (:nome)', [':nome' => $nome]);

        return (int)$this->db->lastInsertId();
    }

    // -----------------------------------------------------------------
    // Lançamentos
    // -----------------------------------------------------------------

    /**
     * Despesas de um período, com filtro opcional por categoria.
     */
    public function listar(string $de, string $ate, ?int $categoriaId = null): array {
        $sql = 'SELECT d.*, c.nome AS categoria_nome, u.nome AS registrado_por_nome
                FROM despesas d
                INNER JOIN categorias_despesas c ON c.id = d.categoria_id
                INNER JOIN usuarios u ON u.id = d.registrado_por
                WHERE d.deleted_at IS NULL
                  AND d.data_despesa BETWEEN :de AND :ate';
        $parametros = [':de' => $de, ':ate' => $ate];

        if ($categoriaId !== null) {
            $sql .= ' AND d.categoria_id = :categoria';
            $parametros[':categoria'] = $categoriaId;
        }

        $sql .= ' ORDER BY d.data_despesa DESC, d.id DESC';

        return $this->buscarTodos($sql, $parametros);
    }

    public function buscarPorId(int $id): ?array {
        return $this->buscarUm(
            'SELECT * FROM despesas WHERE id = :id AND deleted_at IS NULL',
            [':id' => $id]
        );
    }

    public function criar(array $dados, int $registradoPor): int {
        $this->consultar(
            'INSERT INTO despesas (categoria_id, descricao, valor, data_despesa, comprovante, registrado_por)
             VALUES (:categoria, :descricao, :valor, :data, :comprovante, :usuario)',
            [
                ':categoria'   => (int)$dados['categoria_id'],
                ':descricao'   => trim($dados['descricao']),
                ':valor'       => (float)$dados['valor'],
                ':data'        => $dados['data_despesa'],
                ':comprovante' => $dados['comprovante'] ?? null,
                ':usuario'     => $registradoPor,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function excluirLogicamente(int $id): bool {
        return $this->consultar(
            'UPDATE despesas SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL',
            [':id' => $id]
        )->rowCount() > 0;
    }

    // -----------------------------------------------------------------
    // Totais
    // -----------------------------------------------------------------

    public function totalNoPeriodo(string $de, string $ate): float {
        return (float)$this->buscarValor(
            'SELECT COALESCE(SUM(valor), 0) FROM despesas
             WHERE deleted_at IS NULL AND data_despesa BETWEEN :de AND :ate',
            [':de' => $de, ':ate' => $ate]
        );
    }

    /**
     * Total por categoria, base do detalhamento das despesas na tela.
     */
    public function totalPorCategoria(string $de, string $ate): array {
        return $this->buscarTodos(
            'SELECT c.nome, COALESCE(SUM(d.valor), 0) AS total, COUNT(d.id) AS lancamentos
             FROM categorias_despesas c
             LEFT JOIN despesas d
                    ON d.categoria_id = c.id
                   AND d.deleted_at IS NULL
                   AND d.data_despesa BETWEEN :de AND :ate
             GROUP BY c.id, c.nome
             HAVING total > 0
             ORDER BY total DESC',
            [':de' => $de, ':ate' => $ate]
        );
    }
}
