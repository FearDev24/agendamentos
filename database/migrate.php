<?php
/**
 * database/migrate.php - Entrada CLI do sistema de migrations
 *
 * Uso: php database/migrate.php [--status]
 *
 * Este arquivo recusa execução via navegador: a pasta database/ é bloqueada
 * por .htaccess e o script só roda sob a SAPI 'cli'.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Este script só pode ser executado via linha de comando.');
}

define('APP_EXEC', true);
date_default_timezone_set('America/Sao_Paulo');

$config = require __DIR__ . '/../config/config.php';
$GLOBALS['config'] = $config;

require_once __DIR__ . '/MigrationRunner.php';

$apenasStatus = in_array('--status', $argv ?? [], true);

echo "=====================================================\n";
echo " AgendaBarber Web - Migrations\n";
echo " Banco: {$config['db']['dbname']} @ {$config['db']['host']}\n";
echo "=====================================================\n";

try {
    $runner = new MigrationRunner();

    if ($apenasStatus) {
        $aplicadas = $runner->aplicadas();
        $pendentes = $runner->pendentes();

        echo "\nAplicadas (" . count($aplicadas) . "):\n";
        foreach ($aplicadas as $m) { echo "  [x] {$m}\n"; }
        if (empty($aplicadas)) { echo "  (nenhuma)\n"; }

        echo "\nPendentes (" . count($pendentes) . "):\n";
        foreach ($pendentes as $m) { echo "  [ ] {$m}\n"; }
        if (empty($pendentes)) { echo "  (nenhuma)\n"; }

        echo "\n";
        exit(0);
    }

    foreach ($runner->run() as $linha) {
        $prefixo = $linha['tipo'] === 'ok' ? '[OK]  ' : ($linha['tipo'] === 'erro' ? '[ERRO]' : '[INFO]');
        echo "{$prefixo} {$linha['mensagem']}\n";
    }

    echo "\nMigrations concluídas com sucesso.\n";
    exit(0);

} catch (Throwable $e) {
    echo "\n[FALHA] " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}
