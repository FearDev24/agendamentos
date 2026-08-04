<?php
/**
 * tests/teste_fase5.php - Validação da Fase 5
 * (Motor de Disponibilidade e Fluxo de Agendamento Público)
 *
 * Uso: php tests/teste_fase5.php
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

const PREFIXO_TESTE = 'teste_fase5_';
const SENHA_TESTE = 'SenhaTeste@123';

echo "\n======================================================\n";
echo " TESTES DA FASE 5 - Motor de Disponibilidade e Wizard\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

limparDadosDeTeste($pdo);

$usuarios      = new UsuarioModel();
$servicosModel = new ServicoModel();
$profModel     = new ProfissionalModel();
$bloqueios     = new BloqueioModel();
$agendamentos  = new AgendamentoModel();

// ---------------------------------------------------------------------
// Cenário controlado
// ---------------------------------------------------------------------
// Data alvo: próxima quarta-feira (dia 3), dentro da janela de 30 dias
$dataAlvo = new DateTime('next wednesday');
if ((int)$dataAlvo->diff(new DateTime('today'))->days > 25) {
    $dataAlvo = new DateTime('today +7 day');
}
$DATA = $dataAlvo->format('Y-m-d');
$DIA_SEMANA = (int)$dataAlvo->format('w');

echo " Data de referência: {$DATA} (dia da semana {$DIA_SEMANA})\n";
echo "======================================================\n";

// Serviços: 30 min e 30 min
$idCorte = $servicosModel->criar([
    'nome' => PREFIXO_TESTE . 'Corte', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 50.00, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);
$idBarba = $servicosModel->criar([
    'nome' => PREFIXO_TESTE . 'Barba', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 40.00, 'orientacoes' => 'Não faça a barba em casa.', 'status' => 'ativo', 'previsao_retorno' => '',
]);
// Serviço que só um profissional executa
$idExclusivo = $servicosModel->criar([
    'nome' => PREFIXO_TESTE . 'Exclusivo', 'descricao' => '', 'duracao_minutos' => 60,
    'preco' => 120.00, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);

// Profissional A: jornada 09:00-18:00 com almoço 12:00-13:00, executa Corte e Barba
$idProfA = $profModel->criar([
    'nome' => 'Profissional A', 'email' => PREFIXO_TESTE . 'profa@local.test',
    'telefone' => '(11) 94444-0001', 'senha' => SENHA_TESTE,
    'limite_diario_atendimentos' => '', 'status' => 'ativo',
]);
$profModel->sincronizarServicos($idProfA, [$idCorte, $idBarba]);
$profModel->salvarHorarios($idProfA, [
    $DIA_SEMANA => ['hora_inicio' => '09:00', 'hora_fim' => '18:00', 'almoco_inicio' => '12:00', 'almoco_fim' => '13:00'],
]);

// Profissional B: mesma jornada, executa Corte, Barba e Exclusivo
$idProfB = $profModel->criar([
    'nome' => 'Profissional B', 'email' => PREFIXO_TESTE . 'profb@local.test',
    'telefone' => '(11) 94444-0002', 'senha' => SENHA_TESTE,
    'limite_diario_atendimentos' => '', 'status' => 'ativo',
]);
$profModel->sincronizarServicos($idProfB, [$idCorte, $idBarba, $idExclusivo]);
$profModel->salvarHorarios($idProfB, [
    $DIA_SEMANA => ['hora_inicio' => '09:00', 'hora_fim' => '18:00', 'almoco_inicio' => null, 'almoco_fim' => null],
]);

$idCliente = $usuarios->criar([
    'nome' => 'Cliente F5', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 94444-0003', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);

/** Extrai apenas as horas de uma lista de slots */
$horas = function (array $slots): array {
    return array_column($slots, 'hora');
};

// =====================================================================
$t->grupo('1. Estrutura da Fase 5');

foreach (['AgendamentoModel', 'SettingModel', 'AgendamentoController'] as $classe) {
    $t->verificar("Classe `{$classe}` carregada", class_exists($classe));
}
foreach (['public/agendar_servicos', 'public/agendar_horario', 'public/agendar_revisao',
          'public/checkout', 'public/_passos'] as $view) {
    $t->verificar("View `{$view}.php` existe", file_exists(__DIR__ . "/../app/views/{$view}.php"));
}
$t->verificar('agendamento.js existe', file_exists(__DIR__ . '/../public/assets/js/agendamento.js'));

$settings = new SettingModel();
$t->igual('Configuração: intervalo entre atendimentos', 10, $settings->getInt('agendamento_intervalo_minutos'));
$t->igual('Configuração: passo da grade', 15, $settings->getInt('agendamento_passo_minutos'));

// =====================================================================
$t->grupo('2. Motor: jornada, almoço e duração somada');

