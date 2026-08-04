<?php
/**
 * tests/teste_fase2.php - Validação da Fase 2 (Banco de Dados e Migrations)
 *
 * Uso: php tests/teste_fase2.php
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
require_once __DIR__ . '/../app/helpers/Database.php';
require_once __DIR__ . '/../database/MigrationRunner.php';

$t = new TestRunner();
$nomeBanco = $config['db']['dbname'];

echo "\n======================================================\n";
echo " TESTES DA FASE 2 - Banco de Dados e Migrations\n";
echo " Banco: {$nomeBanco}\n";
echo "======================================================\n";

// ---------------------------------------------------------------------
$t->grupo('1. Conexão PDO');

$pdo = Database::getConnection();
$t->verificar('Conexão PDO estabelecida com o banco', $pdo instanceof PDO);
$t->igual('Modo de erro configurado como EXCEPTION', PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
// O driver devolve este atributo como inteiro (0 = emulação desligada)
$t->verificar('Prepared Statements reais (emulação desligada)', (int)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === 0);
$t->verificar('Singleton retorna a mesma instância', $pdo === Database::getConnection());

// ---------------------------------------------------------------------
$t->grupo('2. Estrutura: as 20 tabelas do FSD + controle de migrations');

$tabelasEsperadas = [
    'usuarios', 'profissionais', 'servicos', 'profissional_servicos', 'horarios_trabalho',
    'indisponibilidades_bloqueios', 'agendamentos', 'agendamento_servicos', 'pagamentos',
    'creditos_internos', 'avaliacoes', 'promocoes', 'uso_promocoes', 'categorias_despesas',
    'despesas', 'notas_clientes', 'system_settings', 'audit_logs', 'error_logs', 'security_logs',
];

$stmt = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db');
$stmt->execute([':db' => $nomeBanco]);
$tabelasNoBanco = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tabelasEsperadas as $tabela) {
    $t->verificar("Tabela `{$tabela}` existe", in_array($tabela, $tabelasNoBanco, true));
}
$t->verificar('Tabela de controle `schema_migrations` existe', in_array('schema_migrations', $tabelasNoBanco, true));
$t->igual('Total de tabelas no banco (20 do FSD + schema_migrations)', 21, count($tabelasNoBanco));

// ---------------------------------------------------------------------
$t->grupo('3. Integridade: engine InnoDB e charset utf8mb4');

$stmt = $pdo->prepare("
    SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
    FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db
");
$stmt->execute([':db' => $nomeBanco]);
$naoInnodb = [];
$naoUtf8mb4 = [];
foreach ($stmt->fetchAll() as $linha) {
    if ($linha['ENGINE'] !== 'InnoDB') { $naoInnodb[] = $linha['TABLE_NAME']; }
    if (strpos((string)$linha['TABLE_COLLATION'], 'utf8mb4') !== 0) { $naoUtf8mb4[] = $linha['TABLE_NAME']; }
}
$t->verificar('Todas as tabelas usam engine InnoDB', empty($naoInnodb), implode(', ', $naoInnodb));
$t->verificar('Todas as tabelas usam charset utf8mb4', empty($naoUtf8mb4), implode(', ', $naoUtf8mb4));

// ---------------------------------------------------------------------
$t->grupo('4. Chaves estrangeiras');

$stmt = $pdo->prepare("
    SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = :db AND REFERENCED_TABLE_NAME IS NOT NULL
");
$stmt->execute([':db' => $nomeBanco]);
$fks = $stmt->fetchAll();

$mapaFk = [];
foreach ($fks as $fk) {
    $mapaFk[$fk['TABLE_NAME'] . '.' . $fk['COLUMN_NAME']] = $fk['REFERENCED_TABLE_NAME'];
}

$fksEsperadas = [
    'profissionais.usuario_id'                => 'usuarios',
    'profissional_servicos.profissional_id'   => 'profissionais',
    'profissional_servicos.servico_id'        => 'servicos',
    'horarios_trabalho.profissional_id'       => 'profissionais',
    'indisponibilidades_bloqueios.profissional_id' => 'profissionais',
    'agendamentos.cliente_id'                 => 'usuarios',
    'agendamentos.profissional_id'            => 'profissionais',
    'agendamento_servicos.agendamento_id'     => 'agendamentos',
    'agendamento_servicos.servico_id'         => 'servicos',
    'pagamentos.agendamento_id'               => 'agendamentos',
    'creditos_internos.cliente_id'            => 'usuarios',
    'creditos_internos.agendamento_origem_id' => 'agendamentos',
    'avaliacoes.agendamento_id'               => 'agendamentos',
    'promocoes.servico_id'                    => 'servicos',
    'uso_promocoes.promocao_id'               => 'promocoes',
    'despesas.categoria_id'                   => 'categorias_despesas',
    'despesas.registrado_por'                 => 'usuarios',
    'notas_clientes.cliente_id'               => 'usuarios',
    'audit_logs.usuario_id'                   => 'usuarios',
];

foreach ($fksEsperadas as $origem => $destino) {
    $t->verificar("FK {$origem} -> {$destino}", ($mapaFk[$origem] ?? null) === $destino);
}
$t->verificar('Total de chaves estrangeiras criadas: ' . count($fks), count($fks) >= 22);

// ---------------------------------------------------------------------
$t->grupo('5. Índices de consulta otimizados');

$indicesEsperados = [
    'usuarios'      => ['idx_usuarios_email', 'idx_usuarios_perfil', 'idx_usuarios_deleted'],
    'servicos'      => ['idx_servicos_status'],
    'agendamentos'  => ['idx_agendamentos_busca', 'idx_agendamentos_cliente', 'idx_agendamentos_situacao'],
    'pagamentos'    => ['idx_pagamentos_transacao', 'idx_pagamentos_status'],
    'horarios_trabalho' => ['idx_horarios_prof_dia'],
    'indisponibilidades_bloqueios' => ['idx_bloqueios_datas', 'idx_bloqueios_prof'],
    'creditos_internos' => ['idx_creditos_cliente'],
    'despesas'      => ['idx_despesas_data'],
    'audit_logs'    => ['idx_audit_entidade'],
    'security_logs' => ['idx_security_ip'],
];

$stmt = $pdo->prepare('SELECT DISTINCT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :db');
$stmt->execute([':db' => $nomeBanco]);
$indicesNoBanco = [];
foreach ($stmt->fetchAll() as $linha) {
    $indicesNoBanco[$linha['TABLE_NAME']][] = $linha['INDEX_NAME'];
}

foreach ($indicesEsperados as $tabela => $indices) {
    foreach ($indices as $indice) {
        $t->verificar("Índice `{$indice}` em `{$tabela}`", in_array($indice, $indicesNoBanco[$tabela] ?? [], true));
    }
}

// ---------------------------------------------------------------------
$t->grupo('6. Colunas críticas de regras de negócio');

function colunas(PDO $pdo, string $db, string $tabela): array {
    $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t');
    $stmt->execute([':db' => $db, ':t' => $tabela]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$colAgend = colunas($pdo, $nomeBanco, 'agendamentos');
$t->verificar('agendamentos.reserva_expira_em (retenção de 10 min)', in_array('reserva_expira_em', $colAgend, true));
$t->verificar('agendamentos.reagendamentos_realizados (limite de 2x)', in_array('reagendamentos_realizados', $colAgend, true));
$t->verificar('agendamentos.credito_utilizado (crédito interno)', in_array('credito_utilizado', $colAgend, true));
$t->verificar('agendamentos.hora_inicio_real / hora_fim_real (painel do profissional)',
    in_array('hora_inicio_real', $colAgend, true) && in_array('hora_fim_real', $colAgend, true));

$colHorarios = colunas($pdo, $nomeBanco, 'horarios_trabalho');
$t->verificar('horarios_trabalho.almoco_inicio / almoco_fim (typo do FSD corrigido)',
    in_array('almoco_inicio', $colHorarios, true) && in_array('almoco_fim', $colHorarios, true));

// Soft Delete obrigatório (FSD seção 18)
$comSoftDelete = ['usuarios', 'profissionais', 'servicos', 'agendamentos', 'despesas'];
foreach ($comSoftDelete as $tabela) {
    $t->verificar("Soft Delete: `{$tabela}`.deleted_at", in_array('deleted_at', colunas($pdo, $nomeBanco, $tabela), true));
}

// ENUM de situação do agendamento com os 6 estados do FSD
$stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'agendamentos' AND COLUMN_NAME = 'situacao'");
$stmt->execute([':db' => $nomeBanco]);
$tipoSituacao = (string)$stmt->fetchColumn();
$estados = ['aguardando_pagamento', 'confirmado', 'concluido', 'cancelado', 'falta', 'reembolsado'];
$faltando = array_filter($estados, fn($e) => strpos($tipoSituacao, "'{$e}'") === false);
$t->verificar('ENUM agendamentos.situacao contém os 6 estados do FSD', empty($faltando), implode(', ', $faltando));

// ---------------------------------------------------------------------
$t->grupo('7. Dados iniciais (seeding)');

$admin = $pdo->query("SELECT * FROM usuarios WHERE perfil = 'admin_principal' LIMIT 1")->fetch();
$t->verificar('Usuário Administrador Principal criado', $admin !== false);
$t->igual('E-mail do administrador padrão', 'admin@agendabarber.local', $admin['email'] ?? null);
$t->igual('Status do administrador', 'ativo', $admin['status'] ?? null);
$t->verificar('Senha gravada com hash BCRYPT (nunca em texto plano)',
    isset($admin['senha_hash']) && strpos($admin['senha_hash'], '$2y$') === 0);
$t->verificar('password_verify() valida a senha padrão "Admin@123"',
    isset($admin['senha_hash']) && password_verify('Admin@123', $admin['senha_hash']));

$t->igual('Categorias de despesas cadastradas', 10, (int)$pdo->query('SELECT COUNT(*) FROM categorias_despesas')->fetchColumn());
$t->igual('Serviços base cadastrados', 6, (int)$pdo->query("SELECT COUNT(*) FROM servicos WHERE status = 'ativo'")->fetchColumn());
$t->igual('Configurações globais em system_settings', 17, (int)$pdo->query('SELECT COUNT(*) FROM system_settings')->fetchColumn());

$settings = $pdo->query('SELECT chave, valor FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$t->igual('Regra: reserva de checkout = 10 minutos', '10', $settings['checkout_reserva_minutos'] ?? null);
$t->igual('Regra: janela de cancelamento = 24 horas', '24', $settings['cancelamento_janela_horas'] ?? null);
$t->igual('Regra: validade do crédito interno = 90 dias', '90', $settings['credito_validade_dias'] ?? null);
$t->igual('Regra: intervalo entre atendimentos = 10 minutos', '10', $settings['agendamento_intervalo_minutos'] ?? null);
$t->igual('Regra: máximo de agendamentos ativos = 4', '4', $settings['agendamento_max_ativos_por_cliente'] ?? null);
$t->igual('Regra: antecedência mínima = 2 horas', '2', $settings['agendamento_antecedencia_min_horas'] ?? null);
$t->igual('Regra: antecedência máxima = 30 dias', '30', $settings['agendamento_antecedencia_max_dias'] ?? null);

// ---------------------------------------------------------------------
$t->grupo('8. Restrições do banco em execução (integridade referencial)');

$t->lancaExcecao('FK bloqueia agendamento com cliente inexistente', function () use ($pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO agendamentos (codigo_reserva, cliente_id, profissional_id, data_agendamento,
                                  hora_inicio, hora_fim, duracao_total_minutos, valor_total)
        VALUES ('TESTE-FK-01', 999999, 999999, '2030-01-01', '10:00:00', '11:00:00', 60, 100.00)
    ");
    $stmt->execute();
});

$t->lancaExcecao('UNIQUE bloqueia e-mail de usuário duplicado', function () use ($pdo) {
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, telefone) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Duplicado', 'admin@agendabarber.local', 'x', '(00) 0000-0000']);
});

$t->lancaExcecao('CHECK bloqueia nota de avaliação fora do intervalo 1-5', function () use ($pdo) {
    $stmt = $pdo->prepare("INSERT INTO avaliacoes (agendamento_id, cliente_id, profissional_id, nota) VALUES (1, 1, 1, 9)");
    $stmt->execute();
});

$t->lancaExcecao('UNIQUE bloqueia categoria de despesa duplicada', function () use ($pdo) {
    $pdo->prepare('INSERT INTO categorias_despesas (nome) VALUES (?)')->execute(['Aluguel']);
});

// ---------------------------------------------------------------------
$t->grupo('9. Idempotência do runner de migrations');

$runner = new MigrationRunner();
$aplicadas = $runner->aplicadas();
$t->igual('Migrations registradas em schema_migrations', 2, count($aplicadas));
$t->verificar('001_create_system_tables.php registrada', in_array('001_create_system_tables.php', $aplicadas, true));
$t->verificar('002_seed_initial_data.php registrada', in_array('002_seed_initial_data.php', $aplicadas, true));
$t->igual('Nenhuma migration pendente após execução', 0, count($runner->pendentes()));

$logSegundaExecucao = $runner->run();
$t->verificar('Reexecutar o runner não reaplica migrations (idempotente)',
    count($logSegundaExecucao) === 1 && $logSegundaExecucao[0]['tipo'] === 'info');
$t->igual('Contagem de serviços inalterada após reexecução', 6, (int)$pdo->query('SELECT COUNT(*) FROM servicos')->fetchColumn());
$t->igual('Contagem de administradores inalterada após reexecução', 1,
    (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'admin_principal'")->fetchColumn());

// ---------------------------------------------------------------------
$t->grupo('10. Segurança dos scripts de banco');

// Executa a migration em um processo PHP isolado, sem definir APP_EXEC:
// o guard no topo do arquivo deve abortar a execução.
$arquivosProtegidos = [
    'database/migrations/001_create_system_tables.php',
    'database/migrations/002_seed_initial_data.php',
    'database/MigrationRunner.php',
    'app/helpers/Database.php',
    'config/config.php',
];

foreach ($arquivosProtegidos as $relativo) {
    $caminho = str_replace('\\', '/', realpath(__DIR__ . '/../' . $relativo));
    $comando = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('include ' . var_export($caminho, true) . ';') . ' 2>&1';

    $saida = [];
    exec($comando, $saida);
    $t->verificar("`{$relativo}` recusa execução sem a constante APP_EXEC",
        strpos(implode(' ', $saida), 'Acesso direto negado') !== false,
        trim(implode(' ', $saida)));
}

$conteudoMigrate = file_get_contents(__DIR__ . '/../database/migrate.php');
$t->verificar('migrate.php recusa execução via navegador (checa PHP_SAPI)',
    strpos($conteudoMigrate, "PHP_SAPI !== 'cli'") !== false);
$t->verificar('.htaccess de proteção existe em database/', file_exists(__DIR__ . '/../database/.htaccess'));
$t->verificar('.htaccess de proteção existe em tests/', file_exists(__DIR__ . '/.htaccess'));

$conteudoSeed = file_get_contents(__DIR__ . '/../database/migrations/002_seed_initial_data.php');
$t->verificar('Seeding usa password_hash() e não grava senha em texto plano',
    strpos($conteudoSeed, 'password_hash(') !== false);
$t->verificar('Seeding usa Prepared Statements (prepare/execute)',
    strpos($conteudoSeed, '$pdo->prepare(') !== false);

// ---------------------------------------------------------------------
exit($t->resumo());
