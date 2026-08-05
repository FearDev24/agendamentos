<?php
/**
 * tests/teste_fase9.php - Validação da Fase 9 e dos 12 Critérios de Aceitação
 *
 * Uso: php tests/teste_fase9.php
 *
 * Além de validar as entregas transversais da Fase 9 (logs, auditoria,
 * e-mails e Cron), esta bateria percorre um a um os 12 Critérios de Aceitação
 * Técnica e Funcional do docs/FSD.md seção 26.
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
// O runner fica em database/, fora dos diretórios cobertos pelo autoloader
require_once __DIR__ . '/../database/MigrationRunner.php';

$t = new TestRunner();
$pdo = Database::getConnection();
$baseUrl = $config['app']['url'];
$raiz = __DIR__ . '/..';

const PREFIXO_TESTE = 'teste_fase9_';
const SENHA_TESTE = 'SenhaTeste@123';

echo "\n======================================================\n";
echo " TESTES DA FASE 9 - Logs, Auditoria, E-mails e Aceitação\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

limparDadosDeTeste($pdo);

$usuarios   = new UsuarioModel();
$auditoria  = new AuditModel();
$servicosM  = new ServicoModel();

$idPrincipal = $usuarios->criar([
    'nome' => 'Principal F9', 'email' => PREFIXO_TESTE . 'principal@local.test',
    'telefone' => '(11) 90000-0001', 'senha' => SENHA_TESTE, 'perfil' => 'admin_principal',
]);
$idCliente = $usuarios->criar([
    'nome' => 'Cliente F9', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 90000-0002', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);

$entrar = function (string $email) use ($baseUrl): HttpClient {
    $c = new HttpClient($baseUrl);
    $csrf = $c->csrfDe('/login');
    $c->post('/login', ['csrf_token' => $csrf, 'email' => $email, 'senha' => SENHA_TESTE]);
    return $c;
};

// =====================================================================
$t->grupo('1. PHPMailer e envio de e-mails');

$t->verificar('PHPMailer instalado em vendor/', is_dir($raiz . '/vendor/PHPMailer'));

foreach (['PHPMailer.php', 'SMTP.php', 'Exception.php'] as $arquivo) {
    $caminho = $raiz . '/vendor/PHPMailer/' . $arquivo;
    $t->verificar("vendor/PHPMailer/{$arquivo} presente", is_file($caminho));
    $t->verificar("{$arquivo} tem tamanho compatível", is_file($caminho) && filesize($caminho) > 1000);
}

$conteudoPhpMailer = file_get_contents($raiz . '/vendor/PHPMailer/PHPMailer.php');
$t->verificar('PHPMailer declara a versão',
    preg_match('/VERSION\s*=\s*[\'"]([\d.]+)/', $conteudoPhpMailer, $m) === 1, $m[1] ?? '?');
$t->verificar('vendor/ protegido por .htaccess', is_file($raiz . '/vendor/.htaccess'));

// Envio em desenvolvimento grava arquivo em vez de enviar
$antes = count(glob($raiz . '/logs/emails/*.html'));
$enviou = MailHelper::send(PREFIXO_TESTE . 'destino@local.test', 'Assunto de teste', '<p>Corpo <b>HTML</b>.</p>');
$depois = glob($raiz . '/logs/emails/*.html');

$t->verificar('Envio em desenvolvimento é bem-sucedido', $enviou);
$t->igual('Mensagem gravada para conferência', $antes + 1, count($depois));

$ultimo = end($depois);
$corpoEmail = file_get_contents($ultimo);
$t->verificar('Arquivo registra destinatário e assunto',
    strpos($corpoEmail, PREFIXO_TESTE . 'destino@local.test') !== false
    && strpos($corpoEmail, 'Assunto de teste') !== false);
$t->verificar('Layout do estabelecimento aplicado', strpos($corpoEmail, 'AgendaBarber') !== false);
$t->verificar('Conteúdo enviado preservado', strpos($corpoEmail, 'Corpo <b>HTML</b>') !== false);
$t->verificar('E-mail usa estilos inline (compatibilidade com Outlook)',
    strpos($corpoEmail, 'style="margin:0') !== false);

$t->verificar('Endereço inválido é recusado', !MailHelper::send('nao-e-email', 'x', 'y'));
$t->verificar('Endereço vazio é recusado', !MailHelper::send('', 'x', 'y'));

// =====================================================================
$t->grupo('2. Tratamento global de erros');

$indexPhp = file_get_contents($raiz . '/index.php');
$t->verificar('set_exception_handler configurado', strpos($indexPhp, 'set_exception_handler') !== false);
$t->verificar('set_error_handler configurado', strpos($indexPhp, 'set_error_handler') !== false);
$t->verificar('register_shutdown_function captura erro fatal',
    strpos($indexPhp, 'register_shutdown_function') !== false);

$totalErrosAntes = (int)$pdo->query('SELECT COUNT(*) FROM error_logs')->fetchColumn();

LogModel::erro(new RuntimeException(PREFIXO_TESTE . 'erro de verificacao'));

$t->igual('Exceção é gravada em error_logs', $totalErrosAntes + 1,
    (int)$pdo->query('SELECT COUNT(*) FROM error_logs')->fetchColumn());

$registro = $pdo->query('SELECT * FROM error_logs ORDER BY id DESC LIMIT 1')->fetch();
$t->verificar('Mensagem gravada', strpos($registro['mensagem'], PREFIXO_TESTE) !== false);
$t->verificar('Arquivo e linha gravados', !empty($registro['arquivo']) && (int)$registro['linha'] > 0);
$t->verificar('Stack trace gravado', !empty($registro['stack_trace']));

// Contingência em arquivo quando o banco falha (FSD seção 19.1)
$modelo = new ReflectionClass('LogModel');
$contingencia = $modelo->getMethod('contingenciaArquivo');
$contingencia->setAccessible(true);
$contingencia->invoke(null, PREFIXO_TESTE . 'contingencia em arquivo');

$logArquivo = file_get_contents($raiz . '/logs/error.log');
$t->verificar('Contingência grava em logs/error.log',
    strpos($logArquivo, PREFIXO_TESTE . 'contingencia em arquivo') !== false);

// =====================================================================
$t->grupo('3. Auditoria e restauração');

$idServicoAud = $servicosM->criar([
    'nome' => PREFIXO_TESTE . 'Servico Auditado', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 60.00, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);

SessionHelper::set('user_id', $idPrincipal);
$auditoria->registrar(AuditModel::ATUALIZAR, 'servicos', $idServicoAud,
    ['preco' => '60.00'], ['preco' => '75.00']);
SessionHelper::remove('user_id');

$registros = $auditoria->listar(['de' => date('Y-m-d'), 'ate' => date('Y-m-d')]);
$t->verificar('Consulta de auditoria retorna registros', count($registros) > 0);

$disponivel = $auditoria->valoresDisponiveis();
$t->verificar('Entidades disponíveis para filtro', in_array('servicos', $disponivel['entidades'], true));
$t->verificar('Ações disponíveis para filtro', in_array('atualizar', $disponivel['acoes'], true));

$filtrado = $auditoria->listar(['entidade' => 'servicos', 'acao' => 'atualizar']);
$t->verificar('Filtro por entidade e ação funciona', count($filtrado) > 0);
foreach ($filtrado as $r) {
    if ($r['entidade'] !== 'servicos' || $r['acao'] !== 'atualizar') {
        $t->verificar('Filtro não traz registros de outras entidades', false, $r['entidade']);
        break;
    }
}

$t->igual('Filtro por entidade inexistente devolve vazio', 0,
    count($auditoria->listar(['entidade' => 'nao_existe'])));

// Restauração de registro excluído
$servicosM->excluirLogicamente($idServicoAud);
$t->verificar('Serviço excluído some das buscas', $servicosM->buscarPorId($idServicoAud) === null);

$excluidos = $auditoria->registrosExcluidos();
$t->verificar('Registro aparece na lista de excluídos',
    isset($excluidos['servicos'])
    && in_array($idServicoAud, array_map('intval', array_column($excluidos['servicos'], 'id')), true));

$t->verificar('Restauração devolve o registro', $auditoria->restaurar('servicos', $idServicoAud));
$t->verificar('Serviço volta às buscas', $servicosM->buscarPorId($idServicoAud) !== null);
$t->verificar('Restaurar de novo não gera efeito', !$auditoria->restaurar('servicos', $idServicoAud));

$t->verificar('Restauração é registrada na auditoria',
    (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE acao = 'restaurar' AND entidade = 'servicos'")->fetchColumn() > 0);

// Tabela fora da lista permitida é recusada
$erroTabela = false;
try { $auditoria->restaurar('usuarios; DROP TABLE usuarios', 1); }
catch (InvalidArgumentException $e) { $erroTabela = true; }
$t->verificar('Tabela não permitida é recusada na restauração', $erroTabela);

$t->verificar('Tabela `usuarios` continua existindo',
    (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'")->fetchColumn() === 1);

// Dados sensíveis nunca entram na auditoria
$auditoria->registrar(AuditModel::CRIAR, 'usuarios', 999,
    null, ['nome' => 'X', 'senha' => 'MinhaSenhaSecreta', 'senha_hash' => '$2y$abc', 'token_email' => 'tok123']);

$ultimoAudit = $pdo->query("SELECT dados_novos FROM audit_logs ORDER BY id DESC LIMIT 1")->fetchColumn();
$t->verificar('Senha em texto plano não é auditada', strpos((string)$ultimoAudit, 'MinhaSenhaSecreta') === false);
$t->verificar('Hash de senha não é auditado', strpos((string)$ultimoAudit, '$2y$abc') === false);
$t->verificar('Token não é auditado', strpos((string)$ultimoAudit, 'tok123') === false);

// =====================================================================
$t->grupo('4. Cron de rotinas automáticas');

$cron = $raiz . '/database/cron_lembretes.php';
$t->verificar('Script do Cron existe', is_file($cron));

$conteudoCron = file_get_contents($cron);
$t->verificar('Cron recusa execução via navegador', strpos($conteudoCron, "PHP_SAPI !== 'cli'") !== false);
$t->verificar('Cron expira reservas vencidas', strpos($conteudoCron, 'expirarReservasVencidas') !== false);
$t->verificar('Cron envia lembretes de 24h e 2h',
    strpos($conteudoCron, "'24h' => 24") !== false && strpos($conteudoCron, "'2h' => 2") !== false);
$t->verificar('Cron expira promoções vencidas', strpos($conteudoCron, 'expirarVencidas') !== false);
$t->verificar('Cron documenta o agendamento no crontab', strpos($conteudoCron, 'crontab') !== false);

// Colunas de controle
$colunas = $pdo->query("SHOW COLUMNS FROM agendamentos")->fetchAll(PDO::FETCH_COLUMN);
$t->verificar('Coluna lembrete_24h_em existe', in_array('lembrete_24h_em', $colunas, true));
$t->verificar('Coluna lembrete_2h_em existe', in_array('lembrete_2h_em', $colunas, true));

// Execução real: cria um agendamento na janela de 24h
$idProf = (int)$pdo->query('SELECT id FROM profissionais WHERE deleted_at IS NULL LIMIT 1')->fetchColumn();

if ($idProf > 0) {
    $pdo->prepare("
        INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                                  hora_inicio, hora_fim, duracao_total_minutos, valor_total, situacao)
        VALUES (?, ?, ?, DATE(DATE_ADD(NOW(), INTERVAL 24 HOUR)),
                ADDTIME(TIME(DATE_ADD(NOW(), INTERVAL 24 HOUR)), '00:10:00'),
                TIME(DATE_ADD(NOW(), INTERVAL 25 HOUR)), 30, 50.00, 'confirmado')
    ")->execute([PREFIXO_TESTE . 'CRON', $idCliente, $idProf]);

    $emailsAntes = count(glob($raiz . '/logs/emails/*.html'));

    exec('"' . PHP_BINARY . '" ' . escapeshellarg($cron) . ' 2>&1', $saidaCron, $codigoCron);
    $saida = implode("\n", $saidaCron);

    $t->igual('Cron executa sem erro', 0, $codigoCron);
    $t->verificar('Cron reporta lembretes de 24h enviados',
        preg_match('/Lembretes de 24h enviados: [1-9]/', $saida) === 1, trim($saida));
    $t->verificar('Lembrete gerou o e-mail',
        count(glob($raiz . '/logs/emails/*.html')) > $emailsAntes);

    $marcado = $pdo->query("SELECT lembrete_24h_em FROM agendamentos
                            WHERE codigo_reserva = '" . PREFIXO_TESTE . "CRON'")->fetchColumn();
    $t->verificar('Envio é marcado no agendamento', !empty($marcado));

    // Idempotência: o Cron roda a cada 15 minutos sobre a mesma faixa
    $emailsDepois = count(glob($raiz . '/logs/emails/*.html'));
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($cron) . ' 2>&1', $saida2, $codigo2);

    $t->igual('Segunda execução não reenvia o lembrete', $emailsDepois,
        count(glob($raiz . '/logs/emails/*.html')));
    $t->verificar('Segunda execução reporta zero envios',
        strpos(implode("\n", $saida2), 'Lembretes de 24h enviados: 0') !== false);
}

// Modo simulação não altera nada
$antesSimulacao = (int)$pdo->query("SELECT COUNT(*) FROM agendamentos WHERE lembrete_2h_em IS NOT NULL")->fetchColumn();
exec('"' . PHP_BINARY . '" ' . escapeshellarg($cron) . ' --simular 2>&1', $saidaSim, $codigoSim);
$t->igual('Modo simulação executa sem erro', 0, $codigoSim);
$t->verificar('Modo simulação é sinalizado na saída',
    strpos(implode("\n", $saidaSim), 'SIMULAÇÃO') !== false);
$t->igual('Modo simulação não marca envios', $antesSimulacao,
    (int)$pdo->query("SELECT COUNT(*) FROM agendamentos WHERE lembrete_2h_em IS NOT NULL")->fetchColumn());

// =====================================================================
$t->grupo('5. HTTP: tela de auditoria');

$principalHttp = $entrar(PREFIXO_TESTE . 'principal@local.test');
$clienteHttp   = $entrar(PREFIXO_TESTE . 'cliente@local.test');

foreach (['auditoria', 'seguranca', 'erros', 'excluidos'] as $abaTela) {
    $principalHttp->get('/admin/auditoria?aba=' . $abaTela);
    $t->igual("Aba `{$abaTela}` responde 200", 200, $principalHttp->ultimoStatus);
    $t->verificar("Aba `{$abaTela}` sem erro de PHP",
        $principalHttp->erroPhp() === null, (string)$principalHttp->erroPhp());
}

$principalHttp->get('/admin/auditoria');
$t->verificar('Tela exibe as quatro abas',
    $principalHttp->corpoContem('Auditoria (') && $principalHttp->corpoContem('Segurança (')
    && $principalHttp->corpoContem('Erros (') && $principalHttp->corpoContem('Excluídos'));
$t->verificar('Tela oferece filtro por autor', $principalHttp->corpoContem('name="usuario"'));
$t->verificar('Tela oferece filtro por entidade', $principalHttp->corpoContem('name="entidade"'));

$t->igual('Cliente é bloqueado na auditoria (403)', 403,
    $clienteHttp->get('/admin/auditoria')->ultimoStatus);
$t->igual('Cliente é bloqueado ao restaurar (403)', 403,
    $clienteHttp->post('/admin/auditoria/restaurar', [
        'csrf_token' => $clienteHttp->csrfDe('/cliente/agendamentos'),
        'tabela' => 'servicos', 'id' => '1',
    ])->ultimoStatus);
$t->igual('POST de restauração sem CSRF é rejeitado', 403,
    $principalHttp->post('/admin/auditoria/restaurar', ['tabela' => 'servicos', 'id' => '1'])->ultimoStatus);

// Restauração pela interface
$servicosM->excluirLogicamente($idServicoAud);
$csrf = $principalHttp->csrfDe('/admin/auditoria?aba=excluidos');
$principalHttp->post('/admin/auditoria/restaurar', [
    'csrf_token' => $csrf, 'tabela' => 'servicos', 'id' => (string)$idServicoAud,
]);
$t->verificar('Restauração pela interface funciona', $servicosM->buscarPorId($idServicoAud) !== null);

// =====================================================================
$t->grupo('6. Acessibilidade');

$publico = new HttpClient($baseUrl);
$publico->get('/inicio');

$t->verificar('Página oferece atalho "pular para o conteúdo"',
    $publico->corpoContem('pular-para-conteudo'));
$t->verificar('Destino do atalho existe', $publico->corpoContem('id="conteudo-principal"'));
$t->verificar('Idioma declarado como pt-BR', $publico->corpoContem('lang="pt-BR"'));

$estilo = file_get_contents($raiz . '/public/assets/css/style.css');
$t->verificar('CSS define foco visível no teclado', strpos($estilo, ':focus-visible') !== false);
$t->verificar('CSS respeita prefers-reduced-motion', strpos($estilo, 'prefers-reduced-motion') !== false);
$t->verificar('CSS garante alvo de toque de 44px no mobile',
    strpos($estilo, 'min-height: 44px') !== false);
$t->verificar('CSS oferece classe para leitor de tela',
    strpos($estilo, '.apenas-leitor-de-tela') !== false);

// Toda View com <main> precisa da âncora do atalho
$semAncora = [];
foreach (glob($raiz . '/app/views/**/*.php') as $view) {
    $conteudo = file_get_contents($view);
    if (strpos($conteudo, '<main ') !== false && strpos($conteudo, 'id="conteudo-principal"') === false) {
        $semAncora[] = basename($view);
    }
}
$t->verificar('Todas as Views com <main> têm a âncora do atalho',
    empty($semAncora), implode(', ', $semAncora));

