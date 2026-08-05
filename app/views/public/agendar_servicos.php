<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-4">Agendar Horário</h1>

            <?php require __DIR__ . '/_passos.php'; ?>

            <?php if (empty($servicos)): ?>
                <div class="style-card p-4">
                    <p class="text-muted-custom mb-0">Nenhum serviço disponível para agendamento no momento.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="<?= $baseUrl ?>/agendar/servicos" id="form-servicos">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="style-card p-4 mb-4">
                        <h2 class="h5 text-gold mb-1">Escolha os serviços</h2>
                        <p class="text-muted-custom mb-4">Você pode selecionar mais de um.</p>

                        <div class="row g-3">
                            <?php foreach ($servicos as $s): ?>
                                <div class="col-md-6">
                                    <label class="cartao-servico" for="servico_<?= (int)$s['id'] ?>">
                                        <input class="form-check-input mt-0 me-2" type="checkbox"
                                               id="servico_<?= (int)$s['id'] ?>" name="servicos[]"
                                               value="<?= (int)$s['id'] ?>"
                                               data-preco="<?= htmlspecialchars($s['preco'], ENT_QUOTES, 'UTF-8') ?>"
                                               data-duracao="<?= (int)$s['duracao_minutos'] ?>"
                                               <?= in_array((int)$s['id'], array_map('intval', $selecionados), true) ? 'checked' : '' ?>>

                                        <span class="flex-grow-1">
                                            <strong class="d-block text-on-surface"><?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small class="text-muted-custom">
                                                <?= (int)$s['duracao_minutos'] ?> min · <?= FormatHelper::currency((float)$s['preco']) ?>
                                            </small>
                                            <?php if (!empty($s['orientacoes'])): ?>
                                                <small class="d-block text-muted-custom mt-1">
                                                    <?= htmlspecialchars($s['orientacoes'], ENT_QUOTES, 'UTF-8') ?>
                                                </small>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>

                <!-- Barra fixa com o total dinâmico -->
                <div class="barra-total" id="barra-total">
                    <div class="container d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <span class="label-caps text-muted-custom d-block">TOTAL SELECIONADO</span>
                            <strong class="dado-numerico text-gold fs-5" id="total-valor">R$ 0,00</strong>
                            <span class="text-muted-custom ms-2" id="total-duracao">0 min</span>
                        </div>
                        <button type="submit" form="form-servicos" class="btn btn-gold px-4" id="btn-continuar" disabled>
                            Continuar
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="<?= FormatHelper::asset('assets/js/agendamento.js') ?>"></script>
