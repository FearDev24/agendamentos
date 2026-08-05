<?php
// app/controllers/ProfissionalController.php - Área restrita do profissional
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Painel do Profissional (docs/FSD.md Módulo 6).
 *
 * O profissional enxerga apenas a própria agenda: o registro de profissional é
 * sempre derivado da sessão, nunca de parâmetro da requisição.
 */
class ProfissionalController extends BaseController {

    private AgendamentoModel $agendamentos;
    private ProfissionalModel $profissionais;
    private NotaClienteModel $notas;
    private AvaliacaoModel $avaliacoes;
    private SettingModel $settings;

    public function __construct() {
        parent::__construct();
        $this->agendamentos = new AgendamentoModel();
        $this->profissionais = new ProfissionalModel();
        $this->notas = new NotaClienteModel();
        $this->avaliacoes = new AvaliacaoModel();
        $this->settings = new SettingModel();
    }

    public function agenda(): void {
        AuthHelper::requireRole('profissional');

        $profissional = $this->registroDoProfissionalLogado();
        $data = $this->get('data');

        if ($data === '' || !$this->ehDataValida($data)) {
            $data = date('Y-m-d');
        }

        $atendimentos = $this->agendamentos->agendaDoProfissional((int)$profissional['id'], $data);

        $this->render('profissional/agenda', [
            'pageTitle'    => 'Minha Agenda',
            'profissional' => $profissional,
            'data'         => $data,
            'atendimentos' => $atendimentos,
            'semana'       => $this->agendamentos->resumoSemanalDoProfissional(
                (int)$profissional['id'], date('Y-m-d', strtotime($data . ' -3 days'))
            ),
            'avaliacoes'   => $this->avaliacoes->resumoDoProfissional((int)$profissional['id']),
            'tolerancia'   => $this->settings->getInt('tolerancia_atraso_minutos'),
        ]);
    }

    /**
     * Marca que o cliente chegou e registra o horário real de início.
     */
    public function confirmarPresenca(): void {
        AuthHelper::requireRole('profissional');
        $this->validarCsrf();

        $profissional = $this->registroDoProfissionalLogado();
        $id = (int)$this->post('id', '0');
        $data = $this->post('data', date('Y-m-d'));

        if (!$this->agendamentos->confirmarPresenca($id, (int)$profissional['id'], $this->post('hora_inicio_real'))) {
            $this->comErro('Não foi possível confirmar a presença deste atendimento.', '/profissional/agenda?data=' . $data);
        }

        $this->comSucesso('Presença confirmada.', '/profissional/agenda?data=' . $data);
    }

    /**
     * Conclui o atendimento com os horários reais.
     */
    public function concluir(): void {
        AuthHelper::requireRole('profissional');
        $this->validarCsrf();

        $profissional = $this->registroDoProfissionalLogado();
        $id = (int)$this->post('id', '0');
        $data = $this->post('data', date('Y-m-d'));

        try {
            $this->agendamentos->concluir(
                $id, (int)$profissional['id'],
                $this->post('hora_inicio_real'), $this->post('hora_fim_real')
            );
        } catch (RuntimeException $e) {
            $this->comErro($e->getMessage(), '/profissional/agenda?data=' . $data);
        }

        $this->comSucesso('Atendimento concluído.', '/profissional/agenda?data=' . $data);
    }

