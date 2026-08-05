<?php
// app/gateways/MercadoPagoGateway.php - Integração com a API do Mercado Pago
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Provedor de pagamentos Mercado Pago (docs/FSD.md seção 23.1).
 *
 * Comunica-se diretamente com a API REST v1 por cURL, sem dependência de SDK
 * externo. As credenciais vêm de config/config.php e nunca são versionadas.
 *
 * Segurança: os dados do cartão são tokenizados no navegador pelo SDK JS do
 * Mercado Pago. Este servidor recebe apenas o token — número do cartão, CVV e
 * validade jamais trafegam pela aplicação nem são gravados.
 */
class MercadoPagoGateway implements GatewayPagamento {

    private const BASE_URL = 'https://api.mercadopago.com';

    private string $accessToken;
    private string $webhookSecret;

    /**
     * @param array|null $credenciais Credenciais resolvidas pela GatewayFactory
     *                                (painel administrativo ou config.php).
     *                                Omitir faz o gateway resolvê-las sozinho.
     */
    public function __construct(?array $credenciais = null) {
        $credenciais = $credenciais ?? GatewayFactory::credenciais();

        $this->accessToken = $credenciais['access_token'] ?? '';
        $this->webhookSecret = $credenciais['webhook_secret'] ?? '';
    }

    /**
     * Valida as credenciais contra a API do Mercado Pago.
     *
     * Consulta os meios de pagamento da conta: é uma chamada de leitura, não
     * movimenta nada e falha de imediato se o token estiver errado.
     *
     * @return array ['ok' => bool, 'mensagem' => string, 'meios' => int]
     */
    public function testarConexao(): array {
        if ($this->accessToken === '') {
            return ['ok' => false, 'mensagem' => 'Nenhum Access Token cadastrado.', 'meios' => 0];
        }

        try {
            $resposta = $this->requisitar('GET', '/v1/payment_methods');

            $aceitaPix = false;
            foreach ($resposta as $meio) {
                if (($meio['id'] ?? '') === 'pix') {
                    $aceitaPix = true;
                    break;
                }
            }

            return [
                'ok'       => true,
                'mensagem' => 'Conexão estabelecida com sucesso.'
                    . ($aceitaPix ? ' A conta aceita Pix.' : ' ATENÇÃO: a conta não tem Pix habilitado.'),
                'meios'    => count($resposta),
            ];

        } catch (Throwable $e) {
            return ['ok' => false, 'mensagem' => $e->getMessage(), 'meios' => 0];
        }
    }

    public function nome(): string {
        return 'mercadopago';
    }

    public function criarPix(array $dados): array {
        $resposta = $this->requisitar('POST', '/v1/payments', [
            'transaction_amount' => round((float)$dados['valor'], 2),
            'description'        => $dados['descricao'],
            'payment_method_id'  => 'pix',
            'external_reference' => $dados['codigo_reserva'],
            'payer'              => [
                'email'      => $dados['email'],
                'first_name' => $dados['nome'],
            ],
        ], $this->chaveIdempotencia($dados['codigo_reserva'] . '-pix'));

        $transacao = $resposta['point_of_interaction']['transaction_data'] ?? [];

        return [
            'transacao_id'   => (string)($resposta['id'] ?? ''),
            'status'         => $this->normalizarStatus($resposta['status'] ?? 'pending'),
            'qr_code'        => $transacao['qr_code'] ?? '',
            'qr_code_base64' => isset($transacao['qr_code_base64'])
                ? 'data:image/png;base64,' . $transacao['qr_code_base64']
                : '',
            'expira_em'      => $resposta['date_of_expiration'] ?? null,
        ];
    }

    public function criarCartao(array $dados): array {
        $resposta = $this->requisitar('POST', '/v1/payments', [
            'transaction_amount' => round((float)$dados['valor'], 2),
            'description'        => $dados['descricao'],
            'token'              => $dados['token'],
            'installments'       => (int)($dados['parcelas'] ?? 1),
            'payment_method_id'  => $dados['metodo'] ?? null,
            'external_reference' => $dados['codigo_reserva'],
            'payer'              => [
                'email'          => $dados['email'],
                'identification' => [
                    'type'   => 'CPF',
                    'number' => preg_replace('/\D/', '', $dados['cpf'] ?? ''),
                ],
            ],
        ], $this->chaveIdempotencia($dados['codigo_reserva'] . '-cartao'));

        return [
            'transacao_id' => (string)($resposta['id'] ?? ''),
            'status'       => $this->normalizarStatus($resposta['status'] ?? 'rejected'),
            'detalhe'      => $resposta['status_detail'] ?? '',
        ];
    }

