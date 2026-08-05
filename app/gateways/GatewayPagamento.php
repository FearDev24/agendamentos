<?php
// app/gateways/GatewayPagamento.php - Contrato do provedor de pagamentos
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Contrato que todo provedor de pagamento deve cumprir (docs/FSD.md seção 23).
 *
 * A aplicação nunca conversa diretamente com a API do gateway: fala com esta
 * interface. Isso permite trocar o Mercado Pago por outro provedor e, no
 * ambiente local, usar o simulador sem qualquer chamada externa.
 *
 * Status normalizados devolvidos por todos os métodos:
 *   'pendente' | 'aprovado' | 'recusado' | 'estornado'
 */
interface GatewayPagamento {

    /**
     * Gera uma cobrança Pix.
     *
     * @param array $dados ['valor', 'descricao', 'codigo_reserva', 'email', 'nome']
     * @return array ['transacao_id', 'status', 'qr_code', 'qr_code_base64', 'expira_em']
     */
    public function criarPix(array $dados): array;

    /**
     * Envia um pagamento com cartão de crédito.
     *
     * O cartão NUNCA trafega pelo servidor: o frontend tokeniza os dados e
     * apenas o token é recebido aqui.
     *
     * @param array $dados ['valor', 'descricao', 'token', 'parcelas', 'metodo', 'email', 'cpf']
     * @return array ['transacao_id', 'status', 'detalhe']
     */
    public function criarCartao(array $dados): array;

    /**
     * Consulta a situação atual de uma transação no provedor.
     *
     * @return array ['transacao_id', 'status', 'valor']
     */
    public function consultar(string $transacaoId): array;

    /**
     * Valida a autenticidade da notificação recebida no webhook.
     */
    public function validarAssinatura(array $cabecalhos, string $idRecurso): bool;

    /**
     * Identificador do provedor, usado em logs e telas.
     */
    public function nome(): string;
}
