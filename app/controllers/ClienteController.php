<?php
// app/controllers/ClienteController.php - Área restrita do cliente
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Área do Cliente (docs/FSD.md Módulo 5).
 *
 * Reúne o painel "Meus Agendamentos", comprovante para impressão,
 * reagendamento e cancelamento sob a regra de 24 horas, créditos internos,
 * insígnias de fidelidade e avaliações.
 */
class ClienteController extends BaseController {

    private AgendamentoModel $agendamentos;
    private CreditoModel $creditos;
    private AvaliacaoModel $avaliacoes;
    private ServicoModel $servicos;
    private ProfissionalModel $profissionais;
    private SettingModel $settings;

    public function __construct() {
        parent::__construct();
        $this->agendamentos = new AgendamentoModel();
        $this->creditos = new CreditoModel();
        $this->avaliacoes = new AvaliacaoModel();
        $this->servicos = new ServicoModel();
        $this->profissionais = new ProfissionalModel();
        $this->settings = new SettingModel();
    }

    // =================================================================
    // Painel principal
    // =================================================================

    public function agendamentos(): void {
        AuthHelper::requireRole('cliente');

        $clienteId = AuthHelper::id();
        $listas = $this->agendamentos->listarDoCliente($clienteId);

        $this->render('cliente/agendamentos', [
            'pageTitle'  => 'Meus Agendamentos',
            'proximos'   => $listas['proximos'],
            'historico'  => $listas['historico'],
            'saldo'      => $this->creditos->saldoDisponivel($clienteId),
            'creditos'   => $this->creditos->listarValidos($clienteId),
            'fidelidade' => $this->agendamentos->fidelidade($clienteId),
            'modelo'     => $this->agendamentos,
            'janela'     => $this->settings->getInt('cancelamento_janela_horas'),
        ]);
    }

    /**
     * Comprovante do agendamento, formatado para impressão ou PDF pelo
     * próprio navegador (Ctrl+P / "Salvar como PDF").
     */
    public function comprovante(string $codigo = ''): void {
        AuthHelper::requireRole('cliente');

        $agendamento = $this->carregarDoCliente($codigo);

        $this->render('cliente/comprovante', [
            'pageTitle'   => 'Comprovante ' . $agendamento['codigo_reserva'],
            'agendamento' => $agendamento,
            'servicos'    => $this->agendamentos->servicosDoAgendamento((int)$agendamento['id']),
            'pagamento'   => (new PagamentoModel())->buscarAprovadoDoAgendamento((int)$agendamento['id']),
            'ajustes'     => $this->settings->todos(),
        ]);
    }

    // =================================================================
    // Cancelamento
    // =================================================================

    public function cancelar(): void {
        AuthHelper::requireRole('cliente');
        $this->validarCsrf();

        $codigo = $this->post('codigo');
        $motivo = $this->post('motivo');
        $agendamento = $this->carregarDoCliente($codigo);

        if (mb_strlen($motivo) < 3) {
            $this->comErro('Informe o motivo do cancelamento.', '/cliente/agendamentos');
        }

        try {
            $resultado = $this->agendamentos->cancelar(
                (int)$agendamento['id'], AuthHelper::id(), $motivo
            );
        } catch (RuntimeException $e) {
            $this->comErro($e->getMessage(), '/cliente/agendamentos');
        }

        // Dentro do prazo: solicita o estorno ao gateway
        if ($resultado['no_prazo'] && $resultado['reembolso'] > 0) {
            $this->solicitarEstorno((int)$agendamento['id']);
        }

        $mensagem = $resultado['no_prazo']
            ? 'Agendamento cancelado dentro do prazo. O reembolso de '
              . FormatHelper::currency($resultado['reembolso']) . ' será processado pelo meio de pagamento utilizado.'
            : 'Agendamento cancelado. Por ser com menos de ' . $this->settings->getInt('cancelamento_janela_horas')
              . ' horas de antecedência, não há direito a reembolso.';

        if ($resultado['credito_devolvido'] > 0) {
            $mensagem .= ' O crédito de ' . FormatHelper::currency($resultado['credito_devolvido'])
                       . ' foi devolvido à sua conta.';
        }

        MailHelper::send(
            AuthHelper::email(),
            'Agendamento cancelado - ' . $agendamento['codigo_reserva'],
            '<p>Seu agendamento ' . htmlspecialchars($agendamento['codigo_reserva'], ENT_QUOTES, 'UTF-8')
            . ' foi cancelado.</p><p>' . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . '</p>'
        );

        $this->comSucesso($mensagem, '/cliente/agendamentos');
    }

