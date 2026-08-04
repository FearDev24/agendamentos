<?php
/**
 * tests/teste_fase3.php - Validação da Fase 3 (Autenticação, Sessões e RBAC)
 *
 * Uso: php tests/teste_fase3.php
 *
 * Pré-requisitos: MySQL e Apache do XAMPP em execução.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Este script só pode ser executado via linha de comando.');
}

define('APP_EXEC', true);
date_default_timezone_set('America/Sao_Paulo');

$config = require __DIR__ . '/../config/config.php';
$GLOBALS['config'] = $config;

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/../app/bootstrap.php';

$t = new TestRunner();
$pdo = Database::getConnection();
$baseUrl = $config['app']['url'];

// Prefixo usado para identificar e limpar os dados de teste
const PREFIXO_TESTE = 'teste_fase3_';
const SENHA_TESTE = 'SenhaTeste@123';

echo "\n======================================================\n";
echo " TESTES DA FASE 3 - Autenticação, Sessões e RBAC\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

// ---------------------------------------------------------------------
// Preparação: limpa resíduos de execuções anteriores e cria as contas
// ---------------------------------------------------------------------
limparDadosDeTeste($pdo);

$usuarios = new UsuarioModel();

$idCliente = $usuarios->criar([
    'nome' => 'Cliente de Teste', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 98888-0001', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);
$idProfissional = $usuarios->criar([
    'nome' => 'Profissional de Teste', 'email' => PREFIXO_TESTE . 'prof@local.test',
    'telefone' => '(11) 98888-0002', 'senha' => SENHA_TESTE, 'perfil' => 'profissional',
]);
$idAdminPrincipal = $usuarios->criar([
    'nome' => 'Admin Principal de Teste', 'email' => PREFIXO_TESTE . 'principal@local.test',
    'telefone' => '(11) 98888-0003', 'senha' => SENHA_TESTE, 'perfil' => 'admin_principal',
]);
$idAdminLimitado = $usuarios->criar([
    'nome' => 'Admin Limitado de Teste', 'email' => PREFIXO_TESTE . 'limitado@local.test',
    'telefone' => '(11) 98888-0004', 'senha' => SENHA_TESTE, 'perfil' => 'admin_limitado',
]);
$idBloqueado = $usuarios->criar([
    'nome' => 'Conta Bloqueada', 'email' => PREFIXO_TESTE . 'bloqueado@local.test',
    'telefone' => '(11) 98888-0005', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
    'status' => 'bloqueado',
]);

// Registro de profissional (necessário para o teste de agendamento futuro)
$pdo->prepare('INSERT INTO profissionais (usuario_id) VALUES (?)')->execute([$idProfissional]);
$idRegistroProfissional = (int)$pdo->lastInsertId();

// =====================================================================
$t->grupo('1. Estrutura MVC e autoload');

foreach (['Router', 'BaseController', 'AuthController', 'PerfilController', 'HomeController',
          'ClienteController', 'ProfissionalController', 'AdminController',
          'BaseModel', 'UsuarioModel', 'LogModel', 'Database', 'AuthHelper', 'SessionHelper'] as $classe) {
    $t->verificar("Classe `{$classe}` carregada pelo autoloader", class_exists($classe));
}

$t->verificar('Autoloader ignora nomes de classe maliciosos', !class_exists('../../etc/passwd'));

foreach (['errors/403', 'errors/404', 'errors/500', 'public/login', 'public/cadastro',
          'public/recuperar_senha', 'public/redefinir_senha', 'public/perfil', 'public/home',
          'cliente/agendamentos', 'profissional/agenda', 'admin/dashboard', 'admin/auditoria'] as $view) {
    $t->verificar("View `{$view}.php` existe", file_exists(__DIR__ . "/../app/views/{$view}.php"));
}

$t->verificar('.htaccess de proteção existe em app/', file_exists(__DIR__ . '/../app/.htaccess'));

// =====================================================================
$t->grupo('2. Roteador: normalização de caminhos');

$_SERVER['REQUEST_URI'] = '/sistemaBarbearia/login';
$t->igual('URL com subdiretório vira rota limpa', '/login', Router::caminhoAtual($baseUrl));

$_SERVER['REQUEST_URI'] = '/sistemaBarbearia/';
$t->igual('Raiz do subdiretório vira "/"', '/', Router::caminhoAtual($baseUrl));

$_SERVER['REQUEST_URI'] = '/sistemaBarbearia/index.php';
$t->igual('index.php equivale à raiz', '/', Router::caminhoAtual($baseUrl));

$_SERVER['REQUEST_URI'] = '/sistemaBarbearia/redefinir-senha?token=abc123';
$t->igual('Query string é descartada da rota', '/redefinir-senha', Router::caminhoAtual($baseUrl));

$_SERVER['REQUEST_URI'] = '/sistemaBarbearia/admin/dashboard/';
$t->igual('Barra final é normalizada', '/admin/dashboard', Router::caminhoAtual($baseUrl));

// =====================================================================
$t->grupo('3. UsuarioModel: contas, hash de senha e Soft Delete');

$cliente = $usuarios->buscarPorEmail(PREFIXO_TESTE . 'cliente@local.test');
$t->verificar('Conta localizada por e-mail', $cliente !== null);
$t->igual('Perfil gravado corretamente', 'cliente', $cliente['perfil']);
$t->verificar('Senha gravada como hash BCRYPT', strpos($cliente['senha_hash'], '$2y$') === 0);
$t->verificar('Senha em texto plano NÃO aparece no banco', strpos($cliente['senha_hash'], SENHA_TESTE) === false);
$t->verificar('verificarSenha() aceita a senha correta', $usuarios->verificarSenha($cliente, SENHA_TESTE));
$t->verificar('verificarSenha() rejeita senha incorreta', !$usuarios->verificarSenha($cliente, 'senhaErrada'));
$t->verificar('verificarSenha() rejeita senha vazia', !$usuarios->verificarSenha($cliente, ''));

$t->verificar('Busca por e-mail é case-insensitive', $usuarios->buscarPorEmail(strtoupper(PREFIXO_TESTE . 'CLIENTE@LOCAL.TEST')) !== null);
$t->verificar('emailEmUso() detecta e-mail já cadastrado', $usuarios->emailEmUso(PREFIXO_TESTE . 'cliente@local.test'));
$t->verificar('emailEmUso() ignora o próprio usuário', !$usuarios->emailEmUso(PREFIXO_TESTE . 'cliente@local.test', $idCliente));

// Soft Delete
$idDescartavel = $usuarios->criar([
    'nome' => 'Conta Descartavel', 'email' => PREFIXO_TESTE . 'descartavel@local.test',
    'telefone' => '(11) 98888-0009', 'senha' => SENHA_TESTE,
]);
$usuarios->excluirLogicamente($idDescartavel);
$t->verificar('Soft Delete: conta some das buscas do Model', $usuarios->buscarPorId($idDescartavel) === null);
$linhaCrua = $pdo->query("SELECT deleted_at FROM usuarios WHERE id = {$idDescartavel}")->fetch();
$t->verificar('Soft Delete: registro preservado no banco com deleted_at', $linhaCrua !== false && $linhaCrua['deleted_at'] !== null);

// =====================================================================
$t->grupo('4. Tokens de recuperação de senha (validade de 1 hora)');

$token = $usuarios->gerarTokenRecuperacao($idCliente);
$t->igual('Token gerado com 64 caracteres hexadecimais', 64, strlen($token));
$t->verificar('Token é hexadecimal (bin2hex/random_bytes)', ctype_xdigit($token));
$t->verificar('Token válido localiza o usuário', $usuarios->buscarPorTokenRecuperacao($token) !== null);
$t->verificar('Token inexistente não localiza ninguém', $usuarios->buscarPorTokenRecuperacao('token_falso_123') === null);

$token2 = $usuarios->gerarTokenRecuperacao($idCliente);
$t->verificar('Novo pedido gera token diferente do anterior', $token !== $token2);
$t->verificar('Token antigo deixa de valer após novo pedido', $usuarios->buscarPorTokenRecuperacao($token) === null);

// Expiração: força o vencimento no banco
$pdo->prepare('UPDATE usuarios SET token_expiracao = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([$idCliente]);
$t->verificar('Token EXPIRADO é rejeitado', $usuarios->buscarPorTokenRecuperacao($token2) === null);

// Uso do token e invalidação
$token3 = $usuarios->gerarTokenRecuperacao($idCliente);
$usuarios->atualizarSenha($idCliente, 'NovaSenha@456');
$t->verificar('Token é invalidado após a troca de senha', $usuarios->buscarPorTokenRecuperacao($token3) === null);
$clienteAtualizado = $usuarios->buscarPorId($idCliente);
$t->verificar('Nova senha passa a valer', $usuarios->verificarSenha($clienteAtualizado, 'NovaSenha@456'));
$t->verificar('Senha antiga deixa de valer', !$usuarios->verificarSenha($clienteAtualizado, SENHA_TESTE));
$usuarios->atualizarSenha($idCliente, SENHA_TESTE); // restaura para os testes HTTP

// =====================================================================
$t->grupo('5. Troca de e-mail com confirmação por link');

$emailOriginal = $usuarios->buscarPorId($idCliente)['email'];
$tokenEmail = $usuarios->solicitarTrocaEmail($idCliente, PREFIXO_TESTE . 'novo@local.test');

$aposSolicitacao = $usuarios->buscarPorId($idCliente);
$t->igual('E-mail principal NÃO muda antes da confirmação', $emailOriginal, $aposSolicitacao['email']);
$t->igual('Novo e-mail fica retido em email_pendente', PREFIXO_TESTE . 'novo@local.test', $aposSolicitacao['email_pendente']);
$t->verificar('emailEmUso() também considera e-mails pendentes', $usuarios->emailEmUso(PREFIXO_TESTE . 'novo@local.test'));

$t->verificar('Token de e-mail inválido não confirma nada', $usuarios->confirmarTrocaEmail('token_falso') === null);

$confirmado = $usuarios->confirmarTrocaEmail($tokenEmail);
$t->igual('Token válido efetiva a troca', PREFIXO_TESTE . 'novo@local.test', $confirmado);
$aposConfirmacao = $usuarios->buscarPorId($idCliente);
$t->igual('E-mail principal atualizado', PREFIXO_TESTE . 'novo@local.test', $aposConfirmacao['email']);
$t->verificar('email_pendente é limpo após confirmação', $aposConfirmacao['email_pendente'] === null);
$t->verificar('Token de e-mail não pode ser reutilizado', $usuarios->confirmarTrocaEmail($tokenEmail) === null);

// Restaura o e-mail original para os testes HTTP
$pdo->prepare('UPDATE usuarios SET email = ? WHERE id = ?')->execute([$emailOriginal, $idCliente]);

// =====================================================================
$t->grupo('6. Exclusão de conta e agendamentos futuros ativos');

$t->verificar('Sem agendamentos: exclusão liberada', !$usuarios->possuiAgendamentosFuturosAtivos($idCliente));

$pdo->prepare("
    INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                              hora_inicio, hora_fim, duracao_total_minutos, valor_total, situacao)
    VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '10:00:00', '11:00:00', 60, 90.00, 'confirmado')
")->execute([PREFIXO_TESTE . 'R1', $idCliente, $idRegistroProfissional]);

$t->verificar('Com agendamento futuro confirmado: exclusão bloqueada', $usuarios->possuiAgendamentosFuturosAtivos($idCliente));

$pdo->prepare("UPDATE agendamentos SET situacao = 'cancelado' WHERE codigo_reserva = ?")
    ->execute([PREFIXO_TESTE . 'R1']);
$t->verificar('Agendamento cancelado não bloqueia a exclusão', !$usuarios->possuiAgendamentosFuturosAtivos($idCliente));

$pdo->prepare("
    UPDATE agendamentos SET situacao = 'confirmado', data_agendamento = DATE_SUB(CURDATE(), INTERVAL 5 DAY)
    WHERE codigo_reserva = ?
")->execute([PREFIXO_TESTE . 'R1']);
$t->verificar('Agendamento passado não bloqueia a exclusão', !$usuarios->possuiAgendamentosFuturosAtivos($idCliente));

// =====================================================================
$t->grupo('7. Bloqueio por excesso de tentativas de login');

$emailAlvo = PREFIXO_TESTE . 'forcabruta@local.test';
$pdo->prepare('DELETE FROM security_logs WHERE detalhes = ?')->execute([$emailAlvo]);

$t->verificar('Conta começa desbloqueada', !LogModel::estaBloqueado($emailAlvo));

for ($i = 1; $i <= 4; $i++) {
    LogModel::seguranca(LogModel::LOGIN_FALHOU, $emailAlvo);
}
$t->igual('4 tentativas registradas', 4, LogModel::tentativasRecentes($emailAlvo));
$t->verificar('Com 4 tentativas ainda NÃO está bloqueado', !LogModel::estaBloqueado($emailAlvo));

LogModel::seguranca(LogModel::LOGIN_FALHOU, $emailAlvo);
$t->verificar('Na 5ª tentativa o acesso é bloqueado', LogModel::estaBloqueado($emailAlvo));

LogModel::limparTentativas($emailAlvo);
$t->verificar('Login bem-sucedido limpa o histórico de tentativas', !LogModel::estaBloqueado($emailAlvo));

// Tentativas antigas não devem contar (janela de 15 minutos)
LogModel::seguranca(LogModel::LOGIN_FALHOU, $emailAlvo);
$pdo->prepare("UPDATE security_logs SET created_at = DATE_SUB(NOW(), INTERVAL 20 MINUTE) WHERE detalhes = ?")
    ->execute([$emailAlvo]);
$t->igual('Tentativas fora da janela de 15 min são desconsideradas', 0, LogModel::tentativasRecentes($emailAlvo));

// =====================================================================
$t->grupo('8. HTTP: rotas públicas e códigos de resposta');

$http = new HttpClient($baseUrl);

$t->igual('GET / responde 200', 200, $http->get('/')->ultimoStatus);
$t->igual('GET /login responde 200', 200, $http->get('/login')->ultimoStatus);
$t->igual('GET /cadastro responde 200', 200, $http->get('/cadastro')->ultimoStatus);
$t->igual('GET /recuperar-senha responde 200', 200, $http->get('/recuperar-senha')->ultimoStatus);
$t->igual('Rota inexistente responde 404', 404, $http->get('/rota-que-nao-existe')->ultimoStatus);
$t->igual('Método errado em rota existente responde 405', 405, $http->get('/perfil/senha')->ultimoStatus);

$http->get('/login');
$t->verificar('Formulário de login traz token CSRF', $http->corpoContem('name="csrf_token"'));
$t->verificar('Cadastro exige confirmação de maioridade (+18)',
    $http->get('/cadastro')->corpoContem('confirmo_maioridade'));

// =====================================================================
$t->grupo('9. HTTP: fluxo de login');

$http->novaSessao();

// Sem token CSRF
$http->post('/login', ['email' => PREFIXO_TESTE . 'cliente@local.test', 'senha' => SENHA_TESTE]);
$t->igual('POST /login SEM token CSRF é rejeitado com 403', 403, $http->ultimoStatus);

// Token CSRF inválido
$http->novaSessao();
$http->csrfDe('/login');
$http->post('/login', ['csrf_token' => 'token_forjado', 'email' => PREFIXO_TESTE . 'cliente@local.test', 'senha' => SENHA_TESTE]);
$t->igual('POST /login com token CSRF FORJADO é rejeitado com 403', 403, $http->ultimoStatus);

// Senha incorreta
$http->novaSessao();
$csrf = $http->csrfDe('/login');
$http->post('/login', ['csrf_token' => $csrf, 'email' => PREFIXO_TESTE . 'cliente@local.test', 'senha' => 'senhaErrada']);
$t->igual('Login com senha errada redireciona de volta ao login', '/login', $http->destinoRedirecionamento());
$t->verificar('Mensagem de erro é genérica (não revela se o e-mail existe)',
    $http->get('/login')->corpoContem('E-mail ou senha incorretos'));

// Conta bloqueada
$http->novaSessao();
$csrf = $http->csrfDe('/login');
$http->post('/login', ['csrf_token' => $csrf, 'email' => PREFIXO_TESTE . 'bloqueado@local.test', 'senha' => SENHA_TESTE]);
$http->get('/login');
$t->verificar('Conta com status "bloqueado" não consegue entrar', $http->corpoContem('bloqueada'));

// Login correto
$http->novaSessao();
$csrf = $http->csrfDe('/login');
$http->post('/login', ['csrf_token' => $csrf, 'email' => PREFIXO_TESTE . 'cliente@local.test', 'senha' => SENHA_TESTE]);
$t->igual('Login válido redireciona o cliente ao seu painel', '/cliente/agendamentos', $http->destinoRedirecionamento());
$t->igual('Sessão autenticada acessa área restrita', 200, $http->get('/cliente/agendamentos')->ultimoStatus);
$t->igual('Sessão autenticada acessa o perfil', 200, $http->get('/perfil')->ultimoStatus);

// Logout
$csrfLogout = $http->csrfDe('/perfil');
$http->post('/logout', ['csrf_token' => $csrfLogout]);
$t->igual('Logout redireciona ao login', '/login', $http->destinoRedirecionamento());
$t->igual('Após logout a área restrita volta a redirecionar', 302, $http->get('/cliente/agendamentos')->ultimoStatus);

// =====================================================================
$t->grupo('10. HTTP: RBAC (matriz de permissões do FSD)');

/** Autentica e devolve o cliente HTTP pronto */
$entrarComo = function (string $email) use ($baseUrl): HttpClient {
    $c = new HttpClient($baseUrl);
    $csrf = $c->csrfDe('/login');
    $c->post('/login', ['csrf_token' => $csrf, 'email' => $email, 'senha' => SENHA_TESTE]);
    return $c;
};

