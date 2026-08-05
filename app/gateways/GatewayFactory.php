<?php
// app/gateways/GatewayFactory.php - Seleção do provedor de pagamento
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Decide qual provedor de pagamento a aplicação usa.
 *
 * As credenciais vêm do painel administrativo (tela de Integrações) e, se
 * nada estiver cadastrado lá, do `config/config.php`. A escolha entre
 * simulador e API real segue o modo configurado na origem que estiver valendo.
 */
class GatewayFactory {

    private static ?GatewayPagamento $instancia = null;
    private static ?array $credenciaisCache = null;

    public static function obter(): GatewayPagamento {
        if (self::$instancia === null) {
            self::$instancia = self::emSandbox()
                ? new SandboxGateway()
                : new MercadoPagoGateway(self::credenciais());
        }

        return self::$instancia;
    }

    /**
     * Credenciais em uso, resolvidas entre painel e arquivo.
     */
    public static function credenciais(): array {
        if (self::$credenciaisCache === null) {
            // Antes da criação das tabelas (ex: durante as migrations) o
            // banco pode não estar acessível: o arquivo cobre esse caso.
            try {
                self::$credenciaisCache = (new IntegracaoModel())->mercadoPago();
            } catch (Throwable $e) {
                $arquivo = $GLOBALS['config']['mercadopago'] ?? [];

                self::$credenciaisCache = [
                    'access_token'   => $arquivo['access_token'] ?? '',
                    'public_key'     => $arquivo['public_key'] ?? '',
                    'webhook_secret' => $arquivo['webhook_secret'] ?? '',
                    'sandbox_mode'   => !empty($arquivo['sandbox_mode']),
                    'origem'         => 'arquivo',
                ];
            }
        }

        return self::$credenciaisCache;
    }

    public static function emSandbox(): bool {
        return !empty(self::credenciais()['sandbox_mode']);
    }

    /**
     * Chave pública usada pelo SDK JS na tokenização do cartão.
     */
    public static function chavePublica(): string {
        return self::credenciais()['public_key'] ?? '';
    }

    /**
     * Indica se há credencial suficiente para cobrar de verdade.
     */
    public static function configurado(): bool {
        $c = self::credenciais();

        return !empty($c['access_token']) && !empty($c['public_key']);
    }

    /**
     * Descarta o cache. Necessário após salvar novas credenciais no painel,
     * para que a próxima cobrança já use o que acabou de ser gravado.
     */
    public static function limparCache(): void {
        self::$instancia = null;
        self::$credenciaisCache = null;
    }

    /**
     * Permite injetar um provedor específico nos testes automatizados.
     */
    public static function definir(?GatewayPagamento $gateway): void {
        self::$instancia = $gateway;

        if ($gateway === null) {
            self::$credenciaisCache = null;
        }
    }
}
