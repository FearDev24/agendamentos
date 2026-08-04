<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <div class="p-5 mb-4 rounded-3 style-card-hero text-center">
        <img src="<?= $baseUrl ?>/public/assets/img/logojablonskiprincipal.png" alt="" class="mb-4" style="max-height: 120px;">
        <h1 class="display-5 fw-bold text-gold">Bem-vindo ao <?= htmlspecialchars($config['app']['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="fs-5 text-muted-custom mb-4">Agendamento e gestão integrada para barbearias e estética</p>
        <a href="<?= $baseUrl ?>/agendar" class="btn btn-gold btn-lg px-5">Agendar Horário</a>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="style-card h-100 p-4">
                <h2 class="h5 text-gold">Escolha seus serviços</h2>
                <p class="text-muted-custom mb-0">Selecione um ou mais serviços e veja o valor e a duração calculados na hora.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="style-card h-100 p-4">
                <h2 class="h5 text-gold">Horário garantido</h2>
                <p class="text-muted-custom mb-0">Pagamento antecipado por Pix ou cartão reserva o seu lugar na agenda.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="style-card h-100 p-4">
                <h2 class="h5 text-gold">Tudo em um lugar</h2>
                <p class="text-muted-custom mb-0">Comprovantes, reagendamentos e histórico de atendimentos na sua conta.</p>
            </div>
        </div>
    </div>
</main>
