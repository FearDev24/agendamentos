<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$baseUrl = $config['app']['url'] ?? '';
$userRole = AuthHelper::role();
?>
<aside class="sidebar-custom p-3 bg-surface-container border-end border-secondary-dark style-sidebar">
    <div class="mb-4 text-center">
        <h6 class="text-gold text-uppercase label-caps mb-1">Painel de Controle</h6>
        <small class="text-muted-custom"><?= ucfirst($userRole ?? 'Visitante') ?></small>
    </div>
    <ul class="nav nav-pills flex-column mb-auto">
        <?php if ($userRole === 'admin_principal' || $userRole === 'admin_limitado'): ?>
            <li class="nav-item mb-1"><a href="<?= $baseUrl ?>/admin/dashboard" class="nav-link text-on-surface hover-gold">Dashboard</a></li>
            <li class="nav-item mb-1"><a href="<?= $baseUrl ?>/admin/agenda" class="nav-link text-on-surface hover-gold">Agenda Global</a></li>
            <li class="nav-item mb-1"><a href="<?= $baseUrl ?>/admin/servicos" class="nav-link text-on-surface hover-gold">Serviços</a></li>
            <li class="nav-item mb-1"><a href="<?= $baseUrl ?>/admin/profissionais" class="nav-link text-on-surface hover-gold">Profissionais</a></li>
            <li class="nav-item mb-1"><a href="<?= $baseUrl ?>/admin/financeiro" class="nav-link text-on-surface hover-gold">Financeiro</a></li>
        <?php elseif ($userRole === 'profissional'): ?>
            <li class="nav-item mb-1"><a href="<?= $baseUrl ?>/profissional/agenda" class="nav-link text-on-surface hover-gold">Minha Agenda</a></li>
        <?php else: ?>
            <li class="nav-item mb-1"><a href="<?= $baseUrl ?>/cliente/agendamentos" class="nav-link text-on-surface hover-gold">Meus Agendamentos</a></li>
            <li class="nav-item mb-1"><a href="<?= $baseUrl ?>/cliente/perfil" class="nav-link text-on-surface hover-gold">Meu Perfil</a></li>
        <?php endif; ?>
    </ul>
</aside>