// Visitante não autenticado
$visitante = new HttpClient($baseUrl);
$t->igual('Visitante em /admin/dashboard é redirecionado', 302, $visitante->get('/admin/dashboard')->ultimoStatus);
$t->igual('Redirecionamento aponta para /login', '/login', $visitante->destinoRedirecionamento());
$t->igual('Visitante em /profissional/agenda é redirecionado', 302, $visitante->get('/profissional/agenda')->ultimoStatus);
$t->igual('Visitante em /perfil é redirecionado', 302, $visitante->get('/perfil')->ultimoStatus);

// Cliente
$clienteHttp = $entrarComo(PREFIXO_TESTE . 'cliente@local.test');
$t->igual('Cliente ACESSA /cliente/agendamentos', 200, $clienteHttp->get('/cliente/agendamentos')->ultimoStatus);
$t->igual('Cliente é BLOQUEADO em /admin/dashboard (403)', 403, $clienteHttp->get('/admin/dashboard')->ultimoStatus);
$t->igual('Cliente é BLOQUEADO em /admin/auditoria (403)', 403, $clienteHttp->get('/admin/auditoria')->ultimoStatus);
$t->igual('Cliente é BLOQUEADO em /profissional/agenda (403)', 403, $clienteHttp->get('/profissional/agenda')->ultimoStatus);
$t->verificar('Página 403 traz a mensagem de acesso negado do FSD',
    $clienteHttp->corpoContem('não possui permissão'));

