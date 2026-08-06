<?php
// database/migrations/007_add_senha_alterada_em.php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Momento da última troca de senha, para invalidar sessões antigas.
 *
 * Sem esta marca, redefinir a senha não expulsava ninguém: quem já estivesse
 * autenticado continuava dentro. Justamente no cenário que motiva a troca —
 * conta comprometida — o invasor permanecia com acesso.
 *
 * A comparação é feita contra o `logado_em` guardado na sessão: toda sessão
 * iniciada antes da troca deixa de valer.
 */
return [
    'descricao' => '- Marca da última troca de senha para invalidar sessões anteriores',

    'up' => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE usuarios
                ADD COLUMN senha_alterada_em DATETIME NULL AFTER senha_hash
        ");

        // Contas existentes não devem ser desconectadas pela migration.
        $pdo->exec("UPDATE usuarios SET senha_alterada_em = created_at");
    },
];
