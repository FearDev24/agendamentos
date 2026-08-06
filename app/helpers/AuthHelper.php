<?php
// app/helpers/AuthHelper.php - Autenticação de sessão e middleware RBAC
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Middleware central de autenticação e Controle de Acesso Baseado em Papéis.
 *
 * Regra do projeto (docs/FSD.md seção 16): toda verificação de permissão é
 * feita no backend, independentemente de o botão estar visível na View.
 */
class AuthHelper {

    /** Perfis administrativos (usados nas rotas /admin/) */
    public const PERFIS_ADMIN = ['admin_principal', 'admin_limitado'];

    // -----------------------------------------------------------------
    // Estado da sessão
    // -----------------------------------------------------------------

    /** Evita repetir a consulta de validade a cada chamada de check(). */
    private static bool $sessaoConferida = false;

    public static function check(): bool {
        if (!SessionHelper::has('user_id')) {
            return false;
        }

        if (!self::$sessaoConferida) {
            self::$sessaoConferida = true;

            if (!self::sessaoAindaVale()) {
                SessionHelper::destroy();
                SessionHelper::init();
                SessionHelper::set('flash_erro', 'Sua senha foi alterada. Entre novamente.');
                return false;
            }
        }

        return true;
    }

    /**
     * Uma sessão iniciada antes da última troca de senha deixa de valer.
     *
     * É o que faz a redefinição de senha realmente expulsar quem já estava
     * autenticado — sem isto, no cenário que motiva a troca (conta invadida)
     * o invasor seguia dentro com a sessão dele.
     */
    private static function sessaoAindaVale(): bool {
        $logadoEm = SessionHelper::get('logado_em');

        if ($logadoEm === null) {
            return true;
        }

        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT senha_alterada_em FROM usuarios WHERE id = :id AND deleted_at IS NULL'
            );
            $stmt->execute([':id' => self::id()]);
            $alteradaEm = $stmt->fetchColumn();

        } catch (Throwable $e) {
            // Banco indisponível ou coluna ainda não migrada: não é motivo
            // para derrubar quem está usando o sistema.
            return true;
        }

        // Sem linha: a conta sumiu (excluída) entre uma requisição e outra.
        if ($alteradaEm === false) {
            return false;
        }

        // Coluna nula significa "senha nunca trocada desde o cadastro" — e não
        // conta inválida. Confundir os dois derrubaria todo usuário novo.
        if ($alteradaEm === null) {
            return true;
        }

        return strtotime((string)$alteradaEm) <= strtotime((string)$logadoEm);
    }

    public static function id(): ?int {
        $id = SessionHelper::get('user_id');
        return $id === null ? null : (int)$id;
    }

    public static function role(): ?string {
        return SessionHelper::get('user_perfil');
    }

    public static function nome(): ?string {
        return SessionHelper::get('user_nome');
    }

    public static function email(): ?string {
        return SessionHelper::get('user_email');
    }

    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        return [
            'id'     => self::id(),
            'nome'   => self::nome(),
            'email'  => self::email(),
            'perfil' => self::role(),
            'foto'   => SessionHelper::get('user_foto'),
        ];
    }

    public static function ehAdmin(): bool {
        return in_array(self::role(), self::PERFIS_ADMIN, true);
    }

    // -----------------------------------------------------------------
    // Login / Logout
    // -----------------------------------------------------------------

    /**
     * Registra o usuário na sessão. Regenera o ID da sessão para evitar
     * Session Fixation (docs/FSD.md seção 15).
     */
    public static function login(array $usuario): void {
        SessionHelper::init();

        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);
        }

        SessionHelper::set('user_id', (int)$usuario['id']);
        SessionHelper::set('user_nome', $usuario['nome']);
        SessionHelper::set('user_email', $usuario['email']);
        SessionHelper::set('user_perfil', $usuario['perfil']);
        SessionHelper::set('user_foto', $usuario['foto'] ?? null);
        SessionHelper::set('logado_em', date('Y-m-d H:i:s'));
    }

    public static function logout(): void {
        SessionHelper::destroy();
    }

    /**
     * Rota inicial de cada perfil após o login.
     */
    public static function rotaInicial(?string $perfil = null): string {
        $perfil = $perfil ?? self::role();

        switch ($perfil) {
            case 'admin_principal':
            case 'admin_limitado':
                return '/admin/dashboard';
            case 'profissional':
                return '/profissional/agenda';
            default:
                return '/cliente/agendamentos';
        }
    }

    // -----------------------------------------------------------------
    // Middleware de proteção de rotas
    // -----------------------------------------------------------------

    /**
     * Exige usuário autenticado. Caso contrário, guarda a URL pretendida
     * e redireciona para o login.
     */
    public static function requireAuth(): void {
        if (self::check()) {
            return;
        }

        SessionHelper::set('url_pretendida', $_SERVER['REQUEST_URI'] ?? null);
        SessionHelper::set('flash_erro', 'É necessário entrar na sua conta para acessar esta página.');

        header('Location: ' . self::baseUrl() . '/login');
        exit;
    }

    /**
     * Exige que o usuário autenticado possua um dos perfis informados.
     * Violações são registradas em `security_logs` e resultam em 403.
     */
    public static function requireRole($perfis): void {
        self::requireAuth();

        $permitidos = is_array($perfis) ? $perfis : [$perfis];

        if (in_array(self::role(), $permitidos, true)) {
            return;
        }

        LogModel::seguranca(
            LogModel::ACESSO_NEGADO_RBAC,
            'Rota: ' . ($_SERVER['REQUEST_URI'] ?? '?') . ' | Perfil: ' . (self::role() ?? 'nenhum'),
            self::id()
        );

        self::negarAcesso();
    }

    /**
     * Atalho para rotas administrativas.
     */
    public static function requireAdmin(): void {
        self::requireRole(self::PERFIS_ADMIN);
    }

    /**
     * Impede que usuários já autenticados acessem login/cadastro.
     */
    public static function requireGuest(): void {
        if (!self::check()) {
            return;
        }

        header('Location: ' . self::baseUrl() . self::rotaInicial());
        exit;
    }

    /**
     * Renderiza a página 403 e encerra a requisição.
     */
    public static function negarAcesso(): void {
        http_response_code(403);

        $config = $GLOBALS['config'];
        $pageTitle = 'Acesso Negado';
        require __DIR__ . '/../views/errors/403.php';
        exit;
    }

    private static function baseUrl(): string {
        return $GLOBALS['config']['app']['url'] ?? '';
    }
}