// Profissional
$profHttp = $entrarComo(PREFIXO_TESTE . 'prof@local.test');
$t->igual('Profissional ACESSA /profissional/agenda', 200, $profHttp->get('/profissional/agenda')->ultimoStatus);
$t->igual('Profissional é BLOQUEADO em /admin/dashboard (403)', 403, $profHttp->get('/admin/dashboard')->ultimoStatus);
$t->igual('Profissional é BLOQUEADO em /cliente/agendamentos (403)', 403, $profHttp->get('/cliente/agendamentos')->ultimoStatus);

// Administrador Limitado
$limitadoHttp = $entrarComo(PREFIXO_TESTE . 'limitado@local.test');
$t->igual('Admin Limitado ACESSA /admin/dashboard', 200, $limitadoHttp->get('/admin/dashboard')->ultimoStatus);
$t->igual('Admin Limitado é BLOQUEADO em /admin/auditoria (403)', 403, $limitadoHttp->get('/admin/auditoria')->ultimoStatus);
$t->igual('Admin Limitado é BLOQUEADO em /cliente/agendamentos (403)', 403, $limitadoHttp->get('/cliente/agendamentos')->ultimoStatus);
$t->verificar('Menu do Admin Limitado NÃO exibe o item Auditoria',
    !$limitadoHttp->get('/admin/dashboard')->corpoContem('/admin/auditoria'));

