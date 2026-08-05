<?php
/**
 * database/cron_lembretes.php - Rotinas automáticas do sistema
 *
 * Uso: php database/cron_lembretes.php [--simular]
 *
 * Agende para rodar a cada 15 minutos (docs/FSD.md seção 23.2):
 *
 *   Linux (crontab -e):
 *     0,15,30,45 * * * * /usr/bin/php /caminho/do/projeto/database/cron_lembretes.php >> /caminho/logs/cron.log 2>&1
 *
 *   Windows (Agendador de Tarefas):
 *     C:\xampp\php\php.exe C:\xampp\htdocs\sistemaBarbearia\database\cron_lembretes.php
 *
 * Executa três rotinas:
 *   1. Expira reservas cujo prazo de pagamento venceu, liberando o horário;
 *   2. Envia lembretes de 24 horas e de 2 horas antes do atendimento;
 *   3. Marca como expiradas as promoções cuja validade passou.
 *
 * A flag --simular mostra o que seria feito sem gravar nem enviar nada.
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

$simular = in_array('--simular', $argv ?? [], true);
$inicio = microtime(true);

echo '=====================================================' . PHP_EOL;
echo ' AgendaBarber Web - Rotinas Automáticas' . PHP_EOL;
echo ' ' . date('d/m/Y H:i:s') . ($simular ? '  [SIMULAÇÃO]' : '') . PHP_EOL;
echo '=====================================================' . PHP_EOL;

try {
    $pdo = Database::getConnection();
    $agendamentos = new AgendamentoModel();

    // -----------------------------------------------------------------
    // 1. Reservas vencidas
    // -----------------------------------------------------------------
    if ($simular) {
        $pendentes = (int)$pdo->query(
            "SELECT COUNT(*) FROM agendamentos
             WHERE situacao = 'aguardando_pagamento'
               AND reserva_expira_em IS NOT NULL
               AND reserva_expira_em < NOW()
               AND deleted_at IS NULL"
        )->fetchColumn();

        echo "[--]  {$pendentes} reserva(s) seriam expiradas." . PHP_EOL;
    } else {
        $expiradas = $agendamentos->expirarReservasVencidas();
        echo "[OK]  {$expiradas} reserva(s) expirada(s), horários liberados." . PHP_EOL;
    }

    // -----------------------------------------------------------------
    // 2. Lembretes
    // -----------------------------------------------------------------
    $enviados = ['24h' => 0, '2h' => 0];
    $falhas = 0;

    foreach (['24h' => 24, '2h' => 2] as $rotulo => $horas) {
        $coluna = $rotulo === '24h' ? 'lembrete_24h_em' : 'lembrete_2h_em';

        // Janela de 30 minutos: cobre o intervalo entre execuções do Cron
        // sem depender de o relógio bater no minuto exato.
        $stmt = $pdo->prepare(
            "SELECT a.id, a.codigo_reserva, a.data_agendamento, a.hora_inicio, a.valor_total,
                    u.nome AS cliente_nome, u.email AS cliente_email,
                    pu.nome AS profissional_nome,
                    (SELECT GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ')
                     FROM agendamento_servicos ags
                     INNER JOIN servicos s ON s.id = ags.servico_id
                     WHERE ags.agendamento_id = a.id) AS servicos
             FROM agendamentos a
             INNER JOIN usuarios u ON u.id = a.cliente_id
             INNER JOIN profissionais p ON p.id = a.profissional_id
             INNER JOIN usuarios pu ON pu.id = p.usuario_id
             WHERE a.situacao = 'confirmado'
               AND a.deleted_at IS NULL
               AND a.{$coluna} IS NULL
               AND CONCAT(a.data_agendamento, ' ', a.hora_inicio)
                   BETWEEN DATE_ADD(NOW(), INTERVAL :horas HOUR)
                       AND DATE_ADD(DATE_ADD(NOW(), INTERVAL :horas2 HOUR), INTERVAL 30 MINUTE)
             ORDER BY a.data_agendamento ASC, a.hora_inicio ASC"
        );
        $stmt->bindValue(':horas', $horas, PDO::PARAM_INT);
        $stmt->bindValue(':horas2', $horas, PDO::PARAM_INT);
        $stmt->execute();

        $lista = $stmt->fetchAll();

        foreach ($lista as $a) {
            if ($simular) {
                echo "[--]  Lembrete {$rotulo} para {$a['cliente_email']} ({$a['codigo_reserva']})" . PHP_EOL;
                $enviados[$rotulo]++;
                continue;
            }

            $ok = MailHelper::send(
                $a['cliente_email'],
                'Lembrete: seu atendimento é ' . ($rotulo === '24h' ? 'amanhã' : 'em breve'),
                montarLembrete($a, $rotulo, $config['app']['url'])
            );

            if ($ok) {
                $pdo->prepare("UPDATE agendamentos SET {$coluna} = NOW() WHERE id = :id")
                    ->execute([':id' => (int)$a['id']]);
                $enviados[$rotulo]++;
            } else {
                // Sem marcar a coluna, a próxima execução tenta de novo
                $falhas++;
                echo "[ERRO] Falha ao enviar lembrete {$rotulo} para {$a['cliente_email']}" . PHP_EOL;
            }
        }
    }

    echo "[OK]  Lembretes de 24h enviados: {$enviados['24h']}" . PHP_EOL;
    echo "[OK]  Lembretes de 2h enviados: {$enviados['2h']}" . PHP_EOL;

    if ($falhas > 0) {
        echo "[!!]  {$falhas} envio(s) falharam e serão tentados novamente." . PHP_EOL;
    }

    // -----------------------------------------------------------------
    // 3. Promoções vencidas
    // -----------------------------------------------------------------
    if ($simular) {
        $vencidas = (int)$pdo->query(
            "SELECT COUNT(*) FROM promocoes WHERE status = 'ativa' AND data_fim < CURDATE()"
        )->fetchColumn();

        echo "[--]  {$vencidas} promoção(ões) seriam expiradas." . PHP_EOL;
    } else {
        $expiradas = (new PromocaoModel())->expirarVencidas();
        echo "[OK]  {$expiradas} promoção(ões) marcada(s) como expirada(s)." . PHP_EOL;
    }

    $duracao = round((microtime(true) - $inicio) * 1000);
    echo '-----------------------------------------------------' . PHP_EOL;
    echo " Concluído em {$duracao}ms." . PHP_EOL . PHP_EOL;

    exit(0);

} catch (Throwable $e) {
    // O Cron roda sem supervisão: registrar é a única forma de saber da falha
    if (class_exists('LogModel')) {
        LogModel::erro($e);
    }

    echo PHP_EOL . '[FALHA] ' . $e->getMessage() . PHP_EOL;
    echo 'Arquivo: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL . PHP_EOL;

    exit(1);
}

/**
 * Corpo do e-mail de lembrete.
 */
