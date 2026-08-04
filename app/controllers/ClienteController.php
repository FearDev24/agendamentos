<?php
// app/controllers/ClienteController.php - Área restrita do cliente
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Área do Cliente. Nesta fase entrega apenas o painel base protegido por
 * RBAC; a gestão de agendamentos, créditos e fidelidade vem na Fase 7.
 */
class ClienteController extends BaseController {

    public function agendamentos(): void {
        AuthHelper::requireRole('cliente');

        $this->render('cliente/agendamentos', [
            'pageTitle' => 'Meus Agendamentos',
        ]);
    }
}