    /**
     * Registra falta do cliente, respeitada a tolerância de atraso.
     */
    public function registrarFalta(): void {
        AuthHelper::requireRole('profissional');
        $this->validarCsrf();

        $profissional = $this->registroDoProfissionalLogado();
        $id = (int)$this->post('id', '0');
        $data = $this->post('data', date('Y-m-d'));

        $agendamento = $this->agendamentos->buscarPorIdDoProfissional($id, (int)$profissional['id']);

        if ($agendamento === null) {
            $this->comErro('Atendimento não encontrado na sua agenda.', '/profissional/agenda?data=' . $data);
        }

        // A falta só vale depois de esgotada a tolerância de atraso
        $limite = strtotime($agendamento['data_agendamento'] . ' ' . $agendamento['hora_inicio'])
                + ($this->settings->getInt('tolerancia_atraso_minutos') * 60);

        if (time() < $limite) {
            $this->comErro(
                'A tolerância de ' . $this->settings->getInt('tolerancia_atraso_minutos')
                . ' minutos ainda não se esgotou.',
                '/profissional/agenda?data=' . $data
            );
        }

        $this->agendamentos->registrarFalta($id, (int)$profissional['id']);
        $this->comSucesso('Falta registrada.', '/profissional/agenda?data=' . $data);
    }

    /**
     * Notas privadas sobre o cliente, visíveis apenas para a equipe.
     */
    public function notasDoCliente(string $id = ''): void {
        AuthHelper::requireRole('profissional');

        $profissional = $this->registroDoProfissionalLogado();
        $clienteId = (int)$id;

        // O profissional só acessa notas de clientes que ele já atendeu
        if (!$this->jaAtendeuCliente((int)$profissional['id'], $clienteId)) {
            LogModel::seguranca(
                LogModel::ACESSO_NEGADO_RBAC,
                'Tentativa de acessar notas de cliente sem histórico de atendimento: ' . $clienteId,
                AuthHelper::id()
            );
            AuthHelper::negarAcesso();
        }

        $cliente = (new UsuarioModel())->buscarPorId($clienteId);

        if ($cliente === null) {
            $this->comErro('Cliente não encontrado.', '/profissional/agenda');
        }

        $this->render('profissional/notas', [
            'pageTitle' => 'Notas sobre ' . $cliente['nome'],
            'cliente'   => $cliente,
            'notas'     => $this->notas->listarDoCliente($clienteId),
        ]);
    }

    public function salvarNota(): void {
        AuthHelper::requireRole('profissional');
        $this->validarCsrf();

        $profissional = $this->registroDoProfissionalLogado();
        $clienteId = (int)$this->post('cliente_id', '0');

        if (!$this->jaAtendeuCliente((int)$profissional['id'], $clienteId)) {
            AuthHelper::negarAcesso();
        }

        try {
            $this->notas->criar($clienteId, AuthHelper::id(), $this->post('observacao'));
        } catch (InvalidArgumentException $e) {
            $this->comErro($e->getMessage(), '/profissional/cliente/' . $clienteId . '/notas');
        }

        $this->comSucesso('Observação registrada.', '/profissional/cliente/' . $clienteId . '/notas');
    }

    // =================================================================
    // Auxiliares
    // =================================================================

    /**
     * Registro de profissional correspondente à conta autenticada.
     */
    private function registroDoProfissionalLogado(): array {
        $profissional = $this->profissionais->buscarPorUsuarioId(AuthHelper::id());

        if ($profissional === null) {
            LogModel::seguranca(
                'profissional_sem_registro',
                'Conta com perfil profissional sem registro em `profissionais`.',
                AuthHelper::id()
            );

            AuthHelper::logout();
            $this->comErro(
                'Sua conta de profissional não está configurada. Procure o administrador.',
                '/login'
            );
        }

        return $profissional;
    }

    /**
     * Confirma que houve ao menos um atendimento entre este profissional e
     * este cliente, condição para acessar as notas privadas.
     */
    private function jaAtendeuCliente(int $profissionalId, int $clienteId): bool {
        if ($clienteId <= 0) {
            return false;
        }

        $total = (new AgendamentoModel())->totalEntreProfissionalECliente($profissionalId, $clienteId);

        return $total > 0;
    }

    private function ehDataValida(string $data): bool {
        $d = DateTime::createFromFormat('Y-m-d', $data);
        return $d !== false && $d->format('Y-m-d') === $data;
    }
}