function montarLembrete(array $agendamento, string $rotulo, string $baseUrl): string {
    $quando = $rotulo === '24h'
        ? 'amanhã, ' . FormatHelper::dateBr($agendamento['data_agendamento'])
        : 'hoje, ' . FormatHelper::dateBr($agendamento['data_agendamento']);

    $nome = htmlspecialchars($agendamento['cliente_nome'], ENT_QUOTES, 'UTF-8');
    $servicos = htmlspecialchars((string)$agendamento['servicos'], ENT_QUOTES, 'UTF-8');
    $profissional = htmlspecialchars($agendamento['profissional_nome'], ENT_QUOTES, 'UTF-8');
    $codigo = htmlspecialchars($agendamento['codigo_reserva'], ENT_QUOTES, 'UTF-8');
    $hora = substr($agendamento['hora_inicio'], 0, 5);
    $valor = FormatHelper::currency((float)$agendamento['valor_total']);

    $aviso = $rotulo === '2h'
        ? '<p style="color:#e9c176;"><strong>Seu horário é daqui a pouco.</strong> '
          . 'Lembre-se da tolerância de 10 minutos de atraso.</p>'
        : '<p>Caso precise cancelar ou remarcar, faça isso com no mínimo 24 horas de '
          . 'antecedência para ter direito ao reembolso.</p>';

    return "<p>Olá, {$nome}!</p>"
        . "<p>Este é um lembrete do seu atendimento <strong>{$quando} às {$hora}</strong>.</p>"
        . '<table role="presentation" cellpadding="6" cellspacing="0" border="0" style="width:100%;">'
        . "<tr><td style=\"color:#d1c5b4;\">Serviços</td><td><strong>{$servicos}</strong></td></tr>"
        . "<tr><td style=\"color:#d1c5b4;\">Profissional</td><td><strong>{$profissional}</strong></td></tr>"
        . "<tr><td style=\"color:#d1c5b4;\">Valor</td><td><strong>{$valor}</strong></td></tr>"
        . "<tr><td style=\"color:#d1c5b4;\">Reserva</td><td><strong>{$codigo}</strong></td></tr>"
        . '</table>'
        . $aviso
        . '<p><a href="' . $baseUrl . '/cliente/agendamentos" '
        . 'style="display:inline-block;padding:12px 24px;background-color:#c5a059;color:#412d00;'
        . 'text-decoration:none;border-radius:4px;font-weight:bold;">Ver meu agendamento</a></p>';
}
