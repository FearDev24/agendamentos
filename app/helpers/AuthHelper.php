<?php
// app/helpers/AuthHelper.php - Validação de Autenticação e Middleware RBAC
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

class AuthHelper {
    public static function check(): bool {
        return SessionHelper::has('user_id');
    }

    public static function user(): ?array {
        return SessionHelper::get('user');
    }

    public static function role(): ?string {
        $user = self::user();
        return $user['perfil'] ?? null;
    }

    public static function requireAuth(): void {
        if (!self::check()) {
            header('Location: ' . $GLOBALS['config']['app']['url'] . '/login');
            exit;
        }
    }

    public static function requireRole(string|array $roles): void {
        self::requireAuth();
        $userRole = self::role();
        $allowedRoles = is_array($roles) ? $roles : [$roles];

        if (!in_array($userRole, $allowedRoles, true)) {
            http_response_code(403);
            die('Acesso negado: você não possui permissão para acessar esta área.');
        }
    }
}
