<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
$baseUrl = $config['app']['url'] ?? '';
?>
<footer class="footer mt-auto py-4 border-top border-secondary-dark bg-surface-container-low text-on-surface-variant">
    <div class="container text-center text-md-start">
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <p class="mb-0">&copy; <?= date('Y') ?> AgendaBarber Web. Todos os direitos reservados.</p>
                <small class="text-muted-custom">Padrão Visual Elevated Grooming & Wellness</small>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="#" class="text-gold text-decoration-none me-3">Termos de Uso</a>
                <a href="#" class="text-gold text-decoration-none">Políticas de Cancelamento</a>
            </div>
        </div>
    </div>
</footer>

<!-- Convite para instalar como aplicativo. Começa oculto: só aparece quando o
     navegador confirma que a instalação é possível (ou no iOS, com instruções). -->
<aside class="convite-instalacao" id="convite-instalacao" hidden aria-live="polite">
    <div class="container d-flex align-items-center gap-3 flex-wrap">
        <img src="<?= FormatHelper::asset('assets/img/pwa/icone-192.png') ?>"
             alt="" width="44" height="44" class="convite-icone">

        <div class="flex-grow-1">
            <strong class="d-block text-on-surface">Instale o <?= htmlspecialchars($config['app']['name'] ?? 'AgendaBarber', ENT_QUOTES, 'UTF-8') ?></strong>
            <small class="text-muted-custom" id="instrucoes-padrao">
                Agende mais rápido: adicione à tela inicial e use como aplicativo.
            </small>
            <small class="text-muted-custom" id="instrucoes-ios" hidden>
                Toque em <strong>Compartilhar</strong> e depois em
                <strong>“Adicionar à Tela de Início”</strong>.
            </small>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-gold px-3" id="btn-instalar-app">Instalar</button>
            <button type="button" class="btn btn-outline-gold px-3" id="btn-dispensar-instalacao"
                    aria-label="Dispensar convite de instalação">Agora não</button>
        </div>
    </div>
</aside>

<!-- Bootstrap 5 JS Local -->
<script src="<?= FormatHelper::asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<!-- Script JS Vanilla Principal -->
<script src="<?= FormatHelper::asset('assets/js/main.js') ?>"></script>
<!-- Instalação como aplicativo -->
<script>
    window.AGENDABARBER_PWA = {
        urlServiceWorker: '<?= $baseUrl ?>/sw.js',
        escopo: '<?= rtrim(parse_url($baseUrl, PHP_URL_PATH) ?? "", "/") ?>/'
    };
</script>
<script src="<?= FormatHelper::asset('assets/js/pwa.js') ?>"></script>
</body>
</html>
