<?php
/**
 * tests/teste_seguranca.php - Correções da revisão de segurança
 *
 * Uso: php tests/teste_seguranca.php
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

const PREFIXO_TESTE = 'teste_seg_';
const SENHA_TESTE = 'SenhaTeste@123';
const SENHA_NOVA = 'OutraSenha@456';

echo "\n======================================================\n";
echo " TESTES DE SEGURANÇA - Correções da revisão\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

limpar($pdo);

$usuarios = new UsuarioModel();

$idCliente = $usuarios->criar([
    'nome' => 'Cliente SEG', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 90000-0101', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);

$entrar = function (string $email, string $senha = SENHA_TESTE) use ($baseUrl): HttpClient {
    $c = new HttpClient($baseUrl);
    $csrf = $c->csrfDe('/login');
    $c->post('/login', ['csrf_token' => $csrf, 'email' => $email, 'senha' => $senha]);
    return $c;
};

// =====================================================================
$t->grupo('1. Cabeçalhos de segurança HTTP');

$http = new HttpClient($baseUrl);
$http->get('/login');
$cabecalhos = implode("
", $http->ultimosHeaders);

$t->verificar('Content-Security-Policy é enviada',
    stripos($cabecalhos, 'Content-Security-Policy:') !== false);
$t->verificar('CSP restringe a origem padrão',
    stripos($cabecalhos, "default-src 'self'") !== false);
$t->verificar('CSP proíbe plugins/objetos',
    stripos($cabecalhos, "object-src 'none'") !== false);
$t->verificar('CSP restringe o destino dos formulários',
    stripos($cabecalhos, "form-action 'self'") !== false);
$t->verificar('CSP impede enquadramento por terceiros',
    stripos($cabecalhos, "frame-ancestors 'self'") !== false);

$t->verificar('X-Content-Type-Options continua presente',
    stripos($cabecalhos, 'X-Content-Type-Options: nosniff') !== false);
$t->verificar('X-Frame-Options continua presente',
    stripos($cabecalhos, 'X-Frame-Options: SAMEORIGIN') !== false);
$t->verificar('Referrer-Policy continua presente',
    stripos($cabecalhos, 'Referrer-Policy:') !== false);

// O filtro do X-XSS-Protection não existe mais e já causou falhas próprias
$t->verificar('X-XSS-Protection foi removido',
    stripos($cabecalhos, 'X-XSS-Protection') === false);

// HSTS só faz sentido sob HTTPS; em HTTP local não deve aparecer
$ehHttps = stripos($baseUrl, 'https://') === 0;
$temHsts = stripos($cabecalhos, 'Strict-Transport-Security') !== false;
$t->igual('HSTS acompanha o protocolo em uso', $ehHttps, $temHsts);

// =====================================================================
$t->grupo('2. Cookie de sessão');

$novo = new HttpClient($baseUrl);
$novo->get('/login');
$cabecalhosCookie = implode("
", $novo->ultimosHeaders);

$t->verificar('Cookie de sessão é HttpOnly',
    stripos($cabecalhosCookie, 'HttpOnly') !== false);
$t->verificar('Cookie de sessão declara SameSite',
    stripos($cabecalhosCookie, 'SameSite') !== false);

// Secure sob HTTPS: sem isso o identificador viaja em texto claro
$temSecure = preg_match('/Set-Cookie:[^\r\n]*secure/i', $cabecalhosCookie) === 1;
$t->igual('Flag Secure acompanha o protocolo em uso', $ehHttps, $temSecure);

$t->verificar('SessionHelper reconhece o protocolo configurado',
    SessionHelper::ehHttps() === $ehHttps);

// =====================================================================
$t->grupo('3. Troca de senha invalida sessões antigas');

$sessao = $entrar(PREFIXO_TESTE . 'cliente@local.test');
$t->igual('Sessão inicial está autenticada', 200, $sessao->get('/perfil')->ultimoStatus);

// A troca ocorre por fora (outro dispositivo, ou redefinição por e-mail)
sleep(1); // garante que senha_alterada_em > logado_em
$usuarios->atualizarSenha($idCliente, SENHA_NOVA);

$t->igual('Sessão anterior deixa de valer', 302, $sessao->get('/perfil')->ultimoStatus);
$t->igual('Sessão anterior é mandada ao login', '/login', $sessao->destinoRedirecionamento());

$t->verificar('Marca de troca foi gravada',
    $pdo->query("SELECT senha_alterada_em FROM usuarios WHERE id = {$idCliente}")->fetchColumn() !== null);

$t->igual('Senha antiga não entra mais', 200,
    $entrar(PREFIXO_TESTE . 'cliente@local.test', SENHA_TESTE)->get('/login')->ultimoStatus);

$nova = $entrar(PREFIXO_TESTE . 'cliente@local.test', SENHA_NOVA);
$t->igual('Senha nova entra normalmente', 200, $nova->get('/perfil')->ultimoStatus);

// =====================================================================
$t->grupo('4. Limite de pedidos de recuperação de senha');

$pdo->exec("DELETE FROM security_logs WHERE evento LIKE 'recuperacao_senha%'");

$alvo = PREFIXO_TESTE . 'cliente@local.test';

for ($i = 1; $i <= LogModel::MAX_RECUPERACOES; $i++) {
    $c = new HttpClient($baseUrl);
    $c->post('/recuperar-senha', ['csrf_token' => $c->csrfDe('/recuperar-senha'), 'email' => $alvo]);
    $c->encerrar();
}

$registrados = (int)$pdo->query(
    "SELECT COUNT(*) FROM security_logs WHERE evento = '" . LogModel::RECUPERACAO_PEDIDA . "'"
)->fetchColumn();

$t->igual('Pedidos dentro do limite são processados', LogModel::MAX_RECUPERACOES, $registrados);

// O próximo tem de ser barrado
$c = new HttpClient($baseUrl);
$c->post('/recuperar-senha', ['csrf_token' => $c->csrfDe('/recuperar-senha'), 'email' => $alvo]);

$barrados = (int)$pdo->query(
    "SELECT COUNT(*) FROM security_logs WHERE evento = '" . LogModel::RECUPERACAO_BARRADA . "'"
)->fetchColumn();

$t->igual('Pedido acima do limite é barrado', 1, $barrados);
$t->igual('Pedidos processados não aumentam após o bloqueio', LogModel::MAX_RECUPERACOES,
    (int)$pdo->query("SELECT COUNT(*) FROM security_logs WHERE evento = '" . LogModel::RECUPERACAO_PEDIDA . "'")->fetchColumn());

// A resposta não pode denunciar o bloqueio a quem está abusando
$c->get('/login');
$t->verificar('Resposta do bloqueio é indistinguível da normal',
    $c->corpoContem('Se houver uma conta com este e-mail'));
$t->verificar('Resposta não menciona bloqueio nem limite',
    !$c->corpoContem('bloquead') && !$c->corpoContem('limite'));
$c->encerrar();

// Trocar de e-mail não contorna: o limite é por IP
$outro = new HttpClient($baseUrl);
$outro->post('/recuperar-senha', [
    'csrf_token' => $outro->csrfDe('/recuperar-senha'),
    'email' => PREFIXO_TESTE . 'inexistente@local.test',
]);
$t->igual('Mudar o e-mail alvo não contorna o limite', 2,
    (int)$pdo->query("SELECT COUNT(*) FROM security_logs WHERE evento = '" . LogModel::RECUPERACAO_BARRADA . "'")->fetchColumn());
$outro->encerrar();

// =====================================================================
$t->grupo('5. Upload: pasta de destino');

$arquivo = sys_get_temp_dir() . '/teste_seg_upload.png';
// PNG mínimo válido (1x1), para passar pela checagem real de MIME
file_put_contents($arquivo, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
));

$montarEnvio = function (string $destino) use ($arquivo): array {
    $copia = sys_get_temp_dir() . '/teste_seg_copia.png';
    copy($arquivo, $copia);
    return [
        'name' => 'imagem.png', 'type' => 'image/png', 'tmp_name' => $copia,
        'error' => UPLOAD_ERR_OK, 'size' => filesize($copia),
    ];
};

// Só o retorno null NÃO prova rejeição: fora de uma requisição HTTP real o
// move_uploaded_file() falha de qualquer jeito, e process() devolveria null
// mesmo que a validação tivesse deixado passar. A prova é que nenhuma pasta
// chegue a ser criada — foi assim que se descobriu que "/etc" era normalizado
// para "etc" e criava o diretório antes de falhar.
$raizUploads = __DIR__ . '/../public/uploads';

$pastasDe = function () use ($raizUploads): array {
    $itens = glob($raizUploads . '/*', GLOB_ONLYDIR) ?: [];
    return array_map('basename', $itens);
};

$pastasAntes = $pastasDe();

foreach (['../', '../../app', 'promocoes/../../app', '..', '/etc', 'etc/', 'pasta com espaco', ''] as $malicioso) {
    $retorno = UploadHelper::process($montarEnvio($malicioso), $malicioso);

    $t->igual("Pasta '{$malicioso}': process() devolve null", null, $retorno);
    $t->igual("Pasta '{$malicioso}': nenhum diretório é criado",
        $pastasAntes, $pastasDe());
}

$t->verificar('Extensão não permitida é recusada',
    UploadHelper::process(array_merge($montarEnvio('promocoes'), ['name' => 'script.php']), 'promocoes') === null);

@unlink($arquivo);
@unlink(sys_get_temp_dir() . '/teste_seg_copia.png');

// =====================================================================
$t->grupo('6. Proteção dos diretórios internos');

$visitante = new HttpClient($baseUrl);

$bloqueados = [
    '/config/config.php', '/app/models/UsuarioModel.php', '/app/helpers/CriptografiaHelper.php',
    '/app/controllers/AuthController.php', '/app/views/layouts/header.php',
    '/database/migrate.php', '/tests/teste_seguranca.php', '/docs/FSD.md',
    '/logs/error.log', '/vendor/PHPMailer/PHPMailer.php',
];

foreach ($bloqueados as $caminho) {
    $status = $visitante->get($caminho)->ultimoStatus;
    $t->verificar("Acesso direto a {$caminho} é negado",
        in_array($status, [403, 404], true), "status {$status}");
}

$t->verificar('Nenhuma credencial vaza pelo config',
    !$visitante->get('/config/config.php')->corpoContem('chave_cripto'));

$visitante->encerrar();

// =====================================================================
$t->grupo('7. Expiração de sessão por inatividade');

$t->verificar('Limite de inatividade está definido',
    SessionHelper::INATIVIDADE_MAXIMA > 0);
$t->verificar('Limite de inatividade é razoável (até 24h)',
    SessionHelper::INATIVIDADE_MAXIMA <= 86400);

// =====================================================================
$sessao->encerrar();
$nova->encerrar();
$http->encerrar();
$novo->encerrar();

limpar($pdo);
echo "\n(Dados de teste removidos.)\n";

exit($t->resumo());

// =====================================================================

function limpar(PDO $pdo): void {
    $prefixo = PREFIXO_TESTE . '%';

    $pdo->exec("DELETE FROM security_logs WHERE evento LIKE 'recuperacao_senha%'");
    $pdo->prepare("DELETE FROM security_logs WHERE detalhes LIKE ?")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM security_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM audit_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?")->execute([$prefixo]);
}
