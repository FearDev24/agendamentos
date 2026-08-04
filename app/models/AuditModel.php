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
