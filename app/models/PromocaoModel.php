<?php
// app/models/PromocaoModel.php - Promoções públicas
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Campanhas promocionais divulgadas na vitrine (docs/FSD.md Módulo 7).
 *
 * Cada promoção recebe um link público de compartilhamento. O desconto pode ser
 * um valor fixo ou um percentual, e a vigência é controlada por data.
 */
class PromocaoModel extends BaseModel {

    public const TIPOS_DESCONTO = ['fixo' => 'Valor fixo (R$)', 'percentual' => 'Percentual (%)'];
    public const STATUS = ['ativa' => 'Ativa', 'inativa' => 'Inativa', 'expirada' => 'Expirada'];

    // -----------------------------------------------------------------
    // Consultas
    // -----------------------------------------------------------------

    public function listar(string $status = ''): array {
        $sql = 'SELECT p.*, s.nome AS servico_nome
                FROM promocoes p
                LEFT JOIN servicos s ON s.id = p.servico_id
                WHERE 1=1';
        $parametros = [];

        if ($status !== '' && array_key_exists($status, self::STATUS)) {
            $sql .= ' AND p.status = :status';
            $parametros[':status'] = $status;
        }

        $sql .= ' ORDER BY p.data_inicio DESC, p.id DESC';

        return $this->buscarTodos($sql, $parametros);
    }

    /**
     * Promoções que devem aparecer na vitrine pública neste momento.
     */
    public function vigentes(): array {
        return $this->buscarTodos(
            "SELECT p.*, s.nome AS servico_nome
             FROM promocoes p
             LEFT JOIN servicos s ON s.id = p.servico_id
             WHERE p.status = 'ativa'
               AND CURDATE() BETWEEN p.data_inicio AND p.data_fim
               AND (p.limite_total_usos IS NULL OR p.usos_realizados < p.limite_total_usos)
             ORDER BY p.data_fim ASC"
        );
    }

    public function buscarPorId(int $id): ?array {
        return $this->buscarUm(
            'SELECT p.*, s.nome AS servico_nome
             FROM promocoes p
             LEFT JOIN servicos s ON s.id = p.servico_id
             WHERE p.id = :id',
            [':id' => $id]
        );
    }

    /**
     * Indica se a promoção pode ser divulgada/aplicada agora.
     */
    public function estaVigente(array $promocao): bool {
        $hoje = date('Y-m-d');

        if ($promocao['status'] !== 'ativa') {
            return false;
        }

        if ($hoje < $promocao['data_inicio'] || $hoje > $promocao['data_fim']) {
            return false;
        }

        $limite = $promocao['limite_total_usos'];

        return $limite === null || (int)$promocao['usos_realizados'] < (int)$limite;
    }

    /**
     * Valor do desconto aplicado sobre um total.
     * O desconto nunca pode ultrapassar o próprio valor da compra.
     */
    public function calcularDesconto(array $promocao, float $valorBase): float {
        if ($valorBase <= 0) {
            return 0.0;
        }

        $desconto = $promocao['tipo_desconto'] === 'percentual'
            ? $valorBase * ((float)$promocao['valor_desconto'] / 100)
            : (float)$promocao['valor_desconto'];

        return round(min($desconto, $valorBase), 2);
    }

    // -----------------------------------------------------------------
    // Escrita
    // -----------------------------------------------------------------

