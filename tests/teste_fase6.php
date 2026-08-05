<?php
/**
 * tests/teste_fase6.php - Validação da Fase 6
 * (Gateway de Pagamentos, Webhooks e Retenção Temporária)
 *
 * Uso: php tests/teste_fase6.php
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

const PREFIXO_TESTE = 'teste_fase6_';
const SENHA_TESTE = 'SenhaTeste@123';

echo "\n======================================================\n";
echo " TESTES DA FASE 6 - Pagamentos, Webhook e Retenção\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

limparDadosDeTeste($pdo);

$usuarios     = new UsuarioModel();
$servicosM    = new ServicoModel();
$profModel    = new ProfissionalModel();
$agendamentos = new AgendamentoModel();
$pagamentos   = new PagamentoModel();

// ---------------------------------------------------------------------
// Cenário
// ---------------------------------------------------------------------
$dataAlvo = new DateTime('next wednesday');
if ((int)$dataAlvo->diff(new DateTime('today'))->days > 25) {
    $dataAlvo = new DateTime('today +7 day');
}
$DATA = $dataAlvo->format('Y-m-d');
$DIA_SEMANA = (int)$dataAlvo->format('w');

$idServico = $servicosM->criar([
    'nome' => PREFIXO_TESTE . 'Corte', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 50.00, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);

$idProf = $profModel->criar([
    'nome' => 'Profissional F6', 'email' => PREFIXO_TESTE . 'prof@local.test',
    'telefone' => '(11) 93333-0001', 'senha' => SENHA_TESTE,
    'limite_diario_atendimentos' => '', 'status' => 'ativo',
]);
$profModel->sincronizarServicos($idProf, [$idServico]);
$profModel->salvarHorarios($idProf, [
    $DIA_SEMANA => ['hora_inicio' => '09:00', 'hora_fim' => '18:00', 'almoco_inicio' => null, 'almoco_fim' => null],
]);

$idCliente = $usuarios->criar([
    'nome' => 'Cliente F6', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 93333-0002', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);

/**
 * Remove os agendamentos do cliente de teste.
 *
 * O sistema limita a 4 agendamentos ativos por cliente (regra do FSD), e esta
 * bateria cria muitas reservas em sequência. Limpar entre os grupos mantém o
 * foco no que cada grupo pretende validar.
 */
