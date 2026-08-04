<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-4">Profissionais</h1>

            <!-- Cadastro / edição -->
            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3"><?= $edicao ? 'Editar Profissional' : 'Novo Profissional' ?></h2>

                <form method="POST" action="<?= $baseUrl ?>/admin/profissionais/salvar" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= (int)($edicao['id'] ?? 0) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome" class="form-label label-caps text-muted-custom">NOME COMPLETO *</label>
                            <input type="text" class="campo-form" id="nome" name="nome" required minlength="3"
                                   value="<?= htmlspecialchars($antigo['nome'] ?? $edicao['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label label-caps text-muted-custom">E-MAIL DE ACESSO *</label>
                            <input type="email" class="campo-form" id="email" name="email" required
                                   value="<?= htmlspecialchars($antigo['email'] ?? $edicao['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="telefone" class="form-label label-caps text-muted-custom">TELEFONE *</label>
                            <input type="tel" class="campo-form" id="telefone" name="telefone" required
                                   data-mascara="telefone"
                                   value="<?= htmlspecialchars($antigo['telefone'] ?? $edicao['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="senha" class="form-label label-caps text-muted-custom">
                                SENHA <?= $edicao ? '(deixe em branco para manter)' : '*' ?>
                            </label>
                            <input type="password" class="campo-form" id="senha" name="senha"
                                   <?= $edicao ? '' : 'required' ?> minlength="8" autocomplete="new-password">
                        </div>

                        <div class="col-md-4">
                            <label for="limite_diario_atendimentos" class="form-label label-caps text-muted-custom">LIMITE DIÁRIO</label>
                            <input type="number" class="campo-form" id="limite_diario_atendimentos"
                                   name="limite_diario_atendimentos" min="1"
                                   value="<?= htmlspecialchars((string)($edicao['limite_diario_atendimentos'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <small class="text-muted-custom">Em branco = sem limite.</small>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label label-caps text-muted-custom">STATUS *</label>
                            <select class="campo-form" id="status" name="status" required>
                                <?php $statusAtual = $edicao['status'] ?? 'ativo'; ?>
                                <option value="ativo" <?= $statusAtual === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inativo" <?= $statusAtual === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label for="foto" class="form-label label-caps text-muted-custom">FOTO</label>
                            <input type="file" class="campo-form" id="foto" name="foto" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-gold px-4"><?= $edicao ? 'Salvar Alterações' : 'Cadastrar Profissional' ?></button>
                        <?php if ($edicao): ?>
                            <a href="<?= $baseUrl ?>/admin/profissionais" class="btn btn-outline-gold px-4">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($edicao): ?>
                <!-- Serviços executados -->
                <div class="style-card p-4 mb-4">
                    <h2 class="h5 text-gold mb-1">Serviços Executados</h2>
                    <p class="text-muted-custom">Somente os serviços marcados poderão ser agendados com este profissional.</p>

                    <form method="POST" action="<?= $baseUrl ?>/admin/profissionais/servicos">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="id" value="<?= (int)$edicao['id'] ?>">

                        <div class="row g-2">
                            <?php foreach ($servicosDisponiveis as $s): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               id="servico_<?= (int)$s['id'] ?>" name="servicos[]" value="<?= (int)$s['id'] ?>"
                                               <?= in_array((int)$s['id'], $servicosVinculados, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="servico_<?= (int)$s['id'] ?>">
                                            <?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>
                                            <small class="text-muted-custom">
                                                (<?= (int)$s['duracao_minutos'] ?> min · <?= FormatHelper::currency((float)$s['preco']) ?>)
                                            </small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-gold px-4 mt-3">Salvar Serviços</button>
                    </form>
                </div>

                <!-- Jornada semanal -->
                <div class="style-card p-4 mb-4">
                    <h2 class="h5 text-gold mb-1">Jornada Semanal</h2>
                    <p class="text-muted-custom">Dias não marcados são considerados folga.</p>

                    <form method="POST" action="<?= $baseUrl ?>/admin/profissionais/horarios">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="id" value="<?= (int)$edicao['id'] ?>">

                        <div class="tabela-rolavel">
                            <table class="table tabela-dados align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Dia</th>
                                        <th scope="col">Início</th>
                                        <th scope="col">Término</th>
                                        <th scope="col">Almoço (início)</th>
                                        <th scope="col">Almoço (fim)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach (ProfissionalModel::DIAS_SEMANA as $dia => $nomeDia): ?>
                                    <?php $h = $grade[$dia] ?? null; ?>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       id="dia_<?= $dia ?>" name="dia_ativo[<?= $dia ?>]" value="1"
                                                       <?= $h ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="dia_<?= $dia ?>"><?= $nomeDia ?></label>
                                            </div>
                                        </td>
                                        <td><input type="time" class="campo-form" name="hora_inicio[<?= $dia ?>]"
                                                   value="<?= $h ? substr($h['hora_inicio'], 0, 5) : '09:00' ?>"></td>
                                        <td><input type="time" class="campo-form" name="hora_fim[<?= $dia ?>]"
                                                   value="<?= $h ? substr($h['hora_fim'], 0, 5) : '18:00' ?>"></td>
                                        <td><input type="time" class="campo-form" name="almoco_inicio[<?= $dia ?>]"
                                                   value="<?= $h && $h['almoco_inicio'] ? substr($h['almoco_inicio'], 0, 5) : '' ?>"></td>
                                        <td><input type="time" class="campo-form" name="almoco_fim[<?= $dia ?>]"
                                                   value="<?= $h && $h['almoco_fim'] ? substr($h['almoco_fim'], 0, 5) : '' ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-gold px-4 mt-3">Salvar Jornada</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <form method="GET" action="<?= $baseUrl ?>/admin/profissionais" class="style-card p-3 mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label for="busca" class="form-label label-caps text-muted-custom">BUSCAR</label>
                        <input type="search" class="campo-form" id="busca" name="busca"
                               value="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="filtro_status" class="form-label label-caps text-muted-custom">STATUS</label>
                        <select class="campo-form" id="filtro_status" name="status">
                            <option value="">Todos</option>
                            <option value="ativo" <?= $statusFiltro === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= $statusFiltro === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-gold w-100">Filtrar</button>
                    </div>
                </div>
            </form>

            <!-- Listagem -->
            <div class="style-card p-4">
                <h2 class="h5 text-gold mb-3">Equipe (<?= count($profissionais) ?>)</h2>

                <?php if (empty($profissionais)): ?>
                    <p class="text-muted-custom mb-0">Nenhum profissional cadastrado.</p>
                <?php else: ?>
                    <div class="tabela-rolavel">
                        <table class="table tabela-dados align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Nome</th>
                                    <th scope="col">Contato</th>
                                    <th scope="col">Limite/dia</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($profissionais as $p): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($p['email'], ENT_QUOTES, 'UTF-8') ?><br>
                                        <small class="text-muted-custom"><?= htmlspecialchars($p['telefone'], ENT_QUOTES, 'UTF-8') ?></small>
                                    </td>
                                    <td class="dado-numerico"><?= $p['limite_diario_atendimentos'] !== null ? (int)$p['limite_diario_atendimentos'] : '—' ?></td>
                                    <td><span class="etiqueta etiqueta-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= $baseUrl ?>/admin/profissionais?editar=<?= (int)$p['id'] ?>"
                                           class="btn btn-outline-gold btn-sm">Editar</a>
                                        <form method="POST" action="<?= $baseUrl ?>/admin/profissionais/excluir" class="d-inline"
                                              onsubmit="return confirm('Excluir o profissional &quot;<?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?>&quot;?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
