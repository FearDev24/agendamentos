<?php
// database/migrations/003_add_email_change_tokens.php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Adiciona o suporte à troca de e-mail com confirmação por link (docs/FSD.md, Módulo 2).
 *
 * O novo endereço fica retido em `email_pendente` até que o usuário clique no
 * link enviado para ele. Só então o campo `email` é efetivamente atualizado.
 */
return [
    'descricao' => '- Colunas de confirmação de troca de e-mail em `usuarios`',

    'up' => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE usuarios
                ADD COLUMN email_pendente VARCHAR(100) NULL AFTER email,
                ADD COLUMN token_email VARCHAR(100) NULL AFTER token_expiracao,
                ADD COLUMN token_email_expiracao DATETIME NULL AFTER token_email,
                ADD INDEX idx_usuarios_token_recuperacao (token_recuperacao),
                ADD INDEX idx_usuarios_token_email (token_email)
        ");
    },
];
