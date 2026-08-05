<?php
// app/controllers/WebhookController.php - Notificações do gateway de pagamento
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Recebe as notificações do Mercado Pago (docs/FSD.md seção 23.1).
 *
 * Esta é a única rota pública sem sessão nem CSRF — quem chama é o servidor do
 * gateway, não um navegador. A proteção vem de outros três pontos:
 *
 *  1. Validação da assinatura `x-signature` (HMAC-SHA256 com o segredo);
 *  2. O status nunca vem do corpo da requisição: é reconsultado na API do
 *     provedor, de modo que forjar um POST não confirma agendamento;
 *  3. O processamento é idempotente, pois o Mercado Pago reenvia a mesma
 *     notificação até receber um 200.
 */
class WebhookController extends BaseController {

    private PagamentoModel $pagamentos;
    private AgendamentoModel $agendamentos;

    public function __construct() {
        parent::__construct();
        $this->pagamentos = new PagamentoModel();
        $this->agendamentos = new AgendamentoModel();
    }

    public function mercadoPago(): void {
        $corpo = file_get_contents('php://input') ?: '';
        $dados = json_decode($corpo, true);

        if (!is_array($dados)) {
            $this->responder(400, 'corpo_invalido');
        }

        $idRecurso = $this->extrairIdRecurso($dados);

        if ($idRecurso === '') {
            // Notificações de outros tipos (merchant_order etc.) são aceitas e
            // descartadas: responder 200 evita reenvios infinitos.
            $this->responder(200, 'ignorado');
        }

        $gateway = GatewayFactory::obter();

        if (!$gateway->validarAssinatura($this->cabecalhos(), $idRecurso)) {
            LogModel::seguranca('webhook_assinatura_invalida', 'recurso: ' . $idRecurso);
            $this->responder(401, 'assinatura_invalida');
        }

        // A verdade sobre o pagamento vem da API do provedor, nunca do POST
        try {
            $situacao = $gateway->consultar($idRecurso);
        } catch (Throwable $e) {
            LogModel::erro($e);
            // 500 faz o Mercado Pago tentar de novo mais tarde
            $this->responder(500, 'falha_ao_consultar');
        }

        $resultado = $this->aplicar($situacao['status'], (string)$situacao['transacao_id']);

        $this->responder(200, $resultado);
    }

    // -----------------------------------------------------------------

    /**
     * Aplica ao banco o status confirmado junto ao provedor.
     */
    private function aplicar(string $status, string $transacaoId): string {
        switch ($status) {
            case 'aprovado':
                $resultado = $this->pagamentos->aprovar($transacaoId);

                if ($resultado === 'confirmado') {
                    $this->registrarAuditoria('pagamento_confirmado', $transacaoId);
                }

                if ($resultado === 'pago_apos_expiracao') {
                    // Dinheiro recebido após a liberação do horário:
                    // fica registrado como pendência de reembolso manual.
                    $this->registrarAuditoria('pagamento_apos_expiracao', $transacaoId);
                    LogModel::seguranca(
                        'pagamento_apos_expiracao',
                        'Transação ' . $transacaoId . ' aprovada após a expiração da reserva. Reembolso manual necessário.'
                    );
                }

                return $resultado;

            case 'estornado':
                $this->pagamentos->estornar($transacaoId);
                $this->registrarAuditoria('pagamento_estornado', $transacaoId);
                return 'estornado';

            case 'recusado':
                $this->pagamentos->recusar($transacaoId);
                return 'recusado';

            default:
                return 'pendente';
        }
    }

    private function registrarAuditoria(string $acao, string $transacaoId): void {
        $pagamento = $this->pagamentos->buscarPorTransacao($transacaoId);

        (new AuditModel())->registrar(
            $acao,
            'pagamentos',
            $pagamento !== null ? (int)$pagamento['id'] : null,
            null,
            ['transacao_id' => $transacaoId, 'origem' => 'webhook']
        );
    }

    /**
     * O Mercado Pago envia o ID em formatos diferentes conforme o tipo de
     * notificação: `data.id` (webhooks v2) ou `id` na query string (IPN).
     */
    private function extrairIdRecurso(array $dados): string {
        $tipo = $dados['type'] ?? $dados['topic'] ?? '';

        if ($tipo !== '' && $tipo !== 'payment') {
            return '';
        }

        $id = $dados['data']['id'] ?? $dados['id'] ?? ($_GET['data_id'] ?? '');

        return is_scalar($id) ? (string)$id : '';
    }

    private function cabecalhos(): array {
        if (function_exists('getallheaders')) {
            $cabecalhos = getallheaders();
            if (is_array($cabecalhos)) {
                return $cabecalhos;
            }
        }

        // Contingência para servidores sem getallheaders()
        $cabecalhos = [];
        foreach ($_SERVER as $chave => $valor) {
            if (strpos($chave, 'HTTP_') === 0) {
                $nome = str_replace('_', '-', strtolower(substr($chave, 5)));
                $cabecalhos[$nome] = $valor;
            }
        }

        return $cabecalhos;
    }

    private function responder(int $codigo, string $resultado): void {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['resultado' => $resultado]);
        exit;
    }
}
