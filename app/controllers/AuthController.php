<?php
// app/controllers/AuthController.php - Login, cadastro, logout e recuperação de senha
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Módulo de Autenticação (docs/FSD.md seções 15 e 19).
 *
 * Regras aplicadas:
 *  - Senhas com password_hash()/password_verify();
 *  - Regeneração do ID de sessão no login (anti Session Fixation);
 *  - Bloqueio de 15 minutos após 5 tentativas malsucedidas;
 *  - Token de recuperação de senha com validade de 1 hora;
 *  - Confirmação declaratória de idade (+18) no cadastro;
 *  - Token CSRF obrigatório em todos os POST.
 */
class AuthController extends BaseController {

    private UsuarioModel $usuarios;

    public function __construct() {
        parent::__construct();
        $this->usuarios = new UsuarioModel();
    }

    // =================================================================
    // LOGIN
    // =================================================================

    public function formLogin(): void {
        AuthHelper::requireGuest();

        $this->render('public/login', [
            'pageTitle' => 'Entrar',
            'antigo'    => $this->consumirEntradaAntiga(),
        ]);
    }

    public function login(): void {
        AuthHelper::requireGuest();
        $this->validarCsrf();

        $email = mb_strtolower($this->post('email'));
        $senha = $_POST['senha'] ?? '';

        $this->guardarEntrada(['email' => $email]);

        if ($email === '' || $senha === '') {
            $this->comErro('Informe o e-mail e a senha.', '/login');
        }

        // Bloqueio temporário por excesso de tentativas
        if (LogModel::estaBloqueado($email)) {
            LogModel::seguranca(LogModel::LOGIN_BLOQUEADO, $email);
            $this->comErro(
                'Muitas tentativas malsucedidas. Aguarde ' . LogModel::MINUTOS_BLOQUEIO . ' minutos e tente novamente.',
                '/login'
            );
        }

        $usuario = $this->usuarios->buscarPorEmail($email);

        // Mensagem genérica: não revela se o e-mail existe (evita enumeração de contas)
        if ($usuario === null || !$this->usuarios->verificarSenha($usuario, $senha)) {
            LogModel::seguranca(LogModel::LOGIN_FALHOU, $email, $usuario['id'] ?? null);

            $restantes = LogModel::MAX_TENTATIVAS - LogModel::tentativasRecentes($email);
            $aviso = $restantes > 0 && $restantes <= 2
                ? " Restam {$restantes} tentativa(s) antes do bloqueio temporário."
                : '';

            $this->comErro('E-mail ou senha incorretos.' . $aviso, '/login');
        }

        if ($usuario['status'] !== 'ativo') {
            LogModel::seguranca(LogModel::CONTA_BLOQUEADA, $email, (int)$usuario['id']);

            $motivo = $usuario['status'] === 'bloqueado'
                ? 'Sua conta está bloqueada. Entre em contato com o estabelecimento.'
                : 'Sua conta está inativa. Entre em contato com o estabelecimento.';

            $this->comErro($motivo, '/login');
        }

        // Autenticação bem-sucedida
        LogModel::limparTentativas($email);
        AuthHelper::login($usuario);
        LogModel::seguranca(LogModel::LOGIN_SUCESSO, $email, (int)$usuario['id']);

        SessionHelper::remove('form_antigo');

        // Retorna à página que o usuário tentou acessar antes do login, se houver
        $pretendida = SessionHelper::get('url_pretendida');
        SessionHelper::remove('url_pretendida');

        if (is_string($pretendida) && $pretendida !== '') {
            $caminho = $this->rotaRelativa($pretendida);
            if ($caminho !== null) {
                $this->redirect($caminho);
            }
        }

        $this->redirect(AuthHelper::rotaInicial($usuario['perfil']));
    }

    public function logout(): void {
        if ($this->ehPost()) {
            $this->validarCsrf();
        }

        AuthHelper::logout();
        SessionHelper::set('flash_sucesso', 'Sessão encerrada com sucesso.');
        $this->redirect('/login');
    }

    // =================================================================
    // CADASTRO PÚBLICO DE CLIENTES
    // =================================================================

