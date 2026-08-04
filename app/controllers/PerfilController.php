<?php
// app/controllers/PerfilController.php - Gestão do perfil do usuário autenticado
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Perfil do usuário (docs/FSD.md Módulo 2).
 *
 * Permite alterar nome, telefone, foto e senha. A troca de e-mail exige
 * confirmação por link enviado ao novo endereço. A exclusão da conta só é
 * permitida quando não houver agendamentos futuros ativos.
 */
class PerfilController extends BaseController {

    private UsuarioModel $usuarios;

    public function __construct() {
        parent::__construct();
        $this->usuarios = new UsuarioModel();
    }

    public function index(): void {
        AuthHelper::requireAuth();

        $usuario = $this->usuarios->buscarPorId(AuthHelper::id());

        if ($usuario === null) {
            AuthHelper::logout();
            $this->comErro('Sua conta não está mais disponível.', '/login');
        }

        $this->render('public/perfil', [
            'pageTitle'          => 'Meu Perfil',
            'usuario'            => $usuario,
            'possuiAgendamentos' => $this->usuarios->possuiAgendamentosFuturosAtivos((int)$usuario['id']),
        ]);
    }

    /**
     * Atualiza nome, telefone e (opcionalmente) a foto de perfil.
     */
    public function atualizarDados(): void {
        AuthHelper::requireAuth();
        $this->validarCsrf();

        $id = AuthHelper::id();
        $nome = $this->post('nome');
        $telefone = $this->post('telefone');

        if (mb_strlen($nome) < 3) {
            $this->comErro('Informe seu nome completo (mínimo de 3 caracteres).', '/perfil');
        }

        if (strlen(preg_replace('/\D/', '', $telefone)) < 10) {
            $this->comErro('Informe um telefone válido com DDD.', '/perfil');
        }

        $this->usuarios->atualizarDadosBasicos($id, $nome, $telefone);
        SessionHelper::set('user_nome', $nome);

        if (isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $foto = UploadHelper::process($_FILES['foto'], 'perfis', ['jpg', 'jpeg', 'png', 'webp']);

            if ($foto === null) {
                $this->comErro('A foto enviada é inválida. Use JPG, PNG ou WEBP com até 5MB.', '/perfil');
            }

            $this->usuarios->atualizarFoto($id, $foto);
            SessionHelper::set('user_foto', $foto);
        }

        $this->comSucesso('Dados atualizados com sucesso.', '/perfil');
    }

    /**
     * Troca de senha exigindo a senha atual.
     */
    public function alterarSenha(): void {
        AuthHelper::requireAuth();
        $this->validarCsrf();

        $atual  = $_POST['senha_atual'] ?? '';
        $nova   = $_POST['senha'] ?? '';
        $nova2  = $_POST['senha_confirmacao'] ?? '';

        $usuario = $this->usuarios->buscarPorId(AuthHelper::id());

        if ($usuario === null || !$this->usuarios->verificarSenha($usuario, $atual)) {
            LogModel::seguranca(LogModel::LOGIN_FALHOU, 'troca_de_senha:' . AuthHelper::email(), AuthHelper::id());
            $this->comErro('A senha atual está incorreta.', '/perfil');
        }

        if (strlen($nova) < 8) {
            $this->comErro('A nova senha deve ter no mínimo 8 caracteres.', '/perfil');
        }

        if ($nova !== $nova2) {
            $this->comErro('A confirmação da nova senha não confere.', '/perfil');
        }

        $this->usuarios->atualizarSenha((int)$usuario['id'], $nova);
        LogModel::seguranca(LogModel::SENHA_REDEFINIDA, $usuario['email'], (int)$usuario['id']);

        $this->comSucesso('Senha alterada com sucesso.', '/perfil');
    }

    /**
     * Inicia a troca de e-mail: o novo endereço só passa a valer após
     * o clique no link de confirmação enviado a ele.
     */
    public function solicitarTrocaEmail(): void {
        AuthHelper::requireAuth();
        $this->validarCsrf();

        $id = AuthHelper::id();
        $novoEmail = mb_strtolower($this->post('novo_email'));
        $senha = $_POST['senha_atual'] ?? '';

        $usuario = $this->usuarios->buscarPorId($id);

        if ($usuario === null || !$this->usuarios->verificarSenha($usuario, $senha)) {
            $this->comErro('Confirme sua senha atual para alterar o e-mail.', '/perfil');
        }

        if (!filter_var($novoEmail, FILTER_VALIDATE_EMAIL)) {
            $this->comErro('Informe um e-mail válido.', '/perfil');
        }

        if ($novoEmail === $usuario['email']) {
            $this->comErro('O novo e-mail é igual ao atual.', '/perfil');
        }

        if ($this->usuarios->emailEmUso($novoEmail, $id)) {
            $this->comErro('Este e-mail já está em uso por outra conta.', '/perfil');
        }

        $token = $this->usuarios->solicitarTrocaEmail($id, $novoEmail);
        $link = $this->baseUrl . '/confirmar-email?token=' . $token;

        MailHelper::send(
            $novoEmail,
            'Confirme seu novo e-mail - ' . $this->config['app']['name'],
            '<p>Para concluir a alteração do seu e-mail, confirme este endereço.</p>'
            . '<p><a href="' . $link . '">Clique aqui para confirmar</a></p>'
            . '<p>Este link expira em 1 hora.</p>'
        );

        $this->comSucesso(
            'Enviamos um link de confirmação para ' . htmlspecialchars($novoEmail, ENT_QUOTES, 'UTF-8')
            . '. O e-mail só será alterado após a confirmação.',
            '/perfil'
        );
    }

    /**
     * Exclusão da própria conta (Soft Delete), barrada quando existem
     * agendamentos futuros ativos.
     */
    public function excluirConta(): void {
        AuthHelper::requireAuth();
        $this->validarCsrf();

        $id = AuthHelper::id();
        $senha = $_POST['senha_atual'] ?? '';

        $usuario = $this->usuarios->buscarPorId($id);

        if ($usuario === null || !$this->usuarios->verificarSenha($usuario, $senha)) {
            $this->comErro('Confirme sua senha para excluir a conta.', '/perfil');
        }

        if ($this->usuarios->possuiAgendamentosFuturosAtivos($id)) {
            $this->comErro(
                'Não é possível excluir a conta enquanto houver agendamentos futuros ativos. Cancele-os primeiro.',
                '/perfil'
            );
        }

        $this->usuarios->excluirLogicamente($id);
        LogModel::seguranca(LogModel::CONTA_EXCLUIDA, $usuario['email'], $id);

        AuthHelper::logout();
        SessionHelper::set('flash_sucesso', 'Sua conta foi excluída. Sentiremos sua falta.');
        $this->redirect('/login');
    }
}