// Administrador Principal
$principalHttp = $entrarComo(PREFIXO_TESTE . 'principal@local.test');
$t->igual('Admin Principal ACESSA /admin/dashboard', 200, $principalHttp->get('/admin/dashboard')->ultimoStatus);
$t->igual('Admin Principal ACESSA /admin/auditoria', 200, $principalHttp->get('/admin/auditoria')->ultimoStatus);
$t->verificar('Menu do Admin Principal EXIBE o item Auditoria',
    $principalHttp->get('/admin/dashboard')->corpoContem('/admin/auditoria'));

// =====================================================================
$t->grupo('11. HTTP: proteção CSRF nos formulários');

$t->igual('POST /cadastro sem token CSRF é rejeitado', 403,
    $visitante->post('/cadastro', ['nome' => 'X', 'email' => 'x@x.com'])->ultimoStatus);
$t->igual('POST /perfil/senha com token inválido é rejeitado', 403,
    $clienteHttp->post('/perfil/senha', ['csrf_token' => 'invalido', 'senha_atual' => 'x'])->ultimoStatus);
$t->igual('POST /perfil/excluir com token inválido é rejeitado', 403,
    $clienteHttp->post('/perfil/excluir', ['csrf_token' => 'invalido'])->ultimoStatus);
$t->igual('POST /logout com token inválido é rejeitado', 403,
    $clienteHttp->post('/logout', ['csrf_token' => 'invalido'])->ultimoStatus);
