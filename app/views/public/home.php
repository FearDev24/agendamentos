<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="flex-grow-1">
    <!-- Hero -->
    <section class="container py-5">
        <div class="p-5 rounded-3 style-card-hero text-center">
            <img src="<?= $baseUrl ?>/public/assets/img/logojablonskiprincipal.png" alt="" class="mb-4" style="max-height: 110px;">
            <h1 class="display-5 fw-bold text-gold mb-3">
                <?= htmlspecialchars($ajustes['estabelecimento_nome'] ?? 'AgendaBarber', ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <p class="fs-5 text-muted-custom mb-4">
                Escolha seus serviços, seu profissional e garanta seu horário em poucos cliques.
            </p>
            <a href="<?= $baseUrl ?>/agendar" class="btn btn-gold btn-lg px-5">Agendar Horário</a>
        </div>
    </section>

    <!-- Promoções vigentes -->
    <?php if (!empty($promocoes)): ?>
    <section class="container py-4">
        <h2 class="h4 text-gold mb-1">Promoções</h2>
        <p class="text-muted-custom mb-4">Ofertas por tempo limitado.</p>

        <div class="row g-3">
            <?php foreach ($promocoes as $promo): ?>
                <?php
                $rotulo = $promo['tipo_desconto'] === 'percentual'
                    ? number_format((float)$promo['valor_desconto'], 0, ',', '') . '% OFF'
                    : FormatHelper::currency((float)$promo['valor_desconto']) . ' OFF';
                ?>
                <div class="col-md-6">
                    <a href="<?= $baseUrl ?>/promocao/<?= (int)$promo['id'] ?>"
                       class="cartao-promocao style-card h-100 d-block text-decoration-none overflow-hidden">
                        <?php if (!empty($promo['imagem'])): ?>
                            <img src="<?= $baseUrl ?>/public/uploads/promocoes/<?= urlencode($promo['imagem']) ?>"
                                 alt="" class="banner-promocao">
                        <?php endif; ?>

                        <div class="p-4">
                            <span class="etiqueta-desconto"><?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?></span>
                            <h3 class="h6 text-gold mt-2 mb-1"><?= htmlspecialchars($promo['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="text-muted-custom mb-0">
                                <small>
                                    <?php if (!empty($promo['servico_nome'])): ?>
                                        Em <?= htmlspecialchars($promo['servico_nome'], ENT_QUOTES, 'UTF-8') ?> ·
                                    <?php endif; ?>
                                    até <?= FormatHelper::dateBr($promo['data_fim']) ?>
                                </small>
                            </p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Serviços -->
    <section class="container py-4">
        <h2 class="h4 text-gold mb-1">Nossos Serviços</h2>
        <p class="text-muted-custom mb-4">Preços e durações atualizados.</p>

        <?php if (empty($servicos)): ?>
            <div class="style-card p-4">
                <p class="text-muted-custom mb-0">Nenhum serviço disponível no momento.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($servicos as $s): ?>
                    <div class="col-md-6 col-lg-4">
                        <article class="style-card h-100 p-4 d-flex flex-column">
                            <h3 class="h6 text-gold mb-2"><?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?></h3>

                            <?php if (!empty($s['descricao'])): ?>
                                <p class="text-muted-custom flex-grow-1"><?= htmlspecialchars($s['descricao'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php else: ?>
                                <div class="flex-grow-1"></div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="dado-numerico text-muted-custom"><?= (int)$s['duracao_minutos'] ?> min</span>
                                <strong class="dado-numerico text-gold fs-5"><?= FormatHelper::currency((float)$s['preco']) ?></strong>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Profissionais -->
    <?php if (!empty($profissionais)): ?>
    <section class="container py-4">
        <h2 class="h4 text-gold mb-1">Nossa Equipe</h2>
        <p class="text-muted-custom mb-4">Escolha o profissional da sua preferência.</p>

        <div class="row g-3">
            <?php foreach ($profissionais as $p): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <article class="style-card h-100 p-4 text-center">
                        <?php if (!empty($p['foto'])): ?>
                            <img src="<?= $baseUrl ?>/public/uploads/perfis/<?= htmlspecialchars($p['foto'], ENT_QUOTES, 'UTF-8') ?>"
                                 alt="" class="rounded-circle mb-3" style="width:72px;height:72px;object-fit:cover;">
                        <?php else: ?>
                            <span class="avatar-iniciais mb-3" style="width:72px;height:72px;font-size:28px;" aria-hidden="true">
                                <?= strtoupper(mb_substr($p['nome'], 0, 1)) ?>
                            </span>
                        <?php endif; ?>

                        <h3 class="h6 text-on-surface mb-0"><?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?></h3>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Sobre e contato -->
    <section class="container py-4 pb-5">
        <div class="style-card p-4">
            <h2 class="h5 text-gold mb-3">Onde Estamos</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <p class="mb-2">
                        <span class="label-caps text-muted-custom d-block">ENDEREÇO</span>
                        <?= htmlspecialchars($ajustes['estabelecimento_endereco'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="mb-0">
                        <span class="label-caps text-muted-custom d-block">HORÁRIO DE FUNCIONAMENTO</span>
                        <?= htmlspecialchars($ajustes['horario_funcionamento'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>

                <div class="col-md-6">
                    <p class="mb-2">
                        <span class="label-caps text-muted-custom d-block">TELEFONE</span>
                        <?= htmlspecialchars($ajustes['estabelecimento_telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="mb-0">
                        <span class="label-caps text-muted-custom d-block">E-MAIL</span>
                        <?= htmlspecialchars($ajustes['estabelecimento_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <?php if (!empty($ajustes['estabelecimento_mapa_url'])): ?>
                        <a href="<?= htmlspecialchars($ajustes['estabelecimento_mapa_url'], ENT_QUOTES, 'UTF-8') ?>"
                           target="_blank" rel="noopener noreferrer" class="btn btn-outline-gold mt-3">Ver no mapa</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>