    public function criar(array $dados): int {
        $this->consultar(
            'INSERT INTO promocoes
                (titulo, descricao, imagem, servico_id, tipo_desconto, valor_desconto,
                 data_inicio, data_fim, limite_total_usos, status)
             VALUES
                (:titulo, :descricao, :imagem, :servico, :tipo, :valor,
                 :inicio, :fim, :limite, :status)',
            [
                ':titulo'    => trim($dados['titulo']),
                ':descricao' => $dados['descricao'] !== '' ? $dados['descricao'] : null,
                ':imagem'    => $dados['imagem'] ?? null,
                ':servico'   => !empty($dados['servico_id']) ? (int)$dados['servico_id'] : null,
                ':tipo'      => $dados['tipo_desconto'],
                ':valor'     => (float)$dados['valor_desconto'],
                ':inicio'    => $dados['data_inicio'],
                ':fim'       => $dados['data_fim'],
                ':limite'    => $dados['limite_total_usos'] !== '' ? (int)$dados['limite_total_usos'] : null,
                ':status'    => $dados['status'],
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool {
        $sql = 'UPDATE promocoes SET
                    titulo = :titulo,
                    descricao = :descricao,
                    servico_id = :servico,
                    tipo_desconto = :tipo,
                    valor_desconto = :valor,
                    data_inicio = :inicio,
                    data_fim = :fim,
                    limite_total_usos = :limite,
                    status = :status';

        $parametros = [
            ':titulo'    => trim($dados['titulo']),
            ':descricao' => $dados['descricao'] !== '' ? $dados['descricao'] : null,
            ':servico'   => !empty($dados['servico_id']) ? (int)$dados['servico_id'] : null,
            ':tipo'      => $dados['tipo_desconto'],
            ':valor'     => (float)$dados['valor_desconto'],
            ':inicio'    => $dados['data_inicio'],
            ':fim'       => $dados['data_fim'],
            ':limite'    => $dados['limite_total_usos'] !== '' ? (int)$dados['limite_total_usos'] : null,
            ':status'    => $dados['status'],
            ':id'        => $id,
        ];

        // O banner só é trocado quando um novo arquivo é enviado
        if (!empty($dados['imagem'])) {
            $sql .= ', imagem = :imagem';
            $parametros[':imagem'] = $dados['imagem'];
        }

        $sql .= ' WHERE id = :id';

        return $this->consultar($sql, $parametros)->rowCount() >= 0;
    }

    public function excluir(int $id): bool {
        return $this->consultar('DELETE FROM promocoes WHERE id = :id', [':id' => $id])->rowCount() > 0;
    }

    /**
     * Marca como expiradas as promoções cuja validade já passou.
     */
    public function expirarVencidas(): int {
        return $this->consultar(
            "UPDATE promocoes SET status = 'expirada'
             WHERE status = 'ativa' AND data_fim < CURDATE()"
        )->rowCount();
    }

    // -----------------------------------------------------------------
    // Uso das promoções
    // -----------------------------------------------------------------

    /**
     * Registra o uso por um cliente. A chave única impede reuso pela mesma
     * pessoa; o contador alimenta o limite total da campanha.
     */
    public function registrarUso(int $promocaoId, int $clienteId, int $agendamentoId): bool {
        $this->db->beginTransaction();

        try {
            $this->consultar(
                'INSERT INTO uso_promocoes (promocao_id, cliente_id, agendamento_id)
                 VALUES (:promocao, :cliente, :agendamento)',
                [':promocao' => $promocaoId, ':cliente' => $clienteId, ':agendamento' => $agendamentoId]
            );

            $this->consultar(
                'UPDATE promocoes SET usos_realizados = usos_realizados + 1 WHERE id = :id',
                [':id' => $promocaoId]
            );

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false; // cliente já usou esta promoção
        }
    }

    public function clienteJaUsou(int $promocaoId, int $clienteId): bool {
        return (int)$this->buscarValor(
            'SELECT COUNT(*) FROM uso_promocoes WHERE promocao_id = :p AND cliente_id = :c',
            [':p' => $promocaoId, ':c' => $clienteId]
        ) > 0;
    }

    /**
     * Relatório de utilizações de uma campanha.
     */
    public function usos(int $promocaoId): array {
        return $this->buscarTodos(
            'SELECT up.*, u.nome AS cliente_nome, a.codigo_reserva, a.valor_total
             FROM uso_promocoes up
             INNER JOIN usuarios u ON u.id = up.cliente_id
             INNER JOIN agendamentos a ON a.id = up.agendamento_id
             WHERE up.promocao_id = :id
             ORDER BY up.data_uso DESC',
            [':id' => $promocaoId]
        );
    }
}
