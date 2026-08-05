<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$appName = $config['app']['name'] ?? 'AgendaBarber Web';
$baseUrl = $config['app']['url'] ?? '';
$logado = AuthHelper::check();
$perfilAtual = AuthHelper::role();
$csrf = SessionHelper::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $appName, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Aplicativo instalável (PWA) -->
    <link rel="manifest" href="<?= $baseUrl ?>/manifest.webmanifest">
    <meta name="theme-color" content="#0f1416">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= FormatHelper::asset('assets/img/pwa/apple-touch-icon.png') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= FormatHelper::asset('assets/img/pwa/favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= FormatHelper::asset('assets/img/pwa/favicon-16.png') ?>">

    <!-- Bootstrap 5 CSS Local -->
    <link rel="stylesheet" href="<?= FormatHelper::asset('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <!-- Estilos Globais do Design System (Elevated Grooming & Wellness) -->
    <link rel="stylesheet" href="<?= FormatHelper::asset('assets/css/style.css') ?>">
</head>
<body class="bg-surface text-on-surface d-flex flex-column min-vh-100">
<!-- Primeiro elemento focável: permite pular a navegação com o teclado -->
<a href="#conteudo-principal" class="pular-para-conteudo">Pular para o conteúdo</a>

<header class="navbar navbar-expand-lg border-bottom border-secondary-dark bg-surface-container py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 text-gold fw-bold" href="<?= $baseUrl ?>/">
            <img src="<?= $baseUrl ?>/public/assets/img/logojablonskiprincipal.png" alt="" height="40">
            <span><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <button class="navbar-toggler text-gold" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain"
                aria-controls="navbarMain" aria-expanded="false" aria-label="Abrir menu de navegação">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link text-on-surface hover-gold" href="<?= $baseUrl ?>/">Início</a></li>
                <li class="nav-item"><a class="nav-link text-on-surface hover-gold" href="<?= $baseUrl ?>/agendar">Agendar</a></li>
                <?php if ($logado): ?>
                    <li class="nav-item">
                        <a class="nav-link text-on-surface hover-gold" href="<?= $baseUrl . AuthHelper::rotaInicial() ?>">Meu Painel</a>
                    </li>
                <?php endif; ?>
            </ul>

            <?php if ($logado): ?>
                <div class="d-flex align-items-center gap-3">
                    <a href="<?= $baseUrl ?>/perfil" class="text-decoration-none text-on-surface hover-gold d-flex align-items-center gap-2">
                        <span class="avatar-iniciais" aria-hidden="true"><?= strtoupper(mb_substr(AuthHelper::nome() ?? '?', 0, 1)) ?></span>
                        <span class="d-none d-lg-inline"><?= htmlspecialchars(AuthHelper::nome() ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                    <form method="POST" action="<?= $baseUrl ?>/logout" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="btn btn-outline-gold px-3">Sair</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="d-flex gap-2">
                    <a href="<?= $baseUrl ?>/login" class="btn btn-outline-gold px-4">Entrar</a>
                    <a href="<?= $baseUrl ?>/cadastro" class="btn btn-gold px-4">Cadastrar</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if (!empty($flashSucesso) || !empty($flashErro)): ?>
<div class="container mt-3">
    <?php if (!empty($flashSucesso)): ?>
        <div class="alerta alerta-sucesso" role="status">
            <?= htmlspecialchars($flashSucesso, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($flashErro)): ?>
        <div class="alerta alerta-erro" role="alert">
            <?= htmlspecialchars($flashErro, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
