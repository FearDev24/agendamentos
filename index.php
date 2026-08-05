<?php
/**
 * AgendaBarber Web - Front Controller & Roteador Principal
 *
 * Ponto de entrada único da aplicação. Recebe todas as requisições HTTP,
 * inicializa configurações, sessão, autoloader e despacha a rota para o
 * Controller correspondente.
 */

// Constante de segurança que autoriza o carregamento dos arquivos internos
define('APP_EXEC', true);

date_default_timezone_set('America/Sao_Paulo');

$config = require __DIR__ . '/config/config.php';
$GLOBALS['config'] = $config;

// Exibição de erros conforme o ambiente
if (($config['app']['env'] ?? 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/error.log');

require_once __DIR__ . '/app/bootstrap.php';

/**
 * Tratamento global de erros (docs/FSD.md seção 19.1).
 *
 * Toda exceção não capturada e todo erro do PHP são gravados em `error_logs`,
 * com contingência em `logs/error.log` caso o banco esteja indisponível. O
 * usuário final vê apenas uma mensagem amigável; os detalhes técnicos ficam
 * restritos ao log.
 */
set_error_handler(function (int $severidade, string $mensagem, string $arquivo, int $linha): bool {
    // Respeita o error_reporting configurado (ex: @ suprimindo o aviso)
    if (!(error_reporting() & $severidade)) {
        return false;
    }

    throw new ErrorException($mensagem, 0, $severidade, $arquivo, $linha);
});

set_exception_handler(function (Throwable $e) use ($config): void {
    LogModel::erro($e);

    if (($config['app']['env'] ?? 'production') === 'development') {
        http_response_code(500);
        echo '<pre style="background:#0f1416;color:#ffb4ab;padding:16px;font:13px monospace;">';
        echo htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8');
        echo '</pre>';
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
    }

    $pageTitle = 'Erro Interno';
    require __DIR__ . '/app/views/errors/500.php';
});

/**
 * Erros fatais escapam do set_exception_handler: só o shutdown os alcança.
 */
register_shutdown_function(function () use ($config): void {
    $erro = error_get_last();

    if ($erro === null || !in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    LogModel::erro(new ErrorException(
        $erro['message'], 0, $erro['type'], $erro['file'], $erro['line']
    ));

    if (($config['app']['env'] ?? 'production') !== 'development' && !headers_sent()) {
        http_response_code(500);
        $pageTitle = 'Erro Interno';
        require __DIR__ . '/app/views/errors/500.php';
    }
});

// Cabeçalhos de segurança HTTP (docs/FSD.md seção 24)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

SessionHelper::init();

// -----------------------------------------------------------------
// Resolução da rota
// -----------------------------------------------------------------
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rota = Router::caminhoAtual($config['app']['url']);

// -----------------------------------------------------------------
// Tabela de rotas: 'MÉTODO /caminho' => [Controller, método]
// -----------------------------------------------------------------
$rotas = [
    // Público
    'GET /'                    => [HomeController::class, 'index'],

    // Aplicativo instalável: gerado por PHP porque o caminho base
    // muda entre o ambiente local e a hospedagem de produção
    'GET /manifest.webmanifest' => [PwaController::class, 'manifesto'],

    // Wizard de agendamento
    'GET /agendar'             => [AgendamentoController::class, 'passoServicos'],
    'POST /agendar/servicos'   => [AgendamentoController::class, 'salvarServicos'],
    'GET /agendar/horario'     => [AgendamentoController::class, 'passoHorario'],
    'POST /agendar/horario'    => [AgendamentoController::class, 'salvarHorario'],
    'GET /agendar/revisao'     => [AgendamentoController::class, 'passoRevisao'],
    'POST /agendar/confirmar'  => [AgendamentoController::class, 'confirmar'],
    'GET /api/horarios'        => [AgendamentoController::class, 'apiHorarios'],

    // Checkout e pagamento
    'GET /checkout/{codigo}'         => [CheckoutController::class, 'index'],
    'POST /checkout/{codigo}/pix'    => [CheckoutController::class, 'gerarPix'],
    'POST /checkout/{codigo}/cartao' => [CheckoutController::class, 'pagarCartao'],
    'GET /checkout/{codigo}/status'  => [CheckoutController::class, 'status'],
    'POST /checkout/{codigo}/simular'=> [CheckoutController::class, 'simularPagamento'],

    // Webhook do gateway: rota pública, sem sessão nem CSRF (chamada servidor-a-servidor)
    'POST /webhook/mercadopago'      => [WebhookController::class, 'mercadoPago'],

    // Autenticação
    'GET /login'               => [AuthController::class, 'formLogin'],
    'POST /login'              => [AuthController::class, 'login'],
    'GET /cadastro'            => [AuthController::class, 'formCadastro'],
    'POST /cadastro'           => [AuthController::class, 'cadastrar'],
    'POST /logout'             => [AuthController::class, 'logout'],
    'GET /recuperar-senha'     => [AuthController::class, 'formRecuperarSenha'],
    'POST /recuperar-senha'    => [AuthController::class, 'enviarRecuperacao'],
    'GET /redefinir-senha'     => [AuthController::class, 'formRedefinirSenha'],
    'POST /redefinir-senha'    => [AuthController::class, 'redefinirSenha'],
    'GET /confirmar-email'     => [AuthController::class, 'confirmarEmail'],

    // Perfil do usuário autenticado
    'GET /perfil'              => [PerfilController::class, 'index'],
    'POST /perfil/dados'       => [PerfilController::class, 'atualizarDados'],
    'POST /perfil/senha'       => [PerfilController::class, 'alterarSenha'],
    'POST /perfil/email'       => [PerfilController::class, 'solicitarTrocaEmail'],
    'POST /perfil/excluir'     => [PerfilController::class, 'excluirConta'],

    // Área do cliente
    'GET /cliente/agendamentos'  => [ClienteController::class, 'agendamentos'],
    'GET /cliente/agendamento/{codigo}/comprovante' => [ClienteController::class, 'comprovante'],
    'GET /cliente/agendamento/{codigo}/reagendar'   => [ClienteController::class, 'formReagendar'],
    'POST /cliente/reagendar'    => [ClienteController::class, 'reagendar'],
    'POST /cliente/cancelar'     => [ClienteController::class, 'cancelar'],
    'POST /cliente/avaliar'      => [ClienteController::class, 'avaliar'],
    'POST /cliente/credito'      => [ClienteController::class, 'aplicarCredito'],

    // Área do profissional
    'GET /profissional/agenda'   => [ProfissionalController::class, 'agenda'],
    'POST /profissional/presenca'=> [ProfissionalController::class, 'confirmarPresenca'],
    'POST /profissional/concluir'=> [ProfissionalController::class, 'concluir'],
    'POST /profissional/falta'   => [ProfissionalController::class, 'registrarFalta'],
    'GET /profissional/cliente/{id}/notas' => [ProfissionalController::class, 'notasDoCliente'],
    'POST /profissional/nota'    => [ProfissionalController::class, 'salvarNota'],
    'GET /admin/dashboard'       => [AdminController::class, 'dashboard'],
    'GET /admin/auditoria'       => [AdminController::class, 'auditoria'],
    'POST /admin/auditoria/restaurar' => [AdminController::class, 'restaurarRegistro'],

    // Integrações: vinculação da conta de pagamentos pelo próprio estabelecimento
    'GET /admin/integracoes'                => [IntegracaoController::class, 'index'],
    'POST /admin/integracoes/mercadopago'   => [IntegracaoController::class, 'salvarMercadoPago'],
    'POST /admin/integracoes/testar'        => [IntegracaoController::class, 'testarMercadoPago'],
    'POST /admin/integracoes/desvincular'   => [IntegracaoController::class, 'desvincularMercadoPago'],

    // Cadastro de serviços (exclusivo do Administrador Principal)
    'GET /admin/servicos'            => [ServicoController::class, 'index'],
    'POST /admin/servicos/salvar'    => [ServicoController::class, 'salvar'],
    'POST /admin/servicos/excluir'   => [ServicoController::class, 'excluir'],
    'POST /admin/servicos/restaurar' => [ServicoController::class, 'restaurar'],

    // Cadastro de profissionais (exclusivo do Administrador Principal)
    'GET /admin/profissionais'          => [AdminController::class, 'profissionais'],
    'POST /admin/profissionais/salvar'  => [AdminController::class, 'salvarProfissional'],
    'POST /admin/profissionais/excluir' => [AdminController::class, 'excluirProfissional'],
    'POST /admin/profissionais/servicos'=> [AdminController::class, 'salvarServicosDoProfissional'],
    'POST /admin/profissionais/horarios'=> [AdminController::class, 'salvarHorarios'],

    // Bloqueios de agenda (ambos os perfis administrativos)
    'GET /admin/bloqueios'          => [AdminController::class, 'bloqueios'],
    'POST /admin/bloqueios/salvar'  => [AdminController::class, 'salvarBloqueio'],
    'POST /admin/bloqueios/excluir' => [AdminController::class, 'excluirBloqueio'],

    // Agenda administrativa (ambos os perfis administrativos)
    'GET /admin/agenda'             => [AdminController::class, 'agenda'],
    'POST /admin/agenda/manual'     => [AdminController::class, 'agendarManualmente'],
    'POST /admin/agenda/cancelar'   => [AdminController::class, 'cancelarAgendamento'],
    'POST /admin/agenda/pagamento'  => [AdminController::class, 'registrarPagamento'],

    // Financeiro e relatórios (exclusivo do Administrador Principal)
    'GET /admin/financeiro'                => [FinanceiroController::class, 'index'],
    'POST /admin/financeiro/despesa'       => [FinanceiroController::class, 'salvarDespesa'],
    'POST /admin/financeiro/despesa/excluir' => [FinanceiroController::class, 'excluirDespesa'],
    'POST /admin/financeiro/categoria'     => [FinanceiroController::class, 'salvarCategoria'],
    'GET /admin/financeiro/exportar'       => [FinanceiroController::class, 'exportarFinanceiro'],
    'GET /admin/relatorios'                => [FinanceiroController::class, 'relatorios'],
    'GET /admin/relatorios/exportar'       => [FinanceiroController::class, 'exportar'],

    // Promoções (ambos os perfis administrativos)
    'GET /admin/promocoes'          => [PromocaoController::class, 'index'],
    'POST /admin/promocoes/salvar'  => [PromocaoController::class, 'salvar'],
    'POST /admin/promocoes/excluir' => [PromocaoController::class, 'excluir'],

    // Página pública de compartilhamento da promoção
    'GET /promocao/{id}'            => [PromocaoController::class, 'publica'],
];

// -----------------------------------------------------------------
// Despacho
// -----------------------------------------------------------------
// Exceções não capturadas caem no set_exception_handler definido acima
Router::despachar($rotas, $metodo, $rota);
