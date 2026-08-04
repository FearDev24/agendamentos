<?php
// app/controllers/BaseController.php - Base comum a todos os Controllers
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Concentra renderização de Views, redirecionamentos, mensagens flash,
 * validação de CSRF e sanitização de entrada.
 */
abstract class BaseController {

    protected array $config;
    protected string $baseUrl;

    public function __construct() {
        $this->config = $GLOBALS['config'];
        $this->baseUrl = $this->config['app']['url'];
    }

    // -----------------------------------------------------------------
    // Renderização
    // -----------------------------------------------------------------

    /**
     * Renderiza uma View com header/footer padrão.
     * $dados é extraído para variáveis locais disponíveis dentro da View.
     */
    protected function render(string $view, array $dados = []): void {
        $config = $this->config;
        $baseUrl = $this->baseUrl;
        $pageTitle = ($dados['pageTitle'] ?? 'Início') . ' | ' . $config['app']['name'];

        // Mensagens flash consumidas uma única vez
        $flashSucesso = $this->consumirFlash('flash_sucesso');
        $flashErro    = $this->consumirFlash('flash_erro');

        extract($dados, EXTR_SKIP);

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/' . $view . '.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    // -----------------------------------------------------------------
    // Redirecionamento e mensagens
    // -----------------------------------------------------------------

    protected function redirect(string $rota): void {
        header('Location: ' . $this->baseUrl . $rota);
        exit;
    }

    protected function comSucesso(string $mensagem, string $rota): void {
        SessionHelper::set('flash_sucesso', $mensagem);
        $this->redirect($rota);
    }

    protected function comErro(string $mensagem, string $rota): void {
        SessionHelper::set('flash_erro', $mensagem);
        $this->redirect($rota);
    }

    protected function consumirFlash(string $chave): ?string {
        $valor = SessionHelper::get($chave);
        SessionHelper::remove($chave);
        return $valor;
    }

    /**
     * Guarda os dados digitados para repopular o formulário após um erro,
     * evitando que o usuário tenha que redigitar tudo. Senhas nunca são guardadas.
     */
    protected function guardarEntrada(array $dados): void {
        unset($dados['senha'], $dados['senha_confirmacao'], $dados['senha_atual'], $dados['csrf_token']);
        SessionHelper::set('form_antigo', $dados);
    }

    // -----------------------------------------------------------------
    // Entrada e segurança
    // -----------------------------------------------------------------

    protected function ehPost(): bool {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /**
     * Lê um campo do POST já com trim aplicado.
     */
    protected function post(string $campo, string $padrao = ''): string {
        $valor = $_POST[$campo] ?? $padrao;
        return is_string($valor) ? trim($valor) : $padrao;
    }

    protected function get(string $campo, string $padrao = ''): string {
        $valor = $_GET[$campo] ?? $padrao;
        return is_string($valor) ? trim($valor) : $padrao;
    }

    /**
     * Valida o token CSRF do POST. Em caso de falha, registra em
     * `security_logs` e encerra a requisição com 403.
     */
    protected function validarCsrf(): void {
        if (!SessionHelper::validateCsrfToken($_POST['csrf_token'] ?? null)) {
            LogModel::seguranca(
                LogModel::CSRF_INVALIDO,
                'Rota: ' . ($_SERVER['REQUEST_URI'] ?? '?'),
                AuthHelper::id()
            );

            http_response_code(403);
            $config = $this->config;
            $pageTitle = 'Requisição Inválida';
            $mensagemPersonalizada = 'A sessão do formulário expirou ou a requisição não pôde ser validada. Volte e tente novamente.';
            require __DIR__ . '/../views/errors/403.php';
            exit;
        }
    }

    /**
     * Resposta JSON para as rotas AJAX das próximas fases.
     */
    protected function json(array $dados, int $codigo = 200): void {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
