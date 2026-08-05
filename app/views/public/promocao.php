<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

$desconto = $promocao['tipo_desconto'] === 'percentual'
    ? number_format((float)$promocao['valor_desconto'], 0, ',', '') . '% OFF'
    : FormatHelper::currency((float)$promocao['valor_desconto']) . ' de desconto';
?>

<main class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <?php if (!$vigente): ?>
                <div class="alerta alerta-aviso mb-4">
                    Esta promoção não está mais disponível.
                    <?php if ($promocao['data_fim'] < date('Y-m-d')): ?>
                        Encerrou em <?= FormatHelper::dateBr($promocao['data_fim']) ?>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <article class="style-card overflow-hidden">
                <?php if (!empty($promocao['imagem'])): ?>
                    <img src="<?= $baseUrl ?>/public/uploads/promocoes/<?= urlencode($promocao['imagem']) ?>"
                         alt="" class="banner-promocao">
                <?php endif; ?>

                <div class="p-4 p-md-5 text-center">
                    <span class="etiqueta-desconto"><?= htmlspecialchars($desconto, ENT_QUOTES, 'UTF-8') ?></span>

                    <h1 class="h2 text-gold mt-3 mb-3">
                        <?= htmlspecialchars($promocao['titulo'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>

                    <?php if (!empty($promocao['descricao'])): ?>
                        <p class="fs-5 text-muted-custom mb-4">
                            <?= nl2br(htmlspecialchars($promocao['descricao'], ENT_QUOTES, 'UTF-8')) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($promocao['servico_nome'])): ?>
                        <p class="text-muted-custom mb-3">
                            Válida para <strong class="text-on-surface"><?= htmlspecialchars($promocao['servico_nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                        </p>
                    <?php endif; ?>

                    <p class="text-muted-custom dado-numerico mb-4">
                        <?php if ($vigente): ?>
                            Aproveite até <?= FormatHelper::dateBr($promocao['data_fim']) ?>
                        <?php else: ?>
                            De <?= FormatHelper::dateBr($promocao['data_inicio']) ?>
                            a <?= FormatHelper::dateBr($promocao['data_fim']) ?>
                        <?php endif; ?>
                    </p>

                    <?php if ($vigente): ?>
                        <a href="<?= $baseUrl ?>/agendar" class="btn btn-gold btn-lg px-5">Agendar Agora</a>
                    <?php else: ?>
                        <a href="<?= $baseUrl ?>/" class="btn btn-outline-gold px-5">Ver Outros Serviços</a>
                    <?php endif; ?>

                    <?php if (!empty($promocao['limite_total_usos'])): ?>
                        <?php $restantes = (int)$promocao['limite_total_usos'] - (int)$promocao['usos_realizados']; ?>
                        <?php if ($vigente && $restantes > 0 && $restantes <= 10): ?>
                            <p class="text-erro mt-3 mb-0">
                                <small>Restam apenas <?= $restantes ?> vaga(s) nesta promoção!</small>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </article>

            <!-- Compartilhamento -->
            <?php
            $urlAtual = $baseUrl . '/promocao/' . (int)$promocao['id'];
            $textoCompartilhar = rawurlencode($promocao['titulo'] . ' — ' . $urlAtual);
            ?>
            <div class="style-card p-4 mt-4 text-center">
                <h2 class="h6 text-gold mb-3">Compartilhe com os amigos</h2>

                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a href="https://wa.me/?text=<?= $textoCompartilhar ?>"
                       target="_blank" rel="noopener noreferrer" class="btn btn-outline-gold px-4">WhatsApp</a>

                    <button type="button" class="btn btn-outline-gold px-4"
                            data-copiar data-alvo="url-promocao">Copiar Link</button>

                    <input type="text" id="url-promocao" class="campo-copiavel" readonly
                           value="<?= htmlspecialchars($urlAtual, ENT_QUOTES, 'UTF-8') ?>"
                           aria-label="Endereço desta promoção">
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="<?= $baseUrl ?>/" class="text-gold text-decoration-none">
                    Conheça o <?= htmlspecialchars($ajustes['estabelecimento_nome'] ?? 'estabelecimento', ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </div>
    </div>
</main>
