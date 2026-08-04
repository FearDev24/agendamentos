<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$baseUrl = $GLOBALS['config']['app']['url'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro Interno</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/css/style.css">
</head>
<body class="bg-surface text-on-surface">
<main class="container py-5 text-center">
    <div class="style-card p-5 mx-auto" style="max-width: 560px;">
        <p class="display-4 text-erro mb-2">500</p>
        <h1 class="h4 text-gold mb-3">Ocorreu um erro inesperado</h1>
        <p class="text-muted-custom mb-4">Nossa equipe foi notificada. Tente novamente em alguns instantes.</p>
        <a href="<?= $baseUrl ?>/" class="btn btn-gold px-4">Voltar ao Início</a>
    </div>
</main>
</body>
</html>