/**
 * Substitui os blocos PHP por um marcador antes de analisar o HTML.
 *
 * Sem isso a análise erra: `<?= $var ?>` contém `>`, que encerra
 * prematuramente qualquer expressão do tipo `[^>]*` e faz atributos legítimos
 * (alt, id) parecerem ausentes.
 */
$semPhp = function (string $conteudo): string {
    return (string)preg_replace('/<\?(php|=).*?\?>/s', 'PHP', $conteudo);
};

// Toda imagem precisa de alt: vazio se decorativa, descritivo se funcional
$semAlt = [];
foreach (glob($raiz . '/app/views/**/*.php') as $view) {
    $html = $semPhp(file_get_contents($view));

    if (preg_match_all('/<img(?![^>]*\salt=)[^>]*>/i', $html, $imagens)) {
        $semAlt[] = basename($view) . ' (' . count($imagens[0]) . ')';
    }
}
$t->verificar('Toda imagem declara atributo alt', empty($semAlt), implode(', ', $semAlt));

// Todo campo de formulário precisa de rótulo associado
$semRotulo = [];
foreach (glob($raiz . '/app/views/**/*.php') as $view) {
    $html = $semPhp(file_get_contents($view));

    preg_match_all('/<(input|select|textarea)([^>]*)>/i', $html, $campos, PREG_SET_ORDER);

    foreach ($campos as $campo) {
        $atributos = $campo[2];

        // Campos ocultos e botões não exigem rótulo
        if (preg_match('/type="(hidden|submit|button)"/i', $atributos)) {
            continue;
        }

        $temId = preg_match('/\sid="([^"]+)"/', $atributos, $mId);
        $temAria = strpos($atributos, 'aria-label') !== false;

        if (!$temAria && (!$temId || strpos($html, 'for="' . $mId[1] . '"') === false)) {
            $semRotulo[] = basename($view) . ':' . ($mId[1] ?? 'sem-id');
        }
    }
}
$t->verificar('Todo campo tem rótulo ou aria-label',
    empty($semRotulo), implode(', ', array_slice($semRotulo, 0, 6)));

