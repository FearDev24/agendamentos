<?php
// app/controllers/ProfissionalController.php - Área restrita do profissional
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Área do Profissional. Nesta fase entrega apenas a agenda base protegida
 * por RBAC; a operação dos atendimentos vem na Fase 7.
 */
class ProfissionalController extends BaseController {

    public function agenda(): void {
        AuthHelper::requireRole('profissional');

        $this->render('profissional/agenda', [
            'pageTitle' => 'Minha Agenda',
        ]);
    }
}
