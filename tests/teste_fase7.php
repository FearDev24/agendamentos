<?php
/**
 * tests/teste_fase7.php - Validação da Fase 7
 * (Áreas Restritas do Cliente e do Profissional)
 *
 * Uso: php tests/teste_fase7.php
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

const PREFIXO_TESTE = 'teste_fase7_';
const SENHA_TESTE = 'SenhaTeste@123';

echo "\n======================================================\n";
echo " TESTES DA FASE 7 - Áreas do Cliente e do Profissional\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

limparDadosDeTeste($pdo);

$usuarios     = new UsuarioModel();
$servicosM    = new ServicoModel();
$profModel    = new ProfissionalModel();
$agendamentos = new AgendamentoModel();
$pagamentos   = new PagamentoModel();
$creditos     = new CreditoModel();
$avaliacoes   = new AvaliacaoModel();
$notas        = new NotaClienteModel();

// ---------------------------------------------------------------------
// Cenário
// ---------------------------------------------------------------------
$idBarato = $servicosM->criar([
    'nome' => PREFIXO_TESTE . 'Barato', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 50.00, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);
$idCaro = $servicosM->criar([
    'nome' => PREFIXO_TESTE . 'Caro', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 90.00, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);

$idProf = $profModel->criar([
    'nome' => 'Profissional F7', 'email' => PREFIXO_TESTE . 'prof@local.test',
    'telefone' => '(11) 92222-0001', 'senha' => SENHA_TESTE,
    'limite_diario_atendimentos' => '', 'status' => 'ativo',
]);
$profModel->sincronizarServicos($idProf, [$idBarato, $idCaro]);

// Jornada em todos os dias da semana, para não depender do calendário
$grade = [];
for ($d = 0; $d <= 6; $d++) {
    $grade[$d] = ['hora_inicio' => '08:00', 'hora_fim' => '20:00', 'almoco_inicio' => null, 'almoco_fim' => null];
}
$profModel->salvarHorarios($idProf, $grade);

$idCliente = $usuarios->criar([
    'nome' => 'Cliente F7', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 92222-0002', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);
$idOutroCliente = $usuarios->criar([
    'nome' => 'Outro Cliente F7', 'email' => PREFIXO_TESTE . 'outro@local.test',
    'telefone' => '(11) 92222-0003', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);

/**
 * Cria um agendamento diretamente no banco, com controle total de data,
 * situação e valor. Permite montar cenários de 24h sem esperar o relógio.
 */
$criarAgendamento = function (
    string $codigo, string $data, string $hora, string $situacao = 'confirmado',
    float $valor = 50.00, ?int $cliente = null, int $servicoId = 0, int $reagendamentos = 0
) use ($pdo, $idCliente, $idProf, $idBarato): int {
    $cliente = $cliente ?? $idCliente;
    $servicoId = $servicoId ?: $idBarato;

    $pdo->prepare("
        INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                                  hora_inicio, hora_fim, duracao_total_minutos, valor_total,
                                  situacao, reagendamentos_realizados)
        VALUES (?, ?, ?, ?, ?, ADDTIME(?, '00:30:00'), 30, ?, ?, ?)
    ")->execute([PREFIXO_TESTE . $codigo, $cliente, $idProf, $data, $hora, $hora, $valor, $situacao, $reagendamentos]);

    $id = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO agendamento_servicos (agendamento_id, servico_id, preco_aplicado, duracao_aplicada_minutos)
        VALUES (?, ?, ?, 30)
    ")->execute([$id, $servicoId, $valor]);

    return $id;
};

/** Registra um pagamento aprovado para simular que o cliente já pagou */
$pagar = function (int $agendamentoId, float $valor) use ($pagamentos): void {
    $pagamentos->registrar($agendamentoId, 'pix', $valor, [
        'transacao_id' => 'F7-' . $agendamentoId . '-' . bin2hex(random_bytes(3)),
        'status'       => 'aprovado',
    ]);
};

// =====================================================================
$t->grupo('1. Estrutura da Fase 7');

foreach (['CreditoModel', 'AvaliacaoModel', 'NotaClienteModel'] as $classe) {
    $t->verificar("Classe `{$classe}` carregada", class_exists($classe));
}
foreach (['cliente/agendamentos', 'cliente/comprovante', 'cliente/reagendar',
          'profissional/agenda', 'profissional/notas'] as $view) {
    $t->verificar("View `{$view}.php` existe", file_exists(__DIR__ . "/../app/views/{$view}.php"));
}

// =====================================================================
$t->grupo('2. Créditos internos');

$idCredito = $creditos->gerar($idCliente, 40.00);
$t->verificar('Crédito gerado', $idCredito > 0);
$t->igual('Saldo disponível reflete o crédito', 40.0, $creditos->saldoDisponivel($idCliente));

$credito = $pdo->query("SELECT * FROM creditos_internos WHERE id = {$idCredito}")->fetch();
$diasValidade = (strtotime($credito['data_expiracao']) - strtotime(date('Y-m-d'))) / 86400;
$t->igual('Validade de 90 dias corridos', 90, round($diasValidade));

