<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$expiraEm = $agendamento['reserva_expira_em'];
$expirado = $expiraEm !== null && strtotime($expiraEm) < time();
?>

<main class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h1 class="h3 text-gold mb-4">Pagamento</h1>

            <?php if ($agendamento['situacao'] === 'aguardando_pagamento' && !$expirado): ?>
                <div class="style-card p-4 mb-4 text-center">
                    <span class="label-caps text-muted-custom d-block mb-1">TEMPO RESTANTE PARA PAGAR</span>
                    <strong class="cronometro text-gold" id="cronometro"
                            data-expira="<?= htmlspecialchars($expiraEm, ENT_QUOTES, 'UTF-8') ?>">--:--</strong>
                    <p class="text-muted-custom mb-0 mt-2">Após esse prazo o horário volta a ficar disponível.</p>
                </div>
            <?php elseif ($expirado || $agendamento['situacao'] === 'cancelado'): ?>
                <div class="alerta alerta-erro mb-4">
                    Esta reserva expirou e o horário foi liberado. Faça um novo agendamento.
                </div>
            <?php endif; ?>

            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3">
                    Reserva <?= htmlspecialchars($agendamento['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <dl class="row mb-3">
                    <dt class="col-sm-4 label-caps text-muted-custom">CLIENTE</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($agendamento['cliente_nome'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-sm-4 label-caps text-muted-custom">PROFISSIONAL</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($agendamento['profissional_nome'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-sm-4 label-caps text-muted-custom">DATA E HORA</dt>
                    <dd class="col-sm-8 dado-numerico">
                        <?= FormatHelper::dateBr($agendamento['data_agendamento']) ?>
                        às <?= substr($agendamento['hora_inicio'], 0, 5) ?>
                        (até <?= substr($agendamento['hora_fim'], 0, 5) ?>)
                    </dd>

                    <dt class="col-sm-4 label-caps text-muted-custom">SITUAÇÃO</dt>
                    <dd class="col-sm-8">
                        <span class="etiqueta etiqueta-<?= htmlspecialchars($agendamento['situacao'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(str_replace('_', ' ', $agendamento['situacao']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </dd>
                </dl>

                <h3 class="h6 text-gold mb-2">Serviços contratados</h3>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($servicos as $s): ?>
                        <li class="d-flex justify-content-between border-bottom border-secondary-dark py-2">
                            <span>
                                <?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>
                                <small class="text-muted-custom">(<?= (int)$s['duracao_aplicada_minutos'] ?> min)</small>
                            </span>
                            <span class="dado-numerico"><?= FormatHelper::currency((float)$s['preco_aplicado']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="d-flex justify-content-between align-items-center">
                    <span class="h6 mb-0">Total</span>
                    <strong class="text-gold dado-numerico fs-4">
                        <?= FormatHelper::currency((float)$agendamento['valor_total']) ?>
                    </strong>
                </div>
            </div>

            <div class="style-card p-4">
                <h2 class="h6 text-gold mb-2">Formas de pagamento</h2>
                <p class="text-muted-custom mb-0">
                    O pagamento por Pix (QR Code) e cartão de crédito via Mercado Pago será
                    habilitado na Fase 6. A reserva do horário e o cronômetro já estão ativos.
                </p>
            </div>
        </div>
    </div>
</main>

<script src="<?= $baseUrl ?>/public/assets/js/agendamento.js"></script>
