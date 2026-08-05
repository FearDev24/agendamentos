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

<!-- Bootstrap 5 JS Local -->
<script src="<?= FormatHelper::asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<!-- Script JS Vanilla Principal -->
<script src="<?= FormatHelper::asset('assets/js/main.js') ?>"></script>
</body>
</html>