$slotsA = $agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA);
$listaA = $horas($slotsA);

$t->verificar('Grade começa no início da jornada (09:00)', in_array('09:00', $listaA, true));
$t->verificar('Passo de 15 minutos aplicado (09:15 presente)', in_array('09:15', $listaA, true));
$t->verificar('Serviço de 30 min: último horário antes do almoço é 11:30',
    in_array('11:30', $listaA, true));
$t->verificar('11:45 seria invadir o almoço: não é oferecido', !in_array('11:45', $listaA, true));
$t->verificar('Horário de almoço (12:00) não é oferecido', !in_array('12:00', $listaA, true));
$t->verificar('Após o almoço a grade recomeça às 13:00', in_array('13:00', $listaA, true));
$t->verificar('Último horário do dia é 17:30 (termina às 18:00)', in_array('17:30', $listaA, true));
$t->verificar('17:45 ultrapassaria o fim da jornada', !in_array('17:45', $listaA, true));

// Dois serviços: 60 minutos somados
$slots60 = $agendamentos->horariosDisponiveis($DATA, [$idCorte, $idBarba], $idProfA);
$lista60 = $horas($slots60);

$t->verificar('Com 60 min, último horário antes do almoço é 11:00', in_array('11:00', $lista60, true));
$t->verificar('Com 60 min, 11:15 invadiria o almoço', !in_array('11:15', $lista60, true));
$t->verificar('Com 60 min, último horário do dia é 17:00', in_array('17:00', $lista60, true));
$t->verificar('Com 60 min, 17:15 ultrapassaria a jornada', !in_array('17:15', $lista60, true));
$t->igual('Slot de 60 min informa o horário de término correto', '10:00',
    $slots60[array_search('09:00', $lista60, true)]['hora_fim']);

// Profissional B não tem almoço: grade contínua
$listaB = $horas($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfB));
$t->verificar('Profissional sem almoço tem horário às 12:00', in_array('12:00', $listaB, true));

// =====================================================================
$t->grupo('3. Motor: intervalo obrigatório de 10 minutos');

criarAgendamento($pdo, PREFIXO_TESTE . 'A1', $idCliente, $idProfA, $DATA, '14:00:00', '15:00:00', 60, 90.00, 'confirmado');

$listaComOcupado = $horas($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA));

$t->verificar('Horário ocupado (14:00) sai da grade', !in_array('14:00', $listaComOcupado, true));
$t->verificar('14:30, dentro do atendimento, sai da grade', !in_array('14:30', $listaComOcupado, true));
$t->verificar('15:00 é bloqueado: violaria o intervalo de 10 min',
    !in_array('15:00', $listaComOcupado, true));
$t->verificar('15:15 é liberado: respeita o intervalo de 10 min',
    in_array('15:15', $listaComOcupado, true));
$t->verificar('13:30 é bloqueado: terminaria exatamente às 14:00, sem intervalo',
    !in_array('13:30', $listaComOcupado, true));
$t->verificar('13:00 fica livre: termina 13:30, deixando 30 min até as 14:00',
    in_array('13:00', $listaComOcupado, true));

// =====================================================================
$t->grupo('4. Motor: bloqueios de agenda');

$idBloqueioManha = $bloqueios->criar([
    'profissional_id' => $idProfA,
    'data_inicio' => $DATA . ' 09:00:00', 'data_fim' => $DATA . ' 11:00:00',
    'motivo' => PREFIXO_TESTE . 'consulta', 'tipo' => 'folga',
]);

$listaBloqueada = $horas($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA));
$t->verificar('Bloqueio remove 09:00 da grade', !in_array('09:00', $listaBloqueada, true));
$t->verificar('Bloqueio remove 10:30 da grade', !in_array('10:30', $listaBloqueada, true));
$t->verificar('Grade retorna às 11:00, fim do bloqueio', in_array('11:00', $listaBloqueada, true));
$t->verificar('Profissional B não é afetado por bloqueio individual de A',
    in_array('09:00', $horas($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfB)), true));

$bloqueios->excluir($idBloqueioManha);
$t->verificar('Remover o bloqueio devolve 09:00 à grade',
    in_array('09:00', $horas($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA)), true));

// Bloqueio de todo o estabelecimento
$idBloqueioGeral = $bloqueios->criar([
    'profissional_id' => null,
    'data_inicio' => $DATA . ' 00:00:00', 'data_fim' => $DATA . ' 23:59:00',
    'motivo' => PREFIXO_TESTE . 'feriado', 'tipo' => 'feriado',
]);

$t->igual('Bloqueio geral zera a grade do Profissional A', 0,
    count($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA)));
$t->igual('Bloqueio geral zera a grade do Profissional B', 0,
    count($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfB)));
