<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-1">Bloqueios de Agenda</h1>
            <p class="text-muted-custom mb-4">
                Folgas, feriados e fechamentos. Horários bloqueados deixam de aparecer para os clientes.
            </p>

            <!-- Novo bloqueio -->
            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3">Novo Bloqueio</h2>

                <form method="POST" action="<?= $baseUrl ?>/admin/bloqueios/salvar" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="profissional_id" class="form-label label-caps text-muted-custom">PROFISSIONAL</label>
                            <select class="campo-form" id="profissional_id" name="profissional_id">
                                <option value="">Todo o estabelecimento</option>
                                <?php foreach ($profissionais as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="tipo" class="form-label label-caps text-muted-custom">TIPO *</label>
                            <select class="campo-form" id="tipo" name="tipo" required>
                                <?php foreach (BloqueioModel::TIPOS as $valor => $rotulo): ?>
                                    <option value="<?= $valor ?>"><?= $rotulo ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="data_inicio" class="form-label label-caps text-muted-custom">INÍCIO *</label>
                            <input type="datetime-local" class="campo-form" id="data_inicio" name="data_inicio" required>
                        </div>

                        <div class="col-md-6">
                            <label for="data_fim" class="form-label label-caps text-muted-custom">TÉRMINO *</label>
                            <input type="datetime-local" class="campo-form" id="data_fim" name="data_fim" required>
                        </div>

                        <div class="col-12">
                            <label for="motivo" class="form-label label-caps text-muted-custom">MOTIVO *</label>
                            <input type="text" class="campo-form" id="motivo" name="motivo" required minlength="3"
                                   placeholder="Ex: Feriado municipal">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-gold px-4 mt-4">Cadastrar Bloqueio</button>
                </form>
            </div>

            <!-- Filtros -->
            <form method="GET" action="<?= $baseUrl ?>/admin/bloqueios" class="style-card p-3 mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="filtro_prof" class="form-label label-caps text-muted-custom">PROFISSIONAL</label>
                        <select class="campo-form" id="filtro_prof" name="profissional">
                            <option value="">Todos</option>
                            <?php foreach ($profissionais as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= $filtroProfissional === (string)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="de" class="form-label label-caps text-muted-custom">DE</label>
                        <input type="date" class="campo-form" id="de" name="de" value="<?= htmlspecialchars($de, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="ate" class="form-label label-caps text-muted-custom">ATÉ</label>
                        <input type="date" class="campo-form" id="ate" name="ate" value="<?= htmlspecialchars($ate, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-gold w-100">Filtrar</button>
                    </div>
                </div>
            </form>

            <!-- Listagem -->
            <div class="style-card p-4">
                <h2 class="h5 text-gold mb-3">Bloqueios Cadastrados (<?= count($bloqueios) ?>)</h2>

                <?php if (empty($bloqueios)): ?>
                    <p class="text-muted-custom mb-0">Nenhum bloqueio cadastrado no período.</p>
                <?php else: ?>
                    <div class="tabela-rolavel">
                        <table class="table tabela-dados align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Período</th>
                                    <th scope="col">Alcance</th>
                                    <th scope="col">Tipo</th>
                                    <th scope="col">Motivo</th>
                                    <th scope="col" class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bloqueios as $b): ?>
                                <tr>
                                    <td class="dado-numerico">
                                        <?= FormatHelper::dateTimeBr($b['data_inicio']) ?><br>
                                        <small class="text-muted-custom">até <?= FormatHelper::dateTimeBr($b['data_fim']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($b['profissional_id'] === null): ?>
                                            <span class="etiqueta etiqueta-geral">Estabelecimento</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($b['profissional_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(BloqueioModel::TIPOS[$b['tipo']] ?? $b['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($b['motivo'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= $baseUrl ?>/admin/bloqueios/excluir" class="m-0"
                                              onsubmit="return confirm('Remover este bloqueio? O horário volta a ficar disponível.');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="btn btn-perigo btn-sm">Remover</button>
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