    // =================================================================
    // Reagendamento
    // =================================================================

    public function formReagendar(string $codigo = ''): void {
        AuthHelper::requireRole('cliente');

        $agendamento = $this->carregarDoCliente($codigo);

        if (!$this->agendamentos->podeReagendar($agendamento)) {
            $this->comErro($this->motivoImpedimento($agendamento), '/cliente/agendamentos');
        }

        $servicosAtuais = array_map(
            'intval',
            array_column($this->agendamentos->servicosDoAgendamento((int)$agendamento['id']), 'servico_id')
        );

        // O wizard de horários compartilha a seleção pela sessão
        SessionHelper::set('wizard_agendamento', ['servicos' => $servicosAtuais]);

        $this->render('cliente/reagendar', [
            'pageTitle'      => 'Reagendar ' . $agendamento['codigo_reserva'],
            'agendamento'    => $agendamento,
            'catalogo'       => $this->servicos->listarAtivos(),
            'servicosAtuais' => $servicosAtuais,
            'profissionais'  => $this->profissionais->listar('', 'ativo'),
            'dataMinima'     => date('Y-m-d'),
            'dataMaxima'     => date('Y-m-d', strtotime('+' . $this->settings->getInt('agendamento_antecedencia_max_dias') . ' days')),
            'valorPago'      => $this->agendamentos->valorJaPago((int)$agendamento['id']),
        ]);
    }

    public function reagendar(): void {
        AuthHelper::requireRole('cliente');
        $this->validarCsrf();

        $codigo = $this->post('codigo');
        $agendamento = $this->carregarDoCliente($codigo);

        $data = $this->post('data');
        $hora = substr($this->post('hora'), 0, 5);
        $profissionalId = (int)$this->post('profissional_id', '0');
        $servicoIds = array_map('intval', $_POST['servicos'] ?? []);

        if ($data === '' || $hora === '' || $profissionalId <= 0) {
            $this->comErro('Escolha a nova data e o novo horário.', '/cliente/agendamento/' . $codigo . '/reagendar');
        }

        try {
            $resultado = $this->agendamentos->reagendar(
                (int)$agendamento['id'], AuthHelper::id(), $data, $hora, $profissionalId, $servicoIds
            );
        } catch (RuntimeException $e) {
            $this->comErro($e->getMessage(), '/cliente/agendamento/' . $codigo . '/reagendar');
        }

        SessionHelper::remove('wizard_agendamento');

        // Novo valor maior: o cliente paga a diferença no checkout
        if ($resultado['a_pagar'] > 0) {
            $this->comSucesso(
                'Agendamento remarcado. Falta pagar a diferença de '
                . FormatHelper::currency($resultado['a_pagar']) . '.',
                '/checkout/' . $codigo
            );
        }

        $mensagem = 'Agendamento remarcado com sucesso.';

        if ($resultado['credito_gerado'] > 0) {
            $mensagem .= ' Como o novo valor é menor, geramos um crédito de '
                       . FormatHelper::currency($resultado['credito_gerado'])
                       . ', válido por ' . $this->settings->getInt('credito_validade_dias') . ' dias.';
        }

        MailHelper::send(
            AuthHelper::email(),
            'Agendamento remarcado - ' . $agendamento['codigo_reserva'],
            '<p>Seu agendamento foi remarcado para ' . FormatHelper::dateBr($data) . ' às ' . $hora . '.</p>'
        );

        $this->comSucesso($mensagem, '/cliente/agendamentos');
    }

    // =================================================================
    // Avaliação
    // =================================================================

