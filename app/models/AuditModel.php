<?php
// app/models/AuditModel.php - Trilha de auditoria de ações administrativas
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Registra ações administrativas sensíveis em `audit_logs` (docs/FSD.md seção 17):
 * alteração de preços, jornadas, bloqueios de agenda, descontos manuais,
 * cancelamentos administrativos e exclusões lógicas.
 *
 * A tela de consulta destes registros é entregue na Fase 9.
 */
class AuditModel extends BaseModel {

    public const CRIAR     = 'criar';
    public const ATUALIZAR = 'atualizar';
    public const EXCLUIR   = 'excluir';
    public const RESTAURAR = 'restaurar';

    /**
     * Grava um evento de auditoria com autoria, IP e o antes/depois em JSON.
     */
    public function registrar(
        string $acao,
        string $entidade,
        ?int $entidadeId = null,
        ?array $dadosAnteriores = null,
        ?array $dadosNovos = null
    ): void {
        $this->consultar(
            'INSERT INTO audit_logs (usuario_id, acao, entidade, entidade_id, dados_anteriores, dados_novos, ip_address)
             VALUES (:usuario_id, :acao, :entidade, :entidade_id, :anteriores, :novos, :ip)',
            [
                ':usuario_id'  => AuthHelper::id(),
                ':acao'        => $acao,
                ':entidade'    => $entidade,
                ':entidade_id' => $entidadeId,
                ':anteriores'  => $dadosAnteriores === null ? null : $this->paraJson($dadosAnteriores),
                ':novos'       => $dadosNovos === null ? null : $this->paraJson($dadosNovos),
                ':ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    /**
     * Consulta filtrada da trilha de auditoria (docs/FSD.md seção 17).
     *
     * @param array $filtros ['de', 'ate', 'usuario_id', 'entidade', 'acao']
     */
    public function listar(array $filtros, int $limite = 200): array {
        $sql = 'SELECT a.*, u.nome AS autor, u.email AS autor_email, u.perfil AS autor_perfil
                FROM audit_logs a
                LEFT JOIN usuarios u ON u.id = a.usuario_id
                WHERE 1=1';
        $parametros = [];

        if (!empty($filtros['de'])) {
            $sql .= ' AND a.created_at >= :de';
            $parametros[':de'] = $filtros['de'] . ' 00:00:00';
        }

        if (!empty($filtros['ate'])) {
            $sql .= ' AND a.created_at <= :ate';
            $parametros[':ate'] = $filtros['ate'] . ' 23:59:59';
        }

        if (!empty($filtros['usuario_id'])) {
            $sql .= ' AND a.usuario_id = :usuario';
            $parametros[':usuario'] = (int)$filtros['usuario_id'];
        }

        if (!empty($filtros['entidade'])) {
            $sql .= ' AND a.entidade = :entidade';
            $parametros[':entidade'] = $filtros['entidade'];
        }

        if (!empty($filtros['acao'])) {
            $sql .= ' AND a.acao = :acao';
            $parametros[':acao'] = $filtros['acao'];
        }

        $sql .= ' ORDER BY a.id DESC LIMIT ' . max(1, min(1000, $limite));

        return $this->buscarTodos($sql, $parametros);
    }

    /**
     * Valores distintos presentes na trilha, para montar os filtros da tela.
     */
    public function valoresDisponiveis(): array {
        return [
            'entidades' => $this->consultar(
                'SELECT DISTINCT entidade FROM audit_logs ORDER BY entidade ASC'
            )->fetchAll(PDO::FETCH_COLUMN),
            'acoes' => $this->consultar(
                'SELECT DISTINCT acao FROM audit_logs ORDER BY acao ASC'
            )->fetchAll(PDO::FETCH_COLUMN),
            'autores' => $this->buscarTodos(
                'SELECT DISTINCT a.usuario_id, u.nome
                 FROM audit_logs a
                 INNER JOIN usuarios u ON u.id = a.usuario_id
                 ORDER BY u.nome ASC'
            ),
        ];
    }

    /**
     * Eventos de segurança, exibidos ao lado da auditoria (FSD seção 19.2).
     */
    public function eventosDeSeguranca(array $filtros, int $limite = 100): array {
        $sql = 'SELECT s.*, u.nome AS usuario_nome
                FROM security_logs s
                LEFT JOIN usuarios u ON u.id = s.usuario_id
                WHERE 1=1';
        $parametros = [];

        if (!empty($filtros['de'])) {
            $sql .= ' AND s.created_at >= :de';
            $parametros[':de'] = $filtros['de'] . ' 00:00:00';
        }

        if (!empty($filtros['ate'])) {
            $sql .= ' AND s.created_at <= :ate';
            $parametros[':ate'] = $filtros['ate'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY s.id DESC LIMIT ' . max(1, min(500, $limite));

        return $this->buscarTodos($sql, $parametros);
    }

    /**
     * Erros registrados pela aplicação, para diagnóstico rápido.
     */
    public function errosRecentes(int $limite = 50): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM error_logs ORDER BY id DESC LIMIT :limite'
        );
        $stmt->bindValue(':limite', max(1, min(500, $limite)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Registros excluídos logicamente, restauráveis pelo Administrador
     * Principal (docs/FSD.md seção 18).
     */
    public function registrosExcluidos(): array {
        $excluidos = [];

        $tabelas = [
            'servicos'      => 'nome',
            'profissionais' => null,
            'usuarios'      => 'nome',
            'despesas'      => 'descricao',
            'agendamentos'  => 'codigo_reserva',
        ];

        foreach ($tabelas as $tabela => $colunaRotulo) {
            if ($tabela === 'profissionais') {
                $linhas = $this->buscarTodos(
                    'SELECT p.id, u.nome AS rotulo, p.deleted_at
                     FROM profissionais p
                     INNER JOIN usuarios u ON u.id = p.usuario_id
                     WHERE p.deleted_at IS NOT NULL
                     ORDER BY p.deleted_at DESC'
                );
            } else {
                $linhas = $this->buscarTodos(
                    "SELECT id, {$colunaRotulo} AS rotulo, deleted_at
                     FROM {$tabela}
                     WHERE deleted_at IS NOT NULL
                     ORDER BY deleted_at DESC"
                );
            }

            if (!empty($linhas)) {
                $excluidos[$tabela] = $linhas;
            }
        }

        return $excluidos;
    }

    /**
     * Restaura um registro excluído logicamente.
     *
     * A lista de tabelas é fixa no código: o nome vem da requisição e não pode
     * ser interpolado livremente na SQL.
     */
    public function restaurar(string $tabela, int $id): bool {
        $permitidas = ['servicos', 'profissionais', 'usuarios', 'despesas', 'agendamentos'];

        if (!in_array($tabela, $permitidas, true)) {
            throw new InvalidArgumentException('Tabela não permitida para restauração.');
        }

        $alterou = $this->consultar(
            "UPDATE {$tabela} SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL",
            [':id' => $id]
        )->rowCount() > 0;

        if ($alterou) {
            // Profissional excluído também desativa a conta de login
            if ($tabela === 'profissionais') {
                $this->consultar(
                    "UPDATE usuarios u
                     INNER JOIN profissionais p ON p.usuario_id = u.id
                     SET u.deleted_at = NULL, u.status = 'ativo'
                     WHERE p.id = :id",
                    [':id' => $id]
                );
            }

            if ($tabela === 'usuarios') {
                $this->consultar(
                    "UPDATE usuarios SET status = 'ativo' WHERE id = :id",
                    [':id' => $id]
                );
            }

            $this->registrar(self::RESTAURAR, $tabela, $id);
        }

        return $alterou;
    }

    public function listarPorEntidade(string $entidade, int $entidadeId): array {
        return $this->buscarTodos(
            'SELECT a.*, u.nome AS autor
             FROM audit_logs a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.entidade = :entidade AND a.entidade_id = :id
             ORDER BY a.id DESC',
            [':entidade' => $entidade, ':id' => $entidadeId]
        );
    }

    /**
     * Remove campos sensíveis antes de serializar (nunca auditar senhas/tokens).
     */
    private function paraJson(array $dados): string {
        unset(
            $dados['senha'], $dados['senha_hash'], $dados['senha_confirmacao'],
            $dados['token_recuperacao'], $dados['token_email'], $dados['csrf_token']
        );

        return json_encode($dados, JSON_UNESCAPED_UNICODE);
    }
}