$t->igual('POST /recuperar-senha sem token é rejeitado', 403,
    $visitante->post('/recuperar-senha', ['email' => 'x@x.com'])->ultimoStatus);

// =====================================================================
$t->grupo('12. HTTP: cadastro público e validações');

$emailNovo = PREFIXO_TESTE . 'cadastrado@local.test';
$novo = new HttpClient($baseUrl);

// Sem confirmação de maioridade
$csrf = $novo->csrfDe('/cadastro');
$novo->post('/cadastro', [
    'csrf_token' => $csrf, 'nome' => 'Fulano de Teste', 'email' => $emailNovo,
    'telefone' => '(11) 97777-0001', 'senha' => SENHA_TESTE, 'senha_confirmacao' => SENHA_TESTE,
]);
$t->verificar('Cadastro SEM confirmação de +18 é recusado',
    $usuarios->buscarPorEmail($emailNovo) === null);

// Senha curta
$csrf = $novo->csrfDe('/cadastro');
$novo->post('/cadastro', [
    'csrf_token' => $csrf, 'nome' => 'Fulano de Teste', 'email' => $emailNovo,
    'telefone' => '(11) 97777-0001', 'senha' => '123', 'senha_confirmacao' => '123',
    'confirmo_maioridade' => '1',
]);
$t->verificar('Cadastro com senha curta é recusado', $usuarios->buscarPorEmail($emailNovo) === null);

