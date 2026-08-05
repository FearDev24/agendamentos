<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-4">Agendar Horário</h1>

            <?php require __DIR__ . '/_passos.php'; ?>

            <!-- Resumo do que já foi escolhido -->
            <div class="style-card p-4 mb-4">
                <h2 class="h6 text-gold mb-2">Serviços selecionados</h2>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($servicos as $s): ?>
                        <li class="d-flex justify-content-between border-bottom border-secondary-dark py-2">
                            <span><?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="dado-numerico text-muted-custom">
                                <?= (int)$s['duracao_minutos'] ?> min · <?= FormatHelper::currency((float)$s['preco']) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted-custom dado-numerico">Duração total: <?= (int)$duracaoTotal ?> min</span>
                    <strong class="text-gold dado-numerico fs-5"><?= FormatHelper::currency((float)$valorTotal) ?></strong>
                </div>

                <a href="<?= $baseUrl ?>/agendar" class="btn btn-outline-gold btn-sm mt-3">Alterar serviços</a>
            </div>

            <?php if (empty($profissionais)): ?>
                <div class="alerta alerta-aviso">
                    Nenhum profissional está habilitado para executar todos os serviços escolhidos ao mesmo tempo.
                    Volte e ajuste a seleção.
                </div>
            <?php else: ?>
                <form method="POST" action="<?= $baseUrl ?>/agendar/horario" id="form-horario">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="hora" id="hora-escolhida" value="">
                    <input type="hidden" name="profissional_id" id="profissional-escolhido" value="">

                    <div class="style-card p-4 mb-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="profissional" class="form-label label-caps text-muted-custom">PROFISSIONAL</label>
                                <select class="campo-form" id="profissional" name="profissional_filtro">
                                    <option value="">Qualquer profissional disponível</option>
                                    <?php foreach ($profissionais as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="data" class="form-label label-caps text-muted-custom">DATA</label>
                                <input type="date" class="campo-form" id="data" name="data"
                                       min="<?= $dataMinima ?>" max="<?= $dataMaxima ?>"
                                       value="<?= htmlspecialchars($wizard['data'] ?? $dataMinima, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="style-card p-4">
                        <h2 class="h6 text-gold mb-1">Horários disponíveis</h2>
                        <p class="text-muted-custom">
                            A grade considera a agenda do profissional, folgas e o intervalo entre atendimentos.
                        </p>

                        <div id="grade-horarios" class="grade-horarios" aria-live="polite">
                            <p class="text-muted-custom mb-0">Selecione uma data para ver os horários.</p>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    window.AGENDABARBER = {
        urlHorarios: '<?= $baseUrl ?>/api/horarios'
    };
</script>
<script src="<?= FormatHelper::asset('assets/js/agendamento.js') ?>"></script>
