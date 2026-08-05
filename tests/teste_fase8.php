<?php
/**
 * tests/teste_fase8.php - Validação da Fase 8
 * (Painel Administrativo Operacional, Financeiro e Promoções)
 *
 * Uso: php tests/teste_fase8.php
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

const PREFIXO_TESTE = 'teste_fase8_';
const SENHA_TESTE = 'SenhaTeste@123';

echo "\n======================================================\n";
echo " TESTES DA FASE 8 - Painel Administrativo e Financeiro\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

limparDadosDeTeste($pdo);

$usuarios    = new UsuarioModel();
$servicosM   = new ServicoModel();
$profModel   = new ProfissionalModel();
$agendamentos= new AgendamentoModel();
$pagamentos  = new PagamentoModel();
$despesas    = new DespesaModel();
$promocoes   = new PromocaoModel();
$relatorios  = new RelatorioModel();

// ---------------------------------------------------------------------
// Cenário
// ---------------------------------------------------------------------
$idServico = $servicosM->criar([
    'nome' => PREFIXO_TESTE . 'Corte', 'descricao' => '', 'duracao_minutos' => 30,
    'preco' => 100.00, 'orientacoes' => '', 'status' => 'ativo', 'previsao_retorno' => '',
]);

$idProf = $profModel->criar([
    'nome' => 'Profissional F8', 'email' => PREFIXO_TESTE . 'prof@local.test',
    'telefone' => '(11) 91111-0001', 'senha' => SENHA_TESTE,
    'limite_diario_atendimentos' => '', 'status' => 'ativo',
]);
$profModel->sincronizarServicos($idProf, [$idServico]);

$grade = [];
for ($d = 0; $d <= 6; $d++) {
    $grade[$d] = ['hora_inicio' => '08:00', 'hora_fim' => '20:00', 'almoco_inicio' => null, 'almoco_fim' => null];
}
$profModel->salvarHorarios($idProf, $grade);

$idPrincipal = $usuarios->criar([
    'nome' => 'Principal F8', 'email' => PREFIXO_TESTE . 'principal@local.test',
    'telefone' => '(11) 91111-0002', 'senha' => SENHA_TESTE, 'perfil' => 'admin_principal',
]);
$idLimitado = $usuarios->criar([
    'nome' => 'Limitado F8', 'email' => PREFIXO_TESTE . 'limitado@local.test',
    'telefone' => '(11) 91111-0003', 'senha' => SENHA_TESTE, 'perfil' => 'admin_limitado',
]);
$idCliente = $usuarios->criar([
    'nome' => 'Cliente F8', 'email' => PREFIXO_TESTE . 'cliente@local.test',
    'telefone' => '(11) 91111-0004', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);

$HOJE = date('Y-m-d');
$MES_DE = date('Y-m-01');
$MES_ATE = date('Y-m-t');

/** Cria agendamento com pagamento aprovado, alimentando o financeiro */
$agendamentoPago = function (string $codigo, string $data, string $hora, float $valor, string $situacao = 'concluido')
        use ($pdo, $idCliente, $idProf, $idServico, $pagamentos): int {
    $pdo->prepare("
        INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                                  hora_inicio, hora_fim, duracao_total_minutos, valor_total, situacao)
        VALUES (?, ?, ?, ?, ?, ADDTIME(?, '00:30:00'), 30, ?, ?)
    ")->execute([PREFIXO_TESTE . $codigo, $idCliente, $idProf, $data, $hora, $hora, $valor, $situacao]);

    $id = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO agendamento_servicos (agendamento_id, servico_id, preco_aplicado, duracao_aplicada_minutos)
        VALUES (?, ?, ?, 30)
    ")->execute([$id, $idServico, $valor]);

    $pagamentos->registrar($id, 'pix', $valor, [
        'transacao_id' => 'F8-' . $id . '-' . bin2hex(random_bytes(3)),
        'status'       => 'aprovado',
    ]);

    return $id;
};

// =====================================================================
$t->grupo('1. Estrutura da Fase 8');

foreach (['DespesaModel', 'PromocaoModel', 'RelatorioModel', 'ExportHelper',
          'FinanceiroController', 'PromocaoController'] as $classe) {
    $t->verificar("Classe `{$classe}` carregada", class_exists($classe));
}
foreach (['admin/agenda', 'admin/financeiro', 'admin/relatorios', 'admin/promocoes',
          'public/promocao'] as $view) {
    $t->verificar("View `{$view}.php` existe", file_exists(__DIR__ . "/../app/views/{$view}.php"));
}

// =====================================================================
$t->grupo('2. Despesas e categorias');

$t->verificar('Categorias do seeding disponíveis', count($despesas->categorias()) >= 10);

$idDespesa = $despesas->criar([
    'categoria_id' => 1, 'descricao' => PREFIXO_TESTE . 'Aluguel do mes',
    'valor' => 1500.00, 'data_despesa' => $HOJE,
], $idPrincipal);

$t->verificar('Despesa criada', $idDespesa > 0);
$t->igual('Total do período reflete a despesa', 1500.0, $despesas->totalNoPeriodo($MES_DE, $MES_ATE));

$despesas->criar([
    'categoria_id' => 2, 'descricao' => PREFIXO_TESTE . 'Produtos',
    'valor' => 300.50, 'data_despesa' => $HOJE,
], $idPrincipal);

$t->igual('Total acumula os lançamentos', 1800.5, $despesas->totalNoPeriodo($MES_DE, $MES_ATE));
$t->igual('Agrupamento por categoria retorna 2 linhas', 2, count($despesas->totalPorCategoria($MES_DE, $MES_ATE)));

$t->igual('Filtro por categoria funciona', 1, count($despesas->listar($MES_DE, $MES_ATE, 1)));
$t->igual('Despesa fora do período não é contada', 0.0,
    $despesas->totalNoPeriodo('2020-01-01', '2020-01-31'));

$despesas->excluirLogicamente($idDespesa);
$t->verificar('Soft delete remove da listagem', $despesas->buscarPorId($idDespesa) === null);
$t->igual('Total desconsidera despesa excluída', 300.5, $despesas->totalNoPeriodo($MES_DE, $MES_ATE));
$t->verificar('Registro preservado no banco',
    $pdo->query("SELECT COUNT(*) FROM despesas WHERE id = {$idDespesa}")->fetchColumn() == 1);