// Confirmação divergente
$csrf = $novo->csrfDe('/cadastro');
$novo->post('/cadastro', [
    'csrf_token' => $csrf, 'nome' => 'Fulano de Teste', 'email' => $emailNovo,
    'telefone' => '(11) 97777-0001', 'senha' => SENHA_TESTE, 'senha_confirmacao' => 'Outra@123',
    'confirmo_maioridade' => '1',
]);
$t->verificar('Cadastro com confirmação de senha divergente é recusado',
    $usuarios->buscarPorEmail($emailNovo) === null);

// E-mail duplicado
$csrf = $novo->csrfDe('/cadastro');
$novo->post('/cadastro', [
    'csrf_token' => $csrf, 'nome' => 'Fulano de Teste', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 97777-0001', 'senha' => SENHA_TESTE, 'senha_confirmacao' => SENHA_TESTE,
    'confirmo_maioridade' => '1',
]);
$novo->get('/cadastro');
$t->verificar('Cadastro com e-mail já existente é recusado', $novo->corpoContem('já está cadastrado'));

// Cadastro válido
$csrf = $novo->csrfDe('/cadastro');
$novo->post('/cadastro', [
    'csrf_token' => $csrf, 'nome' => 'Fulano de Teste', 'email' => $emailNovo,
    'telefone' => '(11) 97777-0001', 'senha' => SENHA_TESTE, 'senha_confirmacao' => SENHA_TESTE,
    'confirmo_maioridade' => '1',
]);
$criado = $usuarios->buscarPorEmail($emailNovo);
$t->verificar('Cadastro válido cria a conta', $criado !== null);
$t->igual('Conta criada com perfil "cliente"', 'cliente', $criado['perfil'] ?? null);
$t->verificar('Senha do novo cadastro gravada com hash', isset($criado['senha_hash']) && strpos($criado['senha_hash'], '$2y$') === 0);
$t->igual('Usuário é autenticado logo após o cadastro', '/cliente/agendamentos', $novo->destinoRedirecionamento());

