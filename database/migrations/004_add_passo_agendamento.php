<?php
// database/migrations/004_add_passo_agendamento.php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Acrescenta o passo (granularidade) da grade de horários oferecida ao cliente.
 *
 * Exemplo: com passo de 15 minutos e jornada iniciando às 09:00, os horários
 * candidatos são 09:00, 09:15, 09:30... O motor de disponibilidade descarta
 * os que não couberem na agenda.
 */
return [
    'descricao' => '- Configuração do passo da grade de horários',

    'up' => function (PDO $pdo): void {
        $stmt = $pdo->prepare(
            'INSERT INTO system_settings (chave, valor) VALUES (:chave, :valor)
             ON DUPLICATE KEY UPDATE valor = valor'
        );

        $stmt->execute([':chave' => 'agendamento_passo_minutos', ':valor' => '15']);
    },
];
