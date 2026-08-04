<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$baseUrl = $GLOBALS['config']['app']['url'] ?? '';
$userRole = AuthHelper::role();
$rotaAtual = Router::caminhoAtual($baseUrl);

/**
 * Menu filtrado pelo papel do usuário (docs/FSD.md seção 16).
 * A ocultação de itens é apenas visual: o bloqueio real ocorre nos Controllers.
 */
$itens = [];

if (in_array($userRole, AuthHelper::PERFIS_ADMIN, true)) {
    $itens[] = ['/admin/dashboard', 'Dashboard'];
    $itens[] = ['/admin/agenda', 'Agenda Global'];
    $itens[] = ['/admin/servicos', 'Serviços'];
    $itens[] = ['/admin/profissionais', 'Profissionais'];
    $itens[] = ['/admin/financeiro', 'Financeiro'];

    // Auditoria é exclusiva do Administrador Principal
    if ($userRole === 'admin_principal') {
        $itens[] = ['/admin/auditoria', 'Auditoria'];
    }
} elseif ($userRole === 'profissional') {
    $itens[] = ['/profissional/agenda', 'Minha Agenda'];
} else {
    $itens[] = ['/cliente/agendamentos', 'Meus Agendamentos'];
}

$itens[] = ['/perfil', 'Meu Perfil'];

$rotulosPerfil = [
    'cliente'         => 'Cliente',
    'profissional'    => 'Profissional',
    'admin_principal' => 'Administrador Principal',
    'admin_limitado'  => 'Administrador Limitado',
];
?>
<aside class="style-sidebar p-3">
    <div class="mb-4">
        <h2 class="text-gold text-uppercase label-caps mb-1">Painel de Controle</h2>
        <small class="text-muted-custom"><?= htmlspecialchars($rotulosPerfil[$userRole] ?? 'Visitante', ENT_QUOTES, 'UTF-8') ?></small>
    </div>

    <nav aria-label="Menu do painel">
        <ul class="nav flex-column mb-0">
            <?php foreach ($itens as [$rota, $rotulo]): ?>
                <?php $ativo = ($rotaAtual === $rota); ?>
                <li class="nav-item">
                    <a href="<?= $baseUrl . $rota ?>"
                       class="nav-link item-menu<?= $ativo ? ' item-menu-ativo' : '' ?>"
                       <?= $ativo ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