// =====================================================================
$t->grupo('13. HTTP: proteção contra XSS na renderização');

$nomeMalicioso = '<script>alert(1)</script>';
$pdo->prepare('UPDATE usuarios SET nome = ? WHERE id = ?')->execute([$nomeMalicioso, $idCliente]);

$xssHttp = $entrarComo(PREFIXO_TESTE . 'cliente@local.test');
$xssHttp->get('/cliente/agendamentos');
$t->verificar('Script malicioso NÃO é renderizado como HTML executável',
    !$xssHttp->corpoContem('<script>alert(1)</script>'));
$t->verificar('Conteúdo perigoso aparece escapado (&lt;script&gt;)',
    $xssHttp->corpoContem('&lt;script&gt;'));

$pdo->prepare('UPDATE usuarios SET nome = ? WHERE id = ?')->execute(['Cliente de Teste', $idCliente]);

// =====================================================================
$t->grupo('14. HTTP: proteção de diretórios internos');

$protegidos = [
    '/config/config.php',
    '/app/controllers/AuthController.php',
    '/app/models/UsuarioModel.php',
    '/app/helpers/AuthHelper.php',
    '/database/migrate.php',
    '/database/migrations/001_create_system_tables.php',
    '/logs/error.log',
    '/tests/teste_fase3.php',
];

foreach ($protegidos as $caminho) {
    $status = $visitante->get($caminho)->ultimoStatus;
    $t->verificar("Acesso direto a {$caminho} é bloqueado", in_array($status, [403, 404], true), "HTTP {$status}");
}

// =====================================================================
$t->grupo('15. Registro em security_logs');

$eventos = $pdo->query("SELECT evento, COUNT(*) AS total FROM security_logs GROUP BY evento")
               ->fetchAll(PDO::FETCH_KEY_PAIR);

$t->verificar('Evento `login_sucesso` registrado', ($eventos[LogModel::LOGIN_SUCESSO] ?? 0) > 0);
$t->verificar('Evento `login_falhou` registrado', ($eventos[LogModel::LOGIN_FALHOU] ?? 0) > 0);
$t->verificar('Evento `acesso_negado_rbac` registrado', ($eventos[LogModel::ACESSO_NEGADO_RBAC] ?? 0) > 0);
$t->verificar('Evento `csrf_token_invalido` registrado', ($eventos[LogModel::CSRF_INVALIDO] ?? 0) > 0);
$t->verificar('Evento `login_conta_bloqueada` registrado', ($eventos[LogModel::CONTA_BLOQUEADA] ?? 0) > 0);