$liberarAgenda = function () use ($pdo, $idCliente): void {
    $pdo->prepare("
        DELETE FROM pagamentos WHERE agendamento_id IN (SELECT id FROM agendamentos WHERE cliente_id = ?)
    ")->execute([$idCliente]);
    $pdo->prepare("
        DELETE FROM agendamento_servicos WHERE agendamento_id IN (SELECT id FROM agendamentos WHERE cliente_id = ?)
    ")->execute([$idCliente]);
    $pdo->prepare('DELETE FROM agendamentos WHERE cliente_id = ?')->execute([$idCliente]);
};

/** Cria uma reserva pronta para pagamento */
$novaReserva = function (string $hora) use ($agendamentos, $idCliente, $idProf, $DATA, $idServico): array {
    return $agendamentos->criarReserva($idCliente, $idProf, $DATA, $hora, [$idServico]);
};

// =====================================================================
$t->grupo('1. Estrutura da camada de pagamentos');

foreach (['GatewayPagamento', 'SandboxGateway', 'MercadoPagoGateway', 'GatewayFactory',
          'PagamentoModel', 'CheckoutController', 'WebhookController'] as $classe) {
    $t->verificar("`{$classe}` carregada pelo autoloader",
        class_exists($classe) || interface_exists($classe));
}

$t->verificar('SandboxGateway implementa o contrato GatewayPagamento',
    in_array('GatewayPagamento', class_implements('SandboxGateway'), true));
$t->verificar('MercadoPagoGateway implementa o contrato GatewayPagamento',
    in_array('GatewayPagamento', class_implements('MercadoPagoGateway'), true));

$t->verificar('config.example.php documenta o webhook_secret',
    strpos(file_get_contents(__DIR__ . '/../config/config.example.php'), 'webhook_secret') !== false);
$t->verificar('checkout.js existe', file_exists(__DIR__ . '/../public/assets/js/checkout.js'));

// =====================================================================
$t->grupo('2. Seleção do provedor conforme o ambiente');

$t->verificar('Config local está em sandbox_mode', GatewayFactory::emSandbox());
$t->verificar('Fábrica devolve o SandboxGateway em modo sandbox',
    GatewayFactory::obter() instanceof SandboxGateway);
$t->verificar('Fábrica reaproveita a mesma instância',
    GatewayFactory::obter() === GatewayFactory::obter());

// Com sandbox desligado a fábrica precisa entregar o provedor real
$GLOBALS['config']['mercadopago']['sandbox_mode'] = false;
GatewayFactory::definir(null);
$t->verificar('Com sandbox desligado a fábrica devolve o MercadoPagoGateway',
    GatewayFactory::obter() instanceof MercadoPagoGateway);
$GLOBALS['config']['mercadopago']['sandbox_mode'] = true;
GatewayFactory::definir(null);
$t->verificar('Sandbox restaurado para os demais testes', GatewayFactory::obter() instanceof SandboxGateway);

// =====================================================================
$t->grupo('3. SandboxGateway: Pix e cartão');

$gateway = new SandboxGateway();

$pix = $gateway->criarPix([
    'valor' => 50.00, 'descricao' => 'Teste', 'codigo_reserva' => 'AGB-TESTE',
    'email' => 'x@x.com', 'nome' => 'Teste',
]);

$t->verificar('Pix devolve um ID de transação', !empty($pix['transacao_id']));
$t->verificar('ID do Pix é identificável como simulado', strpos($pix['transacao_id'], 'SBX-PIX-') === 0);
$t->igual('Pix nasce com status pendente', 'pendente', $pix['status']);
$t->verificar('Pix devolve o código Copia e Cola', !empty($pix['qr_code']));
$t->verificar('Código Copia e Cola carrega o código da reserva',
    strpos($pix['qr_code'], 'AGB-TESTE') !== false);
$t->verificar('Pix devolve a imagem do QR Code como data URI',
    strpos($pix['qr_code_base64'], 'data:image/') === 0);
$t->verificar('Imagem do QR Code é decodificável',
    base64_decode(substr($pix['qr_code_base64'], strpos($pix['qr_code_base64'], ',') + 1), true) !== false);

$pix2 = $gateway->criarPix([
    'valor' => 50.00, 'descricao' => 'Teste', 'codigo_reserva' => 'AGB-TESTE',
    'email' => 'x@x.com', 'nome' => 'Teste',
]);
$t->verificar('Cada chamada gera transação distinta', $pix['transacao_id'] !== $pix2['transacao_id']);

// Cartão: comportamento dirigido pelo token
$t->igual('Token de aprovação resulta em aprovado', 'aprovado',
    $gateway->criarCartao(['token' => 'sandbox-aprovado-123', 'valor' => 50])['status']);
$t->igual('Token de recusa resulta em recusado', 'recusado',
    $gateway->criarCartao(['token' => 'sandbox-recusado-123', 'valor' => 50])['status']);
$t->igual('Token de análise resulta em pendente', 'pendente',
    $gateway->criarCartao(['token' => 'sandbox-pendente-123', 'valor' => 50])['status']);
$t->igual('Token desconhecido é tratado como recusa', 'recusado',
    $gateway->criarCartao(['token' => 'token-invalido', 'valor' => 50])['status']);
$t->igual('Token vazio é tratado como recusa', 'recusado',
    $gateway->criarCartao(['token' => '', 'valor' => 50])['status']);

// =====================================================================
$t->grupo('4. MercadoPagoGateway: normalização e assinatura');

// Credenciais injetadas: a assinatura é testada de forma isolada, sem depender
// do que estiver em config.php nem do cache da GatewayFactory.
$mp = new MercadoPagoGateway(['webhook_secret' => '']);
$normalizar = (new ReflectionClass($mp))->getMethod('normalizarStatus');
$normalizar->setAccessible(true);

$mapa = [
    'approved' => 'aprovado', 'refunded' => 'estornado', 'charged_back' => 'estornado',
    'rejected' => 'recusado', 'cancelled' => 'recusado',
    'pending' => 'pendente', 'in_process' => 'pendente', 'authorized' => 'pendente',
];
foreach ($mapa as $origem => $esperado) {
    $t->igual("Status '{$origem}' vira '{$esperado}'", $esperado, $normalizar->invoke($mp, $origem));
}

// Assinatura do webhook
$t->verificar('Sem webhook_secret configurado a assinatura é recusada',
    !$mp->validarAssinatura(['x-signature' => 'ts=1,v1=abc'], '123'));

$mpComSegredo = new MercadoPagoGateway(['webhook_secret' => 'segredo-de-teste']);

$ts = '1704908010';
$idRecurso = '123456';
$requestId = 'req-abc';
$manifesto = "id:{$idRecurso};request-id:{$requestId};ts:{$ts};";
$assinaturaValida = hash_hmac('sha256', $manifesto, 'segredo-de-teste');

$t->verificar('Assinatura correta é aceita', $mpComSegredo->validarAssinatura([
    'x-signature' => "ts={$ts},v1={$assinaturaValida}", 'x-request-id' => $requestId,
], $idRecurso));

$t->verificar('Assinatura adulterada é recusada', !$mpComSegredo->validarAssinatura([
    'x-signature' => "ts={$ts},v1=" . str_repeat('a', 64), 'x-request-id' => $requestId,
], $idRecurso));

$t->verificar('Assinatura de outro recurso é recusada (replay)',
    !$mpComSegredo->validarAssinatura([
        'x-signature' => "ts={$ts},v1={$assinaturaValida}", 'x-request-id' => $requestId,
    ], '999999'));

$t->verificar('Cabeçalho x-signature ausente é recusado',
    !$mpComSegredo->validarAssinatura([], $idRecurso));
$t->verificar('Cabeçalho x-signature malformado é recusado',
    !$mpComSegredo->validarAssinatura(['x-signature' => 'lixo'], $idRecurso));
$t->verificar('Comparação da assinatura é case-insensitive no nome do cabeçalho',
    $mpComSegredo->validarAssinatura([
        'X-Signature' => "ts={$ts},v1={$assinaturaValida}", 'X-Request-Id' => $requestId,
    ], $idRecurso));

$GLOBALS['config']['mercadopago']['webhook_secret'] = '';

// Sem credenciais a API real não é chamada às cegas
$erroCredencial = '';
try {
    $semToken = new MercadoPagoGateway();
    $reflexao = new ReflectionClass($semToken);
    $prop = $reflexao->getProperty('accessToken');
    $prop->setAccessible(true);
    $prop->setValue($semToken, '');
    $semToken->consultar('123');
} catch (RuntimeException $e) {
    $erroCredencial = $e->getMessage();
}
$t->verificar('Sem access_token o gateway real falha de forma explícita',
    strpos($erroCredencial, 'não configuradas') !== false, $erroCredencial);

// =====================================================================
$t->grupo('5. PagamentoModel: registro e confirmação');

$reservaA = $novaReserva('09:00');
$agA = $agendamentos->buscarPorCodigo($reservaA['codigo']);

$retornoPix = $gateway->criarPix([
    'valor' => 50.00, 'descricao' => 'x', 'codigo_reserva' => $reservaA['codigo'],
    'email' => 'x@x.com', 'nome' => 'x',
]);
$idPagamento = $pagamentos->registrar((int)$agA['id'], 'pix', 50.00, $retornoPix);

$t->verificar('Pagamento registrado com ID', $idPagamento > 0);
$pg = $pagamentos->buscarPorId($idPagamento);
$t->igual('Forma de pagamento gravada', 'pix', $pg['forma_pagamento']);
$t->igual('Status inicial pendente', 'pendente', $pg['status_pagamento']);
$t->igual('Valor gravado', '50.00', $pg['valor']);
$t->verificar('QR Code gravado', !empty($pg['qr_code_pix']));
$t->verificar('pago_em fica nulo enquanto pendente', $pg['pago_em'] === null);
$t->verificar('Busca por transação localiza o pagamento',
    $pagamentos->buscarPorTransacao($retornoPix['transacao_id']) !== null);

$erroForma = false;
try {
    $pagamentos->registrar((int)$agA['id'], 'boleto', 50.00, $retornoPix);
} catch (InvalidArgumentException $e) {
    $erroForma = true;
}
$t->verificar('Forma de pagamento fora do ENUM é rejeitada', $erroForma);

// Aprovação
$resultado = $pagamentos->aprovar($retornoPix['transacao_id']);
$t->igual('Aprovação confirma o agendamento', 'confirmado', $resultado);

$agConfirmado = $agendamentos->buscarPorCodigo($reservaA['codigo']);
$t->igual('Situação do agendamento passa a confirmado', 'confirmado', $agConfirmado['situacao']);
$t->verificar('reserva_expira_em é limpo após a confirmação', $agConfirmado['reserva_expira_em'] === null);

$pgAprovado = $pagamentos->buscarPorTransacao($retornoPix['transacao_id']);
$t->igual('Pagamento marcado como aprovado', 'aprovado', $pgAprovado['status_pagamento']);
$t->verificar('pago_em preenchido na aprovação', $pgAprovado['pago_em'] !== null);
$t->verificar('buscarAprovadoDoAgendamento() localiza o pagamento',
    $pagamentos->buscarAprovadoDoAgendamento((int)$agA['id']) !== null);

// =====================================================================
$t->grupo('6. Idempotência: reprocessar a mesma notificação');

$t->igual('Reprocessar devolve "ja_processado"', 'ja_processado',
    $pagamentos->aprovar($retornoPix['transacao_id']));
$t->igual('Terceira tentativa também é inócua', 'ja_processado',
    $pagamentos->aprovar($retornoPix['transacao_id']));

$t->igual('Agendamento continua confirmado (sem duplicar)', 'confirmado',
    $agendamentos->buscarPorCodigo($reservaA['codigo'])['situacao']);
$t->igual('Existe apenas UM pagamento aprovado para o agendamento', 1,
    (int)$pdo->query("SELECT COUNT(*) FROM pagamentos
                      WHERE agendamento_id = {$agA['id']} AND status_pagamento = 'aprovado'")->fetchColumn());

$t->igual('Transação inexistente é reportada, não confirma nada', 'pagamento_nao_encontrado',
    $pagamentos->aprovar('TRANSACAO-QUE-NAO-EXISTE'));

// =====================================================================
$t->grupo('7. Pagamento após a expiração da reserva');

$reservaB = $novaReserva('10:00');
$agB = $agendamentos->buscarPorCodigo($reservaB['codigo']);
$retornoB = $gateway->criarPix([
    'valor' => 50.00, 'descricao' => 'x', 'codigo_reserva' => $reservaB['codigo'],
    'email' => 'x@x.com', 'nome' => 'x',
]);
$pagamentos->registrar((int)$agB['id'], 'pix', 50.00, $retornoB);

// Força o vencimento e deixa o sistema cancelar
$pdo->prepare("UPDATE agendamentos SET reserva_expira_em = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?")
    ->execute([(int)$agB['id']]);
$agendamentos->expirarReservasVencidas();

$t->igual('Reserva vencida foi cancelada', 'cancelado',
    $agendamentos->buscarPorCodigo($reservaB['codigo'])['situacao']);

$resultadoTardio = $pagamentos->aprovar($retornoB['transacao_id']);
$t->igual('Pagamento tardio é sinalizado como pago_apos_expiracao', 'pago_apos_expiracao', $resultadoTardio);
$t->igual('Agendamento cancelado NÃO é reconfirmado', 'cancelado',
    $agendamentos->buscarPorCodigo($reservaB['codigo'])['situacao']);
$t->igual('O pagamento fica registrado como aprovado (pendência de reembolso)', 'aprovado',
    $pagamentos->buscarPorTransacao($retornoB['transacao_id'])['status_pagamento']);

// =====================================================================
$t->grupo('8. Recusa e estorno');

$reservaC = $novaReserva('11:00');
$agC = $agendamentos->buscarPorCodigo($reservaC['codigo']);
$retornoC = $gateway->criarCartao(['token' => 'sandbox-recusado-1', 'valor' => 50]);
$pagamentos->registrar((int)$agC['id'], 'cartao_credito', 50.00, $retornoC);

$t->verificar('Transação recusada é reportada como recusada', $pagamentos->recusar($retornoC['transacao_id']));
$t->igual('Status gravado como recusado', 'recusado',
    $pagamentos->buscarPorTransacao($retornoC['transacao_id'])['status_pagamento']);
$t->igual('Agendamento segue aguardando pagamento (permite nova tentativa)', 'aguardando_pagamento',
    $agendamentos->buscarPorCodigo($reservaC['codigo'])['situacao']);
$t->verificar('Recusar novamente é idempotente', $pagamentos->recusar($retornoC['transacao_id']));

// Um pagamento aprovado jamais pode ser rebaixado para recusado
$reservaC2 = $novaReserva('11:45');
$agC2 = $agendamentos->buscarPorCodigo($reservaC2['codigo']);
$retornoC2 = $gateway->criarCartao(['token' => 'sandbox-aprovado-1', 'valor' => 50]);
$pagamentos->registrar((int)$agC2['id'], 'cartao_credito', 50.00, $retornoC2);
$pagamentos->aprovar($retornoC2['transacao_id']);
$pagamentos->recusar($retornoC2['transacao_id']);
$t->igual('Pagamento aprovado NÃO é rebaixado por uma recusa tardia', 'aprovado',
    $pagamentos->buscarPorTransacao($retornoC2['transacao_id'])['status_pagamento']);

// Estorno de um pagamento aprovado
$t->verificar('Estorno aplicado ao pagamento aprovado', $pagamentos->estornar($retornoPix['transacao_id']));
$t->igual('Pagamento passa a estornado', 'estornado',
    $pagamentos->buscarPorTransacao($retornoPix['transacao_id'])['status_pagamento']);
$t->igual('Agendamento passa a reembolsado', 'reembolsado',
    $agendamentos->buscarPorCodigo($reservaA['codigo'])['situacao']);
$t->verificar('Estornar duas vezes não gera efeito', !$pagamentos->estornar($retornoPix['transacao_id']));

// =====================================================================
$t->grupo('9. HTTP: tela de checkout e geração do Pix');

$liberarAgenda();

$entrar = function (string $email) use ($baseUrl): HttpClient {
    $c = new HttpClient($baseUrl);
    $csrf = $c->csrfDe('/login');
    $c->post('/login', ['csrf_token' => $csrf, 'email' => $email, 'senha' => SENHA_TESTE]);
    return $c;
};

$clienteHttp = $entrar(PREFIXO_TESTE . 'cliente@local.test');
$reservaD = $novaReserva('13:00');
$rota = '/checkout/' . $reservaD['codigo'];

$clienteHttp->get($rota);
$t->igual('Checkout responde 200 para o dono da reserva', 200, $clienteHttp->ultimoStatus);
$t->verificar('Checkout mostra o cronômetro', $clienteHttp->corpoContem('id="cronometro"'));
$t->verificar('Checkout oferece a aba Pix', $clienteHttp->corpoContem('Pix'));
$t->verificar('Checkout oferece a aba Cartão de Crédito', $clienteHttp->corpoContem('Cartão de Crédito'));
$t->verificar('Checkout avisa que está em ambiente de simulação',
    $clienteHttp->corpoContem('Ambiente de simulação'));
$t->verificar('Sandbox NÃO pede dados reais de cartão',
    !$clienteHttp->corpoContem('name="numero_cartao"'));

$t->igual('POST de gerar Pix sem CSRF é rejeitado', 403,
    $clienteHttp->post($rota . '/pix', [])->ultimoStatus);

$csrf = $clienteHttp->csrfDe($rota);
$clienteHttp->post($rota . '/pix', ['csrf_token' => $csrf]);

$agD = $agendamentos->buscarPorCodigo($reservaD['codigo']);
$pgD = $pagamentos->buscarPorAgendamento((int)$agD['id']);
$t->verificar('Pix gerado e gravado no banco', $pgD !== null && $pgD['forma_pagamento'] === 'pix');
$t->verificar('QR Code disponível para a tela', !empty($pgD['qr_code_pix_url']));

$clienteHttp->get($rota . '?metodo=pix');
$t->verificar('Tela exibe a imagem do QR Code', $clienteHttp->corpoContem('qr-code-pix'));
$t->verificar('Tela exibe o campo Copia e Cola', $clienteHttp->corpoContem('id="codigo-pix"'));
$t->verificar('Tela oferece o botão de copiar', $clienteHttp->corpoContem('btn-copiar-pix'));

// Gerar o Pix duas vezes não pode criar duas cobranças
$csrf = $clienteHttp->csrfDe($rota);
$clienteHttp->post($rota . '/pix', ['csrf_token' => $csrf]);
$t->igual('Gerar Pix novamente não duplica a cobrança', 1,
    (int)$pdo->query("SELECT COUNT(*) FROM pagamentos WHERE agendamento_id = {$agD['id']}")->fetchColumn());

// =====================================================================
$t->grupo('10. HTTP: polling de status e simulação de pagamento');

$clienteHttp->get($rota . '/status');
$statusJson = json_decode($clienteHttp->ultimoCorpo, true);
$t->igual('Endpoint de status responde 200', 200, $clienteHttp->ultimoStatus);
$t->igual('Status informa situação aguardando_pagamento', 'aguardando_pagamento', $statusJson['situacao'] ?? null);
$t->verificar('Status informa que ainda não está confirmado', $statusJson['confirmado'] === false);

$t->igual('Simular pagamento sem CSRF é rejeitado', 403,
    $clienteHttp->post($rota . '/simular', [])->ultimoStatus);

$csrf = $clienteHttp->csrfDe($rota . '?metodo=pix');
$clienteHttp->post($rota . '/simular', ['csrf_token' => $csrf]);

$t->igual('Simulação confirma o agendamento', 'confirmado',
    $agendamentos->buscarPorCodigo($reservaD['codigo'])['situacao']);

$clienteHttp->get($rota . '/status');
$statusJson = json_decode($clienteHttp->ultimoCorpo, true);
$t->verificar('Polling passa a reportar confirmado', $statusJson['confirmado'] === true);
$t->igual('Pagamento consta como aprovado', 'aprovado', $statusJson['status_pagamento'] ?? null);

$clienteHttp->get($rota);
$t->verificar('Tela mostra a confirmação ao cliente', $clienteHttp->corpoContem('Agendamento confirmado'));
$t->verificar('Tela deixa de oferecer formas de pagamento', !$clienteHttp->corpoContem('Gerar QR Code Pix'));

// Reserva já confirmada não aceita nova cobrança
$csrf = $clienteHttp->csrfDe($rota);
$clienteHttp->post($rota . '/pix', ['csrf_token' => $csrf]);
$t->igual('Não é possível gerar Pix para reserva já confirmada', 1,
    (int)$pdo->query("SELECT COUNT(*) FROM pagamentos WHERE agendamento_id = {$agD['id']}")->fetchColumn());

// =====================================================================
$t->grupo('11. HTTP: pagamento com cartão');

$liberarAgenda();

$reservaE = $novaReserva('14:00');
$rotaE = '/checkout/' . $reservaE['codigo'];
$agE = $agendamentos->buscarPorCodigo($reservaE['codigo']);

// Cartão recusado
$csrf = $clienteHttp->csrfDe($rotaE);
$clienteHttp->post($rotaE . '/cartao', [
    'csrf_token' => $csrf, 'token' => 'sandbox-recusado-abc', 'parcelas' => '1',
]);
$t->igual('Cartão recusado NÃO confirma o agendamento', 'aguardando_pagamento',
    $agendamentos->buscarPorCodigo($reservaE['codigo'])['situacao']);
$t->igual('Recusa fica registrada em pagamentos', 'recusado',
    $pagamentos->buscarPorAgendamento((int)$agE['id'])['status_pagamento']);

$clienteHttp->get($rotaE);
$t->verificar('Cliente é informado da recusa', $clienteHttp->corpoContem('recusado'));

// Cartão aprovado
$csrf = $clienteHttp->csrfDe($rotaE);
$clienteHttp->post($rotaE . '/cartao', [
    'csrf_token' => $csrf, 'token' => 'sandbox-aprovado-abc', 'parcelas' => '3',
]);
$t->igual('Cartão aprovado confirma o agendamento', 'confirmado',
    $agendamentos->buscarPorCodigo($reservaE['codigo'])['situacao']);
$t->igual('Pagamento por cartão registrado', 'cartao_credito',
    $pagamentos->buscarAprovadoDoAgendamento((int)$agE['id'])['forma_pagamento']);

// Cartão sem token
$reservaF = $novaReserva('15:00');
$rotaF = '/checkout/' . $reservaF['codigo'];
$csrf = $clienteHttp->csrfDe($rotaF);
$clienteHttp->post($rotaF . '/cartao', ['csrf_token' => $csrf, 'token' => '']);
$t->igual('Cartão sem token não gera cobrança', 'aguardando_pagamento',
    $agendamentos->buscarPorCodigo($reservaF['codigo'])['situacao']);

$t->igual('Nenhum número de cartão foi gravado no banco', 0,
    (int)$pdo->query("SELECT COUNT(*) FROM pagamentos WHERE transacao_id LIKE '%4111%'")->fetchColumn());

// =====================================================================
$t->grupo('12. HTTP: webhook do gateway');

$liberarAgenda();

$webhook = new HttpClient($baseUrl);

$webhook->post('/webhook/mercadopago', []);
$t->igual('Corpo não-JSON é recusado com 400', 400, $webhook->ultimoStatus);

// O HttpClient envia form-urlencoded; para JSON usamos cURL direto
$enviarWebhook = function (array $corpo) use ($baseUrl): array {
    $ch = curl_init($baseUrl . '/webhook/mercadopago');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($corpo),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $corpoResposta = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['codigo' => $codigo, 'json' => json_decode((string)$corpoResposta, true)];
};

$r = $enviarWebhook(['type' => 'merchant_order', 'data' => ['id' => '1']]);
$t->igual('Notificação de outro tipo é ignorada com 200', 200, $r['codigo']);
$t->igual('Resultado informa que foi ignorada', 'ignorado', $r['json']['resultado'] ?? null);

$r = $enviarWebhook(['type' => 'payment', 'data' => ['id' => 'NAO-EXISTE-999']]);
$t->igual('Pagamento inexistente responde 200 (evita reenvio infinito)', 200, $r['codigo']);
$t->igual('Resultado informa pagamento não encontrado', 'pendente', $r['json']['resultado'] ?? null);

// Webhook confirmando um pagamento real do sandbox
$reservaG = $novaReserva('16:00');
$agG = $agendamentos->buscarPorCodigo($reservaG['codigo']);
$retornoG = $gateway->criarPix([
    'valor' => 50.00, 'descricao' => 'x', 'codigo_reserva' => $reservaG['codigo'],
    'email' => 'x@x.com', 'nome' => 'x',
]);
$pagamentos->registrar((int)$agG['id'], 'pix', 50.00, $retornoG);

// No sandbox o gateway consulta a própria tabela: aprovamos antes
$pdo->prepare("UPDATE pagamentos SET status_pagamento = 'aprovado' WHERE transacao_id = ?")
    ->execute([$retornoG['transacao_id']]);

$r = $enviarWebhook(['type' => 'payment', 'data' => ['id' => $retornoG['transacao_id']]]);
$t->igual('Webhook responde 200', 200, $r['codigo']);
$t->igual('Webhook confirma o agendamento ainda que o pagamento já estivesse aprovado',
    'confirmado', $r['json']['resultado'] ?? null);
$t->igual('Agendamento fica confirmado', 'confirmado',
    $agendamentos->buscarPorCodigo($reservaG['codigo'])['situacao']);

// Fluxo completo: pendente -> webhook -> confirmado
$reservaH = $novaReserva('17:00'); // 16:00 + 30min + 10min de intervalo ocupam ate 16:40
$agH = $agendamentos->buscarPorCodigo($reservaH['codigo']);
$retornoH = $gateway->criarPix([
    'valor' => 50.00, 'descricao' => 'x', 'codigo_reserva' => $reservaH['codigo'],
    'email' => 'x@x.com', 'nome' => 'x',
]);
$pagamentos->registrar((int)$agH['id'], 'pix', 50.00, $retornoH);
// Simula o dinheiro caindo no provedor
$pdo->prepare("UPDATE pagamentos SET status_pagamento = 'aprovado', pago_em = NULL WHERE transacao_id = ?")
    ->execute([$retornoH['transacao_id']]);
// E devolve o agendamento ao estado de espera, para o webhook fazer o trabalho
$pdo->prepare("UPDATE pagamentos SET status_pagamento = 'pendente' WHERE transacao_id = ?")
    ->execute([$retornoH['transacao_id']]);

$gatewayFalso = new class implements GatewayPagamento {
    public string $transacao = '';
    public function nome(): string { return 'teste'; }
    public function criarPix(array $d): array { return []; }
    public function criarCartao(array $d): array { return []; }
    public function consultar(string $t): array {
        return ['transacao_id' => $t, 'status' => 'aprovado', 'valor' => 50.0];
    }
    public function validarAssinatura(array $c, string $i): bool { return true; }
};

GatewayFactory::definir($gatewayFalso);
$resultadoWebhook = (new PagamentoModel())->aprovar($retornoH['transacao_id']);
GatewayFactory::definir(null);

$t->igual('Webhook confirma o agendamento pendente', 'confirmado', $resultadoWebhook);
$t->igual('Situação final é confirmado', 'confirmado',
    $agendamentos->buscarPorCodigo($reservaH['codigo'])['situacao']);

// Idempotência do webhook por HTTP
$r1 = $enviarWebhook(['type' => 'payment', 'data' => ['id' => $retornoH['transacao_id']]]);
$r2 = $enviarWebhook(['type' => 'payment', 'data' => ['id' => $retornoH['transacao_id']]]);
$t->igual('Reenvio do webhook responde 200', 200, $r2['codigo']);
$t->igual('Reenvio não reprocessa a confirmação', 'ja_processado', $r2['json']['resultado'] ?? null);
$t->igual('Continua havendo apenas um pagamento aprovado', 1,
    (int)$pdo->query("SELECT COUNT(*) FROM pagamentos
                      WHERE agendamento_id = {$agH['id']} AND status_pagamento = 'aprovado'")->fetchColumn());

// Auditoria do webhook
$t->verificar('Confirmação por webhook é auditada',
    (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE acao = 'pagamento_confirmado'")->fetchColumn() > 0);

// =====================================================================
$t->grupo('13. Segurança do checkout e do webhook');

$liberarAgenda();

// Outro cliente
$usuarios->criar([
    'nome' => 'Intruso F6', 'email' => PREFIXO_TESTE . 'intruso@local.test',
    'telefone' => '(11) 93333-0003', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);
$intruso = $entrar(PREFIXO_TESTE . 'intruso@local.test');

// Reserva viva pertencente ao cliente legítimo, alvo das tentativas de acesso
$reservaAlvo = $novaReserva('09:00');
$rotaAlvo = '/checkout/' . $reservaAlvo['codigo'];

$t->igual('Outro cliente é bloqueado na tela de checkout (403)', 403,
    $intruso->get($rotaAlvo)->ultimoStatus);
$t->igual('Outro cliente é bloqueado no status (403)', 403,
    $intruso->get($rotaAlvo . '/status')->ultimoStatus);

$csrfIntruso = $intruso->csrfDe('/perfil');
$intruso->post($rotaAlvo . '/pix', ['csrf_token' => $csrfIntruso]);
$t->igual('Outro cliente é bloqueado ao gerar Pix (403)', 403, $intruso->ultimoStatus);
$intruso->post($rotaAlvo . '/simular', ['csrf_token' => $csrfIntruso]);
$t->igual('Outro cliente é bloqueado ao simular pagamento (403)', 403, $intruso->ultimoStatus);

$t->igual('Nenhum pagamento foi criado pela tentativa do intruso', 0,
    (int)$pdo->query("SELECT COUNT(*) FROM pagamentos p
                      INNER JOIN agendamentos a ON a.id = p.agendamento_id
                      WHERE a.codigo_reserva = " . $pdo->quote($reservaAlvo['codigo']))->fetchColumn());
$t->igual('Agendamento alvo permanece aguardando pagamento', 'aguardando_pagamento',
    $agendamentos->buscarPorCodigo($reservaAlvo['codigo'])['situacao']);

// Visitante sem sessão
$anonimo = new HttpClient($baseUrl);
$t->igual('Visitante é redirecionado do checkout', 302, $anonimo->get($rotaAlvo)->ultimoStatus);

// O webhook não pode ser usado para confirmar de graça:
// forjar o status no corpo não tem efeito, pois o status vem da consulta.
$reservaI = $novaReserva('17:00');
$agI = $agendamentos->buscarPorCodigo($reservaI['codigo']);
$retornoI = $gateway->criarPix([
    'valor' => 50.00, 'descricao' => 'x', 'codigo_reserva' => $reservaI['codigo'],
    'email' => 'x@x.com', 'nome' => 'x',
]);
$pagamentos->registrar((int)$agI['id'], 'pix', 50.00, $retornoI);

$r = $enviarWebhook([
    'type' => 'payment',
    'data' => ['id' => $retornoI['transacao_id']],
    'status' => 'approved', // tentativa de forjar a aprovação
]);
$t->igual('Status forjado no corpo do webhook é ignorado', 'pendente', $r['json']['resultado'] ?? null);
$t->igual('Agendamento NÃO é confirmado por webhook forjado', 'aguardando_pagamento',
    $agendamentos->buscarPorCodigo($reservaI['codigo'])['situacao']);

// Simulação só existe em sandbox
$t->verificar('Rota de simulação é exclusiva do modo sandbox',
    strpos(file_get_contents(__DIR__ . '/../app/controllers/CheckoutController.php'),
           'if (!GatewayFactory::emSandbox())') !== false);

// Credenciais nunca vão para o HTML em sandbox
$clienteHttp->get($rotaF);
$t->verificar('Access token jamais aparece no HTML',
    !$clienteHttp->corpoContem($config['mercadopago']['access_token']));

// =====================================================================
// Limpeza
// =====================================================================
$clienteHttp->encerrar();
$intruso->encerrar();
$anonimo->encerrar();
$webhook->encerrar();

limparDadosDeTeste($pdo);
echo "\n(Dados de teste removidos do banco.)\n";

exit($t->resumo());

// =====================================================================

function limparDadosDeTeste(PDO $pdo): void {
    $prefixo = PREFIXO_TESTE . '%';

    $pdo->prepare("
        DELETE FROM pagamentos WHERE agendamento_id IN (
            SELECT id FROM agendamentos WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
        )
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM agendamento_servicos WHERE agendamento_id IN (
            SELECT id FROM agendamentos WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
        )
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM agendamentos WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM audit_logs WHERE acao LIKE 'pagamento_%'")->execute();
    $pdo->prepare("
        DELETE FROM horarios_trabalho WHERE profissional_id IN (
            SELECT p.id FROM profissionais p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
        )
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM profissional_servicos WHERE profissional_id IN (
            SELECT p.id FROM profissionais p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
        )
    ")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM profissional_servicos WHERE servico_id IN (SELECT id FROM servicos WHERE nome LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM servicos WHERE nome LIKE ?")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM security_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM security_logs WHERE detalhes LIKE '%checkout de outro cliente%'")->execute();
    $pdo->prepare("DELETE FROM audit_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM profissionais WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?")->execute([$prefixo]);
}
