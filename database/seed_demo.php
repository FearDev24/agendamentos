<?php
/**
 * database/seed_demo.php - Dados de demonstração para o ambiente local
 *
 * Uso:  php database/seed_demo.php
 *       php database/seed_demo.php --remover
 *
 * Cria dois profissionais com jornada semanal e serviços vinculados, além de
 * uma conta de cliente, permitindo exercitar o fluxo completo de agendamento
 * sem cadastrar nada manualmente.
 *
 * ATENÇÃO: destinado APENAS a desenvolvimento. Não execute em produção.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Este script só pode ser executado via linha de comando.');
}

define('APP_EXEC', true);
date_default_timezone_set('America/Sao_Paulo');

$config = require __DIR__ . '/../config/config.php';
$GLOBALS['config'] = $config;

require_once __DIR__ . '/../app/bootstrap.php';

// Trava de segurança: não popula demonstração em ambiente de produção
if (($config['app']['env'] ?? 'production') !== 'development') {
    die("Recusado: este script só roda com 'env' => 'development' em config/config.php.\n");
}

const SENHA_DEMO = 'Demo@123';

/** Marca que identifica os registros criados aqui, para permitir a remoção */
const DOMINIO_DEMO = '@demo.agendabarber.local';

$pdo = Database::getConnection();
$usuarios = new UsuarioModel();
$profissionais = new ProfissionalModel();
$servicos = new ServicoModel();

$remover = in_array('--remover', $argv ?? [], true);

echo "=====================================================\n";
echo " AgendaBarber Web - Dados de Demonstração\n";
echo "=====================================================\n";

if ($remover) {
    removerDemo($pdo);
    echo "\nDados de demonstração removidos.\n";
    exit(0);
}

// ---------------------------------------------------------------------
// Serviços: usa os do seeding inicial
// ---------------------------------------------------------------------
$catalogo = $servicos->listarAtivos();

if (empty($catalogo)) {
    die("Nenhum serviço ativo encontrado. Rode antes: php database/migrate.php\n");
}

$idsServicos = array_map('intval', array_column($catalogo, 'id'));
$totalServicos = count($idsServicos);
echo "[OK]  {$totalServicos} serviços ativos encontrados no catálogo.\n";

// ---------------------------------------------------------------------
// Profissionais
// ---------------------------------------------------------------------
$equipe = [
    [
        'nome'  => 'João Barbeiro',
        'email' => 'joao' . DOMINIO_DEMO,
        'telefone' => '(11) 98765-4321',
        'limite' => '',
        // Segunda a sexta 09:00-19:00 com almoço, sábado 09:00-14:00
        'jornada' => [
            1 => ['09:00', '19:00', '12:00', '13:00'],
            2 => ['09:00', '19:00', '12:00', '13:00'],
            3 => ['09:00', '19:00', '12:00', '13:00'],
            4 => ['09:00', '19:00', '12:00', '13:00'],
            5 => ['09:00', '19:00', '12:00', '13:00'],
            6 => ['09:00', '14:00', null, null],
        ],
        'servicos' => $idsServicos, // executa todo o catálogo
    ],
    [
        'nome'  => 'Marina Estética',
        'email' => 'marina' . DOMINIO_DEMO,
        'telefone' => '(11) 91234-5678',
        'limite' => '8',
        // Terça a sábado, jornada mais curta
        'jornada' => [
            2 => ['10:00', '18:00', '13:00', '14:00'],
            3 => ['10:00', '18:00', '13:00', '14:00'],
            4 => ['10:00', '18:00', '13:00', '14:00'],
            5 => ['10:00', '18:00', '13:00', '14:00'],
            6 => ['10:00', '16:00', null, null],
        ],
        // Apenas parte do catálogo, para exercitar a regra de "executa todos"
        'servicos' => array_slice($idsServicos, 0, 3),
    ],
];