    public function formCadastro(): void {
        AuthHelper::requireGuest();

        $this->render('public/cadastro', [
            'pageTitle' => 'Criar Conta',
            'antigo'    => $this->consumirEntradaAntiga(),
        ]);
    }

    public function cadastrar(): void {
        AuthHelper::requireGuest();
        $this->validarCsrf();

        $nome     = $this->post('nome');
        $email    = mb_strtolower($this->post('email'));
        $telefone = $this->post('telefone');
        $senha    = $_POST['senha'] ?? '';
        $senha2   = $_POST['senha_confirmacao'] ?? '';
        $maiorIdade = isset($_POST['confirmo_maioridade']);

        $this->guardarEntrada(['nome' => $nome, 'email' => $email, 'telefone' => $telefone]);

        $erros = $this->validarCadastro($nome, $email, $telefone, $senha, $senha2, $maiorIdade);

        if (!empty($erros)) {
            $this->comErro(implode(' ', $erros), '/cadastro');
        }

        $foto = null;
        if (isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $foto = UploadHelper::process($_FILES['foto'], 'perfis', ['jpg', 'jpeg', 'png', 'webp']);
            if ($foto === null) {
                $this->comErro('A foto enviada é inválida. Use JPG, PNG ou WEBP com até 5MB.', '/cadastro');
            }
        }

        $id = $this->usuarios->criar([
            'nome'     => $nome,
            'email'    => $email,
            'telefone' => $telefone,
            'senha'    => $senha,
            'foto'     => $foto,
            'perfil'   => 'cliente',
        ]);

        $usuario = $this->usuarios->buscarPorId($id);
        AuthHelper::login($usuario);
        SessionHelper::remove('form_antigo');

        MailHelper::send(
            $email,
            'Bem-vindo ao ' . $this->config['app']['name'],
            '<p>Olá, ' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '!</p>'
            . '<p>Sua conta foi criada com sucesso. Já é possível agendar seus atendimentos.</p>'
        );

        $this->comSucesso('Conta criada com sucesso. Bem-vindo!', AuthHelper::rotaInicial('cliente'));
    }

    /**
     * Regras de validação do cadastro público.
     */
    private function validarCadastro(string $nome, string $email, string $telefone, string $senha, string $senha2, bool $maiorIdade): array {
        $erros = [];

        if (mb_strlen($nome) < 3) {
            $erros[] = 'Informe seu nome completo (mínimo de 3 caracteres).';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Informe um e-mail válido.';
        } elseif ($this->usuarios->emailEmUso($email)) {
            $erros[] = 'Este e-mail já está cadastrado.';
        }

        if (strlen(preg_replace('/\D/', '', $telefone)) < 10) {
            $erros[] = 'Informe um telefone válido com DDD.';
        }

        if (strlen($senha) < 8) {
            $erros[] = 'A senha deve ter no mínimo 8 caracteres.';
        } elseif ($senha !== $senha2) {
            $erros[] = 'A confirmação de senha não confere.';
        }

        if (!$maiorIdade) {
            $erros[] = 'É obrigatório confirmar que você tem 18 anos ou mais.';
        }

        return $erros;
    }

    // =================================================================
    // RECUPERAÇÃO DE SENHA
    // =================================================================

    public function formRecuperarSenha(): void {
        AuthHelper::requireGuest();
        $this->render('public/recuperar_senha', ['pageTitle' => 'Recuperar Senha']);
    }

