<?php
// app/controllers/IntegracaoController.php - Vinculação de contas externas
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Tela de Integrações: permite ao estabelecimento vincular a própria conta
 * do Mercado Pago sem depender de acesso ao servidor.
 *
 * Restrita ao Administrador Principal: quem cadastra o Access Token passa a
 * receber os pagamentos, então é a configuração mais sensível do sistema.
 */
class IntegracaoController extends BaseController {

    private IntegracaoModel $integracoes;
    private AuditModel $auditoria;

    public function __construct() {
        parent::__construct();
        $this->integracoes = new IntegracaoModel();
        $this->auditoria = new AuditModel();
    }

    public function index(): void {
        AuthHelper::requireRole('admin_principal');

        $this->render('admin/integracoes', [
            'pageTitle'        => 'Integrações',
            'mp'               => $this->integracoes->paraExibicao(),
            'chaveCriptoOk'    => CriptografiaHelper::chaveConfigurada(),
            'urlWebhook'       => $this->baseUrl . '/webhook/mercadopago',
            'resultadoTeste'   => $this->consumirResultadoDoTeste(),
        ]);
    }

    /**
     * Grava as credenciais informadas pelo estabelecimento.
     */
    public function salvarMercadoPago(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        if (!CriptografiaHelper::chaveConfigurada()) {
            $this->comErro(
                'Defina a chave de criptografia em config/config.php antes de cadastrar credenciais.',
                '/admin/integracoes'
            );
        }

        $dados = [
            'public_key'     => $this->post('public_key'),
            'access_token'   => $this->post('access_token'),
            'webhook_secret' => $this->post('webhook_secret'),
            'sandbox_mode'   => $this->post('modo') === 'sandbox',
        ];

        $erros = $this->validar($dados);

        if (!empty($erros)) {
            $this->comErro(implode(' ', $erros), '/admin/integracoes');
        }

        $anterior = $this->integracoes->paraExibicao();
        $this->integracoes->salvarMercadoPago($dados);
        GatewayFactory::limparCache();

        // A auditoria registra a mudança, jamais o valor das credenciais
        $this->auditoria->registrar(
            AuditModel::ATUALIZAR, 'integracoes', null,
            ['origem' => $anterior['origem'], 'sandbox' => $anterior['sandbox_mode']],
            [
                'origem'  => 'painel',
                'sandbox' => $dados['sandbox_mode'],
                'token_alterado' => $dados['access_token'] !== '',
                'ambiente' => $this->integracoes->detectarAmbiente(
                    $this->integracoes->mercadoPago()['access_token']
                ),
            ]
        );

        $atual = $this->integracoes->mercadoPago();
        $ambiente = $this->integracoes->detectarAmbiente($atual['access_token']);

        $aviso = '';

        if (!$dados['sandbox_mode'] && $ambiente === 'teste') {
            $aviso = ' ATENÇÃO: o modo está como produção, mas a credencial é de teste (TEST-).'
                   . ' Nenhum pagamento real será processado.';
        }

        if ($dados['sandbox_mode'] && $ambiente === 'producao') {
            $aviso = ' Observação: a credencial é de produção, mas o modo simulação está ligado.'
                   . ' Nada será cobrado de verdade até você desligá-lo.';
        }

        $this->comSucesso('Integração salva.' . $aviso, '/admin/integracoes');
    }

    /**
     * Consulta a API do Mercado Pago para confirmar que a credencial funciona.
     */
    public function testarMercadoPago(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $credenciais = $this->integracoes->mercadoPago();

        if (empty($credenciais['access_token'])) {
            $this->comErro('Cadastre o Access Token antes de testar a conexão.', '/admin/integracoes');
        }

        $resultado = (new MercadoPagoGateway($credenciais))->testarConexao();

        SessionHelper::set('resultado_teste_integracao', $resultado);

        if ($resultado['ok']) {
            $this->comSucesso('Teste concluído: ' . $resultado['mensagem'], '/admin/integracoes');
        }

        $this->comErro('Falha no teste: ' . $resultado['mensagem'], '/admin/integracoes');
    }

    /**
     * Remove o vínculo, voltando ao que estiver em config/config.php.
     */
    public function desvincularMercadoPago(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $this->integracoes->desvincularMercadoPago();
        GatewayFactory::limparCache();

        $this->auditoria->registrar(
            AuditModel::EXCLUIR, 'integracoes', null, ['origem' => 'painel'], null
        );

        $this->comSucesso(
            'Conta desvinculada. O sistema voltou a usar as credenciais do arquivo de configuração.',
            '/admin/integracoes'
        );
    }

    // -----------------------------------------------------------------

    private function validar(array $dados): array {
        $erros = [];
        $atual = $this->integracoes->mercadoPago();

        // Segredos em branco significam "manter o que já está gravado"
        $tokenFinal = $dados['access_token'] !== '' ? $dados['access_token'] : $atual['access_token'];

        if ($dados['public_key'] === '') {
            $erros[] = 'Informe a Public Key.';
        } elseif (!preg_match('/^(TEST|APP_USR)-/', $dados['public_key'])) {
            $erros[] = 'A Public Key deve começar com TEST- ou APP_USR-.';
        }

        if ($tokenFinal === '') {
            $erros[] = 'Informe o Access Token.';
        } elseif ($dados['access_token'] !== '' && !preg_match('/^(TEST|APP_USR)-/', $dados['access_token'])) {
            $erros[] = 'O Access Token deve começar com TEST- ou APP_USR-.';
        }

        // Misturar credencial de teste com a de produção não funciona
        $ambienteToken = $this->integracoes->detectarAmbiente($tokenFinal);
        $ambienteChave = strpos($dados['public_key'], 'TEST-') === 0 ? 'teste' : 'producao';

        if ($dados['public_key'] !== '' && $ambienteToken !== 'desconhecido'
            && $ambienteToken !== 'nenhum' && $ambienteToken !== $ambienteChave) {
            $erros[] = 'A Public Key e o Access Token são de ambientes diferentes '
                     . '(um é de teste e o outro de produção). Use o par correspondente.';
        }

        // Sem o segredo do webhook a confirmação automática não funciona
        if (!$dados['sandbox_mode']) {
            $segredoFinal = $dados['webhook_secret'] !== ''
                ? $dados['webhook_secret'] : $atual['webhook_secret'];

            if ($segredoFinal === '') {
                $erros[] = 'Em modo produção o segredo do webhook é obrigatório: '
                         . 'sem ele os pagamentos não são confirmados automaticamente.';
            }
        }

        return $erros;
    }

    private function consumirResultadoDoTeste(): ?array {
        $resultado = SessionHelper::get('resultado_teste_integracao');
        SessionHelper::remove('resultado_teste_integracao');

        return is_array($resultado) ? $resultado : null;
    }
}
