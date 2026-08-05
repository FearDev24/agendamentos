<?php
// app/controllers/CheckoutController.php - Pagamento do agendamento
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Checkout com retenção temporária de 10 minutos (docs/FSD.md Módulo 4).
 *
 * Oferece Pix (QR Code + Copia e Cola) e Cartão de Crédito. Os dados do cartão
 * são tokenizados no navegador: este servidor recebe apenas o token, nunca o
 * número do cartão, o CVV ou a validade.
 */
class CheckoutController extends BaseController {

    private AgendamentoModel $agendamentos;
    private PagamentoModel $pagamentos;
    private SettingModel $settings;

    public function __construct() {
        parent::__construct();
        $this->agendamentos = new AgendamentoModel();
        $this->pagamentos = new PagamentoModel();
        $this->settings = new SettingModel();
    }

    // =================================================================
    // Tela de pagamento
    // =================================================================

    public function index(string $codigo = ''): void {
        $agendamento = $this->carregarAgendamentoAutorizado($codigo);
        $pagamento = $this->pagamentos->buscarPorAgendamento((int)$agendamento['id']);

        $this->render('public/checkout', [
            'pageTitle'   => 'Pagamento',
            'agendamento' => $agendamento,
            'servicos'    => $this->agendamentos->servicosDoAgendamento((int)$agendamento['id']),
            'pagamento'   => $pagamento,
            'emSandbox'   => GatewayFactory::emSandbox(),
            'valorDevido' => $this->agendamentos->valorDevido($agendamento),
            'saldoCredito'=> (new CreditoModel())->saldoDisponivel((int)$agendamento['cliente_id']),
            'publicKey'   => GatewayFactory::chavePublica(),
            'expirado'    => $this->reservaExpirada($agendamento),
        ]);
    }

    /**
     * Gera a cobrança Pix e devolve o QR Code para a tela.
     */
    public function gerarPix(string $codigo = ''): void {
        $this->validarCsrf();
        $agendamento = $this->carregarAgendamentoAutorizado($codigo);
        $this->exigirReservaValida($agendamento);

        // Reaproveita o Pix já gerado, evitando cobranças duplicadas
        $existente = $this->pagamentos->buscarPorAgendamento((int)$agendamento['id']);

        if ($existente !== null
            && $existente['forma_pagamento'] === 'pix'
            && $existente['status_pagamento'] === 'pendente'
            && !empty($existente['qr_code_pix'])) {
            $this->redirect('/checkout/' . $codigo . '?metodo=pix');
        }

        // Cobra o valor devido: total menos desconto, crédito interno e o que
        // já foi pago (caso de reagendamento que aumentou o valor).
        $valorDevido = $this->agendamentos->valorDevido($agendamento);

        if ($valorDevido <= 0) {
            $this->comErro('Não há valor a pagar nesta reserva.', '/checkout/' . $codigo);
        }

        try {
            $retorno = GatewayFactory::obter()->criarPix([
                'valor'          => $valorDevido,
                'descricao'      => 'Agendamento ' . $agendamento['codigo_reserva'],
                'codigo_reserva' => $agendamento['codigo_reserva'],
                'email'          => $agendamento['cliente_email'],
                'nome'           => $agendamento['cliente_nome'],
            ]);

        } catch (Throwable $e) {
            LogModel::erro($e);
            $this->comErro(
                'Não foi possível gerar o Pix agora. Tente novamente ou use cartão de crédito.',
                '/checkout/' . $codigo
            );
        }

        $this->pagamentos->registrar((int)$agendamento['id'], 'pix', $valorDevido, $retorno);

        $this->redirect('/checkout/' . $codigo . '?metodo=pix');
    }

    /**
     * Processa o pagamento com cartão a partir do token gerado no navegador.
     */
    public function pagarCartao(string $codigo = ''): void {
        $this->validarCsrf();
        $agendamento = $this->carregarAgendamentoAutorizado($codigo);
        $this->exigirReservaValida($agendamento);

        $token = $this->post('token');

        if ($token === '') {
            $this->comErro('Não foi possível validar os dados do cartão. Tente novamente.', '/checkout/' . $codigo);
        }

        $valorDevido = $this->agendamentos->valorDevido($agendamento);

        if ($valorDevido <= 0) {
            $this->comErro('Não há valor a pagar nesta reserva.', '/checkout/' . $codigo);
        }

        try {
            $retorno = GatewayFactory::obter()->criarCartao([
                'valor'          => $valorDevido,
                'descricao'      => 'Agendamento ' . $agendamento['codigo_reserva'],
                'codigo_reserva' => $agendamento['codigo_reserva'],
                'token'          => $token,
                'parcelas'       => max(1, (int)$this->post('parcelas', '1')),
                'metodo'         => $this->post('metodo') ?: null,
                'email'          => $agendamento['cliente_email'],
                'cpf'            => $this->post('cpf'),
            ]);

        } catch (Throwable $e) {
            LogModel::erro($e);
            $this->comErro('Falha ao processar o cartão. Tente novamente.', '/checkout/' . $codigo);
        }

        $this->pagamentos->registrar((int)$agendamento['id'], 'cartao_credito', $valorDevido, $retorno);

        if ($retorno['status'] === 'aprovado') {
            $resultado = $this->pagamentos->aprovar($retorno['transacao_id']);

            if ($resultado === 'confirmado') {
                $this->notificarConfirmacao($agendamento);
            }

            $this->comSucesso('Pagamento aprovado! Seu agendamento está confirmado.', '/checkout/' . $codigo);
        }

        if ($retorno['status'] === 'recusado') {
            $this->comErro(
                'Pagamento recusado. ' . ($retorno['detalhe'] ?? '') . ' Tente outro cartão ou pague com Pix.',
                '/checkout/' . $codigo
            );
        }

        $this->comSucesso('Pagamento em análise. Avisaremos assim que for aprovado.', '/checkout/' . $codigo);
    }