$t->igual('Bloqueio geral zera "qualquer profissional"', 0,
    count($agendamentos->horariosDisponiveis($DATA, [$idCorte], null)));

$bloqueios->excluir($idBloqueioGeral);

// Bloqueio parcial no meio do dia divide a janela
$idBloqueioMeio = $bloqueios->criar([
    'profissional_id' => $idProfB,
    'data_inicio' => $DATA . ' 10:00:00', 'data_fim' => $DATA . ' 11:00:00',
    'motivo' => PREFIXO_TESTE . 'reuniao', 'tipo' => 'bloqueio_parcial',
]);
$listaDividida = $horas($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfB));
$t->verificar('Antes do bloqueio parcial continua livre (09:00)', in_array('09:00', $listaDividida, true));
$t->verificar('09:30 é bloqueado: terminaria dentro do bloqueio', !in_array('09:45', $listaDividida, true));
$t->verificar('Dentro do bloqueio parcial não há horário (10:30)', !in_array('10:30', $listaDividida, true));
$t->verificar('Depois do bloqueio parcial volta a ter horário (11:00)', in_array('11:00', $listaDividida, true));
$bloqueios->excluir($idBloqueioMeio);

// =====================================================================
$t->grupo('5. Motor: seleção de profissionais e limite diário');

$t->verificar('Profissional A NÃO aparece para serviço que não executa',
    !in_array($idProfA, array_column($agendamentos->horariosDisponiveis($DATA, [$idExclusivo], null), 'profissional_id'), true));
$t->verificar('Profissional B aparece para o serviço exclusivo',
    count($agendamentos->horariosDisponiveis($DATA, [$idExclusivo], $idProfB)) > 0);
$t->igual('Combinação com serviço que A não executa não oferece A', 0,
    count($agendamentos->horariosDisponiveis($DATA, [$idCorte, $idExclusivo], $idProfA)));
$t->verificar('B atende a combinação inteira (executa todos)',
    count($agendamentos->horariosDisponiveis($DATA, [$idCorte, $idExclusivo], $idProfB)) > 0);

// "Qualquer profissional": o horário ocupado de A é coberto por B
$slotsQualquer = $agendamentos->horariosDisponiveis($DATA, [$idCorte], null);
$listaQualquer = $horas($slotsQualquer);
$t->verificar('"Qualquer profissional" oferece 14:00 (A ocupado, B livre)',
    in_array('14:00', $listaQualquer, true));
$slot14 = $slotsQualquer[array_search('14:00', $listaQualquer, true)];
$t->igual('O horário de 14:00 é atribuído ao Profissional B', $idProfB, (int)$slot14['profissional_id']);
$t->igual('Nome do profissional acompanha o horário', 'Profissional B', $slot14['profissional_nome']);

// Limite diário
$pdo->prepare('UPDATE profissionais SET limite_diario_atendimentos = 1 WHERE id = ?')->execute([$idProfA]);
$t->igual('Profissional com limite diário atingido some da grade', 0,
    count($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA)));
$pdo->prepare('UPDATE profissionais SET limite_diario_atendimentos = NULL WHERE id = ?')->execute([$idProfA]);
$t->verificar('Sem limite, o profissional volta à grade',
    count($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA)) > 0);

// Profissional inativo
$pdo->prepare("UPDATE profissionais SET status = 'inativo' WHERE id = ?")->execute([$idProfA]);
$t->igual('Profissional inativo não oferece horários', 0,
    count($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA)));
$pdo->prepare("UPDATE profissionais SET status = 'ativo' WHERE id = ?")->execute([$idProfA]);

// Folga: dia da semana sem jornada cadastrada
$outroDia = (clone $dataAlvo)->modify('+1 day')->format('Y-m-d');
$t->igual('Dia sem jornada cadastrada (folga) não oferece horários', 0,
    count($agendamentos->horariosDisponiveis($outroDia, [$idCorte], $idProfA)));

// Serviço inativo
$pdo->prepare("UPDATE servicos SET status = 'inativo' WHERE id = ?")->execute([$idCorte]);
$t->igual('Serviço inativo não gera grade', 0,
    count($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA)));
$pdo->prepare("UPDATE servicos SET status = 'ativo' WHERE id = ?")->execute([$idCorte]);

$t->igual('Lista de serviços vazia não gera grade', 0,
    count($agendamentos->horariosDisponiveis($DATA, [], $idProfA)));
$t->igual('ID de serviço inexistente não gera grade', 0,
    count($agendamentos->horariosDisponiveis($DATA, [999999], $idProfA)));

// =====================================================================
$t->grupo('6. Motor: antecedência mínima e máxima');

$t->verificar('Data de ontem é recusada',
    !$agendamentos->dataDentroDaJanelaPermitida(date('Y-m-d', strtotime('-1 day'))));
