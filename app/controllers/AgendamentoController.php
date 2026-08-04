<?php
// app/controllers/AgendamentoController.php - Wizard público de agendamento
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Fluxo de agendamento em três passos (docs/FSD.md Módulo 12.2):
 *  Passo 1 - Seleção múltipla de serviços;
 *  Passo 2 - Profissional, data e horário (grade calculada via AJAX);
 *  Passo 3 - Revisão, políticas e criação da reserva temporária.
 *
 * O progresso fica na sessão até a confirmação. O pagamento é tratado na Fase 6.
 */
class AgendamentoController extends BaseController {

    private const CHAVE_WIZARD = 'wizard_agendamento';

    private AgendamentoModel $agendamentos;
    private ServicoModel $servicos;
    private ProfissionalModel $profissionais;
    private SettingModel $settings;

    public function __construct() {
        parent::__construct();
        $this->agendamentos = new AgendamentoModel();
        $this->servicos = new ServicoModel();
        $this->profissionais = new ProfissionalModel();
        $this->settings = new SettingModel();
    }

    // =================================================================
    // PASSO 1 - Serviços
    // =================================================================

    public function passoServicos(): void {
        $wizard = $this->wizard();

        $this->render('public/agendar_servicos', [
            'pageTitle'    => 'Agendar - Serviços',
            'servicos'     => $this->servicos->listarAtivos(),
            'selecionados' => $wizard['servicos'] ?? [],
            'passo'        => 1,
        ]);
    }

    public function salvarServicos(): void {
        $this->validarCsrf();

        $selecionados = array_values(array_unique(array_map('intval', $_POST['servicos'] ?? [])));

        if (empty($selecionados)) {
            $this->comErro('Selecione ao menos um serviço para continuar.', '/agendar');
        }

        // Confere que todos os IDs recebidos existem e estão ativos
        $validos = array_map('intval', array_column($this->servicos->listarAtivos(), 'id'));
        $selecionados = array_values(array_intersect($selecionados, $validos));

        if (empty($selecionados)) {
            $this->comErro('Os serviços selecionados não estão mais disponíveis.', '/agendar');
        }

        $this->atualizarWizard(['servicos' => $selecionados, 'profissional_id' => null, 'data' => null, 'hora' => null]);

        $this->redirect('/agendar/horario');
    }

    // =================================================================
    // PASSO 2 - Profissional, data e horário
    // =================================================================

    public function passoHorario(): void {
        $wizard = $this->wizard();

        if (empty($wizard['servicos'])) {
            $this->comErro('Comece escolhendo os serviços desejados.', '/agendar');
        }

        $servicos = $this->detalharServicos($wizard['servicos']);

        $this->render('public/agendar_horario', [
            'pageTitle'     => 'Agendar - Data e Horário',
            'servicos'      => $servicos,
            'profissionais' => $this->profissionaisQueAtendem($wizard['servicos']),
            'wizard'        => $wizard,
            'duracaoTotal'  => array_sum(array_column($servicos, 'duracao_minutos')),
            'valorTotal'    => array_sum(array_column($servicos, 'preco')),
            'dataMinima'    => date('Y-m-d'),
            'dataMaxima'    => date('Y-m-d', strtotime('+' . $this->settings->getInt('agendamento_antecedencia_max_dias') . ' days')),
            'passo'         => 2,
        ]);
    }

    public function salvarHorario(): void {
        $this->validarCsrf();

        $wizard = $this->wizard();

        if (empty($wizard['servicos'])) {
            $this->comErro('Comece escolhendo os serviços desejados.', '/agendar');
        }

        $data = $this->post('data');
        $hora = substr($this->post('hora'), 0, 5);
        $profissionalId = (int)$this->post('profissional_id', '0');

        if ($data === '' || $hora === '' || $profissionalId <= 0) {
            $this->comErro('Escolha a data e o horário do atendimento.', '/agendar/horario');
        }

        // Revalida no servidor: o cliente pode ter forjado o formulário
        if (!$this->agendamentos->horarioEstaLivre($data, $hora, $wizard['servicos'], $profissionalId)) {
            $this->comErro('Este horário não está mais disponível. Escolha outro.', '/agendar/horario');
        }

        $this->atualizarWizard([
            'data'            => $data,
            'hora'            => $hora,
            'profissional_id' => $profissionalId,
        ]);

        $this->redirect('/agendar/revisao');
    }

    /**
     * Endpoint AJAX consumido pela grade de horários do Passo 2.
     */
    public function apiHorarios(): void {
        $data = $this->get('data');
        $profissional = $this->get('profissional');
        $wizard = $this->wizard();

        if (empty($wizard['servicos'])) {
            $this->json(['erro' => 'Nenhum serviço selecionado.'], 400);
        }

        if (!$this->agendamentos->dataDentroDaJanelaPermitida($data)) {
            $this->json([
                'horarios' => [],
                'aviso'    => 'Escolha uma data entre hoje e '
                    . $this->settings->getInt('agendamento_antecedencia_max_dias') . ' dias à frente.',
            ]);
        }

        $horarios = $this->agendamentos->horariosDisponiveis(
            $data,
            $wizard['servicos'],
            $profissional !== '' ? (int)$profissional : null
        );

        $this->json([
            'horarios' => $horarios,
            'aviso'    => empty($horarios) ? 'Nenhum horário livre nesta data. Tente outro dia.' : null,
        ]);
    }