    /**
     * Endpoint de polling: a tela consulta a situação sem recarregar.
     */
    public function status(string $codigo = ''): void {
        $agendamento = $this->carregarAgendamentoAutorizado($codigo, true);
        $pagamento = $this->pagamentos->buscarPorAgendamento((int)$agendamento['id']);

        $this->json([
            'situacao'         => $agendamento['situacao'],
            'status_pagamento' => $pagamento['status_pagamento'] ?? null,
            'confirmado'       => $agendamento['situacao'] === 'confirmado',
            'expira_em'        => $agendamento['reserva_expira_em'],
        ]);
    }

    /**
     * Simulação de pagamento aprovado, disponível apenas em sandbox.
     *
     * Substitui o webhook do Mercado Pago no ambiente local, permitindo testar
     * a confirmação automática sem exposição da aplicação à internet.
     */
    public function simularPagamento(string $codigo = ''): void {
        if (!GatewayFactory::emSandbox()) {
            AuthHelper::negarAcesso();
        }

        $this->validarCsrf();
        $agendamento = $this->carregarAgendamentoAutorizado($codigo);
        $pagamento = $this->pagamentos->buscarPorAgendamento((int)$agendamento['id']);

        if ($pagamento === null || empty($pagamento['transacao_id'])) {
            $this->comErro('Gere o Pix antes de simular o pagamento.', '/checkout/' . $codigo);
        }

        $resultado = $this->pagamentos->aprovar($pagamento['transacao_id']);

        if ($resultado === 'confirmado') {
            $this->notificarConfirmacao($agendamento);
            $this->comSucesso('Pagamento simulado com sucesso. Agendamento confirmado!', '/checkout/' . $codigo);
        }

        if ($resultado === 'ja_processado') {
            $this->comSucesso('Este pagamento já havia sido confirmado.', '/checkout/' . $codigo);
        }

        $this->comErro('A reserva expirou antes da confirmação do pagamento.', '/checkout/' . $codigo);
    }

    // =================================================================
    // Auxiliares
    // =================================================================

    /**
     * Carrega o agendamento garantindo que ele pertence ao usuário logado.
     * Um cliente nunca pode abrir o checkout de outro.
     */
    private function carregarAgendamentoAutorizado(string $codigo, bool $comoJson = false): array {
        AuthHelper::requireAuth();

        $agendamento = $this->agendamentos->buscarPorCodigo($codigo);

        if ($agendamento === null) {
            if ($comoJson) {
                $this->json(['erro' => 'Agendamento não encontrado.'], 404);
            }
            $this->comErro('Agendamento não encontrado.', '/cliente/agendamentos');
        }

        if ((int)$agendamento['cliente_id'] !== AuthHelper::id() && !AuthHelper::ehAdmin()) {
            LogModel::seguranca(
                LogModel::ACESSO_NEGADO_RBAC,
                'Tentativa de acessar checkout de outro cliente: ' . $codigo,
                AuthHelper::id()
            );

            if ($comoJson) {
                $this->json(['erro' => 'Acesso negado.'], 403);
            }
            AuthHelper::negarAcesso();
        }

        return $agendamento;
    }

    /**
     * Impede cobrança sobre uma reserva já vencida ou encerrada.
     */
    private function exigirReservaValida(array $agendamento): void {
        $this->agendamentos->expirarReservasVencidas();

        if ($agendamento['situacao'] !== 'aguardando_pagamento' || $this->reservaExpirada($agendamento)) {
            $this->comErro(
                'Esta reserva não está mais aguardando pagamento. Faça um novo agendamento.',
                '/cliente/agendamentos'
            );
        }
    }

    private function reservaExpirada(array $agendamento): bool {
        return $agendamento['reserva_expira_em'] !== null
            && strtotime($agendamento['reserva_expira_em']) < time();
    }

    /**
     * E-mail de confirmação com o comprovante (docs/FSD.md Módulo 4).
     */
    private function notificarConfirmacao(array $agendamento): void {
        $servicos = $this->agendamentos->servicosDoAgendamento((int)$agendamento['id']);
        $lista = '';

        foreach ($servicos as $servico) {
            $lista .= '<li>' . htmlspecialchars($servico['nome'], ENT_QUOTES, 'UTF-8')
                   . ' - ' . FormatHelper::currency((float)$servico['preco_aplicado']) . '</li>';
        }

        MailHelper::send(
            $agendamento['cliente_email'],
            'Agendamento confirmado - ' . $agendamento['codigo_reserva'],
            '<h2>Agendamento confirmado</h2>'
            . '<p>Código da reserva: <strong>' . htmlspecialchars($agendamento['codigo_reserva'], ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p>Data: ' . FormatHelper::dateBr($agendamento['data_agendamento'])
            . ' às ' . substr($agendamento['hora_inicio'], 0, 5) . '</p>'
            . '<p>Profissional: ' . htmlspecialchars($agendamento['profissional_nome'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<ul>' . $lista . '</ul>'
            . '<p>Total pago: <strong>' . FormatHelper::currency((float)$agendamento['valor_total']) . '</strong></p>'
        );
    }
}
