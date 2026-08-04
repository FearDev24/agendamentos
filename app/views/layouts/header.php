<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$appName = $config['app']['name'] ?? 'AgendaBarber Web';
$baseUrl = $config['app']['url'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $appName) ?></title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS Local -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css">
    <!-- Estilos Globais do Design System (Elevated Grooming & Wellness) -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/css/style.css">
</head>
<body class="bg-surface text-on-surface">
<header class="navbar navbar-expand-lg border-bottom border-secondary-dark bg-surface-container py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 text-gold fw-bold" href="<?= $baseUrl ?>/">
            <img src="<?= $baseUrl ?>/public/assets/img/logojablonskiprincipal.png" alt="Logo" height="40">
            <span><?= htmlspecialchars($appName) ?></span>
        </a>
        <button class="navbar-toggler text-gold" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link text-on-surface hover-gold" href="<?= $baseUrl ?>/">Início</a></li>
                <li class="nav-item"><a class="nav-link text-on-surface hover-gold" href="<?= $baseUrl ?>/agendar">Agendar</a></li>
            </ul>
            <div class="d-flex gap-2">
                <a href="<?= $baseUrl ?>/login" class="btn btn-outline-gold px-4">Entrar</a>
                <a href="<?= $baseUrl ?>/cadastro" class="btn btn-gold px-4">Cadastrar</a>
            </div>
        </div>
    </div>
</header>
