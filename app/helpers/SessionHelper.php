<?php
// app/helpers/SessionHelper.php - Gerenciamento de Sessão HTTP Segura
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

class SessionHelper {

    /** Encerra a sessão após este tempo sem nenhuma requisição (segundos). */
    public const INATIVIDADE_MAXIMA = 7200; // 2 horas

    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Lax');

            // Sob HTTPS o cookie não pode acompanhar uma requisição em texto
            // claro: bastaria um link http:// para o identificador de sessão
            // vazar na rede. A origem da verdade é a URL configurada, e não
            // $_SERVER['HTTPS'] — atrás de um túnel ou proxy reverso o PHP
            // recebe a conexão interna em HTTP e não enxerga o TLS.
            if (self::ehHttps()) {
                ini_set('session.cookie_secure', 1);
            }

            session_start();
            self::expirarPorInatividade();
        }
    }

    /** A aplicação é servida sob HTTPS? */
    public static function ehHttps(): bool {
        $url = $GLOBALS['config']['app']['url'] ?? '';
        return stripos($url, 'https://') === 0;
    }

    /**
     * Descarta sessões ociosas. Sem isto, um painel administrativo deixado
     * aberto num computador compartilhado continua válido indefinidamente.
     */
    private static function expirarPorInatividade(): void {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $ultimoAcesso = $_SESSION['ultimo_acesso'] ?? time();

        if (time() - $ultimoAcesso > self::INATIVIDADE_MAXIMA) {
            $_SESSION = [];
            session_destroy();
            session_start();
            $_SESSION['flash_erro'] = 'Sua sessão expirou por inatividade. Entre novamente.';
            return;
        }

        $_SESSION['ultimo_acesso'] = time();
    }

    public static function set(string $key, mixed $value): void {
        self::init();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed {
        self::init();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool {
        self::init();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void {
        self::init();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void {
        if (session_status() === PHP_SESSION_NONE) {
            return;
        }

        session_unset();

        // session_destroy() apaga os dados no servidor, mas deixa o cookie no
        // navegador. Expirá-lo evita que o identificador antigo continue
        // circulando depois do logout.
        if (PHP_SAPI !== 'cli' && !headers_sent() && ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => self::ehHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
    }

    public static function generateCsrfToken(): string {
        self::init();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(?string $token): bool {
        self::init();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