$erroCategoria = false;
try { $despesas->criarCategoria('ab'); } catch (InvalidArgumentException $e) { $erroCategoria = true; }
$t->verificar('Categoria com nome curto é rejeitada', $erroCategoria);

// =====================================================================
$t->grupo('3. Cálculo financeiro');

// Os totais são medidos por diferença: o banco de desenvolvimento pode já
// conter movimento do mês (uma demonstração, um atendimento de balcão), e
// comparar valores absolutos faria o teste falhar por dado alheio a ele.
$base = $relatorios->resumoFinanceiro($MES_DE, $MES_ATE);

$agendamentoPago('FIN1', $HOJE, '09:00:00', 100.00);
$agendamentoPago('FIN2', $HOJE, '10:00:00', 200.00);

$resumo = $relatorios->resumoFinanceiro($MES_DE, $MES_ATE);

$t->igual('Receita bruta soma os pagamentos aprovados', 300.0,
    round($resumo['receita_bruta'] - $base['receita_bruta'], 2));
$t->igual('Despesas do período', 300.5, $resumo['despesas']);
// Invariante, e não um valor fixo: as despesas já entraram na medição de base,
// então o delta do lucro seria a receita inteira e não diria nada de útil.
$t->igual('Lucro líquido = receita − despesas',
    round($resumo['receita_liquida'] - $resumo['despesas'], 2),
    round($resumo['lucro_liquido'], 2));

// Estorno reduz a receita
$idEstornado = $agendamentoPago('FIN3', $HOJE, '11:00:00', 50.00);
$pgEstorno = $pagamentos->buscarAprovadoDoAgendamento($idEstornado);
$pagamentos->estornar($pgEstorno['transacao_id']);

$resumo = $relatorios->resumoFinanceiro($MES_DE, $MES_ATE);
$t->igual('Estorno sai da receita líquida', 300.0,
    round($resumo['receita_liquida'] - $base['receita_liquida'], 2));
$t->igual('Estorno é contabilizado separadamente', 50.0,
    round($resumo['estornos'] - $base['estornos'], 2));
$t->igual('Receita bruta continua incluindo o valor estornado', 350.0,
    round($resumo['receita_bruta'] - $base['receita_bruta'], 2));

// Pagamento pendente não vira receita
$pdo->prepare("
    INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                              hora_inicio, hora_fim, duracao_total_minutos, valor_total, situacao)
    VALUES (?, ?, ?, ?, '15:00:00', '15:30:00', 30, 999.00, 'aguardando_pagamento')
")->execute([PREFIXO_TESTE . 'PEND', $idCliente, $idProf, $HOJE]);
$idPendente = (int)$pdo->lastInsertId();
$pagamentos->registrar($idPendente, 'pix', 999.00, ['transacao_id' => 'F8-PEND', 'status' => 'pendente']);

$t->igual('Pagamento pendente NÃO entra no faturamento', 350.0,
    round($relatorios->resumoFinanceiro($MES_DE, $MES_ATE)['receita_bruta'] - $base['receita_bruta'], 2));

// Margem
$resumo = $relatorios->resumoFinanceiro($MES_DE, $MES_ATE);
$margemEsperada = round((($resumo['receita_liquida'] - $resumo['despesas']) / $resumo['receita_liquida']) * 100, 1);
$t->igual('Margem calculada corretamente', $margemEsperada, $resumo['margem']);

$t->igual('Período sem movimento devolve margem zero', 0.0,
    $relatorios->resumoFinanceiro('2019-01-01', '2019-01-31')['margem']);

// Detalhamentos
$t->verificar('Receita por dia agrupa corretamente',
    count($relatorios->receitaPorDia($MES_DE, $MES_ATE)) >= 1);
$t->verificar('Receita por forma de pagamento retorna Pix',
    in_array('pix', array_column($relatorios->receitaPorFormaDePagamento($MES_DE, $MES_ATE), 'forma_pagamento'), true));

// =====================================================================
$t->grupo('4. Métricas do dashboard');

$metricas = $relatorios->metricasDoDia($HOJE);
$t->verificar('Total de agendamentos do dia contabilizado', $metricas['total'] >= 4);
$t->verificar('Concluídos contabilizados', $metricas['concluidos'] >= 2);
$t->verificar('Aguardando pagamento contabilizado', $metricas['aguardando'] >= 1);
$t->igual('Faturamento do dia bate com a receita', $relatorios->receitaNoPeriodo($HOJE, $HOJE),
    $metricas['faturamento']);