$erroValor = false;
try { $creditos->gerar($idCliente, 0); } catch (InvalidArgumentException $e) { $erroValor = true; }
$t->verificar('Crédito de valor zero é rejeitado', $erroValor);

// Consumo parcial
$consumido = $creditos->consumir($idCliente, 15.00);
$t->igual('Consumo parcial retorna o valor consumido', 15.0, $consumido);
$t->igual('Saldo reduz corretamente', 25.0, $creditos->saldoDisponivel($idCliente));

// Consumo maior que o saldo devolve apenas o disponível
$t->igual('Consumo acima do saldo devolve só o disponível', 25.0, $creditos->consumir($idCliente, 999.00));
$t->igual('Saldo zerado', 0.0, $creditos->saldoDisponivel($idCliente));

// Ordem de consumo: vence primeiro, sai primeiro
$idLonge = $creditos->gerar($idCliente, 30.00);
$idPerto = $creditos->gerar($idCliente, 20.00);
$pdo->prepare('UPDATE creditos_internos SET data_expiracao = DATE_ADD(CURDATE(), INTERVAL 5 DAY) WHERE id = ?')
    ->execute([$idPerto]);

$creditos->consumir($idCliente, 20.00);
$saldoPerto = (float)$pdo->query("SELECT saldo_restante FROM creditos_internos WHERE id = {$idPerto}")->fetchColumn();
$saldoLonge = (float)$pdo->query("SELECT saldo_restante FROM creditos_internos WHERE id = {$idLonge}")->fetchColumn();
$t->igual('Crédito de vencimento mais próximo é consumido primeiro', 0.0, $saldoPerto);
$t->igual('Crédito de vencimento distante permanece intacto', 30.0, $saldoLonge);

// Crédito expirado não conta
$pdo->prepare('UPDATE creditos_internos SET data_expiracao = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE cliente_id = ?')
    ->execute([$idCliente]);
$t->igual('Crédito expirado sai do saldo disponível', 0.0, $creditos->saldoDisponivel($idCliente));
$t->igual('Crédito expirado não é consumido', 0.0, $creditos->consumir($idCliente, 10.00));

$pdo->prepare('DELETE FROM creditos_internos WHERE cliente_id = ?')->execute([$idCliente]);

// =====================================================================
$t->grupo('3. Regra das 24 horas');

$daquiA3Dias = date('Y-m-d', strtotime('+3 days'));
$amanhaCedo  = date('Y-m-d', strtotime('+1 day'));

$idLonge3d = $criarAgendamento('L1', $daquiA3Dias, '10:00:00');
$agLonge = $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'L1');
$t->verificar('Agendamento em 3 dias está dentro da janela de 24h',
    $agendamentos->dentroDaJanelaDe24h($agLonge));
$t->verificar('Pode ser reagendado', $agendamentos->podeReagendar($agLonge));
$t->verificar('Pode ser cancelado', $agendamentos->podeCancelar($agLonge));

// Agendamento daqui a 2 horas: fora da janela
$idCurto = $criarAgendamento('C1', date('Y-m-d'), date('H:i:s', strtotime('+2 hours')));
$agCurto = $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'C1');
$t->verificar('Agendamento em 2 horas está FORA da janela de 24h',
    !$agendamentos->dentroDaJanelaDe24h($agCurto));
$t->verificar('NÃO pode ser reagendado', !$agendamentos->podeReagendar($agCurto));
$t->verificar('Ainda pode ser cancelado (sem reembolso)', $agendamentos->podeCancelar($agCurto));

// Limite de reagendamentos
$criarAgendamento('R2', $daquiA3Dias, '11:00:00', 'confirmado', 50.00, null, 0, 2);
$agLimite = $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'R2');
$t->verificar('Agendamento com 2 reagendamentos não pode mais ser reagendado',
    !$agendamentos->podeReagendar($agLimite));

// =====================================================================
$t->grupo('4. Cancelamento e reembolso');

// Dentro do prazo: reembolso integral
$idCanc1 = $criarAgendamento('K1', $daquiA3Dias, '12:00:00', 'confirmado', 90.00);
$pagar($idCanc1, 90.00);

$resultado = $agendamentos->cancelar($idCanc1, $idCliente, 'Imprevisto');
$t->verificar('Cancelamento em 3 dias é considerado no prazo', $resultado['no_prazo']);
$t->igual('Reembolso integral do valor pago', 90.0, $resultado['reembolso']);
$t->igual('Situação passa a cancelado', 'cancelado',
    $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'K1')['situacao']);

// Fora do prazo: sem reembolso
$idCanc2 = $criarAgendamento('K2', date('Y-m-d'), date('H:i:s', strtotime('+3 hours')), 'confirmado', 90.00);
$pagar($idCanc2, 90.00);