// =====================================================================
// CRITÉRIOS DE ACEITAÇÃO (docs/FSD.md seção 26)
// =====================================================================
$t->grupo('CA 1. Arquitetura MVC e Prepared Statements');

$t->verificar('Front Controller único em index.php', is_file($raiz . '/index.php'));
$t->verificar('Camada de Controllers presente', count(glob($raiz . '/app/controllers/*.php')) >= 10);
$t->verificar('Camada de Models presente', count(glob($raiz . '/app/models/*.php')) >= 12);
$t->verificar('Camada de Views presente', count(glob($raiz . '/app/views/**/*.php')) >= 25);

// Nenhuma View ou Controller pode acessar o banco diretamente
$violacoes = [];
foreach (array_merge(glob($raiz . '/app/views/**/*.php'), glob($raiz . '/app/controllers/*.php')) as $arquivo) {
    $conteudo = file_get_contents($arquivo);

    if (preg_match('/Database::getConnection|new PDO\(|->query\(|->prepare\(/', $conteudo)) {
        $violacoes[] = basename($arquivo);
    }
}
$t->verificar('Nenhuma View ou Controller acessa o banco diretamente',
    empty($violacoes), implode(', ', $violacoes));

// Nenhum Model lê a requisição diretamente: os Controllers sanitizam e
// repassam os valores. É o que garante que nada vindo do usuário chegue à
// SQL por outro caminho que não um parâmetro vinculado.
$modelosComSuperglobal = [];
foreach (glob($raiz . '/app/models/*.php') as $modelo) {
    $conteudo = file_get_contents($modelo);

    if (preg_match('/\$_(GET|POST|REQUEST|COOKIE)\b/', $conteudo)) {
        $modelosComSuperglobal[] = basename($modelo);
    }
}
$t->verificar('Nenhum Model lê $_GET, $_POST ou $_REQUEST',
    empty($modelosComSuperglobal), implode(', ', $modelosComSuperglobal));

