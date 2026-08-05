<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

            <!-- Fidelidade -->
            <div class="style-card p-4 mt-4 text-center">
                <h2 class="h6 text-gold mb-3">Programa de Fidelidade</h2>
                <img src="<?= $baseUrl ?>/public/assets/img/fidelidade/<?= htmlspecialchars($fidelidade['insignia'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="Insígnia <?= htmlspecialchars($fidelidade['rotulo'], ENT_QUOTES, 'UTF-8') ?>"
                     class="insignia-fidelidade mb-2">
                <p class="h6 text-on-surface mb-1"><?= htmlspecialchars($fidelidade['rotulo'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-muted-custom mb-0">
                    <small>
                        <?= (int)$fidelidade['concluidos'] ?> atendimento(s) concluído(s)
                        <?php if ($fidelidade['proximo'] !== null): ?>
                            <br>Faltam <?= (int)$fidelidade['faltam'] ?> para <?= $fidelidade['proximo'] ?>
                        <?php endif; ?>
                    </small>
                </p>
            </div>

            <!-- Créditos -->
            <div class="style-card p-4 mt-4">
                <h2 class="h6 text-gold mb-2">Créditos Internos</h2>
                <p class="dado-numerico fs-4 text-gold mb-1"><?= FormatHelper::currency($saldo) ?></p>

                <?php if (empty($creditos)): ?>
                    <p class="text-muted-custom mb-0"><small>Nenhum crédito disponível.</small></p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($creditos as $c): ?>
                            <li class="text-muted-custom border-top border-secondary-dark pt-2 mt-2">
                                <small>
                                    <?= FormatHelper::currency((float)$c['saldo_restante']) ?>
                                    · vence em <?= FormatHelper::dateBr($c['data_expiracao']) ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-4">Meus Agendamentos</h1>

            <!-- Próximos -->
            <section class="mb-5">
                <h2 class="h5 text-gold mb-3">Próximos (<?= count($proximos) ?>)</h2>

                <?php if (empty($proximos)): ?>
                    <div class="style-card p-4 text-center">
                        <p class="text-muted-custom mb-3">Você não tem agendamentos futuros.</p>
                        <a href="<?= $baseUrl ?>/agendar" class="btn btn-gold px-4">Agendar Horário</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($proximos as $a): ?>
                        <?php
                        $podeCancelar = $modelo->podeCancelar($a);
                        $podeReagendar = $modelo->podeReagendar($a);
                        $noPrazo = $modelo->dentroDaJanelaDe24h($a);
                        ?>
                        <article class="style-card p-4 mb-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                <div>
                                    <span class="etiqueta etiqueta-<?= htmlspecialchars($a['situacao'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $a['situacao']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <h3 class="h6 text-on-surface mt-2 mb-1 dado-numerico">
                                        <?= FormatHelper::dateBr($a['data_agendamento']) ?>
                                        às <?= substr($a['hora_inicio'], 0, 5) ?>
                                    </h3>
                                    <p class="text-muted-custom mb-0">
                                        <?= htmlspecialchars($a['servicos'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                                        <small>com <?= htmlspecialchars($a['profissional_nome'], ENT_QUOTES, 'UTF-8') ?>
                                        · código <?= htmlspecialchars($a['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?></small>
                                    </p>
                                </div>
                                <strong class="text-gold dado-numerico fs-5">
                                    <?= FormatHelper::currency((float)$a['valor_total']) ?>
                                </strong>
                            </div>

                            <?php if (!$noPrazo): ?>
                                <div class="alerta alerta-aviso mb-3">
                                    <small>Faltam menos de <?= (int)$janela ?> horas: cancelamento sem reembolso e reagendamento indisponível.</small>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($a['situacao'] === 'aguardando_pagamento'): ?>
                                    <a href="<?= $baseUrl ?>/checkout/<?= urlencode($a['codigo_reserva']) ?>"
                                       class="btn btn-gold btn-sm">Pagar Agora</a>
                                <?php endif; ?>

                                <a href="<?= $baseUrl ?>/cliente/agendamento/<?= urlencode($a['codigo_reserva']) ?>/comprovante"
                                   class="btn btn-outline-gold btn-sm">Comprovante</a>

                                <?php if ($podeReagendar): ?>
                                    <a href="<?= $baseUrl ?>/cliente/agendamento/<?= urlencode($a['codigo_reserva']) ?>/reagendar"
                                       class="btn btn-outline-gold btn-sm">Reagendar</a>
                                <?php endif; ?>

                                <?php if ($podeCancelar): ?>
                                    <button type="button" class="btn btn-perigo btn-sm"
                                            data-bs-toggle="collapse" data-bs-target="#cancelar-<?= (int)$a['id'] ?>">
                                        Cancelar
                                    </button>
                                <?php endif; ?>
                            </div>

                            <?php if ($podeCancelar): ?>
                                <div class="collapse mt-3" id="cancelar-<?= (int)$a['id'] ?>">
                                    <form method="POST" action="<?= $baseUrl ?>/cliente/cancelar" class="border-top border-secondary-dark pt-3">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="codigo" value="<?= htmlspecialchars($a['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>">

                                        <p class="mb-2">
                                            <?php if ($noPrazo): ?>
                                                <span class="text-success">Cancelamento dentro do prazo: o reembolso de
                                                <?= FormatHelper::currency((float)$a['valor_total']) ?> será processado.</span>
                                            <?php else: ?>
                                                <span class="text-erro">Cancelamento fora do prazo: <strong>sem direito a reembolso</strong>.</span>
                                            <?php endif; ?>
                                        </p>

                                        <label for="motivo-<?= (int)$a['id'] ?>" class="form-label label-caps text-muted-custom">MOTIVO</label>
                                        <input type="text" class="campo-form mb-2" id="motivo-<?= (int)$a['id'] ?>"
                                               name="motivo" required minlength="3">

                                        <button type="submit" class="btn btn-perigo btn-sm">Confirmar Cancelamento</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- Histórico -->
            <section>
                <h2 class="h5 text-gold mb-3">Histórico (<?= count($historico) ?>)</h2>

                <?php if (empty($historico)): ?>
                    <div class="style-card p-4">
                        <p class="text-muted-custom mb-0">Nenhum atendimento anterior.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($historico as $a): ?>
                        <article class="style-card p-4 mb-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <span class="etiqueta etiqueta-<?= htmlspecialchars($a['situacao'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $a['situacao']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <h3 class="h6 text-on-surface mt-2 mb-1 dado-numerico">
                                        <?= FormatHelper::dateBr($a['data_agendamento']) ?>
                                        às <?= substr($a['hora_inicio'], 0, 5) ?>
                                    </h3>
                                    <p class="text-muted-custom mb-0">
                                        <?= htmlspecialchars($a['servicos'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                                        <small>com <?= htmlspecialchars($a['profissional_nome'], ENT_QUOTES, 'UTF-8') ?></small>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <strong class="text-muted-custom dado-numerico">
                                        <?= FormatHelper::currency((float)$a['valor_total']) ?>
                                    </strong><br>
                                    <a href="<?= $baseUrl ?>/cliente/agendamento/<?= urlencode($a['codigo_reserva']) ?>/comprovante"
                                       class="btn btn-outline-gold btn-sm mt-2">Comprovante</a>
                                </div>
                            </div>

                            <!-- Avaliação -->
                            <?php if ($a['situacao'] === 'concluido'): ?>
                                <?php if ((int)$a['avaliado'] > 0): ?>
                                    <p class="text-muted-custom mt-3 mb-0"><small>Atendimento já avaliado. Obrigado!</small></p>
                                <?php else: ?>
                                    <form method="POST" action="<?= $baseUrl ?>/cliente/avaliar"
                                          class="border-top border-secondary-dark mt-3 pt-3">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="codigo" value="<?= htmlspecialchars($a['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>">

                                        <p class="label-caps text-muted-custom mb-2">AVALIE ESTE ATENDIMENTO</p>

                                        <div class="estrelas mb-2" role="radiogroup" aria-label="Nota de 1 a 5 estrelas">
                                            <?php for ($n = 5; $n >= 1; $n--): ?>
                                                <input type="radio" id="nota-<?= (int)$a['id'] ?>-<?= $n ?>"
                                                       name="nota" value="<?= $n ?>" required>
                                                <label for="nota-<?= (int)$a['id'] ?>-<?= $n ?>"
                                                       title="<?= $n ?> estrela(s)">★</label>
                                            <?php endfor; ?>
                                        </div>

                                        <textarea class="campo-form mb-2" name="comentario" rows="2"
                                                  placeholder="Comentário (opcional)"></textarea>
                                        <button type="submit" class="btn btn-gold btn-sm">Enviar Avaliação</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