$resultado = $agendamentos->cancelar($idCanc2, $idCliente, 'Imprevisto');
$t->verificar('Cancelamento em 3 horas é fora do prazo', !$resultado['no_prazo']);
$t->igual('Sem reembolso fora do prazo', 0.0, $resultado['reembolso']);
$t->igual('Ainda assim o agendamento é cancelado', 'cancelado',
    $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'K2')['situacao']);

// Crédito utilizado sempre volta ao cliente
$idCanc3 = $criarAgendamento('K3', date('Y-m-d'), date('H:i:s', strtotime('+3 hours')), 'confirmado', 90.00);
$pdo->prepare('UPDATE agendamentos SET credito_utilizado = 30.00 WHERE id = ?')->execute([$idCanc3]);

$resultado = $agendamentos->cancelar($idCanc3, $idCliente, 'Imprevisto');
$t->igual('Crédito utilizado é devolvido mesmo fora do prazo', 30.0, $resultado['credito_devolvido']);
$t->igual('Saldo do cliente recebe o crédito de volta', 30.0, $creditos->saldoDisponivel($idCliente));
$pdo->prepare('DELETE FROM creditos_internos WHERE cliente_id = ?')->execute([$idCliente]);

// Proteções
$erroDono = '';
try {
    $agendamentos->cancelar($idLonge3d, $idOutroCliente, 'tentativa');
} catch (RuntimeException $e) { $erroDono = $e->getMessage(); }
$t->verificar('Cliente não cancela agendamento de outro',
    strpos($erroDono, 'não pertence') !== false, $erroDono);

$erroDuplo = '';
try {
    $agendamentos->cancelar($idCanc1, $idCliente, 'de novo');
} catch (RuntimeException $e) { $erroDuplo = $e->getMessage(); }
$t->verificar('Agendamento já cancelado não é cancelado de novo', $erroDuplo !== '');

// =====================================================================
$t->grupo('5. Reagendamento e crédito por diferença');

// Novo valor MENOR: gera crédito
$idRe1 = $criarAgendamento('RE1', $daquiA3Dias, '14:00:00', 'confirmado', 90.00, null, $idCaro);
$pagar($idRe1, 90.00);

$novaData = date('Y-m-d', strtotime('+5 days'));
$resultado = $agendamentos->reagendar($idRe1, $idCliente, $novaData, '15:00', $idProf, [$idBarato]);

$t->igual('Diferença negativa calculada', -40.0, $resultado['diferenca']);
$t->igual('Crédito gerado equivale à diferença', 40.0, $resultado['credito_gerado']);
$t->igual('Nada a pagar', 0.0, $resultado['a_pagar']);
$t->igual('Saldo de crédito do cliente', 40.0, $creditos->saldoDisponivel($idCliente));

$agRe1 = $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'RE1');
$t->igual('Nova data gravada', $novaData, $agRe1['data_agendamento']);
$t->igual('Novo horário gravado', '15:00:00', $agRe1['hora_inicio']);
$t->igual('Novo valor gravado', '50.00', $agRe1['valor_total']);
$t->igual('Contador de reagendamentos incrementado', 1, (int)$agRe1['reagendamentos_realizados']);
$t->igual('Continua confirmado (nada a pagar)', 'confirmado', $agRe1['situacao']);
$t->igual('Serviços do agendamento foram substituídos', 1,
    count($agendamentos->servicosDoAgendamento($idRe1)));

$pdo->prepare('DELETE FROM creditos_internos WHERE cliente_id = ?')->execute([$idCliente]);

// Novo valor MAIOR: volta a aguardar pagamento da diferença
$idRe2 = $criarAgendamento('RE2', $daquiA3Dias, '16:00:00', 'confirmado', 50.00, null, $idBarato);
$pagar($idRe2, 50.00);

$resultado = $agendamentos->reagendar($idRe2, $idCliente, $novaData, '17:00', $idProf, [$idCaro]);
$t->igual('Diferença positiva calculada', 40.0, $resultado['diferenca']);
$t->igual('Valor a pagar equivale à diferença', 40.0, $resultado['a_pagar']);
$t->igual('Nenhum crédito gerado', 0.0, $resultado['credito_gerado']);

$agRe2 = $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'RE2');
$t->igual('Situação volta a aguardando_pagamento', 'aguardando_pagamento', $agRe2['situacao']);
$t->verificar('Novo prazo de reserva definido', $agRe2['reserva_expira_em'] !== null);
$t->igual('Valor devido é apenas a diferença', 40.0, $agendamentos->valorDevido($agRe2));

// Regras de bloqueio do reagendamento
$erroPrazo = '';
try {
    $agendamentos->reagendar($idCurto, $idCliente, $novaData, '18:00', $idProf, [$idBarato]);
} catch (RuntimeException $e) { $erroPrazo = $e->getMessage(); }
$t->verificar('Reagendar com menos de 24h é bloqueado',
    strpos($erroPrazo, 'antecedência') !== false, $erroPrazo);

