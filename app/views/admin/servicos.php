<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-4">Serviços</h1>

            <!-- Formulário de cadastro / edição -->
            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3"><?= $edicao ? 'Editar Serviço' : 'Novo Serviço' ?></h2>

                <form method="POST" action="<?= $baseUrl ?>/admin/servicos/salvar" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= (int)($edicao['id'] ?? 0) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome" class="form-label label-caps text-muted-custom">NOME DO SERVIÇO *</label>
                            <input type="text" class="campo-form" id="nome" name="nome" required minlength="3"
                                   value="<?= htmlspecialchars($antigo['nome'] ?? $edicao['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="duracao_minutos" class="form-label label-caps text-muted-custom">DURAÇÃO (MIN) *</label>
                            <input type="number" class="campo-form" id="duracao_minutos" name="duracao_minutos"
                                   required min="5" max="480" step="5"
                                   value="<?= htmlspecialchars($antigo['duracao_minutos'] ?? $edicao['duracao_minutos'] ?? '30', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="preco" class="form-label label-caps text-muted-custom">PREÇO (R$) *</label>
                            <input type="text" class="campo-form" id="preco" name="preco" required
                                   inputmode="decimal" placeholder="0,00"
                                   value="<?= htmlspecialchars($antigo['preco'] ?? $edicao['preco'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-12">
                            <label for="descricao" class="form-label label-caps text-muted-custom">DESCRIÇÃO</label>
                            <textarea class="campo-form" id="descricao" name="descricao" rows="2"><?= htmlspecialchars($antigo['descricao'] ?? $edicao['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="col-12">
                            <label for="orientacoes" class="form-label label-caps text-muted-custom">ORIENTAÇÕES PRÉ-ATENDIMENTO</label>
                            <textarea class="campo-form" id="orientacoes" name="orientacoes" rows="2"
                                      placeholder="Ex: venha com o cabelo lavado"><?= htmlspecialchars($antigo['orientacoes'] ?? $edicao['orientacoes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label label-caps text-muted-custom">STATUS *</label>
                            <select class="campo-form" id="status" name="status" required>
                                <?php
                                $statusAtual = $antigo['status'] ?? $edicao['status'] ?? 'ativo';
                                $rotulos = ['ativo' => 'Ativo', 'indisponivel' => 'Temporariamente Indisponível', 'inativo' => 'Inativo'];
                                foreach ($rotulos as $valor => $rotulo): ?>
                                    <option value="<?= $valor ?>" <?= $statusAtual === $valor ? 'selected' : '' ?>>
                                        <?= $rotulo ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="previsao_retorno" class="form-label label-caps text-muted-custom">PREVISÃO DE RETORNO</label>
                            <input type="date" class="campo-form" id="previsao_retorno" name="previsao_retorno"
                                   value="<?= htmlspecialchars($antigo['previsao_retorno'] ?? $edicao['previsao_retorno'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <small class="text-muted-custom">Usada quando o serviço está indisponível.</small>
                        </div>

                        <div class="col-md-4">
                            <label for="imagem" class="form-label label-caps text-muted-custom">IMAGEM ILUSTRATIVA</label>
                            <input type="file" class="campo-form" id="imagem" name="imagem" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (!empty($edicao['imagem'])): ?>
                                <small class="text-muted-custom">Imagem cadastrada. Envie outra para substituir.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-gold px-4"><?= $edicao ? 'Salvar Alterações' : 'Cadastrar Serviço' ?></button>
                        <?php if ($edicao): ?>
                            <a href="<?= $baseUrl ?>/admin/servicos" class="btn btn-outline-gold px-4">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Filtros -->
            <form method="GET" action="<?= $baseUrl ?>/admin/servicos" class="style-card p-3 mb-4">
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
                            <?php foreach (['ativo' => 'Ativo', 'indisponivel' => 'Indisponível', 'inativo' => 'Inativo'] as $v => $r): ?>
                                <option value="<?= $v ?>" <?= $statusFiltro === $v ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-gold w-100">Filtrar</button>
                    </div>
                </div>
            </form>

            <!-- Tabela -->
            <div class="style-card p-4">
                <h2 class="h5 text-gold mb-3">Serviços Cadastrados (<?= count($servicos) ?>)</h2>

                <?php if (empty($servicos)): ?>
                    <p class="text-muted-custom mb-0">Nenhum serviço encontrado.</p>
                <?php else: ?>
                    <div class="tabela-rolavel">
                        <table class="table tabela-dados align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Serviço</th>
                                    <th scope="col">Duração</th>
                                    <th scope="col">Preço</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($servicos as $s): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if (!empty($s['descricao'])): ?>
                                            <br><small class="text-muted-custom"><?= htmlspecialchars(mb_strimwidth($s['descricao'], 0, 70, '...'), ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="dado-numerico"><?= (int)$s['duracao_minutos'] ?> min</td>
                                    <td class="dado-numerico"><?= FormatHelper::currency((float)$s['preco']) ?></td>
                                    <td>
                                        <span class="etiqueta etiqueta-<?= $s['status'] ?>">
                                            <?= htmlspecialchars(ucfirst($s['status']), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= $baseUrl ?>/admin/servicos?editar=<?= (int)$s['id'] ?>"
                                           class="btn btn-outline-gold btn-sm">Editar</a>
                                        <form method="POST" action="<?= $baseUrl ?>/admin/servicos/excluir" class="d-inline"
                                              onsubmit="return confirm('Excluir o serviço &quot;<?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>&quot;? O histórico de agendamentos será preservado.');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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

            <!-- Restauração de excluídos -->
            <?php if (!empty($excluidos)): ?>
                <div class="style-card p-4 mt-4">
                    <h2 class="h5 text-gold mb-3">Serviços Excluídos (<?= count($excluidos) ?>)</h2>
                    <p class="text-muted-custom">Registros preservados por Soft Delete e restauráveis.</p>

                    <ul class="list-unstyled mb-0">
                        <?php foreach ($excluidos as $s): ?>
                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary-dark">
                                <span><?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                <form method="POST" action="<?= $baseUrl ?>/admin/servicos/restaurar" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button type="submit" class="btn btn-outline-gold btn-sm">Restaurar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