    public function enviarRecuperacao(): void {
        AuthHelper::requireGuest();
        $this->validarCsrf();

        $email = mb_strtolower($this->post('email'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->comErro('Informe um e-mail válido.', '/recuperar-senha');
        }

        $usuario = $this->usuarios->buscarPorEmail($email);

        // Resposta idêntica exista ou não a conta, para não revelar cadastros
        if ($usuario !== null && $usuario['status'] === 'ativo') {
            $token = $this->usuarios->gerarTokenRecuperacao((int)$usuario['id']);
            $link = $this->baseUrl . '/redefinir-senha?token=' . $token;

            MailHelper::send(
                $email,
                'Redefinição de senha - ' . $this->config['app']['name'],
                '<p>Recebemos um pedido para redefinir sua senha.</p>'
                . '<p><a href="' . $link . '">Clique aqui para criar uma nova senha</a></p>'
                . '<p>Este link expira em 1 hora. Se não foi você, ignore este e-mail.</p>'
            );
        }

        $this->comSucesso(
            'Se houver uma conta com este e-mail, enviamos um link de redefinição. Verifique sua caixa de entrada.',
            '/login'
        );
    }

    public function formRedefinirSenha(): void {
        AuthHelper::requireGuest();

        $token = $this->get('token');
        $usuario = $token !== '' ? $this->usuarios->buscarPorTokenRecuperacao($token) : null;

        if ($usuario === null) {
            $this->comErro('Link de redefinição inválido ou expirado. Solicite um novo.', '/recuperar-senha');
        }

        $this->render('public/redefinir_senha', [
            'pageTitle' => 'Criar Nova Senha',
            'token'     => $token,
        ]);
    }

    public function redefinirSenha(): void {
        AuthHelper::requireGuest();
        $this->validarCsrf();

        $token  = $this->post('token');
        $senha  = $_POST['senha'] ?? '';
        $senha2 = $_POST['senha_confirmacao'] ?? '';

        $usuario = $token !== '' ? $this->usuarios->buscarPorTokenRecuperacao($token) : null;

        if ($usuario === null) {
            $this->comErro('Link de redefinição inválido ou expirado. Solicite um novo.', '/recuperar-senha');
        }

        if (strlen($senha) < 8) {
            $this->comErro('A senha deve ter no mínimo 8 caracteres.', '/redefinir-senha?token=' . urlencode($token));
        }

        if ($senha !== $senha2) {
            $this->comErro('A confirmação de senha não confere.', '/redefinir-senha?token=' . urlencode($token));
        }

        $this->usuarios->atualizarSenha((int)$usuario['id'], $senha);
        LogModel::seguranca(LogModel::SENHA_REDEFINIDA, $usuario['email'], (int)$usuario['id']);

        MailHelper::send(
            $usuario['email'],
            'Sua senha foi alterada - ' . $this->config['app']['name'],
            '<p>Sua senha foi redefinida com sucesso. Se não foi você, entre em contato conosco imediatamente.</p>'
        );

        $this->comSucesso('Senha redefinida com sucesso. Faça login com a nova senha.', '/login');
    }

    // =================================================================
    // CONFIRMAÇÃO DE TROCA DE E-MAIL
    // =================================================================

    public function confirmarEmail(): void {
        $token = $this->get('token');
        $novoEmail = $token !== '' ? $this->usuarios->confirmarTrocaEmail($token) : null;

        if ($novoEmail === null) {
            $this->comErro('Link de confirmação inválido ou expirado.', '/login');
        }

        // Se for o próprio usuário logado, atualiza o e-mail na sessão
        if (AuthHelper::check()) {
            SessionHelper::set('user_email', $novoEmail);
            $this->comSucesso('E-mail confirmado e atualizado com sucesso.', '/perfil');
        }

        $this->comSucesso('E-mail confirmado com sucesso. Faça login com o novo endereço.', '/login');
    }

    // =================================================================
    // Auxiliares
    // =================================================================

    private function consumirEntradaAntiga(): array {
        $antigo = SessionHelper::get('form_antigo', []);
        SessionHelper::remove('form_antigo');
        return is_array($antigo) ? $antigo : [];
    }

    /**
     * Converte uma URL absoluta interna em rota relativa segura.
     * Retorna null se a URL apontar para fora da aplicação (open redirect).
     */
    private function rotaRelativa(string $url): ?string {
        // Descarta URLs absolutas ou protocol-relative que apontem para outro host
        if (preg_match('#^(https?:)?//#i', $url)) {
            return null;
        }

        $caminho = parse_url($url, PHP_URL_PATH);
        if (!is_string($caminho)) {
            return null;
        }

        $base = parse_url($this->baseUrl, PHP_URL_PATH) ?? '';
        if ($base !== '' && strpos($caminho, $base) === 0) {
            $caminho = substr($caminho, strlen($base));
        }

        $caminho = '/' . ltrim($caminho, '/');

        return $caminho === '/login' ? null : $caminho;
    }
}
