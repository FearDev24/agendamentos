<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>
        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-1">Histórico de Auditoria</h1>
            <p class="text-muted-custom mb-4">Acesso exclusivo do Administrador Principal.</p>

            <div class="style-card p-4">
                <p class="text-muted-custom mb-0">
                    A consulta a <code>audit_logs</code> com filtros por usuário, entidade e período
                    será entregue na Fase 9.
                </p>
            </div>
        </div>
    </div>
</main>
