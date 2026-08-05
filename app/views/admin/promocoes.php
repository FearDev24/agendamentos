<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-4">Promoções</h1>

            <!-- Cadastro / edição -->
            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3"><?= $edicao ? 'Editar Promoção' : 'Nova Promoção' ?></h2>

                <form method="POST" action="<?= $baseUrl ?>/admin/promocoes/salvar" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= (int)($edicao['id'] ?? 0) ?>">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="titulo" class="form-label label-caps text-muted-custom">TÍTULO *</label>
                            <input type="text" class="campo-form" id="titulo" name="titulo" required minlength="3"
                                   value="<?= htmlspecialchars($antigo['titulo'] ?? $edicao['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label label-caps text-muted-custom">STATUS *</label>
                            <select class="campo-form" id="status" name="status" required>
                                <?php $statusAtual = $antigo['status'] ?? $edicao['status'] ?? 'ativa'; ?>
                                <?php foreach (PromocaoModel::STATUS as $v => $r): ?>
                                    <option value="<?= $v ?>" <?= $statusAtual === $v ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="descricao" class="form-label label-caps text-muted-custom">DESCRIÇÃO</label>
                            <textarea class="campo-form" id="descricao" name="descricao" rows="2"><?= htmlspecialchars($antigo['descricao'] ?? $edicao['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label for="tipo_desconto" class="form-label label-caps text-muted-custom">TIPO DE DESCONTO *</label>
                            <select class="campo-form" id="tipo_desconto" name="tipo_desconto" required>
                                <?php $tipoAtual = $antigo['tipo_desconto'] ?? $edicao['tipo_desconto'] ?? 'fixo'; ?>
                                <?php foreach (PromocaoModel::TIPOS_DESCONTO as $v => $r): ?>
                                    <option value="<?= $v ?>" <?= $tipoAtual === $v ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="valor_desconto" class="form-label label-caps text-muted-custom">VALOR DO DESCONTO *</label>
                            <input type="text" class="campo-form" id="valor_desconto" name="valor_desconto" required
                                   inputmode="decimal" placeholder="0,00"
                                   value="<?= htmlspecialchars($antigo['valor_desconto'] ?? $edicao['valor_desconto'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="servico_id" class="form-label label-caps text-muted-custom">SERVIÇO VINCULADO</label>
                            <select class="campo-form" id="servico_id" name="servico_id">
                                <option value="">Todos os serviços</option>
                                <?php $servicoAtual = $edicao['servico_id'] ?? null; ?>
                                <?php foreach ($catalogo as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= (int)$servicoAtual === (int)$s['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="data_inicio" class="form-label label-caps text-muted-custom">INÍCIO *</label>
                            <input type="date" class="campo-form" id="data_inicio" name="data_inicio" required
                                   value="<?= htmlspecialchars($antigo['data_inicio'] ?? $edicao['data_inicio'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="data_fim" class="form-label label-caps text-muted-custom">TÉRMINO *</label>
                            <input type="date" class="campo-form" id="data_fim" name="data_fim" required
                                   value="<?= htmlspecialchars($antigo['data_fim'] ?? $edicao['data_fim'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="limite_total_usos" class="form-label label-caps text-muted-custom">LIMITE DE USOS</label>
                            <input type="number" class="campo-form" id="limite_total_usos" name="limite_total_usos" min="1"
                                   value="<?= htmlspecialchars((string)($edicao['limite_total_usos'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <small class="text-muted-custom">Em branco = sem limite.</small>
                        </div>

                        <div class="col-12">
                            <label for="imagem" class="form-label label-caps text-muted-custom">BANNER</label>
                            <input type="file" class="campo-form" id="imagem" name="imagem" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (!empty($edicao['imagem'])): ?>
                                <small class="text-muted-custom">Banner cadastrado. Envie outro para substituir.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-gold px-4"><?= $edicao ? 'Salvar Alterações' : 'Criar Promoção' ?></button>
                        <?php if ($edicao): ?>
                            <a href="<?= $baseUrl ?>/admin/promocoes" class="btn btn-outline-gold px-4">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Link de compartilhamento e usos -->
            <?php if ($edicao): ?>
                <?php $linkPublico = $baseUrl . '/promocao/' . (int)$edicao['id']; ?>
                <div class="style-card p-4 mb-4">
                    <h2 class="h6 text-gold mb-2">Link de Compartilhamento</h2>
                    <p class="text-muted-custom">Divulgue este endereço nas redes sociais e no WhatsApp.</p>

                    <div class="row g-2 align-items-end">
                        <div class="col-md-9">
                            <input type="text" class="campo-form" id="link-promocao" readonly
                                   aria-label="Endereço público desta promoção"
                                   value="<?= htmlspecialchars($linkPublico, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="button" class="btn btn-outline-gold flex-grow-1"
                                    data-copiar data-alvo="link-promocao">Copiar</button>
                            <a href="<?= htmlspecialchars($linkPublico, ENT_QUOTES, 'UTF-8') ?>"
                               target="_blank" rel="noopener" class="btn btn-gold">Abrir</a>
                        </div>
                    </div>
                </div>

                <div class="style-card p-4 mb-4">
                    <h2 class="h6 text-gold mb-3">
                        Usos da Promoção (<?= (int)$edicao['usos_realizados'] ?><?= $edicao['limite_total_usos'] ? ' de ' . (int)$edicao['limite_total_usos'] : '' ?>)
                    </h2>

                    <?php if (empty($usos)): ?>
                        <p class="text-muted-custom mb-0">Esta promoção ainda não foi utilizada.</p>
                    <?php else: ?>
                        <div class="tabela-rolavel">
                            <table class="table tabela-dados align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Data</th>
                                        <th scope="col">Cliente</th>
                                        <th scope="col">Reserva</th>
                                        <th scope="col">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($usos as $u): ?>
                                    <tr>
                                        <td class="dado-numerico"><?= FormatHelper::dateTimeBr($u['data_uso']) ?></td>
                                        <td><?= htmlspecialchars($u['cliente_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="dado-numerico"><?= htmlspecialchars($u['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="dado-numerico"><?= FormatHelper::currency((float)$u['valor_total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Listagem -->
            <form method="GET" action="<?= $baseUrl ?>/admin/promocoes" class="style-card p-3 mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label for="filtro_status" class="form-label label-caps text-muted-custom">STATUS</label>
                        <select class="campo-form" id="filtro_status" name="status">
                            <option value="">Todas</option>
                            <?php foreach (PromocaoModel::STATUS as $v => $r): ?>
                                <option value="<?= $v ?>" <?= $statusFiltro === $v ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-gold w-100">Filtrar</button>
                    </div>
                </div>
            </form>

            <div class="style-card p-4">
                <h2 class="h5 text-gold mb-3">Campanhas (<?= count($promocoes) ?>)</h2>

                <?php if (empty($promocoes)): ?>
                    <p class="text-muted-custom mb-0">Nenhuma promoção cadastrada.</p>
                <?php else: ?>
                    <div class="tabela-rolavel">
                        <table class="table tabela-dados align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Título</th>
                                    <th scope="col">Desconto</th>
                                    <th scope="col">Vigência</th>
                                    <th scope="col">Usos</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($promocoes as $p): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($p['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if (!empty($p['servico_nome'])): ?>
                                            <br><small class="text-muted-custom">em <?= htmlspecialchars($p['servico_nome'], ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="dado-numerico">
                                        <?= $p['tipo_desconto'] === 'percentual'
                                            ? number_format((float)$p['valor_desconto'], 0, ',', '') . '%'
                                            : FormatHelper::currency((float)$p['valor_desconto']) ?>
                                    </td>
                                    <td class="dado-numerico">
                                        <?= FormatHelper::dateBr($p['data_inicio']) ?><br>
                                        <small class="text-muted-custom">até <?= FormatHelper::dateBr($p['data_fim']) ?></small>
                                    </td>
                                    <td class="dado-numerico">
                                        <?= (int)$p['usos_realizados'] ?><?= $p['limite_total_usos'] ? '/' . (int)$p['limite_total_usos'] : '' ?>
                                    </td>
                                    <td>
                                        <span class="etiqueta etiqueta-<?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(PromocaoModel::STATUS[$p['status']] ?? $p['status'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= $baseUrl ?>/admin/promocoes?editar=<?= (int)$p['id'] ?>"
                                           class="btn btn-outline-gold btn-sm">Editar</a>
                                        <?php if ((int)$p['usos_realizados'] === 0): ?>
                                            <form method="POST" action="<?= $baseUrl ?>/admin/promocoes/excluir" class="d-inline"
                                                  onsubmit="return confirm('Excluir esta promoção?');">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                <button type="submit" class="btn btn-perigo btn-sm">Excluir</button>
                                            </form>
                                        <?php endif; ?>
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