    public function consultar(string $transacaoId): array {
        $resposta = $this->requisitar('GET', '/v1/payments/' . urlencode($transacaoId));

        return [
            'transacao_id' => (string)($resposta['id'] ?? $transacaoId),
            'status'       => $this->normalizarStatus($resposta['status'] ?? 'pending'),
            'valor'        => (float)($resposta['transaction_amount'] ?? 0),
        ];
    }

    /**
     * Valida o cabeçalho `x-signature` conforme a documentação do Mercado Pago.
     *
     * O manifesto assinado tem o formato:
     *   id:<data.id>;request-id:<x-request-id>;ts:<ts>;
     * e é comparado por HMAC-SHA256 com o segredo do webhook.
     */
    public function validarAssinatura(array $cabecalhos, string $idRecurso): bool {
        if ($this->webhookSecret === '') {
            // Sem segredo configurado não há como validar: recusa por segurança
            LogModel::seguranca('webhook_sem_segredo', 'webhook_secret ausente em config/config.php');
            return false;
        }

        $assinatura = $this->cabecalho($cabecalhos, 'x-signature');
        $requestId = $this->cabecalho($cabecalhos, 'x-request-id');

        if ($assinatura === null) {
            return false;
        }

        // Formato: "ts=1704908010,v1=618c85345248dd820d5fd456117c2ab2ef8c1aa1..."
        $partes = [];
        foreach (explode(',', $assinatura) as $par) {
            $pedacos = explode('=', trim($par), 2);
            if (count($pedacos) === 2) {
                $partes[trim($pedacos[0])] = trim($pedacos[1]);
            }
        }

        if (!isset($partes['ts'], $partes['v1'])) {
            return false;
        }

        $manifesto = "id:{$idRecurso};request-id:{$requestId};ts:{$partes['ts']};";
        $esperado = hash_hmac('sha256', $manifesto, $this->webhookSecret);

        return hash_equals($esperado, $partes['v1']);
    }

    // -----------------------------------------------------------------

    /**
     * Converte os status do Mercado Pago para o vocabulário do sistema.
     */
    private function normalizarStatus(string $status): string {
        switch ($status) {
            case 'approved':
                return 'aprovado';
            case 'refunded':
            case 'charged_back':
                return 'estornado';
            case 'rejected':
            case 'cancelled':
                return 'recusado';
            default: // pending, in_process, authorized
                return 'pendente';
        }
    }

    /**
     * A chave de idempotência impede cobrança duplicada caso a requisição
     * seja reenviada por instabilidade de rede.
     */
    private function chaveIdempotencia(string $semente): string {
        return substr(hash('sha256', $semente), 0, 40);
    }

    private function cabecalho(array $cabecalhos, string $nome): ?string {
        foreach ($cabecalhos as $chave => $valor) {
            if (strtolower((string)$chave) === $nome) {
                return is_array($valor) ? ($valor[0] ?? null) : (string)$valor;
            }
        }

        return null;
    }

    /**
     * @throws RuntimeException quando a API responde erro ou fica indisponível
     */
    private function requisitar(string $metodo, string $caminho, ?array $corpo = null, ?string $idempotencia = null): array {
        if ($this->accessToken === '') {
            throw new RuntimeException('Credenciais do Mercado Pago não configuradas em config/config.php.');
        }

        $cabecalhos = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ];

        if ($idempotencia !== null) {
            $cabecalhos[] = 'X-Idempotency-Key: ' . $idempotencia;
        }

        $ch = curl_init(self::BASE_URL . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_HTTPHEADER     => $cabecalhos,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($corpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE));
        }

        $resposta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($resposta === false) {
            throw new RuntimeException('Falha de comunicação com o Mercado Pago: ' . $erroCurl);
        }

        $dados = json_decode($resposta, true);

        if (!is_array($dados)) {
            throw new RuntimeException('Resposta inválida do Mercado Pago (HTTP ' . $codigo . ').');
        }

        if ($codigo >= 400) {
            $mensagem = $dados['message'] ?? 'erro desconhecido';
            throw new RuntimeException("Mercado Pago recusou a requisição (HTTP {$codigo}): {$mensagem}");
        }

        return $dados;
    }
}
