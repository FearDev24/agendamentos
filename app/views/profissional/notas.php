<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <a href="<?= $baseUrl ?>/profissional/agenda" class="btn btn-outline-gold btn-sm mb-3">Voltar à agenda</a>

            <h1 class="h3 text-gold mb-1">Notas sobre o Cliente</h1>
            <p class="text-muted-custom mb-4">
                <?= htmlspecialchars($cliente['nome'], ENT_QUOTES, 'UTF-8') ?>
                · <?= htmlspecialchars($cliente['telefone'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div class="alerta alerta-aviso mb-4">
                Estas observações são <strong>internas</strong> e visíveis apenas para a equipe.
                O cliente nunca tem acesso a elas.
            </div>

            <div class="style-card p-4 mb-4">
                <h2 class="h6 text-gold mb-3">Nova Observação</h2>

                <form method="POST" action="<?= $baseUrl ?>/profissional/nota">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="cliente_id" value="<?= (int)$cliente['id'] ?>">

                    <label for="observacao" class="form-label label-caps text-muted-custom">OBSERVAÇÃO</label>
                    <textarea class="campo-form mb-3" id="observacao" name="observacao" rows="3" required minlength="3"
                              placeholder="Ex: prefere máquina 2 nas laterais; alérgico a determinado produto"></textarea>

                    <button type="submit" class="btn btn-gold px-4">Salvar Observação</button>
                </form>
            </div>

            <div class="style-card p-4">
                <h2 class="h6 text-gold mb-3">Histórico (<?= count($notas) ?>)</h2>

                <?php if (empty($notas)): ?>
                    <p class="text-muted-custom mb-0">Nenhuma observação registrada até o momento.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($notas as $n): ?>
                            <li class="border-bottom border-secondary-dark pb-3 mb-3">
                                <p class="mb-1"><?= nl2br(htmlspecialchars($n['observacao'], ENT_QUOTES, 'UTF-8')) ?></p>
                                <small class="text-muted-custom">
                                    <?= htmlspecialchars($n['autor_nome'], ENT_QUOTES, 'UTF-8') ?>
                                    · <?= FormatHelper::dateTimeBr($n['created_at']) ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