$idRe3 = $criarAgendamento('RE3', $daquiA3Dias, '09:00:00', 'confirmado', 50.00, null, 0, 2);
$erroLimite = '';
try {
    $agendamentos->reagendar($idRe3, $idCliente, $novaData, '18:30', $idProf, [$idBarato]);
} catch (RuntimeException $e) { $erroLimite = $e->getMessage(); }
$t->verificar('Terceiro reagendamento é bloqueado',
    strpos($erroLimite, 'máximo') !== false, $erroLimite);

$erroOutro = '';
try {
    $agendamentos->reagendar($idRe3, $idOutroCliente, $novaData, '18:30', $idProf, [$idBarato]);
} catch (RuntimeException $e) { $erroOutro = $e->getMessage(); }
$t->verificar('Cliente não reagenda agendamento de outro', $erroOutro !== '');

// Reagendar para o MESMO horário deve funcionar (o próprio não conflita consigo)
$idRe4 = $criarAgendamento('RE4', $daquiA3Dias, '19:00:00', 'confirmado', 50.00);
$pagar($idRe4, 50.00);
$semErro = true;
try {
    $agendamentos->reagendar($idRe4, $idCliente, $daquiA3Dias, '19:00', $idProf, [$idBarato]);
} catch (RuntimeException $e) { $semErro = false; }
$t->verificar('Reagendar para o mesmo horário não acusa conflito consigo mesmo', $semErro);

// =====================================================================
$t->grupo('6. Fidelidade');

$pdo->prepare("DELETE FROM agendamentos WHERE cliente_id = ? AND situacao = 'concluido'")->execute([$idCliente]);

$fid = $agendamentos->fidelidade($idCliente);
$t->igual('Sem atendimentos: nível Bronze', 'bronze', $fid['nivel']);
$t->igual('Faltam 5 para Prata', 5, $fid['faltam']);

for ($i = 1; $i <= 5; $i++) {
    $criarAgendamento('FID' . $i, date('Y-m-d', strtotime("-{$i} days")), '10:00:00', 'concluido');
}
$fid = $agendamentos->fidelidade($idCliente);
$t->igual('5 atendimentos concluídos: nível Prata', 'prata', $fid['nivel']);
$t->igual('Contagem de concluídos', 5, $fid['concluidos']);
$t->igual('Faltam 5 para Ouro', 5, $fid['faltam']);

for ($i = 6; $i <= 10; $i++) {
    $criarAgendamento('FID' . $i, date('Y-m-d', strtotime("-{$i} days")), '11:00:00', 'concluido');
}
$fid = $agendamentos->fidelidade($idCliente);
$t->igual('10 atendimentos concluídos: nível Ouro', 'ouro', $fid['nivel']);
$t->verificar('Ouro é o nível máximo', $fid['proximo'] === null);
$t->verificar('Insígnia corresponde ao nível', $fid['insignia'] === 'insigniaouro.png');
$t->verificar('Arquivo da insígnia existe',
    file_exists(__DIR__ . '/../public/assets/img/fidelidade/' . $fid['insignia']));

// Cancelados não contam para fidelidade
$criarAgendamento('FIDX', date('Y-m-d', strtotime('-20 days')), '12:00:00', 'cancelado');
$t->igual('Agendamento cancelado não conta na fidelidade', 10,
    $agendamentos->fidelidade($idCliente)['concluidos']);

// =====================================================================
$t->grupo('7. Avaliações');

$idAvaliavel = $criarAgendamento('AV1', date('Y-m-d', strtotime('-2 days')), '13:00:00', 'concluido');

$idAvaliacao = $avaliacoes->registrar($idAvaliavel, $idCliente, 5, 'Excelente atendimento');
$t->verificar('Avaliação registrada', $idAvaliacao > 0);
$t->verificar('Agendamento consta como avaliado', $avaliacoes->jaAvaliado($idAvaliavel));

$erroDuplicado = '';
try { $avaliacoes->registrar($idAvaliavel, $idCliente, 4); }
catch (RuntimeException $e) { $erroDuplicado = $e->getMessage(); }
$t->verificar('Não é possível avaliar duas vezes',
    strpos($erroDuplicado, 'já foi avaliado') !== false, $erroDuplicado);

$idNaoConcluido = $criarAgendamento('AV2', $daquiA3Dias, '13:30:00', 'confirmado');
$erroSituacao = '';
try { $avaliacoes->registrar($idNaoConcluido, $idCliente, 5); }
catch (RuntimeException $e) { $erroSituacao = $e->getMessage(); }
$t->verificar('Só atendimentos concluídos podem ser avaliados',
    strpos($erroSituacao, 'concluídos') !== false, $erroSituacao);

$idOutroAg = $criarAgendamento('AV3', date('Y-m-d', strtotime('-2 days')), '14:30:00', 'concluido');
$erroDono = '';
try { $avaliacoes->registrar($idOutroAg, $idOutroCliente, 5); }
catch (RuntimeException $e) { $erroDono = $e->getMessage(); }
$t->verificar('Cliente não avalia atendimento de outro', $erroDono !== '');

