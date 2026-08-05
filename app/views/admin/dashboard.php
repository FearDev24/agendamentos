<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-1">Dashboard</h1>
            <p class="text-muted-custom mb-4"><?= FormatHelper::dateBr($hoje) ?></p>

            <!-- Métricas do dia -->
            <div class="row g-3 mb-4">
                <?php
                $cartoes = [
                    ['Agendamentos hoje', $metricas['total'], null],
                    ['Confirmados',       $metricas['confirmados'], 'confirmado'],
                    ['Concluídos',        $metricas['concluidos'], 'concluido'],
                    ['Aguardando pgto.',  $metricas['aguardando'], 'aguardando_pagamento'],
                ];
                foreach ($cartoes as [$rotulo, $valor, $situacao]): ?>
                    <div class="col-6 col-lg-3">
                        <div class="style-card cartao-metrica p-3 h-100">
                            <span class="label-caps text-muted-custom d-block"><?= strtoupper($rotulo) ?></span>
                            <strong class="dado-numerico fs-2 <?= $situacao ? 'text-on-surface' : 'text-gold' ?>">
                                <?= (int)$valor ?>
                            </strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="style-card p-3">
                        <span class="label-caps text-muted-custom d-block">FATURAMENTO DE HOJE</span>
                        <strong class="dado-numerico fs-3 text-gold">
                            <?= FormatHelper::currency((float)$metricas['faturamento']) ?>
                        </strong>
                        <small class="text-muted-custom d-block">Pagamentos aprovados hoje</small>
                    </div>
                </div>

                <?php if ($resumoMes !== null): ?>
                    <div class="col-md-6">
                        <div class="style-card p-3">
                            <span class="label-caps text-muted-custom d-block">LUCRO LÍQUIDO DO MÊS</span>
                            <strong class="dado-numerico fs-3 <?= $resumoMes['lucro_liquido'] >= 0 ? 'text-gold' : 'text-erro' ?>">
                                <?= FormatHelper::currency((float)$resumoMes['lucro_liquido']) ?>
                            </strong>
                            <small class="text-muted-custom d-block">
                                Receita <?= FormatHelper::currency((float)$resumoMes['receita_liquida']) ?>
                                − despesas <?= FormatHelper::currency((float)$resumoMes['despesas']) ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($metricas['cancelados'] > 0 || $metricas['faltas'] > 0): ?>
                <div class="alerta alerta-aviso mb-4">
                    Hoje houve <strong><?= (int)$metricas['cancelados'] ?></strong> cancelamento(s)
                    e <strong><?= (int)$metricas['faltas'] ?></strong> falta(s).
                </div>
            <?php endif; ?>

            <!-- Atendimentos pendentes de conclusão -->
            <?php if (!empty($pendentes)): ?>
                <div class="style-card style-card-perigo p-4 mb-4">
                    <h2 class="h6 text-erro mb-2">Pendentes de conclusão (<?= count($pendentes) ?>)</h2>
                    <p class="text-muted-custom">
                        Atendimentos cujo horário já passou e continuam como confirmados.
                        O profissional precisa concluí-los ou registrar falta.
                    </p>

                    <div class="tabela-rolavel">
                        <table class="table tabela-dados align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Código</th>
                                    <th scope="col">Horário</th>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Profissional</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pendentes as $p): ?>
                                <tr>
                                    <td class="dado-numerico"><?= htmlspecialchars($p['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="dado-numerico"><?= substr($p['hora_inicio'], 0, 5) ?></td>
                                    <td><?= htmlspecialchars($p['cliente_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['profissional_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Linha do tempo de hoje -->
            <div class="style-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 text-gold mb-0">Agenda de hoje</h2>
                    <a href="<?= $baseUrl ?>/admin/agenda?data=<?= $hoje ?>" class="btn btn-outline-gold btn-sm">
                        Ver agenda completa
                    </a>
                </div>

                <?php if (empty($agendaHoje)): ?>
                    <p class="text-muted-custom mb-0">Nenhum agendamento para hoje.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0 linha-tempo">
                        <?php foreach ($agendaHoje as $a): ?>
                            <li class="linha-tempo-item linha-<?= htmlspecialchars($a['situacao'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="dado-numerico text-gold linha-tempo-hora">
                                    <?= substr($a['hora_inicio'], 0, 5) ?>
                                </span>
                                <div class="flex-grow-1">
                                    <strong class="d-block text-on-surface">
                                        <?= htmlspecialchars($a['cliente_nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </strong>
                                    <small class="text-muted-custom">
                                        <?= htmlspecialchars($a['servicos'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($a['profissional_nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                </div>
                                <span class="etiqueta etiqueta-<?= htmlspecialchars($a['situacao'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $a['situacao']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
