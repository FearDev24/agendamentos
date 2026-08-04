<?php
/**
 * tests/teste_fase4.php - Validação da Fase 4 (Cadastros Administrativos Base)
 *
 * Uso: php tests/teste_fase4.php
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

const PREFIXO_TESTE = 'teste_fase4_';
const SENHA_TESTE = 'SenhaTeste@123';

echo "\n======================================================\n";
echo " TESTES DA FASE 4 - Cadastros Administrativos Base\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

limparDadosDeTeste($pdo);

$usuarios       = new UsuarioModel();
$servicos       = new ServicoModel();
$profissionais  = new ProfissionalModel();
$bloqueios      = new BloqueioModel();

// Contas usadas nos testes de permissão
$idPrincipal = $usuarios->criar([
    'nome' => 'Principal F4', 'email' => PREFIXO_TESTE . 'principal@local.test',
    'telefone' => '(11) 96666-0001', 'senha' => SENHA_TESTE, 'perfil' => 'admin_principal',
]);
$idLimitado = $usuarios->criar([
    'nome' => 'Limitado F4', 'email' => PREFIXO_TESTE . 'limitado@local.test',
    'telefone' => '(11) 96666-0002', 'senha' => SENHA_TESTE, 'perfil' => 'admin_limitado',
]);
$idCliente = $usuarios->criar([
    'nome' => 'Cliente F4', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 96666-0003', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);

// =====================================================================
$t->grupo('1. Estrutura da Fase 4');

foreach (['ServicoModel', 'ProfissionalModel', 'BloqueioModel', 'AuditModel', 'ServicoController'] as $classe) {
    $t->verificar("Classe `{$classe}` carregada", class_exists($classe));
}

foreach (['admin/servicos', 'admin/profissionais', 'admin/bloqueios'] as $view) {
    $t->verificar("View `{$view}.php` existe", file_exists(__DIR__ . "/../app/views/{$view}.php"));
}

// =====================================================================
$t->grupo('2. ServicoModel: CRUD, Soft Delete e restauração');

$idServico = $servicos->criar([
    'nome' => PREFIXO_TESTE . 'Corte Premium', 'descricao' => 'Descrição de teste',
    'duracao_minutos' => 45, 'preco' => 75.50, 'orientacoes' => 'Orientação de teste',
    'status' => 'ativo', 'previsao_retorno' => '',
]);
$t->verificar('Serviço criado com ID válido', $idServico > 0);

$servico = $servicos->buscarPorId($idServico);
$t->igual('Nome gravado', PREFIXO_TESTE . 'Corte Premium', $servico['nome']);
$t->igual('Duração gravada', 45, (int)$servico['duracao_minutos']);
$t->igual('Preço gravado com 2 casas decimais', '75.50', $servico['preco']);
$t->igual('Status padrão aplicado', 'ativo', $servico['status']);
$t->verificar('previsao_retorno vazia é gravada como NULL', $servico['previsao_retorno'] === null);

$t->verificar('nomeEmUso() detecta nome duplicado', $servicos->nomeEmUso(PREFIXO_TESTE . 'Corte Premium'));
$t->verificar('nomeEmUso() ignora o próprio registro', !$servicos->nomeEmUso(PREFIXO_TESTE . 'Corte Premium', $idServico));

// Atualização preserva a imagem quando nenhum arquivo novo é enviado
$pdo->prepare('UPDATE servicos SET imagem = ? WHERE id = ?')->execute(['imagem_antiga.png', $idServico]);
$servicos->atualizar($idServico, [
    'nome' => PREFIXO_TESTE . 'Corte Premium', 'descricao' => 'Nova descrição',
    'duracao_minutos' => 60, 'preco' => 90.00, 'orientacoes' => '',
    'status' => 'indisponivel', 'previsao_retorno' => '2027-01-15',
]);
$atualizado = $servicos->buscarPorId($idServico);
$t->igual('Duração atualizada', 60, (int)$atualizado['duracao_minutos']);
$t->igual('Preço atualizado', '90.00', $atualizado['preco']);
$t->igual('Status atualizado', 'indisponivel', $atualizado['status']);
$t->igual('Previsão de retorno gravada', '2027-01-15', $atualizado['previsao_retorno']);
$t->verificar('Orientações em branco viram NULL', $atualizado['orientacoes'] === null);
$t->igual('Imagem NÃO é apagada quando não há upload novo', 'imagem_antiga.png', $atualizado['imagem']);

// Serviço indisponível não entra na vitrine
$ativosNomes = array_column($servicos->listarAtivos(), 'nome');
$t->verificar('Serviço "indisponivel" fica fora de listarAtivos()',
    !in_array(PREFIXO_TESTE . 'Corte Premium', $ativosNomes, true));

// Soft Delete e restauração
$servicos->excluirLogicamente($idServico);
$t->verificar('Soft Delete: serviço some das buscas', $servicos->buscarPorId($idServico) === null);
$t->verificar('Soft Delete: registro permanece no banco',
    $pdo->query("SELECT COUNT(*) FROM servicos WHERE id = {$idServico}")->fetchColumn() == 1);
$t->verificar('Serviço excluído aparece em listarExcluidos()',
    in_array($idServico, array_map('intval', array_column($servicos->listarExcluidos(), 'id')), true));

$servicos->restaurar($idServico);
$t->verificar('Restauração devolve o serviço às buscas', $servicos->buscarPorId($idServico) !== null);
$t->verificar('Restaurar duas vezes não gera efeito', !$servicos->restaurar($idServico));

// Filtros da listagem
$servicos->criar([
    'nome' => PREFIXO_TESTE . 'Barba Teste', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 40, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);
$t->igual('Filtro de busca por nome funciona', 1, count($servicos->listar(PREFIXO_TESTE . 'Barba')));
$t->verificar('Filtro por status funciona',
    count($servicos->listar(PREFIXO_TESTE, 'indisponivel')) >= 1);

// =====================================================================
$t->grupo('3. ProfissionalModel: conta de acesso, jornada e serviços');

$idProfissional = $profissionais->criar([
    'nome' => 'Barbeiro Teste', 'email' => PREFIXO_TESTE . 'barbeiro@local.test',
    'telefone' => '(11) 95555-0001', 'senha' => SENHA_TESTE,
    'limite_diario_atendimentos' => '8', 'status' => 'ativo',
]);
$t->verificar('Profissional criado com ID válido', $idProfissional > 0);

$prof = $profissionais->buscarPorId($idProfissional);
$t->igual('Nome vem da tabela usuarios via JOIN', 'Barbeiro Teste', $prof['nome']);
$t->igual('Limite diário gravado', 8, (int)$prof['limite_diario_atendimentos']);

$contaAcesso = $usuarios->buscarPorEmail(PREFIXO_TESTE . 'barbeiro@local.test');
$t->verificar('Conta de login foi criada junto', $contaAcesso !== null);
$t->igual('Conta criada com perfil "profissional"', 'profissional', $contaAcesso['perfil']);
$t->verificar('Senha do profissional gravada com hash', strpos($contaAcesso['senha_hash'], '$2y$') === 0);
$t->verificar('buscarPorUsuarioId() localiza o registro operacional',
    $profissionais->buscarPorUsuarioId((int)$contaAcesso['id']) !== null);

// Transação: e-mail duplicado não pode deixar usuário órfão
$totalUsuariosAntes = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$falhou = false;
try {
    $profissionais->criar([
        'nome' => 'Duplicado', 'email' => PREFIXO_TESTE . 'barbeiro@local.test',
        'telefone' => '(11) 95555-0002', 'senha' => SENHA_TESTE,
        'limite_diario_atendimentos' => '', 'status' => 'ativo',
    ]);
} catch (Throwable $e) {
    $falhou = true;
}
$totalUsuariosDepois = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$t->verificar('Criação com e-mail duplicado é rejeitada', $falhou);
$t->igual('Rollback impede usuário órfão sem registro de profissional',
    $totalUsuariosAntes, $totalUsuariosDepois);

// Atualização
$profissionais->atualizar($idProfissional, [
    'nome' => 'Barbeiro Renomeado', 'email' => PREFIXO_TESTE . 'barbeiro@local.test',
    'telefone' => '(11) 95555-0009', 'senha' => '',
    'limite_diario_atendimentos' => '', 'status' => 'inativo',
]);
$prof = $profissionais->buscarPorId($idProfissional);
$t->igual('Nome atualizado na tabela usuarios', 'Barbeiro Renomeado', $prof['nome']);
$t->igual('Telefone atualizado', '(11) 95555-0009', $prof['telefone']);
$t->verificar('Limite em branco vira NULL (sem limite)', $prof['limite_diario_atendimentos'] === null);
$t->igual('Status atualizado', 'inativo', $prof['status']);

$contaAposUpdate = $usuarios->buscarPorEmail(PREFIXO_TESTE . 'barbeiro@local.test');
$t->verificar('Senha NÃO muda quando o campo vem vazio',
    $usuarios->verificarSenha($contaAposUpdate, SENHA_TESTE));

// Vínculo N:N com serviços
$idsServicos = array_map('intval', array_column($servicos->listar(PREFIXO_TESTE), 'id'));
$profissionais->sincronizarServicos($idProfissional, $idsServicos);
$t->igual('Serviços vinculados ao profissional', count($idsServicos),
    count($profissionais->idsServicosVinculados($idProfissional)));

$profissionais->sincronizarServicos($idProfissional, [$idsServicos[0]]);
$t->igual('Sincronizar substitui a lista (não acumula)', 1,
    count($profissionais->idsServicosVinculados($idProfissional)));

$profissionais->sincronizarServicos($idProfissional, [$idsServicos[0], $idsServicos[0]]);
$t->igual('IDs duplicados são descartados', 1,
    count($profissionais->idsServicosVinculados($idProfissional)));

$profissionais->sincronizarServicos($idProfissional, []);
$t->igual('Lista vazia remove todos os vínculos', 0,
    count($profissionais->idsServicosVinculados($idProfissional)));

$profissionais->sincronizarServicos($idProfissional, $idsServicos);
$t->igual('Serviço lista os profissionais habilitados', 1,
    count($servicos->profissionaisHabilitados($idsServicos[0])));

// Grade horária semanal
$profissionais->salvarHorarios($idProfissional, [
    1 => ['hora_inicio' => '09:00', 'hora_fim' => '18:00', 'almoco_inicio' => '12:00', 'almoco_fim' => '13:00'],
    2 => ['hora_inicio' => '09:00', 'hora_fim' => '18:00', 'almoco_inicio' => null, 'almoco_fim' => null],
    6 => ['hora_inicio' => '08:00', 'hora_fim' => '14:00', 'almoco_inicio' => null, 'almoco_fim' => null],
]);

$grade = $profissionais->horariosPorDia($idProfissional);
$t->igual('Três dias de trabalho gravados', 3, count($grade));
$t->verificar('Domingo (0) fica sem jornada = folga', !isset($grade[0]));
$t->igual('Segunda: início gravado', '09:00:00', $grade[1]['hora_inicio']);
$t->igual('Segunda: almoço gravado', '12:00:00', $grade[1]['almoco_inicio']);
$t->verificar('Terça sem almoço fica NULL', $grade[2]['almoco_inicio'] === null);
$t->igual('Sábado: jornada reduzida gravada', '14:00:00', $grade[6]['hora_fim']);

$profissionais->salvarHorarios($idProfissional, [
    1 => ['hora_inicio' => '10:00', 'hora_fim' => '17:00', 'almoco_inicio' => null, 'almoco_fim' => null],
]);
$grade = $profissionais->horariosPorDia($idProfissional);
$t->igual('Salvar a jornada substitui a anterior por completo', 1, count($grade));

// =====================================================================
$t->grupo('4. Bloqueios de agenda e detecção de conflitos');

$idBloqueioGeral = $bloqueios->criar([
    'profissional_id' => null,
    'data_inicio' => '2027-12-25 00:00:00', 'data_fim' => '2027-12-25 23:59:00',
    'motivo' => PREFIXO_TESTE . 'Natal', 'tipo' => 'feriado',
]);
$t->verificar('Bloqueio geral criado (profissional_id NULL)',
    $bloqueios->buscarPorId($idBloqueioGeral)['profissional_id'] === null);

$idBloqueioProf = $bloqueios->criar([
    'profissional_id' => $idProfissional,
    'data_inicio' => '2027-06-10 08:00:00', 'data_fim' => '2027-06-10 12:00:00',
    'motivo' => PREFIXO_TESTE . 'Consulta médica', 'tipo' => 'folga',
]);
$t->verificar('Bloqueio individual criado', $bloqueios->buscarPorId($idBloqueioProf) !== null);

$t->igual('Filtro por profissional funciona', 1, count($bloqueios->listar($idProfissional)));
$t->igual('Filtro por período encontra o bloqueio', 1,
    count($bloqueios->listar($idProfissional, '2027-06-01', '2027-06-30')));
$t->igual('Filtro por período fora da janela não retorna nada', 0,
    count($bloqueios->listar($idProfissional, '2027-07-01', '2027-07-31')));

// Detecção de agendamentos afetados pelo bloqueio (regra de sobreposição do FSD)
$pdo->prepare("
    INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                              hora_inicio, hora_fim, duracao_total_minutos, valor_total, situacao)
    VALUES (?, ?, ?, '2027-06-10', '09:00:00', '10:00:00', 60, 90.00, 'confirmado')
")->execute([PREFIXO_TESTE . 'AG1', $idCliente, $idProfissional]);

$t->igual('Agendamento DENTRO da janela é detectado', 1,
    count($bloqueios->agendamentosNoPeriodo($idProfissional, '2027-06-10 08:00:00', '2027-06-10 12:00:00')));
$t->igual('Sobreposição PARCIAL no início é detectada', 1,
    count($bloqueios->agendamentosNoPeriodo($idProfissional, '2027-06-10 09:30:00', '2027-06-10 11:00:00')));
$t->igual('Janela ANTERIOR ao agendamento não conflita', 0,
    count($bloqueios->agendamentosNoPeriodo($idProfissional, '2027-06-10 07:00:00', '2027-06-10 09:00:00')));
$t->igual('Janela POSTERIOR ao agendamento não conflita', 0,
    count($bloqueios->agendamentosNoPeriodo($idProfissional, '2027-06-10 10:00:00', '2027-06-10 12:00:00')));

$pdo->prepare("UPDATE agendamentos SET situacao = 'cancelado' WHERE codigo_reserva = ?")
    ->execute([PREFIXO_TESTE . 'AG1']);
$t->igual('Agendamento cancelado não é contado como conflito', 0,
    count($bloqueios->agendamentosNoPeriodo($idProfissional, '2027-06-10 08:00:00', '2027-06-10 12:00:00')));

$bloqueios->excluir($idBloqueioGeral);
$t->verificar('Bloqueio removido libera a data', $bloqueios->buscarPorId($idBloqueioGeral) === null);

// =====================================================================
$t->grupo('5. Exclusão de profissional com agendamentos futuros');

$t->verificar('Sem agendamento futuro: exclusão liberada',
    !$profissionais->possuiAgendamentosFuturosAtivos($idProfissional));

$pdo->prepare("
    INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                              hora_inicio, hora_fim, duracao_total_minutos, valor_total, situacao)
    VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', '15:00:00', 60, 90.00, 'confirmado')
")->execute([PREFIXO_TESTE . 'AG2', $idCliente, $idProfissional]);

$t->verificar('Com agendamento futuro: exclusão bloqueada',
    $profissionais->possuiAgendamentosFuturosAtivos($idProfissional));

$pdo->prepare("DELETE FROM agendamentos WHERE codigo_reserva = ?")->execute([PREFIXO_TESTE . 'AG2']);

// =====================================================================
$t->grupo('6. Auditoria (audit_logs)');

$auditoria = new AuditModel();
SessionHelper::set('user_id', $idPrincipal);

$auditoria->registrar(AuditModel::ATUALIZAR, 'servicos', $idServico,
    ['preco' => '75.50', 'senha_hash' => 'NAO_DEVE_APARECER'],
    ['preco' => '90.00']);

$registros = $auditoria->listarPorEntidade('servicos', $idServico);
$t->verificar('Evento de auditoria registrado', count($registros) > 0);
$t->igual('Ação registrada corretamente', 'atualizar', $registros[0]['acao']);
$t->igual('Autoria registrada', $idPrincipal, (int)$registros[0]['usuario_id']);
$t->verificar('Dados anteriores gravados em JSON',
    json_decode($registros[0]['dados_anteriores'], true)['preco'] === '75.50');
$t->verificar('Campos sensíveis são removidos antes de auditar',
    strpos($registros[0]['dados_anteriores'], 'NAO_DEVE_APARECER') === false);

SessionHelper::remove('user_id');

// =====================================================================
$t->grupo('7. HTTP: RBAC dos cadastros administrativos');

$entrarComo = function (string $email) use ($baseUrl): HttpClient {
    $c = new HttpClient($baseUrl);
    $csrf = $c->csrfDe('/login');
    $c->post('/login', ['csrf_token' => $csrf, 'email' => $email, 'senha' => SENHA_TESTE]);
    return $c;
};

$principalHttp = $entrarComo(PREFIXO_TESTE . 'principal@local.test');
$limitadoHttp  = $entrarComo(PREFIXO_TESTE . 'limitado@local.test');
$clienteHttp   = $entrarComo(PREFIXO_TESTE . 'cliente@local.test');
$visitante     = new HttpClient($baseUrl);

// Administrador Principal: acesso total
$t->igual('Admin Principal ACESSA /admin/servicos', 200, $principalHttp->get('/admin/servicos')->ultimoStatus);
$t->igual('Admin Principal ACESSA /admin/profissionais', 200, $principalHttp->get('/admin/profissionais')->ultimoStatus);
$t->igual('Admin Principal ACESSA /admin/bloqueios', 200, $principalHttp->get('/admin/bloqueios')->ultimoStatus);

// Administrador Limitado: sem cadastros, com bloqueios (matriz do FSD seção 8)
$t->igual('Admin Limitado é BLOQUEADO em /admin/servicos (403)', 403, $limitadoHttp->get('/admin/servicos')->ultimoStatus);
$t->igual('Admin Limitado é BLOQUEADO em /admin/profissionais (403)', 403, $limitadoHttp->get('/admin/profissionais')->ultimoStatus);
$t->igual('Admin Limitado ACESSA /admin/bloqueios', 200, $limitadoHttp->get('/admin/bloqueios')->ultimoStatus);

// Cliente e visitante
$t->igual('Cliente é BLOQUEADO em /admin/servicos (403)', 403, $clienteHttp->get('/admin/servicos')->ultimoStatus);
$t->igual('Cliente é BLOQUEADO em /admin/bloqueios (403)', 403, $clienteHttp->get('/admin/bloqueios')->ultimoStatus);
$t->igual('Visitante em /admin/servicos é redirecionado', 302, $visitante->get('/admin/servicos')->ultimoStatus);

// POST protegido por RBAC, não só a tela
$csrfLimitado = $limitadoHttp->csrfDe('/admin/bloqueios');
$t->igual('Admin Limitado é BLOQUEADO no POST de criar serviço', 403,
    $limitadoHttp->post('/admin/servicos/salvar', ['csrf_token' => $csrfLimitado, 'nome' => 'Invasao'])->ultimoStatus);
$t->igual('Admin Limitado é BLOQUEADO no POST de excluir profissional', 403,
    $limitadoHttp->post('/admin/profissionais/excluir', ['csrf_token' => $csrfLimitado, 'id' => $idProfissional])->ultimoStatus);
$t->verificar('Nenhum serviço foi criado pela tentativa do Admin Limitado',
    !$servicos->nomeEmUso('Invasao'));

// =====================================================================
$t->grupo('8. HTTP: proteção CSRF nos formulários da Fase 4');

foreach ([
    '/admin/servicos/salvar', '/admin/servicos/excluir', '/admin/servicos/restaurar',
    '/admin/profissionais/salvar', '/admin/profissionais/excluir',
    '/admin/profissionais/servicos', '/admin/profissionais/horarios',
    '/admin/bloqueios/salvar', '/admin/bloqueios/excluir',
] as $rota) {
    $t->igual("POST {$rota} sem CSRF é rejeitado", 403,
        $principalHttp->post($rota, ['id' => 1])->ultimoStatus);
}

// =====================================================================
$t->grupo('9. HTTP: fluxo completo de cadastro de serviço');

$nomeNovo = PREFIXO_TESTE . 'Servico Via HTTP';
$csrf = $principalHttp->csrfDe('/admin/servicos');
$principalHttp->post('/admin/servicos/salvar', [
    'csrf_token' => $csrf, 'id' => '0', 'nome' => $nomeNovo, 'descricao' => 'Criado por HTTP',
    'duracao_minutos' => '30', 'preco' => '55,90', 'orientacoes' => '', 'status' => 'ativo',
    'previsao_retorno' => '',
]);

$criado = $pdo->prepare('SELECT * FROM servicos WHERE nome = ?');
$criado->execute([$nomeNovo]);
$criado = $criado->fetch();
$t->verificar('Serviço criado pela interface HTTP', $criado !== false);
$t->igual('Preço com vírgula é convertido corretamente', '55.90', $criado['preco'] ?? null);

$idHttp = (int)$criado['id'];
$t->verificar('Criação gerou registro em audit_logs',
    (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entidade='servicos' AND entidade_id={$idHttp} AND acao='criar'")->fetchColumn() > 0);

// Edição altera o preço e deve ser auditada
$csrf = $principalHttp->csrfDe('/admin/servicos?editar=' . $idHttp);
$principalHttp->post('/admin/servicos/salvar', [
    'csrf_token' => $csrf, 'id' => (string)$idHttp, 'nome' => $nomeNovo, 'descricao' => '',
    'duracao_minutos' => '30', 'preco' => '99.00', 'orientacoes' => '', 'status' => 'ativo',
    'previsao_retorno' => '',
]);
$t->igual('Preço alterado pela interface', '99.00',
    $pdo->query("SELECT preco FROM servicos WHERE id={$idHttp}")->fetchColumn());
$t->verificar('Alteração de preço registrada na auditoria',
    (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entidade='servicos' AND entidade_id={$idHttp} AND acao='atualizar'")->fetchColumn() > 0);

// Exclusão e restauração via HTTP
$csrf = $principalHttp->csrfDe('/admin/servicos');
$principalHttp->post('/admin/servicos/excluir', ['csrf_token' => $csrf, 'id' => (string)$idHttp]);
$t->verificar('Exclusão via HTTP aplica Soft Delete', $servicos->buscarPorId($idHttp) === null);

$csrf = $principalHttp->csrfDe('/admin/servicos');
$principalHttp->post('/admin/servicos/restaurar', ['csrf_token' => $csrf, 'id' => (string)$idHttp]);
$t->verificar('Restauração via HTTP devolve o serviço', $servicos->buscarPorId($idHttp) !== null);

// =====================================================================
$t->grupo('10. HTTP: validações do formulário de serviços');

$casos = [
    'nome com menos de 3 caracteres' => ['nome' => 'AB', 'duracao_minutos' => '30', 'preco' => '10', 'status' => 'ativo'],
    'duração abaixo do mínimo (5 min)' => ['nome' => PREFIXO_TESTE . 'X1', 'duracao_minutos' => '2', 'preco' => '10', 'status' => 'ativo'],
    'duração acima do máximo (480 min)' => ['nome' => PREFIXO_TESTE . 'X2', 'duracao_minutos' => '600', 'preco' => '10', 'status' => 'ativo'],
    'preço negativo' => ['nome' => PREFIXO_TESTE . 'X3', 'duracao_minutos' => '30', 'preco' => '-5', 'status' => 'ativo'],
    'preço não numérico' => ['nome' => PREFIXO_TESTE . 'X4', 'duracao_minutos' => '30', 'preco' => 'abc', 'status' => 'ativo'],
    'status fora do ENUM' => ['nome' => PREFIXO_TESTE . 'X5', 'duracao_minutos' => '30', 'preco' => '10', 'status' => 'hackeado'],
    'data de retorno inválida' => ['nome' => PREFIXO_TESTE . 'X6', 'duracao_minutos' => '30', 'preco' => '10', 'status' => 'ativo', 'previsao_retorno' => '2027-13-45'],
];

foreach ($casos as $descricao => $campos) {
    $csrf = $principalHttp->csrfDe('/admin/servicos');
    $principalHttp->post('/admin/servicos/salvar', array_merge(
        ['csrf_token' => $csrf, 'id' => '0', 'descricao' => '', 'orientacoes' => '', 'previsao_retorno' => ''],
        $campos
    ));

    $existe = (int)$pdo->query("SELECT COUNT(*) FROM servicos WHERE nome = " . $pdo->quote($campos['nome']))->fetchColumn();
    $t->igual("Rejeita {$descricao}", 0, $existe);
}

// Nome duplicado
$csrf = $principalHttp->csrfDe('/admin/servicos');
$principalHttp->post('/admin/servicos/salvar', [
    'csrf_token' => $csrf, 'id' => '0', 'nome' => $nomeNovo, 'descricao' => '',
    'duracao_minutos' => '30', 'preco' => '10', 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);
$t->igual('Rejeita nome de serviço duplicado', 1,
    (int)$pdo->query("SELECT COUNT(*) FROM servicos WHERE nome = " . $pdo->quote($nomeNovo) . " AND deleted_at IS NULL")->fetchColumn());

// =====================================================================
$t->grupo('11. HTTP: validações de jornada e de bloqueios');

// Jornada inválida
$csrf = $principalHttp->csrfDe('/admin/profissionais?editar=' . $idProfissional);
$principalHttp->post('/admin/profissionais/horarios', [
    'csrf_token' => $csrf, 'id' => (string)$idProfissional,
    'dia_ativo' => [3 => '1'], 'hora_inicio' => [3 => '18:00'], 'hora_fim' => [3 => '09:00'],
    'almoco_inicio' => [3 => ''], 'almoco_fim' => [3 => ''],
]);
$t->verificar('Rejeita jornada com término antes do início',
    !isset($profissionais->horariosPorDia($idProfissional)[3]));

$csrf = $principalHttp->csrfDe('/admin/profissionais?editar=' . $idProfissional);
$principalHttp->post('/admin/profissionais/horarios', [
    'csrf_token' => $csrf, 'id' => (string)$idProfissional,
    'dia_ativo' => [4 => '1'], 'hora_inicio' => [4 => '09:00'], 'hora_fim' => [4 => '18:00'],
    'almoco_inicio' => [4 => '19:00'], 'almoco_fim' => [4 => '20:00'],
]);
$t->verificar('Rejeita almoço fora da jornada de trabalho',
    !isset($profissionais->horariosPorDia($idProfissional)[4]));

$csrf = $principalHttp->csrfDe('/admin/profissionais?editar=' . $idProfissional);
$principalHttp->post('/admin/profissionais/horarios', [
    'csrf_token' => $csrf, 'id' => (string)$idProfissional,
    'dia_ativo' => [5 => '1'], 'hora_inicio' => [5 => '09:00'], 'hora_fim' => [5 => '18:00'],
    'almoco_inicio' => [5 => '12:00'], 'almoco_fim' => [5 => ''],
]);
$t->verificar('Rejeita intervalo de almoço pela metade',
    !isset($profissionais->horariosPorDia($idProfissional)[5]));

// Jornada válida
$csrf = $principalHttp->csrfDe('/admin/profissionais?editar=' . $idProfissional);
$principalHttp->post('/admin/profissionais/horarios', [
    'csrf_token' => $csrf, 'id' => (string)$idProfissional,
    'dia_ativo' => [1 => '1', 3 => '1'],
    'hora_inicio' => [1 => '09:00', 3 => '10:00'], 'hora_fim' => [1 => '18:00', 3 => '19:00'],
    'almoco_inicio' => [1 => '12:00', 3 => ''], 'almoco_fim' => [1 => '13:00', 3 => ''],
]);
$gradeSalva = $profissionais->horariosPorDia($idProfissional);
$t->igual('Jornada válida é salva para os 2 dias marcados', 2, count($gradeSalva));
$t->igual('Horário de início da quarta gravado', '10:00:00', $gradeSalva[3]['hora_inicio'] ?? null);

// Bloqueios inválidos
$antesBloqueios = count($bloqueios->listar());

$csrf = $principalHttp->csrfDe('/admin/bloqueios');
$principalHttp->post('/admin/bloqueios/salvar', [
    'csrf_token' => $csrf, 'profissional_id' => '',
    'data_inicio' => '2027-05-10T14:00', 'data_fim' => '2027-05-10T09:00',
    'motivo' => PREFIXO_TESTE . 'invertido', 'tipo' => 'folga',
]);
$t->igual('Rejeita bloqueio com término antes do início', $antesBloqueios, count($bloqueios->listar()));

$csrf = $principalHttp->csrfDe('/admin/bloqueios');
$principalHttp->post('/admin/bloqueios/salvar', [
    'csrf_token' => $csrf, 'profissional_id' => '',
    'data_inicio' => '2027-05-10T09:00', 'data_fim' => '2027-05-10T14:00',
    'motivo' => 'ab', 'tipo' => 'folga',
]);
$t->igual('Rejeita bloqueio com motivo muito curto', $antesBloqueios, count($bloqueios->listar()));

$csrf = $principalHttp->csrfDe('/admin/bloqueios');
$principalHttp->post('/admin/bloqueios/salvar', [
    'csrf_token' => $csrf, 'profissional_id' => '',
    'data_inicio' => '2027-05-10T09:00', 'data_fim' => '2027-05-10T14:00',
    'motivo' => PREFIXO_TESTE . 'tipo invalido', 'tipo' => 'tipo_inexistente',
]);
$t->igual('Rejeita tipo de bloqueio fora do ENUM', $antesBloqueios, count($bloqueios->listar()));

// Bloqueio válido
$csrf = $principalHttp->csrfDe('/admin/bloqueios');
$principalHttp->post('/admin/bloqueios/salvar', [
    'csrf_token' => $csrf, 'profissional_id' => (string)$idProfissional,
    'data_inicio' => '2027-05-10T09:00', 'data_fim' => '2027-05-10T14:00',
    'motivo' => PREFIXO_TESTE . 'bloqueio valido', 'tipo' => 'bloqueio_parcial',
]);
$t->igual('Bloqueio válido é criado', $antesBloqueios + 1, count($bloqueios->listar()));

// =====================================================================
$t->grupo('12. HTTP: renderização das telas');

$principalHttp->get('/admin/servicos');
$t->verificar('Tela de serviços lista os cadastros', $principalHttp->corpoContem('Serviços Cadastrados'));
$t->verificar('Tela de serviços traz o formulário de novo serviço', $principalHttp->corpoContem('Novo Serviço'));

$principalHttp->get('/admin/profissionais?editar=' . $idProfissional);
$t->verificar('Edição de profissional exibe os serviços executados', $principalHttp->corpoContem('Serviços Executados'));
$t->verificar('Edição de profissional exibe a jornada semanal', $principalHttp->corpoContem('Jornada Semanal'));
$t->verificar('Todos os 7 dias da semana aparecem no formulário',
    $principalHttp->corpoContem('Domingo') && $principalHttp->corpoContem('Sábado'));
$t->verificar('Senha do profissional NÃO é devolvida no HTML',
    !$principalHttp->corpoContem(SENHA_TESTE));

$principalHttp->get('/admin/bloqueios');
$t->verificar('Tela de bloqueios oferece a opção de estabelecimento inteiro',
    $principalHttp->corpoContem('Todo o estabelecimento'));

// XSS nos campos administrativos
$idXss = $servicos->criar([
    'nome' => PREFIXO_TESTE . '<script>alert(9)</script>', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 10, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);
$principalHttp->get('/admin/servicos');
$t->verificar('Nome de serviço malicioso NÃO é renderizado como script',
    !$principalHttp->corpoContem('<script>alert(9)</script>'));
$t->verificar('Conteúdo perigoso aparece escapado', $principalHttp->corpoContem('&lt;script&gt;'));

// =====================================================================
// Limpeza
// =====================================================================
$principalHttp->encerrar();
$limitadoHttp->encerrar();
$clienteHttp->encerrar();
$visitante->encerrar();

limparDadosDeTeste($pdo);
echo "\n(Dados de teste removidos do banco.)\n";

exit($t->resumo());

// =====================================================================

function limparDadosDeTeste(PDO $pdo): void {
    $prefixo = PREFIXO_TESTE . '%';

    $pdo->prepare("DELETE FROM agendamentos WHERE codigo_reserva LIKE ?")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM indisponibilidades_bloqueios WHERE motivo LIKE ?")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM indisponibilidades_bloqueios
        WHERE profissional_id IN (
            SELECT p.id FROM profissionais p
            INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
        )
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM profissional_servicos WHERE servico_id IN (SELECT id FROM servicos WHERE nome LIKE ?)
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM profissional_servicos WHERE profissional_id IN (
            SELECT p.id FROM profissionais p
            INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
        )
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM horarios_trabalho WHERE profissional_id IN (
            SELECT p.id FROM profissionais p
            INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
        )
    ")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM audit_logs WHERE entidade_id IN (SELECT id FROM servicos WHERE nome LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM servicos WHERE nome LIKE ?")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM audit_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM security_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM security_logs WHERE detalhes LIKE ?")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM profissionais WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?")->execute([$prefixo]);
}