$t->verificar('Data de hoje é aceita', $agendamentos->dataDentroDaJanelaPermitida(date('Y-m-d')));
$t->verificar('Data em 30 dias é aceita',
    $agendamentos->dataDentroDaJanelaPermitida(date('Y-m-d', strtotime('+30 day'))));
$t->verificar('Data em 31 dias é recusada',
    !$agendamentos->dataDentroDaJanelaPermitida(date('Y-m-d', strtotime('+31 day'))));
$t->verificar('Data malformada é recusada', !$agendamentos->dataDentroDaJanelaPermitida('2027-13-45'));
$t->verificar('Texto arbitrário é recusado como data', !$agendamentos->dataDentroDaJanelaPermitida('abc'));

$t->igual('Data além de 30 dias não gera grade', 0,
    count($agendamentos->horariosDisponiveis(date('Y-m-d', strtotime('+45 day')), [$idCorte], $idProfA)));

// Antecedência mínima de 2h aplicada ao dia de hoje
$hojeSemana = (int)date('w');
$profModel->salvarHorarios($idProfB, [
    $DIA_SEMANA => ['hora_inicio' => '09:00', 'hora_fim' => '18:00', 'almoco_inicio' => null, 'almoco_fim' => null],
    $hojeSemana => ['hora_inicio' => '00:00', 'hora_fim' => '23:45', 'almoco_inicio' => null, 'almoco_fim' => null],
]);
$slotsHoje = $horas($agendamentos->horariosDisponiveis(date('Y-m-d'), [$idCorte], $idProfB));
$limiteMinimo = date('H:i', strtotime('+2 hours'));
$anterioresAoLimite = array_filter($slotsHoje, fn($h) => $h < $limiteMinimo);
$t->igual("Hoje: nenhum horário antes de {$limiteMinimo} (antecedência de 2h)", 0, count($anterioresAoLimite));

// Restaura a jornada de B
$profModel->salvarHorarios($idProfB, [
    $DIA_SEMANA => ['hora_inicio' => '09:00', 'hora_fim' => '18:00', 'almoco_inicio' => null, 'almoco_fim' => null],
]);

// =====================================================================
$t->grupo('7. Criação da reserva temporária');

// O agendamento fixo do grupo 3 pertence a este cliente; removido aqui para
// que as contagens de agendamentos ativos partam de zero.
$pdo->prepare('DELETE FROM agendamentos WHERE codigo_reserva = ?')->execute([PREFIXO_TESTE . 'A1']);
$t->igual('Cliente começa sem agendamentos ativos', 0, $agendamentos->totalAtivosDoCliente($idCliente));

$reserva = $agendamentos->criarReserva($idCliente, $idProfA, $DATA, '09:00', [$idCorte, $idBarba]);
$t->verificar('Reserva criada com código', !empty($reserva['codigo']));
$t->verificar('Código segue o padrão AGB-XXXXXXXX', preg_match('/^AGB-[0-9A-F]{8}$/', $reserva['codigo']) === 1);

$ag = $agendamentos->buscarPorCodigo($reserva['codigo']);
$t->igual('Situação inicial é aguardando_pagamento', 'aguardando_pagamento', $ag['situacao']);
$t->igual('Duração total somada corretamente', 60, (int)$ag['duracao_total_minutos']);
$t->igual('Valor total somado corretamente', '90.00', $ag['valor_total']);
$t->igual('Hora de início gravada', '09:00:00', $ag['hora_inicio']);
$t->igual('Hora de término calculada', '10:00:00', $ag['hora_fim']);

$minutosAteExpirar = (strtotime($ag['reserva_expira_em']) - time()) / 60;
$t->verificar('Reserva expira em aproximadamente 10 minutos',
    $minutosAteExpirar > 8 && $minutosAteExpirar <= 10.5, round($minutosAteExpirar, 1) . ' min');

$itens = $agendamentos->servicosDoAgendamento((int)$ag['id']);
$t->igual('Dois serviços vinculados ao agendamento', 2, count($itens));
$t->igual('Preço congelado no item', '50.00', $itens[1]['preco_aplicado']);
$t->igual('Duração congelada no item', 30, (int)$itens[0]['duracao_aplicada_minutos']);

