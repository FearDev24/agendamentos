<?php
// app/controllers/HomeController.php - Vitrine pública
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Página inicial pública. A vitrine completa (serviços, profissionais,
 * promoções e mapa) será construída na Fase 5.
 */
class HomeController extends BaseController {

    public function index(): void {
        $this->render('public/home', ['pageTitle' => 'Início']);
    }

    public function naoEncontrado(): void {
        http_response_code(404);
        $this->render('errors/404', ['pageTitle' => 'Página não encontrada']);
    }
}