    // =================================================================
    // PASSO 3 - Revisão e confirmação
    // =================================================================

    public function passoRevisao(): void {
        $wizard = $this->wizard();

        if (empty($wizard['servicos']) || empty($wizard['data']) || empty($wizard['hora'])) {
            $this->comErro('Complete os passos anteriores para revisar o agendamento.', '/agendar');
        }

        $servicos = $this->detalharServicos($wizard['servicos']);
        $profissional = $this->profissionais->buscarPorId((int)$wizard['profissional_id']);

        $this->render('public/agendar_revisao', [
            'pageTitle'    => 'Agendar - Revisão',
            'servicos'     => $servicos,
            'profissional' => $profissional,
            'wizard'       => $wizard,
            'duracaoTotal' => array_sum(array_column($servicos, 'duracao_minutos')),
            'valorTotal'   => array_sum(array_column($servicos, 'preco')),
            'ajustes'      => $this->settings->todos(),
            'passo'        => 3,
        ]);
    }

    public function confirmar(): void {
        $this->validarCsrf();

        // O agendamento exige conta: guarda o wizard e manda para o login.
        // A rota é gravada em formato relativo: a proteção contra open redirect
        // do AuthController descarta URLs absolutas.
        if (!AuthHelper::check()) {
            SessionHelper::set('url_pretendida', '/agendar/revisao');
            $this->comErro('Entre na sua conta para concluir o agendamento.', '/login');
        }

        if (AuthHelper::role() !== 'cliente') {
            $this->comErro(
                'Apenas contas de cliente podem agendar por esta tela. Use a agenda administrativa.',
                '/agendar/revisao'
            );
        }

        if (!isset($_POST['aceite_politicas'])) {
            $this->comErro('É necessário aceitar as políticas do estabelecimento.', '/agendar/revisao');
        }

        $wizard = $this->wizard();

        if (empty($wizard['servicos']) || empty($wizard['data']) || empty($wizard['hora'])) {
            $this->comErro('Sua seleção expirou. Refaça o agendamento.', '/agendar');
        }

        try {
            $reserva = $this->agendamentos->criarReserva(
                AuthHelper::id(),
                (int)$wizard['profissional_id'],
                $wizard['data'],
                $wizard['hora'],
                $wizard['servicos']
            );

        } catch (RuntimeException $e) {
            $this->comErro($e->getMessage(), '/agendar/horario');
        }

        SessionHelper::remove(self::CHAVE_WIZARD);

        $this->comSucesso(
            'Horário reservado! Conclua o pagamento em até '
            . $this->settings->getInt('checkout_reserva_minutos') . ' minutos para confirmar.',
            '/checkout/' . $reserva['codigo']
        );
    }

    /**
     * Tela de checkout provisória. O pagamento via Mercado Pago entra na Fase 6.
     */
    public function checkout(string $codigo = ''): void {
        AuthHelper::requireAuth();

        $codigo = $codigo !== '' ? $codigo : $this->get('codigo');
        $agendamento = $this->agendamentos->buscarPorCodigo($codigo);

        if ($agendamento === null) {
            $this->comErro('Agendamento não encontrado.', '/cliente/agendamentos');
        }

        // Um cliente nunca pode abrir o checkout de outro
        if ((int)$agendamento['cliente_id'] !== AuthHelper::id() && !AuthHelper::ehAdmin()) {
            LogModel::seguranca(
                LogModel::ACESSO_NEGADO_RBAC,
                'Tentativa de acessar checkout de outro cliente: ' . $codigo,
                AuthHelper::id()
            );
            AuthHelper::negarAcesso();
        }

        $this->render('public/checkout', [
            'pageTitle'   => 'Pagamento',
            'agendamento' => $agendamento,
            'servicos'    => $this->agendamentos->servicosDoAgendamento((int)$agendamento['id']),
        ]);
    }

    // =================================================================
    // Auxiliares
    // =================================================================

    private function wizard(): array {
        $wizard = SessionHelper::get(self::CHAVE_WIZARD, []);
        return is_array($wizard) ? $wizard : [];
    }

    private function atualizarWizard(array $dados): void {
        SessionHelper::set(self::CHAVE_WIZARD, array_merge($this->wizard(), $dados));
    }

    private function detalharServicos(array $ids): array {
        $todos = $this->servicos->listarAtivos();

        return array_values(array_filter($todos, function ($s) use ($ids) {
            return in_array((int)$s['id'], array_map('intval', $ids), true);
        }));
    }

    /**
     * Profissionais que executam todos os serviços escolhidos.
     */
    private function profissionaisQueAtendem(array $servicoIds): array {
        $candidatos = $this->profissionais->listar('', 'ativo');
        $ids = array_map('intval', $servicoIds);

        return array_values(array_filter($candidatos, function ($p) use ($ids) {
            $executa = $this->profissionais->idsServicosVinculados((int)$p['id']);
            return empty(array_diff($ids, $executa));
        }));
    }
}
