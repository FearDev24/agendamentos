<?php
// app/controllers/AdminController.php - Painel administrativo
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Painel Administrativo. Nesta fase entrega o dashboard base e a tela de
 * auditoria (exclusiva do Administrador Principal), ambos protegidos por
 * RBAC. O conteúdo operacional e financeiro vem nas Fases 4 e 8.
 */
class AdminController extends BaseController {

    public function dashboard(): void {
        AuthHelper::requireAdmin();

        $this->render('admin/dashboard', [
            'pageTitle' => 'Dashboard',
        ]);
    }

    /**
     * Auditoria é restrita ao Administrador Principal (docs/FSD.md seção 8).
     */
    public function auditoria(): void {
        AuthHelper::requireRole('admin_principal');

        $this->render('admin/auditoria', [
            'pageTitle' => 'Histórico de Auditoria',
        ]);
    }
}