// Toda consulta passa por prepare(): a execução direta de string montada
// (query com concatenação) é o que abriria espaço para injeção.
$queryConcatenada = [];
foreach (array_merge(glob($raiz . '/app/models/*.php'), glob($raiz . '/app/helpers/*.php')) as $arquivo) {
    $conteudo = file_get_contents($arquivo);

    // ->query( seguido de concatenação com variável
    if (preg_match('/->query\([^)]*\$[a-z_]/i', $conteudo, $m)) {
        $queryConcatenada[] = basename($arquivo);
    }
}
$t->verificar('Nenhuma consulta monta SQL por concatenação de variável',
    empty($queryConcatenada), implode(', ', $queryConcatenada));

// Interpolação de identificador (nome de tabela/coluna) só é aceitável
// quando o valor vem de uma lista fixa validada no código.
$auditModel = file_get_contents($raiz . '/app/models/AuditModel.php');
$t->verificar('Restauração valida a tabela contra lista fixa antes de interpolar',
    strpos($auditModel, "\$permitidas = ['servicos'") !== false
    && strpos($auditModel, 'in_array($tabela, $permitidas, true)') !== false);

$t->verificar('BaseModel centraliza a execução em prepare/execute',
    strpos(file_get_contents($raiz . '/app/models/BaseModel.php'), '$this->db->prepare($sql)') !== false);