foreach ([0, 6, -1] as $notaInvalida) {
    $erroNota = false;
    try { $avaliacoes->registrar($idOutroAg, $idCliente, $notaInvalida); }
    catch (InvalidArgumentException $e) { $erroNota = true; }
    $t->verificar("Nota {$notaInvalida} é rejeitada", $erroNota);
}

$avaliacoes->registrar($idOutroAg, $idCliente, 3);
$resumo = $avaliacoes->resumoDoProfissional($idProf);
$t->igual('Total de avaliações do profissional', 2, $resumo['total']);
$t->igual('Média calculada corretamente', 4.0, $resumo['media']);

// =====================================================================
$t->grupo('8. Notas privadas sobre clientes');

$idNota = $notas->criar($idCliente, $usuarios->buscarPorEmail(PREFIXO_TESTE . 'prof@local.test')['id'],
    'Prefere máquina 2 nas laterais.');
$t->verificar('Nota criada', $idNota > 0);
$t->igual('Nota aparece no histórico do cliente', 1, count($notas->listarDoCliente($idCliente)));

$erroCurta = false;
try { $notas->criar($idCliente, 1, 'ab'); }
catch (InvalidArgumentException $e) { $erroCurta = true; }
$t->verificar('Observação muito curta é rejeitada', $erroCurta);

$t->verificar('Profissional tem histórico com o cliente',
    $agendamentos->totalEntreProfissionalECliente($idProf, $idCliente) > 0);
$t->igual('Sem histórico com outro cliente, o total é zero', 0,
    $agendamentos->totalEntreProfissionalECliente($idProf, $idOutroCliente));

// =====================================================================
$t->grupo('9. Operação do profissional');

$hoje = date('Y-m-d');
$idOperacao = $criarAgendamento('OP1', $hoje, '09:00:00', 'confirmado');

$agenda = $agendamentos->agendaDoProfissional($idProf, $hoje);
$t->verificar('Agendamento aparece na agenda do dia', count($agenda) > 0);
$t->verificar('Agenda traz o nome do cliente',
    in_array('Cliente F7', array_column($agenda, 'cliente_nome'), true));

// Confirmação de presença
$t->verificar('Presença confirmada', $agendamentos->confirmarPresenca($idOperacao, $idProf, '09:05'));
$ag = $pdo->query("SELECT * FROM agendamentos WHERE id = {$idOperacao}")->fetch();
$t->igual('Flag de presença marcada', 1, (int)$ag['confirmado_presenca']);
$t->igual('Horário real de início gravado', '09:05:00', $ag['hora_inicio_real']);

$t->verificar('Outro profissional não confirma presença nesta agenda',
    !$agendamentos->confirmarPresenca($idOperacao, 999999, '09:05'));

// Conclusão
$t->verificar('Atendimento concluído', $agendamentos->concluir($idOperacao, $idProf, '09:05', '09:40'));
$ag = $pdo->query("SELECT * FROM agendamentos WHERE id = {$idOperacao}")->fetch();
$t->igual('Situação passa a concluido', 'concluido', $ag['situacao']);
$t->igual('Horário real de término gravado', '09:40:00', $ag['hora_fim_real']);

$erroConcluir = '';
try { $agendamentos->concluir($idOperacao, $idProf, '09:05', '09:40'); }
catch (RuntimeException $e) { $erroConcluir = $e->getMessage(); }
$t->verificar('Concluir duas vezes é bloqueado',
    strpos($erroConcluir, 'confirmados') !== false, $erroConcluir);

// Horário de término inválido
$idOperacao2 = $criarAgendamento('OP2', $hoje, '15:00:00', 'confirmado');
$erroHora = '';
try { $agendamentos->concluir($idOperacao2, $idProf, '15:00', '14:00'); }
catch (RuntimeException $e) { $erroHora = $e->getMessage(); }
$t->verificar('Término anterior ao início é rejeitado',
    strpos($erroHora, 'posterior') !== false, $erroHora);

$erroAgendaAlheia = '';
try { $agendamentos->concluir($idOperacao2, 999999, '15:00', '15:30'); }
catch (RuntimeException $e) { $erroAgendaAlheia = $e->getMessage(); }
$t->verificar('Profissional não conclui atendimento de outro', $erroAgendaAlheia !== '');

// Falta
$idFalta = $criarAgendamento('OP3', $hoje, '16:00:00', 'confirmado');
$t->verificar('Falta registrada', $agendamentos->registrarFalta($idFalta, $idProf));
$t->igual('Situação passa a falta', 'falta',
    $pdo->query("SELECT situacao FROM agendamentos WHERE id = {$idFalta}")->fetchColumn());

