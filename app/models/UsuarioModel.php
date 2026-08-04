<?php
// app/models/UsuarioModel.php - Acesso a dados de contas do sistema
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Model das contas do sistema (clientes, profissionais e administradores).
 *
 * Todas as leituras operacionais filtram `deleted_at IS NULL` (Soft Delete,
 * docs/FSD.md seção 18).
 */
class UsuarioModel extends BaseModel {

    public const PERFIS = ['cliente', 'profissional', 'admin_principal', 'admin_limitado'];

    // -----------------------------------------------------------------
    // Consultas
    // -----------------------------------------------------------------

    public function buscarPorId(int $id): ?array {
        return $this->buscarUm(
            'SELECT * FROM usuarios WHERE id = :id AND deleted_at IS NULL',
            [':id' => $id]
        );
    }

    public function buscarPorEmail(string $email): ?array {
        return $this->buscarUm(
            'SELECT * FROM usuarios WHERE email = :email AND deleted_at IS NULL',
            [':email' => mb_strtolower(trim($email))]
        );
    }

    public function emailEmUso(string $email, ?int $ignorarId = null): bool {
        $sql = 'SELECT COUNT(*) FROM usuarios WHERE (email = :email OR email_pendente = :email2) AND deleted_at IS NULL';
        $parametros = [':email' => mb_strtolower(trim($email)), ':email2' => mb_strtolower(trim($email))];

        if ($ignorarId !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $ignorarId;
        }

        return (int)$this->buscarValor($sql, $parametros) > 0;
    }

    // -----------------------------------------------------------------
    // Escrita
    // -----------------------------------------------------------------

    /**
     * Cria uma conta. A senha é recebida em texto puro e gravada com hash.
     */
    public function criar(array $dados): int {
        $this->consultar(
            'INSERT INTO usuarios (nome, email, senha_hash, telefone, foto, perfil, status)
             VALUES (:nome, :email, :senha_hash, :telefone, :foto, :perfil, :status)',
            [
                ':nome'       => trim($dados['nome']),
                ':email'      => mb_strtolower(trim($dados['email'])),
                ':senha_hash' => password_hash($dados['senha'], PASSWORD_BCRYPT),
                ':telefone'   => trim($dados['telefone']),
                ':foto'       => $dados['foto'] ?? null,
                ':perfil'     => $dados['perfil'] ?? 'cliente',
                ':status'     => $dados['status'] ?? 'ativo',
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function atualizarDadosBasicos(int $id, string $nome, string $telefone): bool {
        return $this->consultar(
            'UPDATE usuarios SET nome = :nome, telefone = :telefone WHERE id = :id AND deleted_at IS NULL',
            [':nome' => trim($nome), ':telefone' => trim($telefone), ':id' => $id]
        )->rowCount() >= 0;
    }

    public function atualizarFoto(int $id, string $arquivo): bool {
        return $this->consultar(
            'UPDATE usuarios SET foto = :foto WHERE id = :id AND deleted_at IS NULL',
            [':foto' => $arquivo, ':id' => $id]
        )->rowCount() > 0;
    }

    public function atualizarSenha(int $id, string $novaSenha): bool {
        return $this->consultar(
            'UPDATE usuarios SET senha_hash = :hash, token_recuperacao = NULL, token_expiracao = NULL
             WHERE id = :id AND deleted_at IS NULL',
            [':hash' => password_hash($novaSenha, PASSWORD_BCRYPT), ':id' => $id]
        )->rowCount() > 0;
    }

    /**
     * Exclusão lógica da conta (Soft Delete).
     */
    public function excluirLogicamente(int $id): bool {
        return $this->consultar(
            'UPDATE usuarios SET deleted_at = NOW(), status = :status WHERE id = :id AND deleted_at IS NULL',
            [':status' => 'inativo', ':id' => $id]
        )->rowCount() > 0;
    }

    /**
     * Conta agendamentos futuros ativos do cliente. Usado para autorizar
     * (ou barrar) a exclusão da conta, conforme docs/FSD.md Módulo 2.
     */
    public function possuiAgendamentosFuturosAtivos(int $id): bool {
        $total = (int)$this->buscarValor(
            "SELECT COUNT(*) FROM agendamentos
             WHERE cliente_id = :id
               AND deleted_at IS NULL
               AND situacao IN ('aguardando_pagamento', 'confirmado')
               AND CONCAT(data_agendamento, ' ', hora_inicio) > NOW()",
            [':id' => $id]
        );

        return $total > 0;
    }

    // -----------------------------------------------------------------
    // Autenticação
    // -----------------------------------------------------------------

    /**
     * Valida a senha informada contra o hash armazenado.
     */
    public function verificarSenha(array $usuario, string $senha): bool {
        return password_verify($senha, $usuario['senha_hash']);
    }

    // -----------------------------------------------------------------
    // Tokens de recuperação de senha (validade de 1 hora)
    // -----------------------------------------------------------------

    public function gerarTokenRecuperacao(int $id): string {
        $token = bin2hex(random_bytes(32));

        $this->consultar(
            'UPDATE usuarios SET token_recuperacao = :token, token_expiracao = DATE_ADD(NOW(), INTERVAL 1 HOUR)
             WHERE id = :id AND deleted_at IS NULL',
            [':token' => $token, ':id' => $id]
        );

        return $token;
    }

    /**
     * Busca o usuário dono de um token de recuperação ainda válido.
     */
    public function buscarPorTokenRecuperacao(string $token): ?array {
        return $this->buscarUm(
            'SELECT * FROM usuarios
             WHERE token_recuperacao = :token
               AND token_expiracao > NOW()
               AND deleted_at IS NULL',
            [':token' => $token]
        );
    }

    public function limparTokenRecuperacao(int $id): void {
        $this->consultar(
            'UPDATE usuarios SET token_recuperacao = NULL, token_expiracao = NULL WHERE id = :id',
            [':id' => $id]
        );
    }

    // -----------------------------------------------------------------
    // Tokens de troca de e-mail (validade de 1 hora)
    // -----------------------------------------------------------------

    public function solicitarTrocaEmail(int $id, string $novoEmail): string {
        $token = bin2hex(random_bytes(32));

        $this->consultar(
            'UPDATE usuarios
             SET email_pendente = :email,
                 token_email = :token,
                 token_email_expiracao = DATE_ADD(NOW(), INTERVAL 1 HOUR)
             WHERE id = :id AND deleted_at IS NULL',
            [':email' => mb_strtolower(trim($novoEmail)), ':token' => $token, ':id' => $id]
        );

        return $token;
    }

    /**
     * Efetiva a troca de e-mail caso o token esteja válido.
     * Retorna o novo e-mail confirmado ou null se o token for inválido/expirado.
     */
    public function confirmarTrocaEmail(string $token): ?string {
        $usuario = $this->buscarUm(
            'SELECT id, email_pendente FROM usuarios
             WHERE token_email = :token
               AND token_email_expiracao > NOW()
               AND email_pendente IS NOT NULL
               AND deleted_at IS NULL',
            [':token' => $token]
        );

        if ($usuario === null) {
            return null;
        }

        $this->consultar(
            'UPDATE usuarios
             SET email = email_pendente,
                 email_pendente = NULL,
                 token_email = NULL,
                 token_email_expiracao = NULL
             WHERE id = :id',
            [':id' => $usuario['id']]
        );

        return $usuario['email_pendente'];
    }
}
