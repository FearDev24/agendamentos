<?php
/**
 * tests/teste_integracoes.php - Validação da vinculação de conta de pagamentos
 *
 * Uso: php tests/teste_integracoes.php
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

const PREFIXO_TESTE = 'teste_integ_';
const SENHA_TESTE = 'SenhaTeste@123';

echo "\n======================================================\n";
echo " TESTES DE INTEGRAÇÕES - Vinculação de conta de pagamentos\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

/** Guarda o estado real para restaurar ao fim */
$estadoOriginal = $pdo->query(
    "SELECT chave, valor FROM system_settings WHERE chave LIKE 'mp_%'"
)->fetchAll(PDO::FETCH_KEY_PAIR);

limparIntegracao($pdo);
limparDadosDeTeste($pdo);

$usuarios = new UsuarioModel();
$integracoes = new IntegracaoModel();

$idPrincipal = $usuarios->criar([
    'nome' => 'Principal INT', 'email' => PREFIXO_TESTE . 'principal@local.test',
    'telefone' => '(11) 90000-0001', 'senha' => SENHA_TESTE, 'perfil' => 'admin_principal',
]);
$idLimitado = $usuarios->criar([
    'nome' => 'Limitado INT', 'email' => PREFIXO_TESTE . 'limitado@local.test',
    'telefone' => '(11) 90000-0002', 'senha' => SENHA_TESTE, 'perfil' => 'admin_limitado',
]);
$idCliente = $usuarios->criar([
    'nome' => 'Cliente INT', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 90000-0003', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);

$entrar = function (string $email) use ($baseUrl): HttpClient {
    $c = new HttpClient($baseUrl);
    $csrf = $c->csrfDe('/login');
    $c->post('/login', ['csrf_token' => $csrf, 'email' => $email, 'senha' => SENHA_TESTE]);
    return $c;
};

const TOKEN_TESTE = 'TEST-1234567890123456-010101-abcdefabcdefabcdefabcdefabcdef12-123456789';
const CHAVE_TESTE = 'TEST-12345678-1234-1234-1234-123456789012';
const TOKEN_PROD  = 'APP_USR-1234567890123456-010101-abcdefabcdefabcdefabcdefabcdef12-123456789';
const CHAVE_PROD  = 'APP_USR-12345678-1234-1234-1234-123456789012';

// =====================================================================
$t->grupo('1. Criptografia das credenciais');

$t->verificar('CriptografiaHelper disponível', class_exists('CriptografiaHelper'));
$t->verificar('Chave de criptografia configurada', CriptografiaHelper::chaveConfigurada());

$original = TOKEN_PROD;
$cifrado = CriptografiaHelper::cifrar($original);

$t->verificar('Texto cifrado difere do original', $cifrado !== $original);
$t->verificar('Cifrado é identificável pelo prefixo de versão', strpos($cifrado, 'enc:v1:') === 0);
$t->verificar('Token original não aparece no texto cifrado', strpos($cifrado, $original) === false);
$t->igual('Decifragem devolve o original', $original, CriptografiaHelper::decifrar($cifrado));

// Cada cifragem usa IV novo: o mesmo texto nunca gera a mesma saída
$t->verificar('Cifragens sucessivas produzem saídas diferentes',
    CriptografiaHelper::cifrar($original) !== CriptografiaHelper::cifrar($original));

// AES-GCM autentica: adulterar o conteúdo é detectado
$t->igual('Conteúdo adulterado é rejeitado', '',
    CriptografiaHelper::decifrar(substr($cifrado, 0, -6) . 'XXXXXX'));

$t->igual('Texto vazio permanece vazio', '', CriptografiaHelper::cifrar(''));
$t->igual('Valor sem prefixo é devolvido como está (migração)',
    'texto-puro-antigo', CriptografiaHelper::decifrar('texto-puro-antigo'));

// Mascaramento
$t->igual('Mascaramento mostra apenas o final', '••••••6789', CriptografiaHelper::mascarar('APP_USR-123456789'));
$t->igual('Segredo curto é totalmente ocultado', '••••••', CriptografiaHelper::mascarar('abc'));
$t->igual('Vazio não vira máscara', '', CriptografiaHelper::mascarar(''));

$t->verificar('Gerador produz chave de 32 bytes',
    strlen(base64_decode(CriptografiaHelper::gerarChave(), true)) === 32);
$t->verificar('Chaves geradas são distintas',
    CriptografiaHelper::gerarChave() !== CriptografiaHelper::gerarChave());

// =====================================================================
$t->grupo('2. Armazenamento das credenciais');

// Sem nada cadastrado, vale o arquivo de configuração
$atual = $integracoes->mercadoPago();
$t->igual('Sem cadastro, a origem é o arquivo', 'arquivo', $atual['origem']);

$integracoes->salvarMercadoPago([
    'public_key' => CHAVE_TESTE, 'access_token' => TOKEN_TESTE,
    'webhook_secret' => 'segredo-do-webhook', 'sandbox_mode' => true,
]);

$atual = $integracoes->mercadoPago();
$t->igual('Após cadastrar, a origem passa a ser o painel', 'painel', $atual['origem']);
$t->igual('Access Token recuperado corretamente', TOKEN_TESTE, $atual['access_token']);
$t->igual('Public Key recuperada corretamente', CHAVE_TESTE, $atual['public_key']);
$t->igual('Segredo do webhook recuperado', 'segredo-do-webhook', $atual['webhook_secret']);
$t->verificar('Modo simulação preservado', $atual['sandbox_mode']);

// O que está no banco precisa estar cifrado
$noBanco = $pdo->query("SELECT valor FROM system_settings WHERE chave = 'mp_access_token'")->fetchColumn();
$t->verificar('Access Token está cifrado no banco', CriptografiaHelper::estaCifrado((string)$noBanco));
$t->verificar('Token em texto puro NÃO aparece no banco', strpos((string)$noBanco, TOKEN_TESTE) === false);

$segredoNoBanco = $pdo->query("SELECT valor FROM system_settings WHERE chave = 'mp_webhook_secret'")->fetchColumn();
$t->verificar('Segredo do webhook está cifrado no banco',
    CriptografiaHelper::estaCifrado((string)$segredoNoBanco));

// A Public Key não é secreta e fica legível
$chaveNoBanco = $pdo->query("SELECT valor FROM system_settings WHERE chave = 'mp_public_key'")->fetchColumn();
$t->igual('Public Key fica legível (não é secreta)', CHAVE_TESTE, $chaveNoBanco);

// Campo em branco preserva o segredo já gravado
$integracoes->salvarMercadoPago([
    'public_key' => CHAVE_TESTE, 'access_token' => '', 'webhook_secret' => '', 'sandbox_mode' => true,
]);
$t->igual('Access Token em branco mantém o valor anterior', TOKEN_TESTE,
    $integracoes->mercadoPago()['access_token']);
$t->igual('Segredo em branco mantém o valor anterior', 'segredo-do-webhook',
    $integracoes->mercadoPago()['webhook_secret']);

// Detecção de ambiente pelo prefixo
$t->igual('Token TEST- é reconhecido como teste', 'teste', $integracoes->detectarAmbiente(TOKEN_TESTE));
$t->igual('Token APP_USR- é reconhecido como produção', 'producao', $integracoes->detectarAmbiente(TOKEN_PROD));
$t->igual('Token sem prefixo conhecido', 'desconhecido', $integracoes->detectarAmbiente('qualquer-coisa'));
$t->igual('Sem token', 'nenhum', $integracoes->detectarAmbiente(''));

// Exibição nunca devolve o segredo completo
$exibicao = $integracoes->paraExibicao();
$t->verificar('Exibição não traz o Access Token completo',
    strpos(json_encode($exibicao), TOKEN_TESTE) === false);
$t->verificar('Exibição traz o token mascarado', strpos($exibicao['access_token_mascara'], '••••') === 0);
$t->verificar('Exibição sinaliza que há token cadastrado', $exibicao['tem_access_token']);

// Desvincular volta ao arquivo
$integracoes->desvincularMercadoPago();
$t->igual('Desvincular devolve a origem ao arquivo', 'arquivo', $integracoes->mercadoPago()['origem']);
$t->igual('Nenhuma credencial permanece no banco', 0,
    (int)$pdo->query("SELECT COUNT(*) FROM system_settings WHERE chave LIKE 'mp_%'")->fetchColumn());

// =====================================================================
$t->grupo('3. Resolução pelo GatewayFactory');

GatewayFactory::limparCache();
$t->igual('Sem cadastro, a fábrica usa o arquivo', 'arquivo', GatewayFactory::credenciais()['origem']);

$integracoes->salvarMercadoPago([
    'public_key' => CHAVE_TESTE, 'access_token' => TOKEN_TESTE,
    'webhook_secret' => 'seg', 'sandbox_mode' => true,
]);
GatewayFactory::limparCache();

$t->igual('Fábrica passa a usar o painel', 'painel', GatewayFactory::credenciais()['origem']);
$t->igual('Chave pública vem do painel', CHAVE_TESTE, GatewayFactory::chavePublica());
$t->verificar('Modo simulação leva ao SandboxGateway', GatewayFactory::obter() instanceof SandboxGateway);
$t->verificar('Sistema reconhece a integração como configurada', GatewayFactory::configurado());

// Produção leva ao gateway real
$integracoes->salvarMercadoPago([
    'public_key' => CHAVE_PROD, 'access_token' => TOKEN_PROD,
    'webhook_secret' => 'seg', 'sandbox_mode' => false,
]);
GatewayFactory::limparCache();

$t->verificar('Modo produção leva ao MercadoPagoGateway',
    GatewayFactory::obter() instanceof MercadoPagoGateway);
$t->verificar('emSandbox() reflete o modo do painel', !GatewayFactory::emSandbox());

// O gateway real recebe as credenciais do painel
$gatewayReal = new MercadoPagoGateway(GatewayFactory::credenciais());
$reflexao = new ReflectionClass($gatewayReal);
$prop = $reflexao->getProperty('accessToken');
$prop->setAccessible(true);
$t->igual('Gateway recebe o token cadastrado no painel', TOKEN_PROD, $prop->getValue($gatewayReal));

// Assinatura do webhook usa o segredo do painel
$propSegredo = $reflexao->getProperty('webhookSecret');
$propSegredo->setAccessible(true);
$t->igual('Gateway recebe o segredo do webhook do painel', 'seg', $propSegredo->getValue($gatewayReal));

// Volta ao modo simulação para não afetar as demais baterias
$integracoes->desvincularMercadoPago();
GatewayFactory::limparCache();

// =====================================================================
$t->grupo('4. HTTP: acesso e permissões');

$principalHttp = $entrar(PREFIXO_TESTE . 'principal@local.test');
$limitadoHttp  = $entrar(PREFIXO_TESTE . 'limitado@local.test');
$clienteHttp   = $entrar(PREFIXO_TESTE . 'cliente@local.test');
$visitante     = new HttpClient($baseUrl);

$principalHttp->get('/admin/integracoes');
$t->igual('Admin Principal acessa a tela', 200, $principalHttp->ultimoStatus);
$t->verificar('Tela renderiza sem erro de PHP',
    $principalHttp->erroPhp() === null, (string)$principalHttp->erroPhp());

$t->igual('Admin Limitado é bloqueado (403)', 403, $limitadoHttp->get('/admin/integracoes')->ultimoStatus);
$t->igual('Cliente é bloqueado (403)', 403, $clienteHttp->get('/admin/integracoes')->ultimoStatus);
$t->igual('Visitante é redirecionado', 302, $visitante->get('/admin/integracoes')->ultimoStatus);

$t->verificar('Menu do Admin Limitado não exibe Integrações',
    !$limitadoHttp->get('/admin/dashboard')->corpoContem('/admin/integracoes'));

foreach (['/admin/integracoes/mercadopago', '/admin/integracoes/testar', '/admin/integracoes/desvincular'] as $rota) {
    $t->igual("POST {$rota} sem CSRF é rejeitado", 403,
        $principalHttp->post($rota, [])->ultimoStatus);

    $csrfLimitado = $limitadoHttp->csrfDe('/admin/dashboard');
    $t->igual("Admin Limitado é bloqueado em {$rota}", 403,
        $limitadoHttp->post($rota, ['csrf_token' => $csrfLimitado])->ultimoStatus);
}

// =====================================================================
$t->grupo('5. HTTP: cadastro pela interface');

$principalHttp->get('/admin/integracoes');
$t->verificar('Tela mostra a URL do webhook para copiar', $principalHttp->corpoContem('/webhook/mercadopago'));
$t->verificar('Tela explica como obter as credenciais',
    $principalHttp->corpoContem('developers.mercadopago.com'));
$t->verificar('Tela oferece escolha entre simulação e produção',
    $principalHttp->corpoContem('modo_sandbox') && $principalHttp->corpoContem('modo_producao'));
$t->verificar('Campo de token é do tipo senha',
    preg_match('/id="access_token"[^>]*type="password"|type="password"[^>]*id="access_token"/', $principalHttp->ultimoCorpo) === 1
    || strpos($principalHttp->ultimoCorpo, 'type="password" class="campo-form" id="access_token"') !== false);

$csrf = $principalHttp->csrfDe('/admin/integracoes');
$principalHttp->post('/admin/integracoes/mercadopago', [
    'csrf_token' => $csrf, 'public_key' => CHAVE_TESTE, 'access_token' => TOKEN_TESTE,
    'webhook_secret' => 'segredo-http', 'modo' => 'sandbox',
]);

$salvo = $integracoes->mercadoPago();
$t->igual('Credenciais salvas pela interface', TOKEN_TESTE, $salvo['access_token']);
$t->igual('Origem passa a ser o painel', 'painel', $salvo['origem']);

$principalHttp->get('/admin/integracoes');
$t->verificar('Tela confirma credencial de teste', $principalHttp->corpoContem('Credencial de teste'));
$t->verificar('Token completo NUNCA aparece no HTML',
    !$principalHttp->corpoContem(TOKEN_TESTE));
$t->verificar('Segredo do webhook NUNCA aparece no HTML',
    !$principalHttp->corpoContem('segredo-http'));
$t->verificar('Tela mostra o token mascarado', $principalHttp->corpoContem('••••'));

// Validações
$casos = [
    'Public Key vazia'            => ['public_key' => '', 'access_token' => TOKEN_TESTE],
    'Public Key com prefixo inválido' => ['public_key' => 'CHAVE-QUALQUER', 'access_token' => TOKEN_TESTE],
    'Access Token com prefixo inválido' => ['public_key' => CHAVE_TESTE, 'access_token' => 'TOKEN-QUALQUER'],
    'ambientes misturados'        => ['public_key' => CHAVE_TESTE, 'access_token' => TOKEN_PROD],
];

foreach ($casos as $descricao => $campos) {
    $antes = $integracoes->mercadoPago();

    $csrf = $principalHttp->csrfDe('/admin/integracoes');
    $principalHttp->post('/admin/integracoes/mercadopago', array_merge(
        ['csrf_token' => $csrf, 'webhook_secret' => '', 'modo' => 'sandbox'], $campos
    ));

    $depois = $integracoes->mercadoPago();
    $t->igual("Rejeita {$descricao}", $antes['public_key'], $depois['public_key']);
}

// Produção exige o segredo do webhook
$integracoes->desvincularMercadoPago();
$csrf = $principalHttp->csrfDe('/admin/integracoes');
$principalHttp->post('/admin/integracoes/mercadopago', [
    'csrf_token' => $csrf, 'public_key' => CHAVE_PROD, 'access_token' => TOKEN_PROD,
    'webhook_secret' => '', 'modo' => 'producao',
]);
$t->igual('Produção sem segredo do webhook é recusada', 'arquivo',
    $integracoes->mercadoPago()['origem']);

$principalHttp->get('/admin/integracoes');
$t->verificar('Motivo da recusa é explicado ao usuário',
    $principalHttp->corpoContem('segredo do webhook é obrigatório'));

// Produção com segredo é aceita, e o sistema avisa sobre a credencial de teste
$csrf = $principalHttp->csrfDe('/admin/integracoes');
$principalHttp->post('/admin/integracoes/mercadopago', [
    'csrf_token' => $csrf, 'public_key' => CHAVE_TESTE, 'access_token' => TOKEN_TESTE,
    'webhook_secret' => 'seg', 'modo' => 'producao',
]);
$principalHttp->get('/admin/integracoes');
$t->verificar('Sistema avisa: modo produção com credencial de teste',
    $principalHttp->corpoContem('a credencial é de teste'));

// Desvincular pela interface
$csrf = $principalHttp->csrfDe('/admin/integracoes');
$principalHttp->post('/admin/integracoes/desvincular', ['csrf_token' => $csrf]);
$t->igual('Desvinculação pela interface funciona', 'arquivo', $integracoes->mercadoPago()['origem']);

// =====================================================================
$t->grupo('6. Auditoria e segurança');

$auditados = $pdo->query(
    "SELECT dados_novos FROM audit_logs WHERE entidade = 'integracoes' ORDER BY id DESC LIMIT 5"
)->fetchAll(PDO::FETCH_COLUMN);

$t->verificar('Alterações de integração são auditadas', count($auditados) > 0);

$tudo = implode(' ', $auditados);
$t->verificar('Auditoria NÃO grava o Access Token', strpos($tudo, TOKEN_TESTE) === false);
$t->verificar('Auditoria NÃO grava o segredo do webhook', strpos($tudo, 'segredo-http') === false);
$t->verificar('Auditoria registra o ambiente da credencial', strpos($tudo, 'ambiente') !== false);

// O token não pode vazar por nenhuma tela
$integracoes->salvarMercadoPago([
    'public_key' => CHAVE_TESTE, 'access_token' => TOKEN_TESTE,
    'webhook_secret' => 'seg', 'sandbox_mode' => true,
]);
GatewayFactory::limparCache();

foreach (['/admin/integracoes', '/admin/dashboard', '/admin/financeiro', '/admin/auditoria'] as $tela) {
    $principalHttp->get($tela);
    $t->verificar("Access Token não vaza em {$tela}", !$principalHttp->corpoContem(TOKEN_TESTE));
}

// A chave pública PODE aparecer: o navegador precisa dela para tokenizar
$t->verificar('Public Key é entregue ao navegador quando há checkout',
    GatewayFactory::chavePublica() === CHAVE_TESTE);

// =====================================================================
// Restaura o estado anterior do ambiente
// =====================================================================
$principalHttp->encerrar();
$limitadoHttp->encerrar();
$clienteHttp->encerrar();
$visitante->encerrar();

limparIntegracao($pdo);

if (!empty($estadoOriginal)) {
    $stmt = $pdo->prepare('INSERT INTO system_settings (chave, valor) VALUES (?, ?)');
    foreach ($estadoOriginal as $chave => $valor) {
        $stmt->execute([$chave, $valor]);
    }
}

GatewayFactory::limparCache();
SettingModel::limparCache();
limparDadosDeTeste($pdo);

echo "\n(Dados de teste removidos e configuração original restaurada.)\n";

exit($t->resumo());

// =====================================================================

function limparIntegracao(PDO $pdo): void {
    $pdo->exec("DELETE FROM system_settings WHERE chave LIKE 'mp_%'");
}

function limparDadosDeTeste(PDO $pdo): void {
    $prefixo = PREFIXO_TESTE . '%';

    $pdo->prepare("DELETE FROM audit_logs WHERE entidade = 'integracoes'")->execute();
    $pdo->prepare("DELETE FROM security_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM audit_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?")->execute([$prefixo]);
}
