<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$hoje = date('Y-m-d');
?>

<main class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>

            <div class="style-card p-4 mt-4">
                <h2 class="h6 text-gold mb-2">Minhas Avaliações</h2>
                <?php if ($avaliacoes['total'] > 0): ?>
                    <p class="dado-numerico fs-4 text-gold mb-0">
                        <?= number_format($avaliacoes['media'], 1, ',', '') ?>
                        <span class="fs-6 text-muted-custom">/ 5</span>
                    </p>
                    <p class="text-muted-custom mb-0"><small><?= (int)$avaliacoes['total'] ?> avaliação(ões)</small></p>
                <?php else: ?>
                    <p class="text-muted-custom mb-0"><small>Nenhuma avaliação ainda.</small></p>
                <?php endif; ?>
            </div>

            <div class="style-card p-4 mt-4">
                <h2 class="h6 text-gold mb-2">Próximos Dias</h2>
                <?php if (empty($semana)): ?>
                    <p class="text-muted-custom mb-0"><small>Sem atendimentos no período.</small></p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($semana as $dia): ?>
                            <li class="d-flex justify-content-between border-top border-secondary-dark pt-2 mt-2">
                                <a href="<?= $baseUrl ?>/profissional/agenda?data=<?= $dia['data_agendamento'] ?>"
                                   class="text-decoration-none <?= $dia['data_agendamento'] === $data ? 'text-gold' : 'text-on-surface' ?>">
                                    <?= FormatHelper::dateBr($dia['data_agendamento']) ?>
                                </a>
                                <span class="text-muted-custom dado-numerico">
                                    <small><?= (int)$dia['concluidos'] ?>/<?= (int)$dia['total'] ?></small>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-1">Minha Agenda</h1>
            <p class="text-muted-custom mb-4">
                <?= htmlspecialchars($profissional['nome'] ?? AuthHelper::nome() ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <!-- Seletor de data -->
            <form method="GET" action="<?= $baseUrl ?>/profissional/agenda" class="style-card p-3 mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label for="data" class="form-label label-caps text-muted-custom">DATA</label>
                        <input type="date" class="campo-form" id="data" name="data" value="<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-gold w-100">Ver Agenda</button>
                    </div>
                    <div class="col-md-4">
                        <a href="<?= $baseUrl ?>/profissional/agenda?data=<?= $hoje ?>" class="btn btn-gold w-100">Hoje</a>
                    </div>
                </div>
            </form>

            <h2 class="h5 text-gold mb-3">
                <?= FormatHelper::dateBr($data) ?> — <?= count($atendimentos) ?> atendimento(s)
            </h2>

            <?php if (empty($atendimentos)): ?>
                <div class="style-card p-4">
                    <p class="text-muted-custom mb-0">Nenhum atendimento nesta data.</p>
                </div>
            <?php else: ?>
                <?php foreach ($atendimentos as $a): ?>
                    <?php
                    $confirmado = $a['situacao'] === 'confirmado';
                    $concluido  = $a['situacao'] === 'concluido';
                    $inicioSugerido = substr($a['hora_inicio_real'] ?? $a['hora_inicio'], 0, 5);

                    // O término sugerido só pode ser o relógio atual quando o
                    // atendimento é hoje e já começou. Caso contrário, sugerir
                    // "agora" produziria um horário anterior ao início.
                    $agora = date('H:i');
                    $ehHoje = $a['data_agendamento'] === $hoje;
                    $fimSugerido = ($ehHoje && $agora > $inicioSugerido)
                        ? $agora
                        : substr($a['hora_fim'], 0, 5);
                    ?>
                    <article class="style-card p-4 mb-3 cartao-atendimento cartao-<?= htmlspecialchars($a['situacao'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div>
                                <span class="dado-numerico fs-4 text-gold">
                                    <?= substr($a['hora_inicio'], 0, 5) ?>
                                </span>
                                <span class="text-muted-custom">— <?= substr($a['hora_fim'], 0, 5) ?></span>

                                <span class="etiqueta etiqueta-<?= htmlspecialchars($a['situacao'], ENT_QUOTES, 'UTF-8') ?> ms-2">
                                    <?= htmlspecialchars(str_replace('_', ' ', $a['situacao']), ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <?php if ((int)$a['confirmado_presenca'] === 1 && $confirmado): ?>
                                    <span class="etiqueta etiqueta-ativo ms-1">presença confirmada</span>
                                <?php endif; ?>

                                <h3 class="h6 text-on-surface mt-2 mb-1">
                                    <?= htmlspecialchars($a['cliente_nome'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                                <p class="text-muted-custom mb-0">
                                    <?= htmlspecialchars($a['servicos'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                                    <small>
                                        <?= htmlspecialchars($a['cliente_telefone'], ENT_QUOTES, 'UTF-8') ?>
                                        · <?= htmlspecialchars($a['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                </p>

                                <?php if ($concluido && !empty($a['hora_inicio_real'])): ?>
                                    <p class="text-muted-custom mb-0 mt-2 dado-numerico">
                                        <small>Atendimento real:
                                            <?= substr($a['hora_inicio_real'], 0, 5) ?>
                                            às <?= substr((string)$a['hora_fim_real'], 0, 5) ?>
                                        </small>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="text-end">
                                <strong class="dado-numerico text-muted-custom">
                                    <?= FormatHelper::currency((float)$a['valor_total']) ?>
                                </strong><br>
                                <a href="<?= $baseUrl ?>/profissional/cliente/<?= (int)$a['cliente_usuario_id'] ?>/notas"
                                   class="btn btn-outline-gold btn-sm mt-2">Notas do Cliente</a>
                            </div>
                        </div>

                        <?php if ($confirmado): ?>
                            <div class="border-top border-secondary-dark mt-3 pt-3 d-flex flex-wrap gap-2">
                                <?php if ((int)$a['confirmado_presenca'] !== 1): ?>
                                    <form method="POST" action="<?= $baseUrl ?>/profissional/presenca" class="m-0">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <input type="hidden" name="data" value="<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-gold btn-sm">Confirmar Presença</button>
                                    </form>
                                <?php endif; ?>

                                <button type="button" class="btn btn-outline-gold btn-sm"
                                        data-bs-toggle="collapse" data-bs-target="#concluir-<?= (int)$a['id'] ?>">
                                    Concluir Atendimento
                                </button>

                                <form method="POST" action="<?= $baseUrl ?>/profissional/falta" class="m-0"
                                      onsubmit="return confirm('Registrar falta do cliente? Não haverá reembolso.');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <input type="hidden" name="data" value="<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-perigo btn-sm">Registrar Falta</button>
                                </form>
                            </div>

                            <div class="collapse mt-3" id="concluir-<?= (int)$a['id'] ?>">
                                <form method="POST" action="<?= $baseUrl ?>/profissional/concluir"
                                      class="border-top border-secondary-dark pt-3">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <input type="hidden" name="data" value="<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>">

                                    <p class="text-muted-custom mb-2"><small>Registre os horários reais do atendimento.</small></p>

                                    <div class="row g-2 align-items-end">
                                        <div class="col-sm-4">
                                            <label for="ini-<?= (int)$a['id'] ?>" class="form-label label-caps text-muted-custom">INÍCIO REAL</label>
                                            <input type="time" class="campo-form" id="ini-<?= (int)$a['id'] ?>"
                                                   name="hora_inicio_real" value="<?= $inicioSugerido ?>">
                                        </div>
                                        <div class="col-sm-4">
                                            <label for="fim-<?= (int)$a['id'] ?>" class="form-label label-caps text-muted-custom">TÉRMINO REAL</label>
                                            <input type="time" class="campo-form" id="fim-<?= (int)$a['id'] ?>"
                                                   name="hora_fim_real" value="<?= $fimSugerido ?>">
                                        </div>
                                        <div class="col-sm-4">
                                            <button type="submit" class="btn btn-gold w-100">Concluir</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <p class="text-muted-custom mt-4">
                <small>Tolerância de atraso: <?= (int)$tolerancia ?> minutos. A falta só pode ser
                registrada depois desse prazo.</small>
            </p>
        </div>
    </div>
</main>
