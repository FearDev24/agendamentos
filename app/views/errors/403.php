<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$baseUrl = $config['app']['url'] ?? '';
$mensagem = $mensagemPersonalizada
    ?? 'Acesso negado. Você não possui permissão para acessar esta funcionalidade.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Acesso Negado</title>
    <link rel="stylesheet" href="<?= FormatHelper::asset('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= FormatHelper::asset('assets/css/style.css') ?>">
</head>
<body class="bg-surface text-on-surface">
<main class="container py-5 text-center">
    <div class="style-card p-5 mx-auto" style="max-width: 560px;">
        <p class="display-4 text-erro mb-2">403</p>
        <h1 class="h4 text-gold mb-3">Acesso Negado</h1>
        <p class="text-muted-custom mb-4"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= $baseUrl ?>/" class="btn btn-gold px-4">Voltar ao Início</a>
    </div>
</main>
</body>
</html>
