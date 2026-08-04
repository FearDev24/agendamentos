<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="style-card p-4 p-md-5">
                <h1 class="h3 text-gold mb-1">Recuperar Senha</h1>
                <p class="text-muted-custom mb-4">
                    Informe seu e-mail cadastrado. Enviaremos um link para criar uma nova senha,
                    válido por 1 hora.
                </p>

                <form method="POST" action="<?= $baseUrl ?>/recuperar-senha" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="mb-4">
                        <label for="email" class="form-label label-caps text-muted-custom">E-MAIL</label>
                        <input type="email" class="campo-form" id="email" name="email" required autofocus
                               autocomplete="email">
                    </div>

                    <button type="submit" class="btn btn-gold w-100 py-2 mb-3">Enviar Link</button>
                </form>

                <p class="text-center mb-0">
                    <a href="<?= $baseUrl ?>/login" class="text-gold text-decoration-none">Voltar para o login</a>
                </p>
            </div>
        </div>
    </div>
</main>