// Cancelados não aparecem na agenda do profissional
$idCancelado = $criarAgendamento('OP4', $hoje, '17:00:00', 'cancelado');
$codigosAgenda = array_column($agendamentos->agendaDoProfissional($idProf, $hoje), 'codigo_reserva');
$t->verificar('Agendamento cancelado não aparece na agenda',
    !in_array(PREFIXO_TESTE . 'OP4', $codigosAgenda, true));

// =====================================================================
$t->grupo('10. HTTP: painel do cliente');

$entrar = function (string $email) use ($baseUrl): HttpClient {
    $c = new HttpClient($baseUrl);
    $csrf = $c->csrfDe('/login');
    $c->post('/login', ['csrf_token' => $csrf, 'email' => $email, 'senha' => SENHA_TESTE]);
    return $c;
};

$clienteHttp = $entrar(PREFIXO_TESTE . 'cliente@local.test');

$clienteHttp->get('/cliente/agendamentos');
$t->igual('Painel do cliente responde 200', 200, $clienteHttp->ultimoStatus);
$t->verificar('Painel separa próximos e histórico',
    $clienteHttp->corpoContem('Próximos') && $clienteHttp->corpoContem('Histórico'));
$t->verificar('Painel exibe a insígnia de fidelidade', $clienteHttp->corpoContem('insigniaouro.png'));
$t->verificar('Painel exibe o bloco de créditos', $clienteHttp->corpoContem('Créditos Internos'));
$t->verificar('Painel oferece o formulário de avaliação', $clienteHttp->corpoContem('AVALIE ESTE ATENDIMENTO'));

// Comprovante
$clienteHttp->get('/cliente/agendamento/' . PREFIXO_TESTE . 'RE1/comprovante');
$t->igual('Comprovante responde 200', 200, $clienteHttp->ultimoStatus);
$t->verificar('Comprovante mostra o código da reserva',
    $clienteHttp->corpoContem(PREFIXO_TESTE . 'RE1'));
$t->verificar('Comprovante tem botão de impressão', $clienteHttp->corpoContem('window.print()'));

// Isolamento entre clientes
$outroHttp = $entrar(PREFIXO_TESTE . 'outro@local.test');
$t->igual('Cliente não vê comprovante de outro (403)', 403,
    $outroHttp->get('/cliente/agendamento/' . PREFIXO_TESTE . 'RE1/comprovante')->ultimoStatus);
$t->igual('Cliente não abre reagendamento de outro (403)', 403,
    $outroHttp->get('/cliente/agendamento/' . PREFIXO_TESTE . 'RE1/reagendar')->ultimoStatus);

// CSRF em todas as ações do cliente
foreach (['/cliente/cancelar', '/cliente/reagendar', '/cliente/avaliar', '/cliente/credito'] as $rota) {
    $t->igual("POST {$rota} sem CSRF é rejeitado", 403,
        $clienteHttp->post($rota, ['codigo' => PREFIXO_TESTE . 'RE1'])->ultimoStatus);
}

// Cancelamento por HTTP
$idHttpCancel = $criarAgendamento('HC1', $daquiA3Dias, '08:30:00', 'confirmado', 50.00);
$pagar($idHttpCancel, 50.00);

$csrf = $clienteHttp->csrfDe('/cliente/agendamentos');
$clienteHttp->post('/cliente/cancelar', [
    'csrf_token' => $csrf, 'codigo' => PREFIXO_TESTE . 'HC1', 'motivo' => 'Mudanca de planos',
]);
// Cancelamento no prazo encadeia: cancela -> estorna o pagamento -> a situação
// final vira 'reembolsado', distinguindo do cancelamento sem devolução.
$t->igual('Cancelamento no prazo aciona o estorno do pagamento', 'estornado',
    $pagamentos->buscarPorAgendamento($idHttpCancel)['status_pagamento']);
$t->igual('Situação final do agendamento reembolsado', 'reembolsado',
    $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'HC1')['situacao']);
$t->verificar('Agendamento sai da lista de ativos do cliente',
    !in_array(PREFIXO_TESTE . 'HC1',
        array_column($agendamentos->listarDoCliente($idCliente)['proximos'], 'codigo_reserva'), true));

// Cancelamento sem motivo
$idHttpCancel2 = $criarAgendamento('HC2', $daquiA3Dias, '08:00:00', 'confirmado', 50.00);
$csrf = $clienteHttp->csrfDe('/cliente/agendamentos');
$clienteHttp->post('/cliente/cancelar', [
    'csrf_token' => $csrf, 'codigo' => PREFIXO_TESTE . 'HC2', 'motivo' => '',
]);
$t->igual('Cancelamento sem motivo é recusado', 'confirmado',
    $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'HC2')['situacao']);

// Avaliação por HTTP
$idHttpAval = $criarAgendamento('HA1', date('Y-m-d', strtotime('-3 days')), '10:30:00', 'concluido');
$csrf = $clienteHttp->csrfDe('/cliente/agendamentos');
$clienteHttp->post('/cliente/avaliar', [
    'csrf_token' => $csrf, 'codigo' => PREFIXO_TESTE . 'HA1', 'nota' => '4', 'comentario' => 'Muito bom',
]);
$t->verificar('Avaliação por HTTP registrada', $avaliacoes->jaAvaliado($idHttpAval));
$t->igual('Nota gravada', 4, (int)$avaliacoes->buscarPorAgendamento($idHttpAval)['nota']);