    public function avaliar(): void {
        AuthHelper::requireRole('cliente');
        $this->validarCsrf();

        $codigo = $this->post('codigo');
        $agendamento = $this->carregarDoCliente($codigo);
        $nota = (int)$this->post('nota', '0');

        try {
            $this->avaliacoes->registrar(
                (int)$agendamento['id'], AuthHelper::id(), $nota, $this->post('comentario')
            );
        } catch (Throwable $e) {
            $this->comErro($e->getMessage(), '/cliente/agendamentos');
        }

        $this->comSucesso('Obrigado pela sua avaliação!', '/cliente/agendamentos');
    }

    // =================================================================
    // Crédito interno no checkout
    // =================================================================

    /**
     * Aplica o saldo de crédito disponível a uma reserva ainda não paga.
     */
    public function aplicarCredito(): void {
        AuthHelper::requireRole('cliente');
        $this->validarCsrf();

        $codigo = $this->post('codigo');
        $agendamento = $this->carregarDoCliente($codigo);

        try {
            $aplicado = $this->agendamentos->aplicarCredito(
                (int)$agendamento['id'],
                AuthHelper::id(),
                (float)str_replace(',', '.', $this->post('valor', '0'))
            );
        } catch (RuntimeException $e) {
            $this->comErro($e->getMessage(), '/checkout/' . $codigo);
        }

        if ($aplicado <= 0) {
            $this->comErro('Não há crédito disponível para aplicar.', '/checkout/' . $codigo);
        }

        $this->comSucesso(
            'Crédito de ' . FormatHelper::currency($aplicado) . ' aplicado ao agendamento.',
            '/checkout/' . $codigo
        );
    }

    // =================================================================
    // Auxiliares
    // =================================================================

    /**
     * Carrega o agendamento garantindo que ele pertence ao cliente logado.
     */
    private function carregarDoCliente(string $codigo): array {
        $agendamento = $this->agendamentos->buscarPorCodigo($codigo);

        if ($agendamento === null) {
            $this->comErro('Agendamento não encontrado.', '/cliente/agendamentos');
        }

        if ((int)$agendamento['cliente_id'] !== AuthHelper::id()) {
            LogModel::seguranca(
                LogModel::ACESSO_NEGADO_RBAC,
                'Tentativa de acessar agendamento de outro cliente: ' . $codigo,
                AuthHelper::id()
            );
            AuthHelper::negarAcesso();
        }

        return $agendamento;
    }

    /**
     * Explica por que o reagendamento não é permitido.
     */
    private function motivoImpedimento(array $agendamento): string {
        if ($agendamento['situacao'] !== 'confirmado') {
            return 'Apenas agendamentos confirmados podem ser reagendados.';
        }

        $limite = $this->settings->getInt('reagendamento_limite');

        if ((int)$agendamento['reagendamentos_realizados'] >= $limite) {
            return "Este agendamento já atingiu o limite de {$limite} reagendamentos.";
        }

        return 'O reagendamento exige no mínimo ' . $this->settings->getInt('cancelamento_janela_horas')
             . ' horas de antecedência.';
    }

    /**
     * Aciona o estorno no gateway. Se falhar, o cancelamento permanece válido
     * e a devolução fica registrada como pendência administrativa.
     */
    private function solicitarEstorno(int $agendamentoId): void {
        $pagamentos = new PagamentoModel();
        $pagamento = $pagamentos->buscarAprovadoDoAgendamento($agendamentoId);

        if ($pagamento === null) {
            return;
        }

        try {
            $pagamentos->estornar($pagamento['transacao_id']);

            (new AuditModel())->registrar(
                'reembolso_solicitado', 'pagamentos', (int)$pagamento['id'],
                null, ['transacao_id' => $pagamento['transacao_id'], 'origem' => 'cancelamento_cliente']
            );

        } catch (Throwable $e) {
            LogModel::erro($e);
            LogModel::seguranca(
                'reembolso_pendente',
                'Falha ao estornar a transação ' . $pagamento['transacao_id'] . '. Reembolso manual necessário.'
            );
        }
    }
}
