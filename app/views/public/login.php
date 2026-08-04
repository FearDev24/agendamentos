<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="style-card p-4 p-md-5">
                <h1 class="h3 text-gold mb-1">Entrar</h1>
                <p class="text-muted-custom mb-4">Acesse sua conta para gerenciar seus agendamentos.</p>

                <form method="POST" action="<?= $baseUrl ?>/login" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label label-caps text-muted-custom">E-MAIL</label>
                        <input type="email" class="campo-form" id="email" name="email" required autofocus
                               autocomplete="email"
                               value="<?= htmlspecialchars($antigo['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-4">
                        <label for="senha" class="form-label label-caps text-muted-custom">SENHA</label>
                        <input type="password" class="campo-form" id="senha" name="senha" required
                               autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn btn-gold w-100 py-2 mb-3">Entrar</button>
                </form>

                <div class="d-flex justify-content-between">
                    <a href="<?= $baseUrl ?>/recuperar-senha" class="text-gold text-decoration-none">Esqueci minha senha</a>
                    <a href="<?= $baseUrl ?>/cadastro" class="text-gold text-decoration-none">Criar conta</a>
                </div>
            </div>
        </div>
    </div>
</main>
