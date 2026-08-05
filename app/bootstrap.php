<?php
// app/bootstrap.php - Autoloader e carregamento das classes da aplicação
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Autoloader nativo por convenção de diretório.
 * Procura a classe em app/helpers/, app/models/ e app/controllers/.
 */
spl_autoload_register(function (string $classe): void {
    // Aceita apenas nomes simples de classe (sem namespace/travessia de caminho)
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $classe)) {
        return;
    }

    $diretorios = [
        __DIR__ . '/helpers/',
        __DIR__ . '/models/',
        __DIR__ . '/controllers/',
        __DIR__ . '/core/',
        __DIR__ . '/gateways/',
    ];

    foreach ($diretorios as $diretorio) {
        $arquivo = $diretorio . $classe . '.php';
        if (is_file($arquivo)) {
            require_once $arquivo;
            return;
        }
    }
});
