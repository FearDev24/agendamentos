<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$query = http_build_query([
    'de' => $de, 'ate' => $ate,
    'profissional' => $filtros['profissional_id'],
    'situacao' => $filtros['situacao'],
    'servico' => $filtros['servico_id'],
]);
?>

<main class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-4">Relatórios</h1>

            <!-- Filtros -->
            <form method="GET" action="<?= $baseUrl ?>/admin/relatorios" class="style-card p-3 mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="de" class="form-label label-caps text-muted-custom">DE</label>
                        <input type="date" class="campo-form" id="de" name="de" value="<?= htmlspecialchars($de, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="ate" class="form-label label-caps text-muted-custom">ATÉ</label>
                        <input type="date" class="campo-form" id="ate" name="ate" value="<?= htmlspecialchars($ate, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="profissional" class="form-label label-caps text-muted-custom">PROFISSIONAL</label>
                        <select class="campo-form" id="profissional" name="profissional">
                            <option value="">Todos</option>
                            <?php foreach ($profissionais as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= $filtros['profissional_id'] === (string)$p['id'] ? 'selected' : '' ?>>
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
                                <option value="<?= $s ?>" <?= $filtros['situacao'] === $s ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="servico" class="form-label label-caps text-muted-custom">SERVIÇO</label>
                        <select class="campo-form" id="servico" name="servico">
                            <option value="">Todos</option>
                            <?php foreach ($catalogo as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= $filtros['servico_id'] === (string)$s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-gold flex-grow-1">Aplicar Filtros</button>
                        <a href="<?= $baseUrl ?>/admin/relatorios/exportar?<?= $query ?>&formato=csv" class="btn btn-gold">CSV</a>
                        <a href="<?= $baseUrl ?>/admin/relatorios/exportar?<?= $query ?>&formato=xls" class="btn btn-gold">Excel</a>
                    </div>
                </div>
                <p class="text-muted-custom mb-0 mt-3">
                    <small>A exportação respeita exatamente os filtros aplicados aqui.</small>
                </p>
            </form>

            <!-- Resumo financeiro do período -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="style-card p-3 h-100">
                        <span class="label-caps text-muted-custom d-block">AGENDAMENTOS</span>
                        <strong class="dado-numerico fs-4 text-gold"><?= count($agendamentos) ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="style-card p-3 h-100">
                        <span class="label-caps text-muted-custom d-block">RECEITA</span>
                        <strong class="dado-numerico fs-5 text-gold"><?= FormatHelper::currency($resumo['receita_liquida']) ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="style-card p-3 h-100">
                        <span class="label-caps text-muted-custom d-block">DESPESAS</span>
                        <strong class="dado-numerico fs-5 text-erro"><?= FormatHelper::currency($resumo['despesas']) ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="style-card p-3 h-100">
                        <span class="label-caps text-muted-custom d-block">LUCRO</span>
                        <strong class="dado-numerico fs-5 <?= $resumo['lucro_liquido'] >= 0 ? 'text-sucesso' : 'text-erro' ?>">
                            <?= FormatHelper::currency($resumo['lucro_liquido']) ?>
                        </strong>
                    </div>
                </div>
            </div>

            <!-- Desempenho por profissional -->
            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3">Desempenho por Profissional</h2>

                <?php if (empty($desempenho)): ?>
                    <p class="text-muted-custom mb-0">Nenhum profissional cadastrado.</p>
                <?php else: ?>
                    <div class="tabela-rolavel">
                        <table class="table tabela-dados align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Profissional</th>
                                    <th scope="col">Atendimentos</th>
                                    <th scope="col">Concluídos</th>
                                    <th scope="col">Faltas</th>
                                    <th scope="col">Horas</th>
                                    <th scope="col">Faturamento</th>
                                    <th scope="col">Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($desempenho as $d): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($d['profissional_nome'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td class="dado-numerico"><?= (int)$d['total'] ?></td>
                                    <td class="dado-numerico"><?= (int)$d['concluidos'] ?></td>
                                    <td class="dado-numerico"><?= (int)$d['faltas'] ?></td>
                                    <td class="dado-numerico"><?= number_format((int)$d['minutos_ocupados'] / 60, 1, ',', '') ?>h</td>
                                    <td class="dado-numerico text-gold"><?= FormatHelper::currency((float)$d['faturamento']) ?></td>
                                    <td class="dado-numerico">
                                        <?= $d['nota_media'] !== null ? number_format((float)$d['nota_media'], 1, ',', '') . ' ★' : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Serviços mais contratados -->
            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3">Serviços Mais Contratados</h2>

                <?php if (empty($servicos)): ?>
                    <p class="text-muted-custom mb-0">Nenhuma contratação no período.</p>
                <?php else: ?>
                    <div class="tabela-rolavel">
                        <table class="table tabela-dados align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Serviço</th>
                                    <th scope="col">Contratações</th>
                                    <th scope="col">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($servicos as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="dado-numerico"><?= (int)$s['contratacoes'] ?></td>
                                    <td class="dado-numerico"><?= FormatHelper::currency((float)$s['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Agendamentos -->
            <div class="style-card p-4">
                <h2 class="h5 text-gold mb-3">Agendamentos (<?= count($agendamentos) ?>)</h2>

                <?php if (empty($agendamentos)): ?>
                    <p class="text-muted-custom mb-0">Nenhum agendamento com os filtros aplicados.</p>
                <?php else: ?>
                    <div class="tabela-rolavel">
                        <table class="table tabela-dados align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Código</th>
                                    <th scope="col">Data</th>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Profissional</th>
                                    <th scope="col">Serviços</th>
                                    <th scope="col">Valor</th>
                                    <th scope="col">Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($agendamentos as $a): ?>
                                <tr>
                                    <td class="dado-numerico"><?= htmlspecialchars($a['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="dado-numerico">
                                        <?= FormatHelper::dateBr($a['data_agendamento']) ?><br>
                                        <small class="text-muted-custom"><?= substr($a['hora_inicio'], 0, 5) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($a['cliente_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($a['profissional_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><small><?= htmlspecialchars($a['servicos'] ?? '', ENT_QUOTES, 'UTF-8') ?></small></td>
                                    <td class="dado-numerico">
                                        <?= FormatHelper::currency((float)$a['valor_total'] - (float)$a['valor_desconto']) ?>
                                        <?php if ((float)$a['valor_desconto'] > 0): ?>
                                            <br><small class="text-muted-custom">−<?= FormatHelper::currency((float)$a['valor_desconto']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="etiqueta etiqueta-<?= htmlspecialchars($a['situacao'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $a['situacao']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