foreach ($equipe as $membro) {
    if ($usuarios->buscarPorEmail($membro['email']) !== null) {
        echo "[--]  {$membro['nome']} já existe. Pulando.\n";
        continue;
    }

    $id = $profissionais->criar([
        'nome'     => $membro['nome'],
        'email'    => $membro['email'],
        'telefone' => $membro['telefone'],
        'senha'    => SENHA_DEMO,
        'limite_diario_atendimentos' => $membro['limite'],
        'status'   => 'ativo',
    ]);

    $profissionais->sincronizarServicos($id, $membro['servicos']);

    $grade = [];
    foreach ($membro['jornada'] as $dia => [$inicio, $fim, $almocoInicio, $almocoFim]) {
        $grade[$dia] = [
            'hora_inicio'   => $inicio,
            'hora_fim'      => $fim,
            'almoco_inicio' => $almocoInicio,
            'almoco_fim'    => $almocoFim,
        ];
    }
    $profissionais->salvarHorarios($id, $grade);

    echo "[OK]  {$membro['nome']} criado: " . count($membro['servicos']) . " serviços, "
       . count($grade) . " dias de jornada.\n";
}

// ---------------------------------------------------------------------
// Cliente de demonstração
// ---------------------------------------------------------------------
$emailCliente = 'cliente' . DOMINIO_DEMO;

if ($usuarios->buscarPorEmail($emailCliente) === null) {
    $usuarios->criar([
        'nome'     => 'Carlos Cliente',
        'email'    => $emailCliente,
        'telefone' => '(11) 99999-8888',
        'senha'    => SENHA_DEMO,
        'perfil'   => 'cliente',
    ]);
    echo "[OK]  Cliente de demonstração criado.\n";
} else {
    echo "[--]  Cliente de demonstração já existe. Pulando.\n";
}

// ---------------------------------------------------------------------
echo "\n=====================================================\n";
echo " CONTAS DE ACESSO\n";
echo "=====================================================\n";
echo " Administrador : admin@agendabarber.local / Admin@123\n";
echo " Profissional  : joao" . DOMINIO_DEMO . " / " . SENHA_DEMO . "\n";
echo " Profissional  : marina" . DOMINIO_DEMO . " / " . SENHA_DEMO . "\n";
echo " Cliente       : cliente" . DOMINIO_DEMO . " / " . SENHA_DEMO . "\n";
echo "=====================================================\n";
echo " Para remover tudo isto: php database/seed_demo.php --remover\n\n";

/**
 * Remove os dados de demonstração, preservando o restante do banco.
 */
function removerDemo(PDO $pdo): void {
    $marca = '%' . DOMINIO_DEMO;

    $pdo->prepare("
        DELETE FROM pagamentos WHERE agendamento_id IN (
            SELECT id FROM agendamentos WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
        )
    ")->execute([$marca]);
    $pdo->prepare("
        DELETE FROM agendamento_servicos WHERE agendamento_id IN (
            SELECT id FROM agendamentos WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
        )
    ")->execute([$marca]);
    $pdo->prepare("
        DELETE FROM agendamentos WHERE cliente_id IN (SELECT id FROM usuarios WHERE email LIKE ?)
           OR profissional_id IN (
                SELECT p.id FROM profissionais p
                INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
           )
    ")->execute([$marca, $marca]);
    $pdo->prepare("
        DELETE FROM horarios_trabalho WHERE profissional_id IN (
            SELECT p.id FROM profissionais p
            INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
        )
    ")->execute([$marca]);
    $pdo->prepare("
        DELETE FROM profissional_servicos WHERE profissional_id IN (
            SELECT p.id FROM profissionais p
            INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
        )
    ")->execute([$marca]);
    $pdo->prepare("
        DELETE FROM indisponibilidades_bloqueios WHERE profissional_id IN (
            SELECT p.id FROM profissionais p
            INNER JOIN usuarios u ON u.id = p.usuario_id WHERE u.email LIKE ?
        )
    ")->execute([$marca]);
    $pdo->prepare("DELETE FROM security_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$marca]);
    $pdo->prepare("DELETE FROM audit_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$marca]);
    $pdo->prepare("DELETE FROM profissionais WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$marca]);
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?")->execute([$marca]);
}