// =====================================================================
$t->grupo('11. HTTP: reagendamento pela interface');

$idHttpRe = $criarAgendamento('HR1', $daquiA3Dias, '13:00:00', 'confirmado', 90.00, null, $idCaro);
$pagar($idHttpRe, 90.00);

$clienteHttp->get('/cliente/agendamento/' . PREFIXO_TESTE . 'HR1/reagendar');
$t->igual('Tela de reagendamento responde 200', 200, $clienteHttp->ultimoStatus);
$t->verificar('Tela informa o valor já pago', $clienteHttp->corpoContem('R$ 90,00'));
$t->verificar('Tela permite trocar os serviços', $clienteHttp->corpoContem('name="servicos[]"'));

// A API de horários aceita os serviços por parâmetro (usado no reagendamento)
$clienteHttp->get('/api/horarios?data=' . date('Y-m-d', strtotime('+6 days'))
    . '&profissional=' . $idProf . '&servicos[]=' . $idBarato);
$json = json_decode($clienteHttp->ultimoCorpo, true);
$t->igual('API responde 200 com serviços por parâmetro', 200, $clienteHttp->ultimoStatus);
$t->verificar('API devolve horários para os serviços informados', !empty($json['horarios']));

$dataNova = date('Y-m-d', strtotime('+6 days'));
$csrf = $clienteHttp->csrfDe('/cliente/agendamento/' . PREFIXO_TESTE . 'HR1/reagendar');
$clienteHttp->post('/cliente/reagendar', [
    'csrf_token' => $csrf, 'codigo' => PREFIXO_TESTE . 'HR1',
    'data' => $dataNova, 'hora' => '14:00', 'profissional_id' => (string)$idProf,
    'servicos' => [$idBarato],
]);

$agHttpRe = $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'HR1');
$t->igual('Reagendamento por HTTP grava a nova data', $dataNova, $agHttpRe['data_agendamento']);
$t->igual('Novo valor aplicado', '50.00', $agHttpRe['valor_total']);
$t->igual('Crédito de R$ 40 gerado pela diferença', 40.0, $creditos->saldoDisponivel($idCliente));

// Aplicação de crédito no checkout
$idComCredito = $criarAgendamento('HCR', $daquiA3Dias, '18:00:00', 'aguardando_pagamento', 90.00, null, $idCaro);
$pdo->prepare('UPDATE agendamentos SET reserva_expira_em = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?')
    ->execute([$idComCredito]);

$csrf = $clienteHttp->csrfDe('/checkout/' . PREFIXO_TESTE . 'HCR');
$clienteHttp->post('/cliente/credito', [
    'csrf_token' => $csrf, 'codigo' => PREFIXO_TESTE . 'HCR', 'valor' => '40,00',
]);

$agCredito = $agendamentos->buscarPorCodigo(PREFIXO_TESTE . 'HCR');
$t->igual('Crédito aplicado ao agendamento', '40.00', $agCredito['credito_utilizado']);
$t->igual('Saldo de crédito zerado após o uso', 0.0, $creditos->saldoDisponivel($idCliente));
$t->igual('Valor devido cai para R$ 50', 50.0, $agendamentos->valorDevido($agCredito));

$clienteHttp->get('/checkout/' . PREFIXO_TESTE . 'HCR');
$t->verificar('Checkout mostra o crédito aplicado', $clienteHttp->corpoContem('Crédito interno aplicado'));
$t->verificar('Checkout mostra o valor a pagar já descontado', $clienteHttp->corpoContem('R$ 50,00'));

// =====================================================================
$t->grupo('12. HTTP: painel do profissional');

$profHttp = $entrar(PREFIXO_TESTE . 'prof@local.test');

$profHttp->get('/profissional/agenda');
$t->igual('Agenda do profissional responde 200', 200, $profHttp->ultimoStatus);
$t->verificar('Agenda exibe o seletor de data', $profHttp->corpoContem('name="data"'));
$t->verificar('Agenda exibe o resumo de avaliações', $profHttp->corpoContem('Minhas Avaliações'));

$idHoje = $criarAgendamento('PH1', $hoje, '11:30:00', 'confirmado');
$profHttp->get('/profissional/agenda?data=' . $hoje);
$t->verificar('Atendimento do dia aparece na agenda', $profHttp->corpoContem(PREFIXO_TESTE . 'PH1'));
$t->verificar('Agenda oferece confirmar presença', $profHttp->corpoContem('Confirmar Presença'));
$t->verificar('Agenda oferece concluir atendimento', $profHttp->corpoContem('Concluir Atendimento'));

