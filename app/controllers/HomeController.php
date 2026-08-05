<?php
// app/controllers/HomeController.php - Vitrine pública
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Vitrine Pública (docs/FSD.md Módulo 12.1).
 *
 * Apresenta o estabelecimento, a grade de serviços com preços e durações e a
 * equipe de profissionais. O carrossel de promoções entra na Fase 8.
 */
class HomeController extends BaseController {

    public function index(): void {
        $servicos = new ServicoModel();
        $profissionais = new ProfissionalModel();
        $settings = new SettingModel();

        $promocoes = new PromocaoModel();
        $promocoes->expirarVencidas();

        $this->render('public/home', [
            'pageTitle'     => 'Início',
            'servicos'      => $servicos->listarAtivos(),
            'profissionais' => $profissionais->listar('', 'ativo'),
            'promocoes'     => $promocoes->vigentes(),
            // Chave 'ajustes' (e não 'config') para não colidir com a
            // configuração técnica da aplicação já presente nas Views
            'ajustes'       => $settings->todos(),
        ]);
    }

    public function naoEncontrado(): void {
        http_response_code(404);
        $this->render('errors/404', ['pageTitle' => 'Página não encontrada']);
    }
}
