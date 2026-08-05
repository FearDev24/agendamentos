<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-1">Reagendar Atendimento</h1>
            <p class="text-muted-custom mb-4">
                Reserva <?= htmlspecialchars($agendamento['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>
                · originalmente em <?= FormatHelper::dateBr($agendamento['data_agendamento']) ?>
                às <?= substr($agendamento['hora_inicio'], 0, 5) ?>
            </p>

            <div class="alerta alerta-aviso mb-4">
                Você já pagou <strong><?= FormatHelper::currency($valorPago) ?></strong>.
                Se o novo total for menor, a diferença vira crédito interno; se for maior,
                você paga apenas a diferença.
                <br><small>Reagendamentos usados: <?= (int)$agendamento['reagendamentos_realizados'] ?> de 2.</small>
            </div>

            <form method="POST" action="<?= $baseUrl ?>/cliente/reagendar" id="form-horario">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="codigo" value="<?= htmlspecialchars($agendamento['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="hora" id="hora-escolhida" value="">
                <input type="hidden" name="profissional_id" id="profissional-escolhido" value="">

                <!-- Serviços -->
                <div class="style-card p-4 mb-4">
                    <h2 class="h6 text-gold mb-1">Serviços</h2>
                    <p class="text-muted-custom mb-3">Você pode manter os mesmos ou alterar a seleção.</p>

                    <div class="row g-2">
                        <?php foreach ($catalogo as $s): ?>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="svc-<?= (int)$s['id'] ?>" name="servicos[]" value="<?= (int)$s['id'] ?>"
                                           <?= in_array((int)$s['id'], $servicosAtuais, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="svc-<?= (int)$s['id'] ?>">
                                        <?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>
                                        <small class="text-muted-custom">
                                            (<?= (int)$s['duracao_minutos'] ?> min · <?= FormatHelper::currency((float)$s['preco']) ?>)
                                        </small>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="text-muted-custom mt-3 mb-0">
                        <small>Ao trocar os serviços, recarregue os horários abaixo para ver a nova grade.</small>
                    </p>
                </div>

                <!-- Data e horário -->
                <div class="style-card p-4 mb-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="profissional" class="form-label label-caps text-muted-custom">PROFISSIONAL</label>
                            <select class="campo-form" id="profissional" name="profissional_filtro">
                                <option value="">Qualquer profissional disponível</option>
                                <?php foreach ($profissionais as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>"
                                        <?= (int)$p['id'] === (int)$agendamento['profissional_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="data" class="form-label label-caps text-muted-custom">NOVA DATA</label>
                            <input type="date" class="campo-form" id="data" name="data"
                                   min="<?= $dataMinima ?>" max="<?= $dataMaxima ?>" value="<?= $dataMinima ?>">
                        </div>
                    </div>
                </div>

                <div class="style-card p-4">
                    <h2 class="h6 text-gold mb-3">Horários disponíveis</h2>
                    <div id="grade-horarios" class="grade-horarios" aria-live="polite">
                        <p class="text-muted-custom mb-0">Selecione uma data para ver os horários.</p>
                    </div>

                    <a href="<?= $baseUrl ?>/cliente/agendamentos" class="btn btn-outline-gold mt-4">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    window.AGENDABARBER = {
        urlHorarios: '<?= $baseUrl ?>/api/horarios',
        // O reagendamento reenvia os serviços marcados a cada consulta
        formularioServicos: true
    };
</script>
<script src="<?= FormatHelper::asset('assets/js/agendamento.js') ?>"></script>