// Operações por HTTP
$csrf = $profHttp->csrfDe('/profissional/agenda?data=' . $hoje);
$profHttp->post('/profissional/presenca', [
    'csrf_token' => $csrf, 'id' => (string)$idHoje, 'data' => $hoje, 'hora_inicio_real' => '11:35',
]);
$t->igual('Presença confirmada por HTTP', 1,
    (int)$pdo->query("SELECT confirmado_presenca FROM agendamentos WHERE id = {$idHoje}")->fetchColumn());

$csrf = $profHttp->csrfDe('/profissional/agenda?data=' . $hoje);
$profHttp->post('/profissional/concluir', [
    'csrf_token' => $csrf, 'id' => (string)$idHoje, 'data' => $hoje,
    'hora_inicio_real' => '11:35', 'hora_fim_real' => '12:10',
]);
$t->igual('Atendimento concluído por HTTP', 'concluido',
    $pdo->query("SELECT situacao FROM agendamentos WHERE id = {$idHoje}")->fetchColumn());

// Notas do cliente
$profHttp->get('/profissional/cliente/' . $idCliente . '/notas');
$t->igual('Tela de notas responde 200', 200, $profHttp->ultimoStatus);
$t->verificar('Tela avisa que as notas são internas', $profHttp->corpoContem('internas'));

$csrf = $profHttp->csrfDe('/profissional/cliente/' . $idCliente . '/notas');
$profHttp->post('/profissional/nota', [
    'csrf_token' => $csrf, 'cliente_id' => (string)$idCliente,
    'observacao' => 'Cliente pontual e educado.',
]);
$t->igual('Nota salva por HTTP', 2, count($notas->listarDoCliente($idCliente)));

// Profissional não acessa notas de cliente que nunca atendeu
$t->igual('Notas de cliente sem histórico são bloqueadas (403)', 403,
    $profHttp->get('/profissional/cliente/' . $idOutroCliente . '/notas')->ultimoStatus);

// CSRF nas ações do profissional
foreach (['/profissional/presenca', '/profissional/concluir', '/profissional/falta', '/profissional/nota'] as $rota) {
    $t->igual("POST {$rota} sem CSRF é rejeitado", 403,
        $profHttp->post($rota, ['id' => '1'])->ultimoStatus);
}

// =====================================================================
$t->grupo('13. RBAC e privacidade das áreas restritas');

$t->igual('Profissional é bloqueado no painel do cliente (403)', 403,
    $profHttp->get('/cliente/agendamentos')->ultimoStatus);
$t->igual('Cliente é bloqueado na agenda do profissional (403)', 403,
    $clienteHttp->get('/profissional/agenda')->ultimoStatus);
$t->igual('Cliente é bloqueado na tela de notas (403)', 403,
    $clienteHttp->get('/profissional/cliente/' . $idCliente . '/notas')->ultimoStatus);

$csrfCliente = $clienteHttp->csrfDe('/cliente/agendamentos');
$t->igual('Cliente é bloqueado ao tentar concluir atendimento (403)', 403,
    $clienteHttp->post('/profissional/concluir', [
        'csrf_token' => $csrfCliente, 'id' => (string)$idOperacao2, 'data' => $hoje,
    ])->ultimoStatus);

// As notas privadas jamais aparecem para o cliente
$clienteHttp->get('/cliente/agendamentos');
$t->verificar('Notas privadas não vazam para o painel do cliente',
    !$clienteHttp->corpoContem('Prefere máquina 2'));

$visitante = new HttpClient($baseUrl);
foreach (['/cliente/agendamentos', '/profissional/agenda'] as $rota) {
    $t->igual("Visitante é redirecionado de {$rota}", 302, $visitante->get($rota)->ultimoStatus);
}

// XSS no comentário de avaliação
$idXss = $criarAgendamento('XSS', date('Y-m-d', strtotime('-4 days')), '15:30:00', 'concluido');
$avaliacoes->registrar($idXss, $idCliente, 5, '<script>alert(7)</script>');
$profHttp->get('/profissional/agenda');
$clienteHttp->get('/cliente/agendamentos');
$t->verificar('Comentário malicioso não é renderizado como script',
    !$clienteHttp->corpoContem('<script>alert(7)</script>'));

// =====================================================================
// Limpeza
// =====================================================================
$clienteHttp->encerrar();
$outroHttp->encerrar();
$profHttp->encerrar();
$visitante->encerrar();

limparDadosDeTeste($pdo);
echo "\n(Dados de teste removidos do banco.)\n";

exit($t->resumo());

// =====================================================================

function limparDadosDeTeste(PDO $pdo): void {
    $prefixo = PREFIXO_TESTE . '%';

    $pdo->prepare("
        DELETE FROM avaliacoes WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM notas_clientes WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
           OR autor_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo, $prefixo]);
    $pdo->prepare("
        DELETE FROM creditos_internos WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo]);
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
    $pdo->prepare("DELETE FROM audit_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM profissionais WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?")->execute([$prefixo]);
}