// =====================================================================
$t->grupo('CA 2. Sem arquivo .env e credenciais protegidas');

$t->verificar('Não existe arquivo .env', !is_file($raiz . '/.env'));
$t->verificar('Configuração fica em config/config.php', is_file($raiz . '/config/config.php'));
$t->verificar('config.php exige a constante APP_EXEC',
    strpos(file_get_contents($raiz . '/config/config.php'), 'APP_EXEC') !== false);
$t->verificar('config.php está fora do controle de versão',
    strpos(file_get_contents($raiz . '/.gitignore'), 'config/config.php') !== false);
$t->verificar('Modelo config.example.php versionado', is_file($raiz . '/config/config.example.php'));

// =====================================================================
$t->grupo('CA 3. Proteção dos diretórios internos');

foreach (['/config/config.php', '/app/models/UsuarioModel.php', '/app/controllers/AuthController.php',
          '/database/migrate.php', '/database/cron_lembretes.php', '/logs/error.log',
          '/vendor/PHPMailer/PHPMailer.php', '/tests/teste_fase9.php'] as $caminho) {
    $status = $publico->get($caminho)->ultimoStatus;
    $t->verificar("Acesso direto a {$caminho} é bloqueado",
        in_array($status, [403, 404], true), 'HTTP ' . $status);
}

