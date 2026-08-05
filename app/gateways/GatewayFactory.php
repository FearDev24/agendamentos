<?php
// app/gateways/GatewayFactory.php - Seleção do provedor de pagamento
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Decide qual provedor de pagamento a aplicação usa.
 *
 * A escolha vem de `mercadopago.sandbox_mode` em config/config.php:
 *  - true  → SandboxGateway (nenhuma chamada externa, ideal para o XAMPP);
 *  - false → MercadoPagoGateway (API real).
 */
class GatewayFactory {

    private static ?GatewayPagamento $instancia = null;

    public static function obter(): GatewayPagamento {
        if (self::$instancia === null) {
            self::$instancia = self::emSandbox()
                ? new SandboxGateway()
                : new MercadoPagoGateway();
        }

        return self::$instancia;
    }

    public static function emSandbox(): bool {
        return !empty($GLOBALS['config']['mercadopago']['sandbox_mode']);
    }

    /**
     * Permite injetar um provedor específico nos testes automatizados.
     */
    public static function definir(?GatewayPagamento $gateway): void {
        self::$instancia = $gateway;
    }
}
