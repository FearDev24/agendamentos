<?php
// database/migrations/006_add_controle_lembretes.php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Controle de envio dos lembretes automáticos (docs/FSD.md seção 23.2).
 *
 * O Cron roda a cada 15 minutos e varre a mesma faixa de agendamentos várias
 * vezes. Sem marcar o que já foi enviado, o cliente receberia o mesmo lembrete
 * repetidamente.
 */
return [
    'descricao' => '- Marcação de lembretes de 24h e 2h já enviados',

    'up' => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE agendamentos
                ADD COLUMN lembrete_24h_em DATETIME NULL AFTER confirmado_presenca,
                ADD COLUMN lembrete_2h_em DATETIME NULL AFTER lembrete_24h_em,
                ADD INDEX idx_agendamentos_lembretes (situacao, data_agendamento)
        ");
    },
];
