<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/** Agrupa por data, para as visões semanal e mensal */
$porData = [];
foreach ($agendamentos as $a) {
    $porData[$a['data_agendamento']][] = $a;
}
?>

<main class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-4">Agenda Global</h1>

            <!-- Controles de visão e filtros -->
            <form method="GET" action="<?= $baseUrl ?>/admin/agenda" class="style-card p-3 mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="visao" class="form-label label-caps text-muted-custom">VISÃO</label>
                        <select class="campo-form" id="visao" name="visao">
                            <?php foreach (['dia' => 'Diária', 'semana' => 'Semanal', 'mes' => 'Mensal'] as $v => $r): ?>
                                <option value="<?= $v ?>" <?= $visao === $v ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="data" class="form-label label-caps text-muted-custom">DATA</label>
                        <input type="date" class="campo-form" id="data" name="data"
                               value="<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="profissional" class="form-label label-caps text-muted-custom">PROFISSIONAL</label>
                        <select class="campo-form" id="profissional" name="profissional">
                            <option value="">Todos</option>
                            <?php foreach ($profissionais as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= $filtroProfissional === (string)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="situacao" class="form-label label-caps text-muted-custom">SITUAÇÃO</label>
                        <select class="campo-form" id="situacao" name="situacao">
                            <option value="">Todas</option>
                            <?php foreach (AgendamentoModel::SITUACOES_TODAS as $s): ?>
                                <option value="<?= $s ?>" <?= $filtroSituacao === $s ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-outline-gold px-4">Aplicar</button>
                        <a href="<?= $baseUrl ?>/admin/agenda?data=<?= date('Y-m-d') ?>" class="btn btn-gold px-4">Hoje</a>
                        <button type="button" class="btn btn-gold px-4 ms-auto"
                                data-bs-toggle="collapse" data-bs-target="#form-manual">
                            Novo Agendamento
                        </button>
                    </div>
                </div>
            </form>

            <!-- Agendamento manual -->
            <div class="collapse mb-4" id="form-manual">
                <div class="style-card p-4">
                    <h2 class="h5 text-gold mb-1">Agendamento pelo Balcão</h2>
                    <p class="text-muted-custom mb-4">
                        Se o e-mail não estiver cadastrado, a conta do cliente é criada automaticamente.
                    </p>

                    <form method="POST" action="<?= $baseUrl ?>/admin/agenda/manual" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="cliente_email" class="form-label label-caps text-muted-custom">E-MAIL DO CLIENTE *</label>
                                <input type="email" class="campo-form" id="cliente_email" name="cliente_email" required>
                            </div>
                            <div class="col-md-4">
                                <label for="cliente_nome" class="form-label label-caps text-muted-custom">NOME (SE NOVO)</label>
                                <input type="text" class="campo-form" id="cliente_nome" name="cliente_nome">
                            </div>
                            <div class="col-md-4">
                                <label for="cliente_telefone" class="form-label label-caps text-muted-custom">TELEFONE (SE NOVO)</label>
                                <input type="tel" class="campo-form" id="cliente_telefone" name="cliente_telefone"
                                       data-mascara="telefone">
                            </div>

                            <div class="col-md-4">
                                <label for="profissional_id" class="form-label label-caps text-muted-custom">PROFISSIONAL *</label>
                                <select class="campo-form" id="profissional_id" name="profissional_id" required>
                                    <?php foreach ($profissionais as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="data_agendamento" class="form-label label-caps text-muted-custom">DATA *</label>
                                <input type="date" class="campo-form" id="data_agendamento" name="data_agendamento"
                                       required value="<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="hora" class="form-label label-caps text-muted-custom">HORÁRIO *</label>
                                <input type="time" class="campo-form" id="hora" name="hora" required step="900">
                            </div>

                            <div class="col-12">
                                <span class="form-label label-caps text-muted-custom d-block">SERVIÇOS *</span>
                                <div class="row g-2">
                                    <?php foreach ($catalogo as $s): ?>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       id="man-svc-<?= (int)$s['id'] ?>" name="servicos[]" value="<?= (int)$s['id'] ?>">
                                                <label class="form-check-label" for="man-svc-<?= (int)$s['id'] ?>">
                                                    <?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>
                                                    <small class="text-muted-custom">
                                                        (<?= (int)$s['duracao_minutos'] ?> min · <?= FormatHelper::currency((float)$s['preco']) ?>)
                                                    </small>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="col-12"><hr class="border-secondary-dark"></div>

                            <div class="col-md-3">
                                <label for="desconto_tipo" class="form-label label-caps text-muted-custom">TIPO DE DESCONTO</label>
                                <select class="campo-form" id="desconto_tipo" name="desconto_tipo">
                                    <option value="fixo">Valor fixo (R$)</option>
                                    <option value="percentual">Percentual (%)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="desconto_valor" class="form-label label-caps text-muted-custom">DESCONTO</label>
                                <input type="text" class="campo-form" id="desconto_valor" name="desconto_valor"
                                       inputmode="decimal" placeholder="0,00">
                            </div>
                            <div class="col-md-6">
                                <label for="desconto_justificativa" class="form-label label-caps text-muted-custom">
                                    JUSTIFICATIVA (OBRIGATÓRIA COM DESCONTO)
                                </label>
                                <input type="text" class="campo-form" id="desconto_justificativa"
                                       name="desconto_justificativa" placeholder="Ex: cliente antigo, cortesia">
                            </div>

                            <div class="col-md-6">
                                <label for="forma_pagamento" class="form-label label-caps text-muted-custom">
                                    RECEBIMENTO
                                </label>
                                <select class="campo-form" id="forma_pagamento" name="forma_pagamento">
                                    <?php foreach (PagamentoModel::FORMAS_BALCAO as $valor => $rotulo): ?>
                                        <option value="<?= $valor ?>"><?= $rotulo ?></option>
                                    <?php endforeach; ?>
                                    <option value="">Registrar depois</option>
                                </select>
                                <small class="text-muted-custom">
                                    Sem registrar o recebimento, o atendimento não entra no faturamento.
                                </small>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-gold px-4 mt-4">Criar Agendamento</button>
                    </form>
                </div>
            </div>

            <!-- Visão mensal: calendário -->
            <?php if ($visao === 'mes'): ?>
                <?php
                $primeiroDia = strtotime($de);
                $totalDias = (int)date('t', $primeiroDia);
                $deslocamento = (int)date('w', $primeiroDia);
                ?>
                <div class="style-card p-4 mb-4">
                    <h2 class="h5 text-gold mb-3"><?= ucfirst(FormatHelper::mesAnoBr($de)) ?></h2>

                    <div class="calendario-mes">
                        <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $dia): ?>
                            <div class="calendario-cabecalho"><?= $dia ?></div>
                        <?php endforeach; ?>

                        <?php for ($i = 0; $i < $deslocamento; $i++): ?>
                            <div class="calendario-vazio"></div>
                        <?php endfor; ?>

                        <?php for ($dia = 1; $dia <= $totalDias; $dia++): ?>
                            <?php
                            $dataDia = date('Y-m-', $primeiroDia) . sprintf('%02d', $dia);
                            $info = $contagemPorDia[$dataDia] ?? null;
                            $ehHoje = $dataDia === date('Y-m-d');
                            ?>
                            <a href="<?= $baseUrl ?>/admin/agenda?visao=dia&data=<?= $dataDia ?>"
                               class="calendario-dia <?= $ehHoje ? 'calendario-hoje' : '' ?> <?= $info ? 'calendario-com-agenda' : '' ?>">
                                <span class="calendario-numero"><?= $dia ?></span>
                                <?php if ($info): ?>
                                    <span class="calendario-total"><?= $info['total'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Listagem -->
            <div class="style-card p-4">
                <h2 class="h5 text-gold mb-1">
                    <?php if ($visao === 'dia'): ?>
                        <?= FormatHelper::dateBr($data) ?>
                    <?php else: ?>
                        <?= FormatHelper::dateBr($de) ?> a <?= FormatHelper::dateBr($ate) ?>
                    <?php endif; ?>
                </h2>
                <p class="text-muted-custom mb-4"><?= count($agendamentos) ?> agendamento(s)</p>

                <?php if (empty($agendamentos)): ?>
                    <p class="text-muted-custom mb-0">Nenhum agendamento no período com os filtros aplicados.</p>
                <?php else: ?>
                    <?php foreach ($porData as $dataGrupo => $lista): ?>
                        <?php if ($visao !== 'dia'): ?>
                            <h3 class="h6 text-muted-custom border-bottom border-secondary-dark pb-2 mt-4 mb-3">
                                <?= FormatHelper::dateBr($dataGrupo) ?> — <?= count($lista) ?> atendimento(s)
                            </h3>
                        <?php endif; ?>

                        <?php foreach ($lista as $a): ?>
                            <article class="cartao-agenda mb-2">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div class="d-flex gap-3">
                                        <span class="dado-numerico text-gold fs-5">
                                            <?= substr($a['hora_inicio'], 0, 5) ?>
                                        </span>
                                        <div>
                                            <strong class="d-block text-on-surface">
                                                <?= htmlspecialchars($a['cliente_nome'], ENT_QUOTES, 'UTF-8') ?>
                                            </strong>
                                            <small class="text-muted-custom">
                                                <?= htmlspecialchars($a['servicos'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                                                <?= htmlspecialchars($a['profissional_nome'], ENT_QUOTES, 'UTF-8') ?>
                                                · <?= htmlspecialchars($a['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>
                                                · <?= htmlspecialchars($a['cliente_telefone'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                            <?php if ((float)$a['valor_desconto'] > 0): ?>
                                                <small class="d-block text-muted-custom">
                                                    Desconto de <?= FormatHelper::currency((float)$a['valor_desconto']) ?>:
                                                    <?= htmlspecialchars($a['justificativa_desconto'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <span class="etiqueta etiqueta-<?= htmlspecialchars($a['situacao'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $a['situacao']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <strong class="d-block dado-numerico text-muted-custom mt-1">
                                            <?= FormatHelper::currency((float)$a['valor_total'] - (float)$a['valor_desconto']) ?>
                                        </strong>

                                        <?php if (!empty($a['forma_paga'])): ?>
                                            <small class="d-block text-sucesso">
                                                pago em <?= htmlspecialchars(PagamentoModel::ROTULOS_FORMA[$a['forma_paga']] ?? $a['forma_paga'], ENT_QUOTES, 'UTF-8') ?>
                                            </small>
                                        <?php elseif (($a['valor_devido'] ?? 0) > 0
                                                      && $a['situacao'] !== 'cancelado'
                                                      && $a['situacao'] !== 'reembolsado'): ?>
                                            <small class="d-block text-erro">
                                                em aberto: <?= FormatHelper::currency((float)$a['valor_devido']) ?>
                                            </small>
                                        <?php endif; ?>

                                        <div class="d-flex gap-1 justify-content-end mt-2">
                                            <?php if (empty($a['forma_paga']) && ($a['valor_devido'] ?? 0) > 0
                                                      && in_array($a['situacao'], ['confirmado', 'concluido', 'aguardando_pagamento'], true)): ?>
                                                <button type="button" class="btn btn-gold btn-sm"
                                                        data-bs-toggle="collapse" data-bs-target="#pagar-adm-<?= (int)$a['id'] ?>">
                                                    Receber
                                                </button>
                                            <?php endif; ?>

                                            <?php if (in_array($a['situacao'], AgendamentoModel::SITUACOES_ATIVAS, true)): ?>
                                                <button type="button" class="btn btn-perigo btn-sm"
                                                        data-bs-toggle="collapse" data-bs-target="#cancelar-adm-<?= (int)$a['id'] ?>">
                                                    Cancelar
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (empty($a['forma_paga']) && ($a['valor_devido'] ?? 0) > 0
                                          && in_array($a['situacao'], ['confirmado', 'concluido', 'aguardando_pagamento'], true)): ?>
                                    <div class="collapse mt-3" id="pagar-adm-<?= (int)$a['id'] ?>">
                                        <form method="POST" action="<?= $baseUrl ?>/admin/agenda/pagamento"
                                              class="border-top border-secondary-dark pt-3">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="codigo" value="<?= htmlspecialchars($a['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>">

                                            <p class="text-muted-custom mb-2">
                                                <small>Registrar o recebimento de
                                                <strong class="text-gold"><?= FormatHelper::currency((float)$a['valor_devido']) ?></strong>
                                                no balcão. O valor passa a contar no faturamento.</small>
                                            </p>

                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-9">
                                                    <label for="forma-<?= (int)$a['id'] ?>" class="form-label label-caps text-muted-custom">FORMA *</label>
                                                    <select class="campo-form" id="forma-<?= (int)$a['id'] ?>" name="forma_pagamento" required>
                                                        <?php foreach (PagamentoModel::FORMAS_BALCAO as $valor => $rotulo): ?>
                                                            <option value="<?= $valor ?>"><?= $rotulo ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <button type="submit" class="btn btn-gold w-100">Confirmar</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <?php if (in_array($a['situacao'], AgendamentoModel::SITUACOES_ATIVAS, true)): ?>
                                    <div class="collapse mt-3" id="cancelar-adm-<?= (int)$a['id'] ?>">
                                        <form method="POST" action="<?= $baseUrl ?>/admin/agenda/cancelar"
                                              class="border-top border-secondary-dark pt-3">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="codigo" value="<?= htmlspecialchars($a['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>">

                                            <p class="text-muted-custom mb-2">
                                                <small>O cancelamento pelo estabelecimento sempre devolve o valor pago
                                                e notifica o cliente por e-mail.</small>
                                            </p>

                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-9">
                                                    <label for="motivo-adm-<?= (int)$a['id'] ?>" class="form-label label-caps text-muted-custom">MOTIVO *</label>
                                                    <input type="text" class="campo-form" id="motivo-adm-<?= (int)$a['id'] ?>"
                                                           name="motivo" required minlength="3">
                                                </div>
                                                <div class="col-md-3">
                                                    <button type="submit" class="btn btn-perigo w-100">Confirmar</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
