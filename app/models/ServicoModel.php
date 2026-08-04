<?php
// app/models/ServicoModel.php - Catálogo de serviços oferecidos
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Serviços do estabelecimento (nome, preço, duração, imagem e orientações).
 *
 * Leituras operacionais filtram `deleted_at IS NULL` (Soft Delete).
 * A exclusão nunca remove o registro fisicamente, preservando o vínculo
 * histórico com `agendamento_servicos`.
 */
class ServicoModel extends BaseModel {

    public const STATUS = ['ativo', 'indisponivel', 'inativo'];

    /**
     * Listagem administrativa com busca por nome e filtro de status.
     */
    public function listar(string $busca = '', string $status = '', bool $incluirExcluidos = false): array {
        $sql = 'SELECT * FROM servicos WHERE 1=1';
        $parametros = [];

        if (!$incluirExcluidos) {
            $sql .= ' AND deleted_at IS NULL';
        }

        if ($busca !== '') {
            $sql .= ' AND nome LIKE :busca';
            $parametros[':busca'] = '%' . $busca . '%';
        }

        if ($status !== '' && in_array($status, self::STATUS, true)) {
            $sql .= ' AND status = :status';
            $parametros[':status'] = $status;
        }

        $sql .= ' ORDER BY nome ASC';

        return $this->buscarTodos($sql, $parametros);
    }

    /**
     * Serviços disponíveis para o cliente agendar (usado a partir da Fase 5).
     */
    public function listarAtivos(): array {
        return $this->buscarTodos(
            "SELECT * FROM servicos WHERE status = 'ativo' AND deleted_at IS NULL ORDER BY nome ASC"
        );
    }

    public function listarExcluidos(): array {
        return $this->buscarTodos('SELECT * FROM servicos WHERE deleted_at IS NOT NULL ORDER BY nome ASC');
    }

    public function buscarPorId(int $id): ?array {
        return $this->buscarUm(
            'SELECT * FROM servicos WHERE id = :id AND deleted_at IS NULL',
            [':id' => $id]
        );
    }

    public function nomeEmUso(string $nome, ?int $ignorarId = null): bool {
        $sql = 'SELECT COUNT(*) FROM servicos WHERE nome = :nome AND deleted_at IS NULL';
        $parametros = [':nome' => trim($nome)];

        if ($ignorarId !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $ignorarId;
        }

        return (int)$this->buscarValor($sql, $parametros) > 0;
    }

    public function criar(array $dados): int {
        $this->consultar(
            'INSERT INTO servicos (nome, descricao, duracao_minutos, preco, imagem, orientacoes, status, previsao_retorno)
             VALUES (:nome, :descricao, :duracao, :preco, :imagem, :orientacoes, :status, :previsao)',
            [
                ':nome'        => trim($dados['nome']),
                ':descricao'   => $dados['descricao'] !== '' ? $dados['descricao'] : null,
                ':duracao'     => (int)$dados['duracao_minutos'],
                ':preco'       => (float)$dados['preco'],
                ':imagem'      => $dados['imagem'] ?? null,
                ':orientacoes' => $dados['orientacoes'] !== '' ? $dados['orientacoes'] : null,
                ':status'      => $dados['status'],
                ':previsao'    => $dados['previsao_retorno'] ?: null,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function atualizar(int $id, array $dados): bool {
        $sql = 'UPDATE servicos SET
                    nome = :nome,
                    descricao = :descricao,
                    duracao_minutos = :duracao,
                    preco = :preco,
                    orientacoes = :orientacoes,
                    status = :status,
                    previsao_retorno = :previsao';

        $parametros = [
            ':nome'        => trim($dados['nome']),
            ':descricao'   => $dados['descricao'] !== '' ? $dados['descricao'] : null,
            ':duracao'     => (int)$dados['duracao_minutos'],
            ':preco'       => (float)$dados['preco'],
            ':orientacoes' => $dados['orientacoes'] !== '' ? $dados['orientacoes'] : null,
            ':status'      => $dados['status'],
            ':previsao'    => $dados['previsao_retorno'] ?: null,
            ':id'          => $id,
        ];

        // A imagem só é sobrescrita quando um novo arquivo é enviado
        if (!empty($dados['imagem'])) {
            $sql .= ', imagem = :imagem';
            $parametros[':imagem'] = $dados['imagem'];
        }

        $sql .= ' WHERE id = :id AND deleted_at IS NULL';

        return $this->consultar($sql, $parametros)->rowCount() >= 0;
    }

    public function excluirLogicamente(int $id): bool {
        return $this->consultar(
            'UPDATE servicos SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL',
            [':id' => $id]
        )->rowCount() > 0;
    }

    public function restaurar(int $id): bool {
        return $this->consultar(
            'UPDATE servicos SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL',
            [':id' => $id]
        )->rowCount() > 0;
    }

    /**
     * Indica se o serviço já foi contratado em algum agendamento.
     * Usado para alertar o administrador antes da exclusão lógica.
     */
    public function possuiHistorico(int $id): bool {
        return (int)$this->buscarValor(
            'SELECT COUNT(*) FROM agendamento_servicos WHERE servico_id = :id',
            [':id' => $id]
        ) > 0;
    }

    /**
     * Profissionais habilitados a executar um serviço.
     */
    public function profissionaisHabilitados(int $servicoId): array {
        return $this->buscarTodos(
            'SELECT p.id, u.nome
             FROM profissional_servicos ps
             INNER JOIN profissionais p ON p.id = ps.profissional_id
             INNER JOIN usuarios u ON u.id = p.usuario_id
             WHERE ps.servico_id = :id AND p.deleted_at IS NULL AND u.deleted_at IS NULL
             ORDER BY u.nome ASC',
            [':id' => $servicoId]
        );
    }
}