foreach (['config', 'app', 'database', 'logs', 'vendor', 'tests'] as $pasta) {
    $t->verificar("Pasta {$pasta}/ tem .htaccess", is_file($raiz . '/' . $pasta . '/.htaccess'));
}

// =====================================================================
$t->grupo('CA 4. Migrations automatizadas');

$runner = new MigrationRunner();
$t->verificar('Runner de migrations disponível', class_exists('MigrationRunner'));
$t->igual('Todas as migrations aplicadas', count($runner->arquivosDisponiveis()), count($runner->aplicadas()));
$t->igual('Nenhuma migration pendente', 0, count($runner->pendentes()));
$t->verificar('Tabela de controle existe',
    (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'")->fetchColumn() === 1);

$tabelas = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchColumn();
$t->verificar('As 20 tabelas do FSD existem (+ controle)', (int)$tabelas >= 21, $tabelas . ' tabelas');

// =====================================================================
$t->grupo('CA 5. Motor de agendamento e intervalo de 10 minutos');

$agendamentos = new AgendamentoModel();
$settings = new SettingModel();

$t->igual('Intervalo obrigatório configurado em 10 minutos', 10,
    $settings->getInt('agendamento_intervalo_minutos'));

// Sobreposição é impedida pela regra do FSD 14.8
$conflita = (new ReflectionClass($agendamentos))->getMethod('conflita');
$conflita->setAccessible(true);

$ocupado = [[840, 910]]; // 14:00 às 15:00 + 10 min
$t->verificar('Sobreposição total é detectada', $conflita->invoke($agendamentos, 840, 900, $ocupado));
$t->verificar('Sobreposição parcial no início é detectada', $conflita->invoke($agendamentos, 800, 850, $ocupado));
$t->verificar('Sobreposição parcial no fim é detectada', $conflita->invoke($agendamentos, 900, 950, $ocupado));
$t->verificar('Horário anterior sem toque é liberado', !$conflita->invoke($agendamentos, 700, 840, $ocupado));
$t->verificar('Horário posterior ao intervalo é liberado', !$conflita->invoke($agendamentos, 910, 970, $ocupado));

// =====================================================================
$t->grupo('CA 6. Retenção de checkout por 10 minutos');

$t->igual('Prazo de reserva configurado em 10 minutos', 10, $settings->getInt('checkout_reserva_minutos'));
$t->verificar('Método de expiração disponível', method_exists($agendamentos, 'expirarReservasVencidas'));

$colunas = $pdo->query("SHOW COLUMNS FROM agendamentos")->fetchAll(PDO::FETCH_COLUMN);
$t->verificar('Coluna reserva_expira_em existe', in_array('reserva_expira_em', $colunas, true));

// =====================================================================
$t->grupo('CA 7. Confirmação por webhook ou sandbox');

$t->verificar('WebhookController existe', class_exists('WebhookController'));
$t->verificar('Gateway modular por interface', interface_exists('GatewayPagamento'));
$t->verificar('Simulador disponível para o ambiente local', class_exists('SandboxGateway'));
$t->verificar('Provedor real implementado', class_exists('MercadoPagoGateway'));
$t->verificar('Confirmação idempotente', method_exists('PagamentoModel', 'aprovar'));

$webhookPhp = file_get_contents($raiz . '/app/controllers/WebhookController.php');
$t->verificar('Webhook valida a assinatura', strpos($webhookPhp, 'validarAssinatura') !== false);
$t->verificar('Webhook reconsulta o status no provedor',
    strpos($webhookPhp, '$gateway->consultar') !== false);

// =====================================================================
$t->grupo('CA 8. Regra de 24 horas');

$t->igual('Janela de cancelamento configurada em 24 horas', 24,
    $settings->getInt('cancelamento_janela_horas'));
$t->igual('Limite de reagendamentos configurado em 2', 2, $settings->getInt('reagendamento_limite'));
$t->igual('Validade do crédito interno em 90 dias', 90, $settings->getInt('credito_validade_dias'));

$t->verificar('Verificação da janela implementada', method_exists($agendamentos, 'dentroDaJanelaDe24h'));
$t->verificar('Regra de reagendamento implementada', method_exists($agendamentos, 'podeReagendar'));
$t->verificar('Geração de crédito implementada', method_exists('CreditoModel', 'gerar'));

// =====================================================================
$t->grupo('CA 9. Uploads seguros');

$uploadPhp = file_get_contents($raiz . '/app/helpers/UploadHelper.php');
$t->verificar('Validação real de MIME-type', strpos($uploadPhp, 'finfo_file') !== false);
$t->verificar('Limite de 5MB aplicado', strpos($uploadPhp, '5242880') !== false);
$t->verificar('Renomeação por hash', strpos($uploadPhp, 'md5(uniqid') !== false);

$htaccessUploads = file_get_contents($raiz . '/public/uploads/.htaccess');
$t->verificar('Execução de PHP desativada em uploads',
    strpos($htaccessUploads, 'engine off') !== false);

// =====================================================================
$t->grupo('CA 10. Logs com contingência');

$t->verificar('Tabela error_logs em uso',
    (int)$pdo->query('SELECT COUNT(*) FROM error_logs')->fetchColumn() > 0);
$t->verificar('Tabela security_logs em uso',
    (int)$pdo->query('SELECT COUNT(*) FROM security_logs')->fetchColumn() > 0);
$t->verificar('Arquivo de contingência existe', is_file($raiz . '/logs/error.log'));
$t->verificar('Contingência implementada no LogModel',
    strpos(file_get_contents($raiz . '/app/models/LogModel.php'), 'contingenciaArquivo') !== false);

// =====================================================================
$t->grupo('CA 11. Fidelidade e avaliações');

$fidelidade = $agendamentos->fidelidade($idCliente);
$t->verificar('Cálculo de fidelidade disponível', isset($fidelidade['nivel']));
$t->igual('Cliente sem atendimentos começa em Bronze', 'bronze', $fidelidade['nivel']);

foreach (['insigniabronze.png', 'insigniaprata.png', 'insigniaouro.png'] as $insignia) {
    $t->verificar("Insígnia {$insignia} disponível",
        is_file($raiz . '/public/assets/img/fidelidade/' . $insignia));
}

$avaliacoes = new AvaliacaoModel();
$erroSituacao = '';
try {
    $pdo->prepare("
        INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                                  hora_inicio, hora_fim, duracao_total_minutos, valor_total, situacao)
        VALUES (?, ?, ?, CURDATE(), '10:00:00', '10:30:00', 30, 50.00, 'confirmado')
    ")->execute([PREFIXO_TESTE . 'AVAL', $idCliente, $idProf]);

    $avaliacoes->registrar((int)$pdo->lastInsertId(), $idCliente, 5);
} catch (RuntimeException $e) {
    $erroSituacao = $e->getMessage();
}
$t->verificar('Avaliação exige atendimento concluído',
    strpos($erroSituacao, 'concluídos') !== false, $erroSituacao);

// =====================================================================
$t->grupo('CA 12. Fidelidade visual ao DESIGN.md');

$t->verificar('Paleta Midnight Slate aplicada', strpos($estilo, '#0f1416') !== false);
$t->verificar('Acento Bronze/Gold aplicado', strpos($estilo, '#e9c176') !== false);
$t->verificar('Tokens de cor em variáveis CSS', strpos($estilo, '--color-surface') !== false);
$t->verificar('Raio de 4px nos componentes', strpos($estilo, 'border-radius: 4px') !== false);
$t->verificar('Etiquetas com raio de 8px', strpos($estilo, 'border-radius: 8px') !== false);
$t->verificar('Barra dourada no item de menu ativo',
    strpos($estilo, '.item-menu-ativo') !== false && strpos($estilo, 'border-left-color') !== false);
$t->verificar('Números com alinhamento tabular', strpos($estilo, 'tabular-nums') !== false);
$t->verificar('Tabelas rolam horizontalmente no mobile', strpos($estilo, '.tabela-rolavel') !== false);

// =====================================================================
// Limpeza
// =====================================================================
$principalHttp->encerrar();
$clienteHttp->encerrar();
$publico->encerrar();

limparDadosDeTeste($pdo);
array_map('unlink', glob($raiz . '/logs/emails/*.html'));
echo "\n(Dados de teste removidos do banco.)\n";

exit($t->resumo());

// =====================================================================

function limparDadosDeTeste(PDO $pdo): void {
    $prefixo = PREFIXO_TESTE . '%';

    $pdo->prepare("DELETE FROM avaliacoes WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM agendamento_servicos WHERE agendamento_id IN (
            SELECT id FROM agendamentos WHERE codigo_reserva LIKE ?
               OR cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
        )
    ")->execute([$prefixo, $prefixo]);
    $pdo->prepare("
        DELETE FROM pagamentos WHERE agendamento_id IN (
            SELECT id FROM agendamentos WHERE codigo_reserva LIKE ?
        )
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM agendamentos WHERE codigo_reserva LIKE ?
           OR cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo, $prefixo]);
    $pdo->prepare("DELETE FROM audit_logs WHERE entidade_id IN (SELECT id FROM servicos WHERE nome LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM profissional_servicos WHERE servico_id IN (SELECT id FROM servicos WHERE nome LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM servicos WHERE nome LIKE ?")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM error_logs WHERE mensagem LIKE ?")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM security_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM audit_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM audit_logs WHERE entidade = 'usuarios' AND entidade_id = 999")->execute();
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?")->execute([$prefixo]);
}
