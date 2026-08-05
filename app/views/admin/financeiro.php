<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$periodo = 'de=' . urlencode($de) . '&ate=' . urlencode($ate);
$maiorReceita = 0.0;
foreach ($receitaPorDia as $linha) { $maiorReceita = max($maiorReceita, (float)$linha['total']); }
?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-4">Financeiro</h1>

            <!-- Período -->
            <form method="GET" action="<?= $baseUrl ?>/admin/financeiro" class="style-card p-3 mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="de" class="form-label label-caps text-muted-custom">DE</label>
                        <input type="date" class="campo-form" id="de" name="de" value="<?= htmlspecialchars($de, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="ate" class="form-label label-caps text-muted-custom">ATÉ</label>
                        <input type="date" class="campo-form" id="ate" name="ate" value="<?= htmlspecialchars($ate, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-gold flex-grow-1">Filtrar</button>
                        <a href="<?= $baseUrl ?>/admin/financeiro/exportar?<?= $periodo ?>" class="btn btn-gold">Exportar</a>
                    </div>
                </div>
            </form>

            <!-- Resumo -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="style-card p-3 h-100">
                        <span class="label-caps text-muted-custom d-block">RECEITA LÍQUIDA</span>
                        <strong class="dado-numerico fs-4 text-gold"><?= FormatHelper::currency($resumo['receita_liquida']) ?></strong>
                        <?php if ($resumo['estornos'] > 0): ?>
                            <small class="d-block text-muted-custom">
                                Bruta <?= FormatHelper::currency($resumo['receita_bruta']) ?>
                                − estornos <?= FormatHelper::currency($resumo['estornos']) ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="style-card p-3 h-100">
                        <span class="label-caps text-muted-custom d-block">DESPESAS</span>
                        <strong class="dado-numerico fs-4 text-erro"><?= FormatHelper::currency($resumo['despesas']) ?></strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="style-card p-3 h-100">
                        <span class="label-caps text-muted-custom d-block">LUCRO LÍQUIDO</span>
                        <strong class="dado-numerico fs-4 <?= $resumo['lucro_liquido'] >= 0 ? 'text-sucesso' : 'text-erro' ?>">
                            <?= FormatHelper::currency($resumo['lucro_liquido']) ?>
                        </strong>
                        <small class="d-block text-muted-custom">Margem de <?= number_format($resumo['margem'], 1, ',', '') ?>%</small>
                    </div>
                </div>
            </div>

            <!-- Evolução do faturamento -->
            <?php if (!empty($receitaPorDia)): ?>
                <div class="style-card p-4 mb-4">
                    <h2 class="h6 text-gold mb-3">Evolução do faturamento</h2>
                    <div class="grafico-barras" role="img"
                         aria-label="Faturamento diário do período de <?= FormatHelper::dateBr($de) ?> a <?= FormatHelper::dateBr($ate) ?>">
                        <?php foreach ($receitaPorDia as $linha): ?>
                            <?php $altura = $maiorReceita > 0 ? max(4, ((float)$linha['total'] / $maiorReceita) * 100) : 4; ?>
                            <div class="grafico-coluna"
                                 title="<?= FormatHelper::dateBr($linha['dia']) ?>: <?= FormatHelper::currency((float)$linha['total']) ?>">
                                <span class="grafico-barra" style="height: <?= round($altura) ?>%"></span>
                                <span class="grafico-rotulo"><?= date('d', strtotime($linha['dia'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <!-- Receita por forma de pagamento -->
                <div class="col-md-6">
                    <div class="style-card p-4 h-100">
                        <h2 class="h6 text-gold mb-3">Por forma de pagamento</h2>
                        <?php if (empty($porForma)): ?>
                            <p class="text-muted-custom mb-0">Nenhum pagamento no período.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($porForma as $f): ?>
                                    <li class="d-flex justify-content-between border-bottom border-secondary-dark py-2">
                                        <span><?= htmlspecialchars(PagamentoModel::ROTULOS_FORMA[$f['forma_pagamento']] ?? $f['forma_pagamento'], ENT_QUOTES, 'UTF-8') ?>
                                            <small class="text-muted-custom">(<?= (int)$f['transacoes'] ?>)</small>
                                        </span>
                                        <span class="dado-numerico"><?= FormatHelper::currency((float)$f['total']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Despesas por categoria -->
                <div class="col-md-6">
                    <div class="style-card p-4 h-100">
                        <h2 class="h6 text-gold mb-3">Despesas por categoria</h2>
                        <?php if (empty($porCategoria)): ?>
                            <p class="text-muted-custom mb-0">Nenhuma despesa no período.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($porCategoria as $c): ?>
                                    <li class="d-flex justify-content-between border-bottom border-secondary-dark py-2">
                                        <span><?= htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8') ?>
                                            <small class="text-muted-custom">(<?= (int)$c['lancamentos'] ?>)</small>
                                        </span>
                                        <span class="dado-numerico"><?= FormatHelper::currency((float)$c['total']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Lançamento de despesa -->
            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3">Lançar Despesa</h2>

                <form method="POST" action="<?= $baseUrl ?>/admin/financeiro/despesa?<?= $periodo ?>"
                      enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="categoria_id" class="form-label label-caps text-muted-custom">CATEGORIA *</label>
                            <select class="campo-form" id="categoria_id" name="categoria_id" required>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="descricao" class="form-label label-caps text-muted-custom">DESCRIÇÃO *</label>
                            <input type="text" class="campo-form" id="descricao" name="descricao" required minlength="3">
                        </div>
                        <div class="col-md-3">
                            <label for="valor" class="form-label label-caps text-muted-custom">VALOR (R$) *</label>
                            <input type="text" class="campo-form" id="valor" name="valor" required inputmode="decimal" placeholder="0,00">
                        </div>
                        <div class="col-md-4">
                            <label for="data_despesa" class="form-label label-caps text-muted-custom">DATA *</label>
                            <input type="date" class="campo-form" id="data_despesa" name="data_despesa"
                                   required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-8">
                            <label for="comprovante" class="form-label label-caps text-muted-custom">COMPROVANTE</label>
                            <input type="file" class="campo-form" id="comprovante" name="comprovante"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf">
                            <small class="text-muted-custom">JPG, PNG, WEBP ou PDF com até 5MB.</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-gold px-4 mt-4">Lançar Despesa</button>
                </form>

                <hr class="border-secondary-dark my-4">

                <form method="POST" action="<?= $baseUrl ?>/admin/financeiro/categoria" class="row g-2 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="col-md-8">
                        <label for="nome_categoria" class="form-label label-caps text-muted-custom">NOVA CATEGORIA</label>
                        <input type="text" class="campo-form" id="nome_categoria" name="nome" minlength="3">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-gold w-100">Criar Categoria</button>
                    </div>
                </form>
            </div>

            <!-- Lançamentos -->
            <div class="style-card p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="h5 text-gold mb-0">Lançamentos (<?= count($lancamentos) ?>)</h2>

                    <form method="GET" action="<?= $baseUrl ?>/admin/financeiro" class="d-flex gap-2">
                        <input type="hidden" name="de" value="<?= htmlspecialchars($de, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="ate" value="<?= htmlspecialchars($ate, ENT_QUOTES, 'UTF-8') ?>">
                        <select class="campo-form" name="categoria" aria-label="Filtrar por categoria">
                            <option value="">Todas as categorias</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= $categoriaFiltro === (string)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-gold btn-sm">Filtrar</button>
                    </form>
                </div>

                <?php if (empty($lancamentos)): ?>
                    <p class="text-muted-custom mb-0">Nenhuma despesa lançada no período.</p>
                <?php else: ?>
                    <div class="tabela-rolavel">
                        <table class="table tabela-dados align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Data</th>
                                    <th scope="col">Categoria</th>
                                    <th scope="col">Descrição</th>
                                    <th scope="col">Valor</th>
                                    <th scope="col">Comprovante</th>
                                    <th scope="col" class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($lancamentos as $d): ?>
                                <tr>
                                    <td class="dado-numerico"><?= FormatHelper::dateBr($d['data_despesa']) ?></td>
                                    <td><?= htmlspecialchars($d['categoria_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?= htmlspecialchars($d['descricao'], ENT_QUOTES, 'UTF-8') ?><br>
                                        <small class="text-muted-custom">por <?= htmlspecialchars($d['registrado_por_nome'], ENT_QUOTES, 'UTF-8') ?></small>
                                    </td>
                                    <td class="dado-numerico text-erro"><?= FormatHelper::currency((float)$d['valor']) ?></td>
                                    <td>
                                        <?php if (!empty($d['comprovante'])): ?>
                                            <a href="<?= $baseUrl ?>/public/uploads/comprovantes/<?= urlencode($d['comprovante']) ?>"
                                               target="_blank" rel="noopener" class="text-gold">Ver</a>
                                        <?php else: ?>
                                            <span class="text-muted-custom">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= $baseUrl ?>/admin/financeiro/despesa/excluir" class="m-0"
                                              onsubmit="return confirm('Excluir este lançamento?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                            <button type="submit" class="btn btn-perigo btn-sm">Excluir</button>
                                        </form>
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