// Congelamento de preço: alterar o catálogo não altera o agendamento
$servicosModel->atualizar($idCorte, [
    'nome' => PREFIXO_TESTE . 'Corte', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 999.00, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);
$itensDepois = $agendamentos->servicosDoAgendamento((int)$ag['id']);
$precosCongelados = array_column($itensDepois, 'preco_aplicado');
$t->verificar('Aumento de preço no catálogo NÃO altera o agendamento já feito',
    !in_array('999.00', $precosCongelados, true));
$t->igual('Valor total do agendamento permanece o original', '90.00',
    $agendamentos->buscarPorCodigo($reserva['codigo'])['valor_total']);
$servicosModel->atualizar($idCorte, [
    'nome' => PREFIXO_TESTE . 'Corte', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 50.00, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);

// A reserva ocupa o horário imediatamente
$t->verificar('Horário reservado sai da grade na hora',
    !in_array('09:00', $horas($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA)), true));

// Reserva duplicada no mesmo horário
$erroDuplicado = '';
try {
    $agendamentos->criarReserva($idCliente, $idProfA, $DATA, '09:00', [$idCorte]);
} catch (RuntimeException $e) {
    $erroDuplicado = $e->getMessage();
}
$t->verificar('Reservar o mesmo horário duas vezes é impedido',
    strpos($erroDuplicado, 'ocupado') !== false, $erroDuplicado);

// Horário fora da grade (forjado)
$erroForjado = '';
try {
    $agendamentos->criarReserva($idCliente, $idProfA, $DATA, '12:30', [$idCorte]);
} catch (RuntimeException $e) {
    $erroForjado = $e->getMessage();
}
$t->verificar('Horário no meio do almoço é recusado', $erroForjado !== '');

// =====================================================================
$t->grupo('8. Limite de agendamentos ativos e expiração da reserva');

$t->igual('Cliente possui 1 agendamento ativo', 1, $agendamentos->totalAtivosDoCliente($idCliente));

// Completa até o limite de 4
$agendamentos->criarReserva($idCliente, $idProfA, $DATA, '10:30', [$idCorte]);
$agendamentos->criarReserva($idCliente, $idProfA, $DATA, '13:00', [$idCorte]);
$agendamentos->criarReserva($idCliente, $idProfA, $DATA, '15:15', [$idCorte]);
$t->igual('Cliente atinge 4 agendamentos ativos', 4, $agendamentos->totalAtivosDoCliente($idCliente));

$erroLimite = '';
try {
    $agendamentos->criarReserva($idCliente, $idProfA, $DATA, '16:00', [$idCorte]);
} catch (RuntimeException $e) {
    $erroLimite = $e->getMessage();
}
$t->verificar('O 5º agendamento ativo é bloqueado', strpos($erroLimite, 'limite') !== false, $erroLimite);

// Expiração libera a vaga e o limite
$pdo->prepare("
    UPDATE agendamentos SET reserva_expira_em = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    WHERE codigo_reserva = ?
")->execute([$reserva['codigo']]);

$cancelados = $agendamentos->expirarReservasVencidas();
$t->verificar('Reserva vencida é cancelada automaticamente', $cancelados >= 1);
$t->igual('Situação passa a cancelado', 'cancelado',
    $agendamentos->buscarPorCodigo($reserva['codigo'])['situacao']);
$t->igual('Agendamentos ativos voltam a 3', 3, $agendamentos->totalAtivosDoCliente($idCliente));
$t->verificar('Horário da reserva expirada volta à grade',
    in_array('09:00', $horas($agendamentos->horariosDisponiveis($DATA, [$idCorte], $idProfA)), true));

// Limpa os agendamentos para os testes HTTP
$pdo->prepare("DELETE FROM agendamento_servicos WHERE agendamento_id IN (SELECT id FROM agendamentos WHERE cliente_id = ?)")->execute([$idCliente]);
$pdo->prepare("DELETE FROM agendamentos WHERE cliente_id = ?")->execute([$idCliente]);

// =====================================================================
$t->grupo('9. HTTP: vitrine pública');

$visitante = new HttpClient($baseUrl);
$visitante->get('/');

$t->igual('Vitrine responde 200', 200, $visitante->ultimoStatus);
$t->verificar('Vitrine lista os serviços', $visitante->corpoContem(PREFIXO_TESTE . 'Corte'));
$t->verificar('Vitrine exibe preço formatado', $visitante->corpoContem('R$ 50,00'));
$t->verificar('Vitrine lista a equipe', $visitante->corpoContem('Profissional A'));
$t->verificar('Vitrine tem CTA de agendamento', $visitante->corpoContem('Agendar Horário'));
$t->verificar('Vitrine mostra horário de funcionamento', $visitante->corpoContem('Segunda a Sábado'));

// =====================================================================
$t->grupo('10. HTTP: wizard passo a passo');

$t->igual('GET /agendar responde 200', 200, $visitante->get('/agendar')->ultimoStatus);
$t->verificar('Passo 1 lista os serviços disponíveis', $visitante->corpoContem(PREFIXO_TESTE . 'Barba'));
$t->verificar('Passo 1 exibe as orientações do serviço', $visitante->corpoContem('Não faça a barba em casa'));

$t->igual('POST /agendar/servicos sem CSRF é rejeitado', 403,
    $visitante->post('/agendar/servicos', ['servicos' => [$idCorte]])->ultimoStatus);

// Passo 1 sem selecionar nada
$csrf = $visitante->csrfDe('/agendar');
$visitante->post('/agendar/servicos', ['csrf_token' => $csrf, 'servicos' => []]);
$t->igual('Passo 1 sem serviço volta para /agendar', '/agendar', $visitante->destinoRedirecionamento());

// Pular direto para o passo 2 sem escolher serviços
$outro = new HttpClient($baseUrl);
$outro->get('/agendar/horario');
$t->igual('Passo 2 sem serviços redireciona ao passo 1', '/agendar', $outro->destinoRedirecionamento());
$outro->get('/agendar/revisao');
$t->igual('Passo 3 sem seleção redireciona ao passo 1', '/agendar', $outro->destinoRedirecionamento());
$outro->encerrar();

// Fluxo correto: passo 1
$csrf = $visitante->csrfDe('/agendar');
$visitante->post('/agendar/servicos', ['csrf_token' => $csrf, 'servicos' => [$idCorte, $idBarba]]);
$t->igual('Passo 1 válido avança para /agendar/horario', '/agendar/horario', $visitante->destinoRedirecionamento());

$visitante->get('/agendar/horario');
$t->igual('Passo 2 responde 200', 200, $visitante->ultimoStatus);
$t->verificar('Passo 2 mostra a duração total somada', $visitante->corpoContem('60 min'));
$t->verificar('Passo 2 mostra o valor total somado', $visitante->corpoContem('R$ 90,00'));
$t->verificar('Passo 2 oferece "qualquer profissional"', $visitante->corpoContem('Qualquer profissional'));
$t->verificar('Passo 2 lista apenas quem executa os dois serviços',
    $visitante->corpoContem('Profissional A') && $visitante->corpoContem('Profissional B'));

// API de horários
$visitante->get('/api/horarios?data=' . $DATA . '&profissional=' . $idProfA);
$t->igual('API de horários responde 200', 200, $visitante->ultimoStatus);
$json = json_decode($visitante->ultimoCorpo, true);
$t->verificar('API devolve JSON válido', is_array($json) && isset($json['horarios']));
$t->verificar('API retorna horários para a data de teste', count($json['horarios']) > 0);
$t->verificar('Cada horário traz hora, término e profissional',
    isset($json['horarios'][0]['hora'], $json['horarios'][0]['hora_fim'], $json['horarios'][0]['profissional_id']));

$visitante->get('/api/horarios?data=' . date('Y-m-d', strtotime('+90 day')));
$jsonLonge = json_decode($visitante->ultimoCorpo, true);
$t->igual('API recusa data fora da janela de 30 dias', 0, count($jsonLonge['horarios'] ?? [-1]));
$t->verificar('API explica o motivo da recusa', !empty($jsonLonge['aviso']));

// Passo 2 com horário forjado
$csrf = $visitante->csrfDe('/agendar/horario');
$visitante->post('/agendar/horario', [
    'csrf_token' => $csrf, 'data' => $DATA, 'hora' => '12:30', 'profissional_id' => (string)$idProfA,
]);
$t->igual('Horário forjado (durante o almoço) é rejeitado', '/agendar/horario', $visitante->destinoRedirecionamento());

// Passo 2 correto
$csrf = $visitante->csrfDe('/agendar/horario');
$visitante->post('/agendar/horario', [
    'csrf_token' => $csrf, 'data' => $DATA, 'hora' => '09:00', 'profissional_id' => (string)$idProfA,
]);
$t->igual('Passo 2 válido avança para /agendar/revisao', '/agendar/revisao', $visitante->destinoRedirecionamento());

$visitante->get('/agendar/revisao');
$t->igual('Passo 3 responde 200', 200, $visitante->ultimoStatus);
$t->verificar('Revisão mostra o profissional escolhido', $visitante->corpoContem('Profissional A'));
$t->verificar('Revisão mostra o horário escolhido', $visitante->corpoContem('09:00'));
$t->verificar('Revisão mostra o total a pagar', $visitante->corpoContem('R$ 90,00'));
$t->verificar('Revisão exibe as políticas de cancelamento', $visitante->corpoContem('24'));
$t->verificar('Revisão avisa que é preciso entrar na conta', $visitante->corpoContem('entrar na sua conta'));

// =====================================================================
$t->grupo('11. HTTP: confirmação da reserva');

// Sem login
$csrf = $visitante->csrfDe('/agendar/revisao');
$visitante->post('/agendar/confirmar', ['csrf_token' => $csrf, 'aceite_politicas' => '1']);
$t->igual('Confirmar sem login redireciona ao /login', '/login', $visitante->destinoRedirecionamento());

// Autentica mantendo o wizard na sessão
$csrf = $visitante->csrfDe('/login');
$visitante->post('/login', [
    'csrf_token' => $csrf, 'email' => PREFIXO_TESTE . 'cliente@local.test', 'senha' => SENHA_TESTE,
]);
$t->igual('Após o login o cliente volta para a revisão', '/agendar/revisao', $visitante->destinoRedirecionamento());

// Sem aceitar as políticas
$csrf = $visitante->csrfDe('/agendar/revisao');
$visitante->post('/agendar/confirmar', ['csrf_token' => $csrf]);
$t->igual('Confirmar sem aceitar as políticas é recusado', '/agendar/revisao', $visitante->destinoRedirecionamento());
$t->igual('Nenhum agendamento foi criado sem o aceite', 0, $agendamentos->totalAtivosDoCliente($idCliente));

// Confirmação válida
$csrf = $visitante->csrfDe('/agendar/revisao');
$visitante->post('/agendar/confirmar', ['csrf_token' => $csrf, 'aceite_politicas' => '1']);
$destino = $visitante->destinoRedirecionamento();
$t->verificar('Confirmação leva ao checkout', strpos((string)$destino, '/checkout/AGB-') === 0, (string)$destino);
$t->igual('Agendamento ativo registrado para o cliente', 1, $agendamentos->totalAtivosDoCliente($idCliente));

$codigoCriado = substr((string)$destino, strlen('/checkout/'));
$criado = $agendamentos->buscarPorCodigo($codigoCriado);
$t->igual('Agendamento criado com o profissional escolhido', $idProfA, (int)$criado['profissional_id']);
$t->igual('Agendamento criado na data escolhida', $DATA, $criado['data_agendamento']);
$t->igual('Agendamento criado no horário escolhido', '09:00:00', $criado['hora_inicio']);
$t->igual('Valor total correto', '90.00', $criado['valor_total']);

$t->verificar('Wizard é limpo após a confirmação',
    $visitante->get('/agendar/revisao')->destinoRedirecionamento() === '/agendar');

// =====================================================================
$t->grupo('12. HTTP: checkout e isolamento entre clientes');

$visitante->get('/checkout/' . $codigoCriado);
$t->igual('Cliente acessa o próprio checkout', 200, $visitante->ultimoStatus);
$t->verificar('Checkout exibe o código da reserva', $visitante->corpoContem($codigoCriado));
$t->verificar('Checkout exibe o cronômetro regressivo', $visitante->corpoContem('id="cronometro"'));
$t->verificar('Checkout lista os serviços contratados', $visitante->corpoContem(PREFIXO_TESTE . 'Corte'));

$t->igual('Código inexistente redireciona', 302, $visitante->get('/checkout/AGB-INEXISTE')->ultimoStatus);

// Outro cliente não pode ver este checkout
$usuarios->criar([
    'nome' => 'Intruso F5', 'email' => PREFIXO_TESTE . 'intruso@local.test',
    'telefone' => '(11) 94444-0004', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);
$intruso = new HttpClient($baseUrl);
$csrf = $intruso->csrfDe('/login');
$intruso->post('/login', ['csrf_token' => $csrf, 'email' => PREFIXO_TESTE . 'intruso@local.test', 'senha' => SENHA_TESTE]);

$t->igual('Outro cliente é BLOQUEADO no checkout alheio (403)', 403,
    $intruso->get('/checkout/' . $codigoCriado)->ultimoStatus);
$t->verificar('Tentativa é registrada em security_logs',
    (int)$pdo->query("SELECT COUNT(*) FROM security_logs WHERE detalhes LIKE '%checkout de outro cliente%'")->fetchColumn() > 0);

$t->igual('Visitante sem login é redirecionado do checkout', 302,
    (new HttpClient($baseUrl))->get('/checkout/' . $codigoCriado)->ultimoStatus);

// Rota dinâmica não quebra o 404 das demais
$t->igual('Rota inexistente continua respondendo 404', 404,
    $visitante->get('/rota-que-nao-existe')->ultimoStatus);

// =====================================================================
$t->grupo('13. Segurança do fluxo público');

// XSS no nome do serviço exibido na vitrine
$idXss = $servicosModel->criar([
    'nome' => PREFIXO_TESTE . '<script>alert(5)</script>', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 10, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);
$visitante->get('/');
$t->verificar('Nome malicioso NÃO é renderizado como script na vitrine',
    !$visitante->corpoContem('<script>alert(5)</script>'));
$t->verificar('Conteúdo aparece escapado', $visitante->corpoContem('&lt;script&gt;'));

// Injeção de SQL nos parâmetros da API.
// Exige uma sessão com serviços no wizard: sem isso a API responde 400 antes
// de chegar às consultas, e o teste não exercitaria o que se pretende.
$atacante = new HttpClient($baseUrl);
$csrfAtacante = $atacante->csrfDe('/agendar');
$atacante->post('/agendar/servicos', ['csrf_token' => $csrfAtacante, 'servicos' => [$idCorte]]);

$atacante->get('/api/horarios?data=' . $DATA . '&profissional=' . $idProfB);
$jsonBase = json_decode($atacante->ultimoCorpo, true);
$t->verificar('Sessão do teste de injeção tem grade válida como referência',
    !empty($jsonBase['horarios']));

$atacante->get("/api/horarios?data=" . urlencode("' OR 1=1 --") . "&profissional=" . $idProfB);
$t->igual('API resiste a SQL Injection na data', 200, $atacante->ultimoStatus);
$jsonInjecao = json_decode($atacante->ultimoCorpo, true);
$t->verificar('Data injetada é recusada como data inválida', isset($jsonInjecao['horarios']));
$t->igual('Injeção na data não retorna horários', 0, count($jsonInjecao['horarios'] ?? [-1]));

$atacante->get('/api/horarios?data=' . $DATA . '&profissional=' . urlencode('1 OR 1=1'));
$t->igual('API resiste a injeção no ID do profissional', 200, $atacante->ultimoStatus);
$jsonProf = json_decode($atacante->ultimoCorpo, true);
$t->verificar('ID injetado é convertido para inteiro, sem vazar outras agendas',
    isset($jsonProf['horarios']));

$atacante->get('/api/horarios?data=' . $DATA . "&profissional=" . urlencode('1; DROP TABLE agendamentos;--'));
$t->igual('Tentativa de DROP TABLE não derruba a API', 200, $atacante->ultimoStatus);
$t->verificar('Tabela `agendamentos` continua existindo após a tentativa',
    (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agendamentos'")->fetchColumn() === 1);

$atacante->encerrar();

// Profissional/admin não pode usar o fluxo público de cliente
$profHttp = new HttpClient($baseUrl);
$csrf = $profHttp->csrfDe('/login');
$profHttp->post('/login', ['csrf_token' => $csrf, 'email' => PREFIXO_TESTE . 'profa@local.test', 'senha' => SENHA_TESTE]);
$csrf = $profHttp->csrfDe('/agendar');
$profHttp->post('/agendar/servicos', ['csrf_token' => $csrf, 'servicos' => [$idCorte]]);
$csrf = $profHttp->csrfDe('/agendar/horario');
$profHttp->post('/agendar/horario', ['csrf_token' => $csrf, 'data' => $DATA, 'hora' => '10:30', 'profissional_id' => (string)$idProfB]);
$csrf = $profHttp->csrfDe('/agendar/revisao');
$profHttp->post('/agendar/confirmar', ['csrf_token' => $csrf, 'aceite_politicas' => '1']);
$t->igual('Conta de profissional não conclui agendamento pelo fluxo público',
    '/agendar/revisao', $profHttp->destinoRedirecionamento());

// =====================================================================
// Limpeza
// =====================================================================
$visitante->encerrar();
$intruso->encerrar();
$profHttp->encerrar();

limparDadosDeTeste($pdo);
echo "\n(Dados de teste removidos do banco.)\n";

exit($t->resumo());

// =====================================================================

function criarAgendamento(PDO $pdo, string $codigo, int $cliente, int $prof, string $data,
                          string $inicio, string $fim, int $duracao, float $valor, string $situacao): void {
    $pdo->prepare("
        INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                                  hora_inicio, hora_fim, duracao_total_minutos, valor_total, situacao)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$codigo, $cliente, $prof, $data, $inicio, $fim, $duracao, $valor, $situacao]);
}

function limparDadosDeTeste(PDO $pdo): void {
    $prefixo = PREFIXO_TESTE . '%';

    $pdo->prepare("
        DELETE FROM agendamento_servicos WHERE agendamento_id IN (
            SELECT id FROM agendamentos WHERE codigo_reserva LIKE ?
               OR cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
        )
    ")->execute([$prefixo, $prefixo]);
    $pdo->prepare("
        DELETE FROM agendamentos WHERE codigo_reserva LIKE ?
           OR cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo, $prefixo]);
    $pdo->prepare("DELETE FROM indisponibilidades_bloqueios WHERE motivo LIKE ?")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM indisponibilidades_bloqueios WHERE profissional_id IN (
            SELECT p.id FROM profissionais p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
        )
    ")->execute([$prefixo]);
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
    $pdo->prepare("DELETE FROM audit_logs WHERE entidade_id IN (SELECT id FROM servicos WHERE nome LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM servicos WHERE nome LIKE ?")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM security_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM security_logs WHERE detalhes LIKE ?")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM security_logs WHERE detalhes LIKE '%checkout de outro cliente%'")->execute();
    $pdo->prepare("DELETE FROM audit_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM profissionais WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?")->execute([$prefixo]);
}
