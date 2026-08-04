<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="style-card p-4 p-md-5">
                <h1 class="h3 text-gold mb-1">Criar Nova Senha</h1>
                <p class="text-muted-custom mb-4">Defina a nova senha da sua conta.</p>

                <form method="POST" action="<?= $baseUrl ?>/redefinir-senha" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="senha" class="form-label label-caps text-muted-custom">NOVA SENHA</label>
                        <input type="password" class="campo-form" id="senha" name="senha" required autofocus
                               minlength="8" autocomplete="new-password">
                        <small class="text-muted-custom">Mínimo de 8 caracteres.</small>
                    </div>

                    <div class="mb-4">
                        <label for="senha_confirmacao" class="form-label label-caps text-muted-custom">CONFIRMAR NOVA SENHA</label>
                        <input type="password" class="campo-form" id="senha_confirmacao" name="senha_confirmacao"
                               required minlength="8" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-gold w-100 py-2">Salvar Nova Senha</button>
                </form>
            </div>
        </div>
    </div>
</main>