// Pendentes de conclusão: confirmado cujo horário já passou
$pdo->prepare("
    INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                              hora_inicio, hora_fim, duracao_total_minutos, valor_total, situacao)
    VALUES (?, ?, ?, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00', '09:30:00', 30, 100.00, 'confirmado')
")->execute([PREFIXO_TESTE . 'PEND2', $idCliente, $idProf]);

$pendentes = $relatorios->pendentesDeConclusao($HOJE);
$t->verificar('Atendimento vencido aparece como pendente de conclusão',
    in_array(PREFIXO_TESTE . 'PEND2', array_column($pendentes, 'codigo_reserva'), true));

// =====================================================================
$t->grupo('5. Agenda administrativa e agendamento manual');

$amanha = date('Y-m-d', strtotime('+1 day'));

$resultado = $agendamentos->criarManual($idCliente, $idProf, $amanha, '14:00', [$idServico]);
$t->verificar('Agendamento manual criado', !empty($resultado['codigo']));
$t->igual('Nasce confirmado (pagamento no balcão)', 'confirmado',
    $agendamentos->buscarPorCodigo($resultado['codigo'])['situacao']);
$t->igual('Valor bruto correto', 100.0, $resultado['valor_bruto']);
$t->igual('Sem desconto por padrão', 0.0, $resultado['valor_desconto']);

// Desconto fixo
$resultado = $agendamentos->criarManual($idCliente, $idProf, $amanha, '15:00', [$idServico],
    ['tipo' => 'fixo', 'valor' => 20.00, 'justificativa' => 'Cliente antigo']);
$t->igual('Desconto fixo aplicado', 20.0, $resultado['valor_desconto']);
$t->igual('Valor final descontado', 80.0, $resultado['valor_final']);

$agDesconto = $agendamentos->buscarPorCodigo($resultado['codigo']);
$t->igual('Justificativa gravada', 'Cliente antigo', $agDesconto['justificativa_desconto']);

// Desconto percentual
$resultado = $agendamentos->criarManual($idCliente, $idProf, $amanha, '16:00', [$idServico],
    ['tipo' => 'percentual', 'valor' => 30, 'justificativa' => 'Promocao de aniversario']);
$t->igual('Desconto percentual de 30% sobre R$ 100', 30.0, $resultado['valor_desconto']);

// Desconto sem justificativa é bloqueado
$erroJustificativa = '';
try {
    $agendamentos->criarManual($idCliente, $idProf, $amanha, '17:00', [$idServico],
        ['tipo' => 'fixo', 'valor' => 10.00, 'justificativa' => '']);
} catch (RuntimeException $e) { $erroJustificativa = $e->getMessage(); }
$t->verificar('Desconto sem justificativa é recusado',
    strpos($erroJustificativa, 'justificativa') !== false, $erroJustificativa);

// Desconto maior que o valor não deixa o total negativo
$resultado = $agendamentos->criarManual($idCliente, $idProf, $amanha, '18:00', [$idServico],
    ['tipo' => 'fixo', 'valor' => 500.00, 'justificativa' => 'Cortesia total']);
$t->igual('Desconto é limitado ao valor do serviço', 100.0, $resultado['valor_desconto']);
$t->igual('Valor final nunca fica negativo', 0.0, $resultado['valor_final']);

// Horário ocupado
$erroOcupado = '';
try {
    $agendamentos->criarManual($idCliente, $idProf, $amanha, '14:00', [$idServico]);
} catch (RuntimeException $e) { $erroOcupado = $e->getMessage(); }
$t->verificar('Agendamento manual respeita horário ocupado',
    strpos($erroOcupado, 'disponível') !== false, $erroOcupado);

// Visões da agenda
$t->verificar('Visão diária lista os agendamentos do dia',
    count($relatorios->agendaDoPeriodo($amanha, $amanha)) >= 4);

$semanaDe = date('Y-m-d', strtotime($amanha . ' -3 days'));
$semanaAte = date('Y-m-d', strtotime($amanha . ' +3 days'));
$t->verificar('Visão semanal cobre um intervalo maior',
    count($relatorios->agendaDoPeriodo($semanaDe, $semanaAte)) >=
    count($relatorios->agendaDoPeriodo($amanha, $amanha)));

$contagem = $relatorios->contagemPorDia($semanaDe, $semanaAte);
$t->verificar('Contagem por dia alimenta o calendário', isset($contagem[$amanha]));
$t->verificar('Contagem do dia bate com a listagem',
    ($contagem[$amanha]['total'] ?? 0) === count($relatorios->agendaDoPeriodo($amanha, $amanha)));

$t->verificar('Filtro por profissional funciona',
    count($relatorios->agendaDoPeriodo($amanha, $amanha, $idProf)) > 0);
$t->igual('Filtro por profissional inexistente devolve vazio', 0,
    count($relatorios->agendaDoPeriodo($amanha, $amanha, 999999)));
$t->verificar('Filtro por situação funciona',
    count($relatorios->agendaDoPeriodo($amanha, $amanha, null, 'confirmado')) > 0);

// Cancelamento pelo estabelecimento sempre reembolsa
$idParaCancelar = $agendamentoPago('CANC', $HOJE, '19:00:00', 100.00, 'confirmado');
$resultadoCancel = $agendamentos->cancelarPeloEstabelecimento($idParaCancelar, 'Profissional indisponível');
$t->verificar('Cancelamento pelo estabelecimento é sempre no prazo', $resultadoCancel['no_prazo']);
$t->igual('Reembolso integral mesmo em cima da hora', 100.0, $resultadoCancel['reembolso']);

// =====================================================================
$t->grupo('5b. Recebimento presencial (balcão)');

$t->verificar('ENUM de pagamentos aceita `dinheiro`',
    in_array('dinheiro', PagamentoModel::FORMAS, true));

$colunaEnum = (string)$pdo->query(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamentos' AND COLUMN_NAME = 'forma_pagamento'"
)->fetchColumn();
$t->verificar('Banco aceita a forma `dinheiro`', strpos($colunaEnum, "'dinheiro'") !== false, $colunaEnum);

$receitaAntes = $relatorios->receitaNoPeriodo($MES_DE, $MES_ATE);

// Agendamento de balcão sem pagamento não entra no faturamento
$semPagamento = $agendamentos->criarManual($idCliente, $idProf, $amanha, '08:00', [$idServico]);
$t->igual('Atendimento de balcão sem recebimento NÃO altera a receita',
    $receitaAntes, $relatorios->receitaNoPeriodo($MES_DE, $MES_ATE));

$agSemPag = $agendamentos->buscarPorCodigo($semPagamento['codigo']);
$t->igual('Valor fica em aberto', 100.0, $agendamentos->valorDevido($agSemPag));

// Registrando o recebimento em dinheiro
$idPagBalcao = $pagamentos->registrarRecebimentoPresencial(
    $semPagamento['id'], 'dinheiro', 100.00, $idPrincipal
);
$t->verificar('Recebimento presencial registrado', $idPagBalcao > 0);

$pgBalcao = $pagamentos->buscarPorId($idPagBalcao);
$t->igual('Forma gravada como dinheiro', 'dinheiro', $pgBalcao['forma_pagamento']);
$t->igual('Nasce já aprovado', 'aprovado', $pgBalcao['status_pagamento']);
$t->verificar('Data de compensação preenchida', $pgBalcao['pago_em'] !== null);
$t->verificar('Referência identifica origem de balcão',
    strpos($pgBalcao['transacao_id'], 'BALCAO-') === 0, $pgBalcao['transacao_id']);

$t->igual('Receita passa a incluir o recebimento em dinheiro',
    round($receitaAntes + 100.0, 2), round($relatorios->receitaNoPeriodo($MES_DE, $MES_ATE), 2));
$t->igual('Valor deixa de estar em aberto', 0.0,
    $agendamentos->valorDevido($agendamentos->buscarPorCodigo($semPagamento['codigo'])));

$t->verificar('Dinheiro aparece no detalhamento por forma',
    in_array('dinheiro', array_column($relatorios->receitaPorFormaDePagamento($MES_DE, $MES_ATE), 'forma_pagamento'), true));

// Recebimento é auditado
$t->verificar('Recebimento presencial é auditado',
    (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE acao = 'recebimento_presencial'")->fetchColumn() > 0);

// Proteções
$erroDuplo = '';
try {
    $pagamentos->registrarRecebimentoPresencial($semPagamento['id'], 'dinheiro', 100.00, $idPrincipal);
} catch (RuntimeException $e) { $erroDuplo = $e->getMessage(); }
$t->verificar('Não é possível registrar recebimento duas vezes',
    strpos($erroDuplo, 'já possui pagamento') !== false, $erroDuplo);

$erroForma = false;
try {
    $pagamentos->registrarRecebimentoPresencial($semPagamento['id'], 'bitcoin', 10.00, $idPrincipal);
} catch (InvalidArgumentException $e) { $erroForma = true; }
$t->verificar('Forma de recebimento inválida é rejeitada', $erroForma);

$erroValor = false;
try {
    $novo = $agendamentos->criarManual($idCliente, $idProf, $amanha, '08:45', [$idServico]);
    $pagamentos->registrarRecebimentoPresencial($novo['id'], 'dinheiro', 0, $idPrincipal);
} catch (InvalidArgumentException $e) { $erroValor = true; }
$t->verificar('Valor zero é rejeitado', $erroValor);

// Agendamento manual já com recebimento no ato
$receitaAntes2 = $relatorios->receitaNoPeriodo($MES_DE, $MES_ATE);
$comPagamento = $agendamentos->criarManual($idCliente, $idProf, $amanha, '09:30', [$idServico],
    ['tipo' => 'fixo', 'valor' => 20.00, 'justificativa' => 'Desconto de balcao']);
$pagamentos->registrarRecebimentoPresencial(
    $comPagamento['id'], 'dinheiro', $comPagamento['valor_final'], $idPrincipal
);
$t->igual('Recebimento considera o valor já com desconto', 80.0, $comPagamento['valor_final']);
$t->igual('Receita cresce pelo valor efetivamente cobrado',
    round($receitaAntes2 + 80.0, 2), round($relatorios->receitaNoPeriodo($MES_DE, $MES_ATE), 2));

// =====================================================================
$t->grupo('6. Relatórios operacionais');

$filtros = ['de' => $MES_DE, 'ate' => $MES_ATE, 'profissional_id' => '', 'situacao' => '', 'servico_id' => ''];
$lista = $relatorios->agendamentos($filtros);
$t->verificar('Relatório lista agendamentos do período', count($lista) > 0);
$t->verificar('Relatório traz os serviços contratados', !empty($lista[0]['servicos']));

$t->verificar('Filtro por situação reduz o resultado',
    count($relatorios->agendamentos($filtros + [] === [] ? [] : array_merge($filtros, ['situacao' => 'concluido'])))
    <= count($lista));

$semResultado = $relatorios->agendamentos(array_merge($filtros, ['profissional_id' => 999999]));
$t->igual('Filtro por profissional inexistente devolve vazio', 0, count($semResultado));

$desempenho = $relatorios->desempenhoDosProfissionais($MES_DE, $MES_ATE);
$linhaProf = null;
foreach ($desempenho as $d) {
    if ($d['profissional_nome'] === 'Profissional F8') { $linhaProf = $d; break; }
}
$t->verificar('Profissional aparece no desempenho', $linhaProf !== null);
$t->verificar('Contagem de concluídos no desempenho', (int)($linhaProf['concluidos'] ?? 0) >= 2);
$t->verificar('Minutos ocupados contabilizados', (int)($linhaProf['minutos_ocupados'] ?? 0) > 0);

$maisContratados = $relatorios->servicosMaisContratados($MES_DE, $MES_ATE);
$t->verificar('Serviço de teste aparece entre os contratados',
    in_array(PREFIXO_TESTE . 'Corte', array_column($maisContratados, 'nome'), true));

// =====================================================================
$t->grupo('7. Exportação de planilha');

$reflexao = new ReflectionClass('ExportHelper');
$normalizar = $reflexao->getMethod('normalizarCelula');
$normalizar->setAccessible(true);

// Proteção contra fórmulas maliciosas no Excel
foreach (['=1+1', '+1', '-1', '@SUM(A1)'] as $perigoso) {
    $t->verificar("Célula iniciada por '{$perigoso[0]}' é neutralizada",
        strpos($normalizar->invoke(null, $perigoso), "'") === 0);
}
$t->igual('Texto comum não é alterado', 'Corte Masculino',
    $normalizar->invoke(null, 'Corte Masculino'));
$t->igual('Valor nulo vira string vazia', '', $normalizar->invoke(null, null));

$nomeSeguro = $reflexao->getMethod('nomeSeguro');
$nomeSeguro->setAccessible(true);
$t->verificar('Nome de arquivo remove travessia de diretório',
    strpos($nomeSeguro->invoke(null, '../../etc/passwd'), '..') === false);
$t->verificar('Nome vazio recebe um padrão', $nomeSeguro->invoke(null, '!!!') !== '');

// =====================================================================
$t->grupo('8. Promoções');

$idPromo = $promocoes->criar([
    'titulo' => PREFIXO_TESTE . 'Combo de Verao', 'descricao' => 'Aproveite',
    'servico_id' => $idServico, 'tipo_desconto' => 'percentual', 'valor_desconto' => 20,
    'data_inicio' => $HOJE, 'data_fim' => date('Y-m-d', strtotime('+30 days')),
    'limite_total_usos' => 2, 'status' => 'ativa',
]);

$t->verificar('Promoção criada', $idPromo > 0);
$promo = $promocoes->buscarPorId($idPromo);
$t->verificar('Promoção vigente aparece na vitrine',
    in_array($idPromo, array_map('intval', array_column($promocoes->vigentes(), 'id')), true));
$t->verificar('estaVigente() confirma', $promocoes->estaVigente($promo));

$t->igual('Desconto percentual sobre R$ 100', 20.0, $promocoes->calcularDesconto($promo, 100.00));

$promoFixa = ['tipo_desconto' => 'fixo', 'valor_desconto' => 30.00];
$t->igual('Desconto fixo aplicado', 30.0, $promocoes->calcularDesconto($promoFixa, 100.00));
$t->igual('Desconto nunca supera o valor da compra', 25.0, $promocoes->calcularDesconto($promoFixa, 25.00));
$t->igual('Valor zero não gera desconto', 0.0, $promocoes->calcularDesconto($promoFixa, 0));

// Vigência
$idFutura = $promocoes->criar([
    'titulo' => PREFIXO_TESTE . 'Futura', 'descricao' => '', 'servico_id' => '',
    'tipo_desconto' => 'fixo', 'valor_desconto' => 10,
    'data_inicio' => date('Y-m-d', strtotime('+10 days')), 'data_fim' => date('Y-m-d', strtotime('+20 days')),
    'limite_total_usos' => '', 'status' => 'ativa',
]);
$t->verificar('Promoção futura NÃO aparece na vitrine',
    !in_array($idFutura, array_map('intval', array_column($promocoes->vigentes(), 'id')), true));

$idVencida = $promocoes->criar([
    'titulo' => PREFIXO_TESTE . 'Vencida', 'descricao' => '', 'servico_id' => '',
    'tipo_desconto' => 'fixo', 'valor_desconto' => 10,
    'data_inicio' => date('Y-m-d', strtotime('-20 days')), 'data_fim' => date('Y-m-d', strtotime('-1 day')),
    'limite_total_usos' => '', 'status' => 'ativa',
]);
$t->verificar('Promoção vencida NÃO aparece na vitrine',
    !in_array($idVencida, array_map('intval', array_column($promocoes->vigentes(), 'id')), true));

$promocoes->expirarVencidas();
$t->igual('expirarVencidas() muda o status', 'expirada', $promocoes->buscarPorId($idVencida)['status']);

$idInativa = $promocoes->criar([
    'titulo' => PREFIXO_TESTE . 'Inativa', 'descricao' => '', 'servico_id' => '',
    'tipo_desconto' => 'fixo', 'valor_desconto' => 10,
    'data_inicio' => $HOJE, 'data_fim' => date('Y-m-d', strtotime('+10 days')),
    'limite_total_usos' => '', 'status' => 'inativa',
]);
$t->verificar('Promoção inativa NÃO aparece na vitrine',
    !in_array($idInativa, array_map('intval', array_column($promocoes->vigentes(), 'id')), true));

// Uso e limite
$idAgPromo = $agendamentoPago('PROMO1', $HOJE, '12:00:00', 100.00);
$t->verificar('Uso registrado', $promocoes->registrarUso($idPromo, $idCliente, $idAgPromo));
$t->verificar('Contador de usos incrementado', (int)$promocoes->buscarPorId($idPromo)['usos_realizados'] === 1);
$t->verificar('Cliente consta como já tendo usado', $promocoes->clienteJaUsou($idPromo, $idCliente));

$t->verificar('Mesmo cliente não usa duas vezes',
    !$promocoes->registrarUso($idPromo, $idCliente, $idAgPromo));
$t->igual('Contador não incrementa na tentativa duplicada', 1,
    (int)$promocoes->buscarPorId($idPromo)['usos_realizados']);

// Limite total esgotado
$idOutroCliente = $usuarios->criar([
    'nome' => 'Outro F8', 'email' => PREFIXO_TESTE . 'outro@local.test',
    'telefone' => '(11) 91111-0009', 'senha' => SENHA_TESTE, 'perfil' => 'cliente',
]);
$promocoes->registrarUso($idPromo, $idOutroCliente, $idAgPromo);
$t->igual('Segundo uso registrado', 2, (int)$promocoes->buscarPorId($idPromo)['usos_realizados']);
$t->verificar('Promoção sai da vitrine ao esgotar o limite',
    !in_array($idPromo, array_map('intval', array_column($promocoes->vigentes(), 'id')), true));
$t->verificar('estaVigente() reflete o limite esgotado',
    !$promocoes->estaVigente($promocoes->buscarPorId($idPromo)));

$t->igual('Relatório de usos lista as utilizações', 2, count($promocoes->usos($idPromo)));

// =====================================================================
$t->grupo('9. HTTP: RBAC do painel administrativo');

$entrar = function (string $email) use ($baseUrl): HttpClient {
    $c = new HttpClient($baseUrl);
    $csrf = $c->csrfDe('/login');
    $c->post('/login', ['csrf_token' => $csrf, 'email' => $email, 'senha' => SENHA_TESTE]);
    return $c;
};

$principalHttp = $entrar(PREFIXO_TESTE . 'principal@local.test');
$limitadoHttp  = $entrar(PREFIXO_TESTE . 'limitado@local.test');
$clienteHttp   = $entrar(PREFIXO_TESTE . 'cliente@local.test');
$visitante     = new HttpClient($baseUrl);

// Administrador Principal.
// Verificar só o código 200 não basta: um erro fatal de PHP também responde
// 200 quando display_errors está ligado, e a tela chega quebrada ao usuário.
foreach (['/admin/dashboard', '/admin/agenda', '/admin/financeiro', '/admin/relatorios', '/admin/promocoes'] as $rota) {
    $t->igual("Admin Principal ACESSA {$rota}", 200, $principalHttp->get($rota)->ultimoStatus);
    $t->verificar("{$rota} renderiza sem erro de PHP",
        $principalHttp->erroPhp() === null, (string)$principalHttp->erroPhp());
}

// Administrador Limitado: agenda e promoções sim, financeiro não
$t->igual('Admin Limitado ACESSA /admin/agenda', 200, $limitadoHttp->get('/admin/agenda')->ultimoStatus);
$t->igual('Admin Limitado ACESSA /admin/promocoes', 200, $limitadoHttp->get('/admin/promocoes')->ultimoStatus);
$t->igual('Admin Limitado é BLOQUEADO em /admin/financeiro (403)', 403,
    $limitadoHttp->get('/admin/financeiro')->ultimoStatus);
$t->igual('Admin Limitado é BLOQUEADO em /admin/relatorios (403)', 403,
    $limitadoHttp->get('/admin/relatorios')->ultimoStatus);
$t->igual('Admin Limitado é BLOQUEADO na exportação (403)', 403,
    $limitadoHttp->get('/admin/relatorios/exportar')->ultimoStatus);

$t->verificar('Menu do Admin Limitado NÃO exibe Financeiro',
    !$limitadoHttp->get('/admin/dashboard')->corpoContem('/admin/financeiro'));
$t->verificar('Dashboard do Admin Limitado NÃO mostra o lucro do mês',
    !$limitadoHttp->corpoContem('LUCRO LÍQUIDO DO MÊS'));
$t->verificar('Dashboard do Admin Principal mostra o lucro do mês',
    $principalHttp->get('/admin/dashboard')->corpoContem('LUCRO LÍQUIDO DO MÊS'));

// Cliente e visitante
foreach (['/admin/agenda', '/admin/financeiro', '/admin/promocoes'] as $rota) {
    $t->igual("Cliente é BLOQUEADO em {$rota} (403)", 403, $clienteHttp->get($rota)->ultimoStatus);
    $t->igual("Visitante é redirecionado de {$rota}", 302, $visitante->get($rota)->ultimoStatus);
}

// POST protegido, não só a tela
$csrfLimitado = $limitadoHttp->csrfDe('/admin/agenda');
$t->igual('Admin Limitado é BLOQUEADO ao lançar despesa (403)', 403,
    $limitadoHttp->post('/admin/financeiro/despesa', ['csrf_token' => $csrfLimitado, 'valor' => '10'])->ultimoStatus);

// =====================================================================
$t->grupo('10. HTTP: CSRF nas ações da Fase 8');

foreach (['/admin/agenda/manual', '/admin/agenda/cancelar', '/admin/financeiro/despesa',
          '/admin/financeiro/despesa/excluir', '/admin/financeiro/categoria',
          '/admin/promocoes/salvar', '/admin/promocoes/excluir'] as $rota) {
    $t->igual("POST {$rota} sem CSRF é rejeitado", 403,
        $principalHttp->post($rota, ['id' => '1'])->ultimoStatus);
}

// =====================================================================
$t->grupo('11. HTTP: fluxos administrativos');

// Lançamento de despesa pela interface
$csrf = $principalHttp->csrfDe('/admin/financeiro');
$principalHttp->post('/admin/financeiro/despesa', [
    'csrf_token' => $csrf, 'categoria_id' => '3',
    'descricao' => PREFIXO_TESTE . 'Energia eletrica', 'valor' => '450,75',
    'data_despesa' => $HOJE,
]);
$criada = $pdo->prepare('SELECT * FROM despesas WHERE descricao = ?');
$criada->execute([PREFIXO_TESTE . 'Energia eletrica']);
$criada = $criada->fetch();
$t->verificar('Despesa lançada pela interface', $criada !== false);
$t->igual('Valor com vírgula convertido', '450.75', $criada['valor'] ?? null);

// Validações da despesa
$casos = [
    'valor negativo'        => ['valor' => '-50', 'descricao' => PREFIXO_TESTE . 'Neg'],
    'valor zero'            => ['valor' => '0', 'descricao' => PREFIXO_TESTE . 'Zero'],
    'valor não numérico'    => ['valor' => 'abc', 'descricao' => PREFIXO_TESTE . 'Abc'],
    'descrição curta'       => ['valor' => '10', 'descricao' => 'ab'],
    'data no futuro'        => ['valor' => '10', 'descricao' => PREFIXO_TESTE . 'Futuro',
                                'data_despesa' => date('Y-m-d', strtotime('+5 days'))],
    'categoria inexistente' => ['valor' => '10', 'descricao' => PREFIXO_TESTE . 'SemCat', 'categoria_id' => '99999'],
];

foreach ($casos as $descricao => $campos) {
    $csrf = $principalHttp->csrfDe('/admin/financeiro');
    $principalHttp->post('/admin/financeiro/despesa', array_merge(
        ['csrf_token' => $csrf, 'categoria_id' => '1', 'data_despesa' => $HOJE], $campos
    ));

    $existe = (int)$pdo->query(
        "SELECT COUNT(*) FROM despesas WHERE descricao = " . $pdo->quote($campos['descricao'])
    )->fetchColumn();
    $t->igual("Rejeita despesa com {$descricao}", 0, $existe);
}

// Agendamento manual pela interface, com cliente novo
$emailNovo = PREFIXO_TESTE . 'novocliente@local.test';
$csrf = $principalHttp->csrfDe('/admin/agenda');
$principalHttp->post('/admin/agenda/manual', [
    'csrf_token' => $csrf, 'cliente_email' => $emailNovo,
    'cliente_nome' => 'Cliente Criado no Balcao', 'cliente_telefone' => '(11) 90000-0000',
    // 10:00 conflitaria com o atendimento das 09:30 do grupo 5b
    'profissional_id' => (string)$idProf, 'data_agendamento' => $amanha, 'hora' => '11:00',
    'servicos' => [$idServico],
    'desconto_tipo' => 'fixo', 'desconto_valor' => '', 'desconto_justificativa' => '',
]);

$novoCliente = $usuarios->buscarPorEmail($emailNovo);
$t->verificar('Cliente novo é criado automaticamente', $novoCliente !== null);
$t->igual('Cliente criado com perfil correto', 'cliente', $novoCliente['perfil'] ?? null);
$t->verificar('Agendamento do balcão criado',
    (int)$pdo->query("SELECT COUNT(*) FROM agendamentos WHERE cliente_id = " . (int)$novoCliente['id'])->fetchColumn() === 1);

// Auditoria do agendamento manual
$t->verificar('Agendamento manual é auditado',
    (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entidade = 'agendamentos' AND acao = 'criar'")->fetchColumn() > 0);

// Agendamento de balcão com recebimento no ato, pela interface
$receitaAntesHttp = $relatorios->receitaNoPeriodo($MES_DE, $MES_ATE);

$csrf = $principalHttp->csrfDe('/admin/agenda');
$principalHttp->post('/admin/agenda/manual', [
    'csrf_token' => $csrf, 'cliente_email' => PREFIXO_TESTE . 'cliente@local.test',
    'cliente_nome' => '', 'cliente_telefone' => '',
    'profissional_id' => (string)$idProf, 'data_agendamento' => $amanha, 'hora' => '13:00',
    'servicos' => [$idServico],
    'desconto_tipo' => 'fixo', 'desconto_valor' => '', 'desconto_justificativa' => '',
    'forma_pagamento' => 'dinheiro',
]);

$t->igual('Recebimento no ato entra na receita pela interface',
    round($receitaAntesHttp + 100.0, 2), round($relatorios->receitaNoPeriodo($MES_DE, $MES_ATE), 2));

$principalHttp->get('/admin/agenda?data=' . $amanha);
$t->verificar('Mensagem confirma o recebimento registrado',
    $principalHttp->corpoContem('Recebimento de'));

// Registro de recebimento posterior, pela agenda
$aReceber = $agendamentos->criarManual($idCliente, $idProf, $amanha, '19:00', [$idServico]);
$receitaAntesPosterior = $relatorios->receitaNoPeriodo($MES_DE, $MES_ATE);

$principalHttp->get('/admin/agenda?data=' . $amanha);
$t->verificar('Agenda marca o atendimento como em aberto',
    $principalHttp->corpoContem('em aberto'));

$csrf = $principalHttp->csrfDe('/admin/agenda?data=' . $amanha);
$principalHttp->post('/admin/agenda/pagamento', [
    'csrf_token' => $csrf, 'codigo' => $aReceber['codigo'], 'forma_pagamento' => 'dinheiro',
]);

$t->igual('Recebimento posterior entra na receita',
    round($receitaAntesPosterior + 100.0, 2), round($relatorios->receitaNoPeriodo($MES_DE, $MES_ATE), 2));
$t->igual('Atendimento deixa de ter valor em aberto', 0.0,
    $agendamentos->valorDevido($agendamentos->buscarPorCodigo($aReceber['codigo'])));

// Cobrar duas vezes o mesmo atendimento é impedido
$csrf = $principalHttp->csrfDe('/admin/agenda?data=' . $amanha);
$principalHttp->post('/admin/agenda/pagamento', [
    'csrf_token' => $csrf, 'codigo' => $aReceber['codigo'], 'forma_pagamento' => 'dinheiro',
]);
$t->igual('Segundo recebimento no mesmo atendimento é recusado', 1,
    (int)$pdo->query("SELECT COUNT(*) FROM pagamentos p
                      INNER JOIN agendamentos a ON a.id = p.agendamento_id
                      WHERE a.codigo_reserva = " . $pdo->quote($aReceber['codigo']))->fetchColumn());

// O cliente nunca vê 'dinheiro' como opção no checkout online.
// Os atendimentos de balcão criados acima ocupam o limite de 4 ativos por
// cliente; concluí-los libera espaço sem desfazer a receita já apurada.
$pdo->prepare("UPDATE agendamentos SET situacao = 'concluido'
               WHERE cliente_id = ? AND situacao = 'confirmado'")->execute([$idCliente]);

$reservaOnline = $agendamentos->criarReserva($idCliente, $idProf, $amanha, '17:30', [$idServico]);
$clienteHttp->get('/checkout/' . $reservaOnline['codigo']);
$t->verificar('Checkout online NÃO oferece pagamento em dinheiro',
    !$clienteHttp->corpoContem('value="dinheiro"'));

// Promoção pela interface
$csrf = $principalHttp->csrfDe('/admin/promocoes');
$principalHttp->post('/admin/promocoes/salvar', [
    'csrf_token' => $csrf, 'id' => '0', 'titulo' => PREFIXO_TESTE . 'Promo HTTP',
    'descricao' => 'Criada por HTTP', 'servico_id' => '', 'tipo_desconto' => 'percentual',
    'valor_desconto' => '15', 'data_inicio' => $HOJE,
    'data_fim' => date('Y-m-d', strtotime('+15 days')), 'limite_total_usos' => '', 'status' => 'ativa',
]);
$promoHttp = $pdo->prepare('SELECT * FROM promocoes WHERE titulo = ?');
$promoHttp->execute([PREFIXO_TESTE . 'Promo HTTP']);
$promoHttp = $promoHttp->fetch();
$t->verificar('Promoção criada pela interface', $promoHttp !== false);

// Validações da promoção
$casosPromo = [
    'percentual acima de 100' => ['titulo' => PREFIXO_TESTE . 'P1', 'tipo_desconto' => 'percentual', 'valor_desconto' => '150'],
    'desconto zero'           => ['titulo' => PREFIXO_TESTE . 'P2', 'valor_desconto' => '0'],
    'título curto'            => ['titulo' => 'ab'],
    'data fim antes do início'=> ['titulo' => PREFIXO_TESTE . 'P4', 'data_fim' => date('Y-m-d', strtotime('-5 days'))],
    'status inválido'         => ['titulo' => PREFIXO_TESTE . 'P5', 'status' => 'hackeado'],
];

foreach ($casosPromo as $descricao => $campos) {
    $csrf = $principalHttp->csrfDe('/admin/promocoes');
    $principalHttp->post('/admin/promocoes/salvar', array_merge([
        'csrf_token' => $csrf, 'id' => '0', 'descricao' => '', 'servico_id' => '',
        'tipo_desconto' => 'fixo', 'valor_desconto' => '10', 'data_inicio' => $HOJE,
        'data_fim' => date('Y-m-d', strtotime('+10 days')), 'limite_total_usos' => '', 'status' => 'ativa',
    ], $campos));

    $existe = (int)$pdo->query(
        "SELECT COUNT(*) FROM promocoes WHERE titulo = " . $pdo->quote($campos['titulo'])
    )->fetchColumn();
    $t->igual("Rejeita promoção com {$descricao}", 0, $existe);
}

// Promoção usada não pode ser excluída
$csrf = $principalHttp->csrfDe('/admin/promocoes');
$principalHttp->post('/admin/promocoes/excluir', ['csrf_token' => $csrf, 'id' => (string)$idPromo]);
$t->verificar('Promoção já utilizada não é excluída', $promocoes->buscarPorId($idPromo) !== null);

// =====================================================================
$t->grupo('12. HTTP: exportação de relatórios');

$principalHttp->get('/admin/relatorios/exportar?de=' . $MES_DE . '&ate=' . $MES_ATE . '&formato=csv');
$t->igual('Exportação CSV responde 200', 200, $principalHttp->ultimoStatus);

$cabecalhosCsv = implode(' ', $principalHttp->ultimosHeaders);
$t->verificar('CSV é servido como download', stripos($cabecalhosCsv, 'attachment') !== false);
$t->verificar('CSV declara o tipo correto', stripos($cabecalhosCsv, 'text/csv') !== false);
$t->verificar('CSV começa com BOM UTF-8', strpos($principalHttp->ultimoCorpo, "\xEF\xBB\xBF") === 0);
$t->verificar('CSV usa ponto e vírgula como separador',
    strpos($principalHttp->ultimoCorpo, ';') !== false);
$t->verificar('CSV traz o cabeçalho das colunas',
    strpos($principalHttp->ultimoCorpo, 'Código da Reserva') !== false);
$t->verificar('CSV contém dados dos agendamentos',
    strpos($principalHttp->ultimoCorpo, PREFIXO_TESTE) !== false);

$linhasCsv = substr_count(trim($principalHttp->ultimoCorpo), "\n");
$t->verificar('CSV tem uma linha por agendamento mais o cabeçalho',
    $linhasCsv >= count($relatorios->agendamentos($filtros)), $linhasCsv . ' linhas');

$principalHttp->get('/admin/relatorios/exportar?de=' . $MES_DE . '&ate=' . $MES_ATE . '&formato=xls');
$t->igual('Exportação Excel responde 200', 200, $principalHttp->ultimoStatus);
$t->verificar('Excel é servido como download',
    stripos(implode(' ', $principalHttp->ultimosHeaders), 'attachment') !== false);
$t->verificar('Excel contém uma tabela', strpos($principalHttp->ultimoCorpo, '<table') !== false);

// Exportação respeita os filtros
$principalHttp->get('/admin/relatorios/exportar?de=' . $MES_DE . '&ate=' . $MES_ATE
    . '&situacao=concluido&formato=csv');
$t->verificar('Exportação filtrada não traz outras situações',
    strpos($principalHttp->ultimoCorpo, 'aguardando pagamento') === false);

$principalHttp->get('/admin/financeiro/exportar?de=' . $MES_DE . '&ate=' . $MES_ATE);
$t->igual('Exportação financeira responde 200', 200, $principalHttp->ultimoStatus);
$t->verificar('Exportação financeira traz o lucro líquido',
    strpos($principalHttp->ultimoCorpo, 'Lucro') !== false);

// =====================================================================
$t->grupo('13. HTTP: página pública da promoção');

$publico = new HttpClient($baseUrl);
$idPublica = (int)$promoHttp['id'];

$publico->get('/promocao/' . $idPublica);
$t->igual('Página da promoção é pública (sem login)', 200, $publico->ultimoStatus);
$t->verificar('Página exibe o título', $publico->corpoContem(PREFIXO_TESTE . 'Promo HTTP'));
$t->verificar('Página exibe o desconto', $publico->corpoContem('15% OFF'));
$t->verificar('Página oferece agendamento', $publico->corpoContem('Agendar Agora'));
$t->verificar('Página oferece compartilhamento no WhatsApp', $publico->corpoContem('wa.me'));

$t->igual('Promoção inexistente devolve 404', 404, $publico->get('/promocao/999999')->ultimoStatus);

// Promoção vencida abre, mas avisa
$publico->get('/promocao/' . $idVencida);
$t->igual('Promoção vencida ainda abre', 200, $publico->ultimoStatus);
$t->verificar('Promoção vencida avisa indisponibilidade',
    $publico->corpoContem('não está mais disponível'));
$t->verificar('Promoção vencida não oferece agendamento',
    !$publico->corpoContem('Agendar Agora'));

// Vitrine mostra as promoções vigentes
$publico->get('/inicio');
$t->verificar('Vitrine exibe a promoção vigente', $publico->corpoContem(PREFIXO_TESTE . 'Promo HTTP'));
$t->verificar('Vitrine NÃO exibe promoção vencida', !$publico->corpoContem(PREFIXO_TESTE . 'Vencida'));
$t->verificar('Vitrine NÃO exibe promoção futura', !$publico->corpoContem(PREFIXO_TESTE . 'Futura'));
$t->verificar('Vitrine NÃO exibe promoção inativa', !$publico->corpoContem(PREFIXO_TESTE . 'Inativa'));

// =====================================================================
$t->grupo('14. Segurança das telas administrativas');

// XSS nos campos administrativos
$promocoes->criar([
    'titulo' => PREFIXO_TESTE . '<script>alert(8)</script>', 'descricao' => '', 'servico_id' => '',
    'tipo_desconto' => 'fixo', 'valor_desconto' => 10, 'data_inicio' => $HOJE,
    'data_fim' => date('Y-m-d', strtotime('+10 days')), 'limite_total_usos' => '', 'status' => 'ativa',
]);

$principalHttp->get('/admin/promocoes');
$t->verificar('Título malicioso não é renderizado como script',
    !$principalHttp->corpoContem('<script>alert(8)</script>'));
$t->verificar('Conteúdo aparece escapado', $principalHttp->corpoContem('&lt;script&gt;'));

$publico->get('/inicio');
$t->verificar('Vitrine também escapa o título malicioso',
    !$publico->corpoContem('<script>alert(8)</script>'));

// Injeção nos filtros de data
$principalHttp->get('/admin/financeiro?de=' . urlencode("' OR 1=1 --") . '&ate=' . urlencode('xxx'));
$t->igual('Filtro de data com injeção não quebra a página', 200, $principalHttp->ultimoStatus);
$t->verificar('Injeção no filtro não gera erro de PHP', $principalHttp->erroPhp() === null);
$t->verificar('Datas inválidas caem no padrão do mês corrente',
    $principalHttp->corpoContem(date('Y-m-01')));
$t->verificar('Tabela `pagamentos` continua íntegra após a injeção',
    (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagamentos'")->fetchColumn() === 1);

$principalHttp->get('/admin/agenda?visao=hackeado&data=abc');
$t->igual('Visão inválida cai no padrão diário', 200, $principalHttp->ultimoStatus);
$t->verificar('Parâmetros inválidos na agenda não geram erro de PHP',
    $principalHttp->erroPhp() === null, (string)$principalHttp->erroPhp());

// Comprovantes não podem ser executados
$t->verificar('Diretório de uploads bloqueia execução de PHP',
    strpos(file_get_contents(__DIR__ . '/../public/uploads/.htaccess'), 'engine off') !== false);

// =====================================================================
// Limpeza
// =====================================================================
$principalHttp->encerrar();
$limitadoHttp->encerrar();
$clienteHttp->encerrar();
$visitante->encerrar();
$publico->encerrar();

limparDadosDeTeste($pdo);
echo "\n(Dados de teste removidos do banco.)\n";

exit($t->resumo());

// =====================================================================

function limparDadosDeTeste(PDO $pdo): void {
    $prefixo = PREFIXO_TESTE . '%';

    $pdo->prepare("DELETE FROM uso_promocoes WHERE promocao_id IN (SELECT id FROM promocoes WHERE titulo LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM promocoes WHERE titulo LIKE ?")->execute([$prefixo]);
    $pdo->prepare("DELETE FROM despesas WHERE descricao LIKE ?")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM despesas WHERE registrado_por IN (SELECT id FROM usuarios WHERE email LIKE ?)
    ")->execute([$prefixo]);
    $pdo->prepare("
        DELETE FROM avaliacoes WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
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
    $pdo->prepare("DELETE FROM audit_logs WHERE entidade_id IN (SELECT id FROM servicos WHERE nome LIKE ?)")
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
