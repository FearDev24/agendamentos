<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>
        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-1">Minha Agenda</h1>
            <p class="text-muted-custom mb-4">Olá, <?= htmlspecialchars(AuthHelper::nome() ?? '', ENT_QUOTES, 'UTF-8') ?>.</p>

            <div class="style-card p-4">
                <p class="text-muted-custom mb-0">
                    A agenda diária, a confirmação de presença, o registro de horários reais e as notas
                    privadas de clientes serão entregues na Fase 7.
                </p>
            </div>
        </div>
    </div>
</main>
