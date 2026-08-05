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

    /**
     * Porta de entrada do sistema.
     *
     * Visitante começa pela conta, não pela vitrine: o agendamento exige login
     * de qualquer forma, e no celular a vitrine oferecia como única ação
     * visível o "Agendar Horário" — a pessoa preenchia o assistente inteiro
     * para só então descobrir que precisava entrar. A vitrine continua pública
     * em /inicio, ligada a partir da tela de login e do menu.
     */
    public function index(): void {
        if (!AuthHelper::check()) {
            $this->redirect('/login');
        }

        $this->vitrine();
    }

    /** Vitrine Pública (docs/FSD.md Módulo 12.1), acessível a qualquer um. */
    public function vitrine(): void {
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
