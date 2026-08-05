<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 text-gold mb-4">Agendar Horário</h1>

            <?php require __DIR__ . '/_passos.php'; ?>

            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3">Revise seu agendamento</h2>

                <dl class="row mb-0">
                    <dt class="col-sm-4 label-caps text-muted-custom">PROFISSIONAL</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($profissional['nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-sm-4 label-caps text-muted-custom">DATA</dt>
                    <dd class="col-sm-8 dado-numerico"><?= FormatHelper::dateBr($wizard['data']) ?></dd>

                    <dt class="col-sm-4 label-caps text-muted-custom">HORÁRIO</dt>
                    <dd class="col-sm-8 dado-numerico"><?= htmlspecialchars($wizard['hora'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-sm-4 label-caps text-muted-custom">DURAÇÃO</dt>
                    <dd class="col-sm-8 dado-numerico"><?= (int)$duracaoTotal ?> minutos</dd>
                </dl>

                <hr class="border-secondary-dark">

                <h3 class="h6 text-gold mb-2">Serviços</h3>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($servicos as $s): ?>
                        <li class="d-flex justify-content-between border-bottom border-secondary-dark py-2">
                            <span><?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="dado-numerico"><?= FormatHelper::currency((float)$s['preco']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="d-flex justify-content-between align-items-center">
                    <span class="h6 mb-0">Total a pagar</span>
                    <strong class="text-gold dado-numerico fs-4"><?= FormatHelper::currency((float)$valorTotal) ?></strong>
                </div>

                <?php
                $orientacoes = array_filter(array_column($servicos, 'orientacoes'));
                if (!empty($orientacoes)):
                ?>
                    <div class="alerta alerta-aviso mt-4">
                        <strong class="d-block mb-1">Orientações antes do atendimento</strong>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($orientacoes as $o): ?>
                                <li><?= htmlspecialchars($o, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Políticas -->
            <div class="style-card p-4 mb-4">
                <h2 class="h6 text-gold mb-3">Políticas do estabelecimento</h2>

                <ul class="text-muted-custom">
                    <li>O pagamento é <strong>antecipado</strong> e confirma a reserva do horário.</li>
                    <li>O horário fica reservado por <strong><?= (int)($ajustes['checkout_reserva_minutos'] ?? 10) ?> minutos</strong> para a conclusão do pagamento.</li>
                    <li>Tolerância de atraso de <strong><?= (int)($ajustes['tolerancia_atraso_minutos'] ?? 10) ?> minutos</strong>. Após esse prazo o atendimento pode ser registrado como falta, sem reembolso.</li>
                    <li>Cancelamentos e reagendamentos são permitidos com <strong><?= (int)($ajustes['cancelamento_janela_horas'] ?? 24) ?> horas</strong> ou mais de antecedência.</li>
                    <li>Cancelamentos com menos de <?= (int)($ajustes['cancelamento_janela_horas'] ?? 24) ?> horas não geram reembolso.</li>
                </ul>

                <form method="POST" action="<?= $baseUrl ?>/agendar/confirmar" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="aceite_politicas" name="aceite_politicas" required>
                        <label class="form-check-label" for="aceite_politicas">
                            Li e aceito as políticas de pagamento, atraso e cancelamento. *
                        </label>
                    </div>

                    <?php if (!AuthHelper::check()): ?>
                        <div class="alerta alerta-aviso mb-3">
                            Você precisa entrar na sua conta para concluir. Sua seleção será mantida.
                        </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <a href="<?= $baseUrl ?>/agendar/horario" class="btn btn-outline-gold px-4">Voltar</a>
                        <button type="submit" class="btn btn-gold px-4 flex-grow-1">Ir para Pagamento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