$comSenha = (int)$pdo->query(
    "SELECT COUNT(*) FROM security_logs WHERE detalhes LIKE '%" . SENHA_TESTE . "%'"
)->fetchColumn();
$t->igual('Nenhuma senha em texto plano foi gravada em security_logs', 0, $comSenha);

$logRbac = $pdo->query(
    "SELECT detalhes FROM security_logs WHERE evento = '" . LogModel::ACESSO_NEGADO_RBAC . "' ORDER BY id DESC LIMIT 1"
)->fetchColumn();
$t->verificar('Log de RBAC registra a rota e o perfil da tentativa',
    is_string($logRbac) && strpos($logRbac, 'Rota:') !== false && strpos($logRbac, 'Perfil:') !== false);

// =====================================================================
$t->grupo('16. Sessão e cabeçalhos de segurança');

// Sessão nova: só a PRIMEIRA requisição recebe o cabeçalho Set-Cookie
$primeiroAcesso = new HttpClient($baseUrl);
$primeiroAcesso->get('/login');
$cabecalhos = implode(' | ', $primeiroAcesso->ultimosHeaders);

$t->verificar('Cabeçalho X-Frame-Options presente', stripos($cabecalhos, 'X-Frame-Options') !== false);
$t->verificar('Cabeçalho X-Content-Type-Options presente', stripos($cabecalhos, 'X-Content-Type-Options') !== false);
$t->verificar('Cabeçalho Referrer-Policy presente', stripos($cabecalhos, 'Referrer-Policy') !== false);
$t->verificar('Cookie de sessão é emitido no primeiro acesso', stripos($cabecalhos, 'Set-Cookie: PHPSESSID') !== false);
$t->verificar('Cookie de sessão marcado como HttpOnly', stripos($cabecalhos, 'HttpOnly') !== false);
$t->verificar('Cookie de sessão com SameSite=Lax', stripos($cabecalhos, 'SameSite=Lax') !== false);
$primeiroAcesso->encerrar();

// Session Fixation: o ID muda após o login
$fixacao = new HttpClient($baseUrl);
$fixacao->get('/login');
$idAntes = extrairIdSessao($fixacao->ultimosHeaders);
$csrf = $fixacao->csrfDe('/login');
$fixacao->post('/login', ['csrf_token' => $csrf, 'email' => PREFIXO_TESTE . 'prof@local.test', 'senha' => SENHA_TESTE]);
$idDepois = extrairIdSessao($fixacao->ultimosHeaders);
$t->verificar('ID de sessão é regenerado no login (anti Session Fixation)',
    $idAntes !== null && $idDepois !== null && $idAntes !== $idDepois);

$t->verificar('Usuário logado é desviado de /login (requireGuest)',
    $clienteHttp->get('/login')->ultimoStatus === 302);

// =====================================================================
// Limpeza
// =====================================================================
$http->encerrar();
$visitante->encerrar();
$clienteHttp->encerrar();
$profHttp->encerrar();
$limitadoHttp->encerrar();
$principalHttp->encerrar();
$novo->encerrar();
$xssHttp->encerrar();
$fixacao->encerrar();

limparDadosDeTeste($pdo);
echo "\n(Dados de teste removidos do banco.)\n";

exit($t->resumo());

// =====================================================================
// Funções auxiliares
// =====================================================================

function extrairIdSessao(array $cabecalhos): ?string {
    foreach ($cabecalhos as $linha) {
        if (stripos($linha, 'Set-Cookie:') === 0 && preg_match('/PHPSESSID=([^;]+)/', $linha, $m)) {
            return $m[1];
        }
    }
    return null;
}

function limparDadosDeTeste(PDO $pdo): void {
    $prefixo = PREFIXO_TESTE . '%';

    $pdo->prepare("DELETE FROM agendamentos WHERE codigo_reserva LIKE ?")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM security_logs WHERE detalhes LIKE ?")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM profissionais WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM security_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ? OR email_pendente LIKE ?")->execute([$prefixo, $prefixo]);
}
