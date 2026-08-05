<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="style-card p-4 p-md-5">
                <h1 class="h3 text-gold mb-1">Criar Conta</h1>
                <p class="text-muted-custom mb-4">Cadastre-se para agendar seus atendimentos.</p>

                <form method="POST" action="<?= $baseUrl ?>/cadastro" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label for="nome" class="form-label label-caps text-muted-custom">NOME COMPLETO *</label>
                        <input type="text" class="campo-form" id="nome" name="nome" required autofocus
                               autocomplete="name" minlength="3"
                               value="<?= htmlspecialchars($antigo['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label label-caps text-muted-custom">E-MAIL *</label>
                        <input type="email" class="campo-form" id="email" name="email" required
                               autocomplete="email"
                               value="<?= htmlspecialchars($antigo['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="telefone" class="form-label label-caps text-muted-custom">TELEFONE (COM DDD) *</label>
                        <input type="tel" class="campo-form" id="telefone" name="telefone" required
                               autocomplete="tel" data-mascara="telefone" placeholder="(00) 00000-0000"
                               value="<?= htmlspecialchars($antigo['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="senha" class="form-label label-caps text-muted-custom">SENHA *</label>
                            <input type="password" class="campo-form" id="senha" name="senha" required
                                   minlength="8" autocomplete="new-password">
                            <small class="text-muted-custom">Mínimo de 8 caracteres.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="senha_confirmacao" class="form-label label-caps text-muted-custom">CONFIRMAR SENHA *</label>
                            <input type="password" class="campo-form" id="senha_confirmacao" name="senha_confirmacao"
                                   required minlength="8" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="foto" class="form-label label-caps text-muted-custom">FOTO DE PERFIL (OPCIONAL)</label>
                        <input type="file" class="campo-form" id="foto" name="foto" accept=".jpg,.jpeg,.png,.webp">
                        <small class="text-muted-custom">JPG, PNG ou WEBP com até 5MB.</small>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="confirmo_maioridade" name="confirmo_maioridade" required>
                        <label class="form-check-label text-on-surface" for="confirmo_maioridade">
                            Declaro que tenho <strong>18 anos ou mais</strong>. *
                        </label>
                    </div>

                    <button type="submit" class="btn btn-gold w-100 py-2 mb-3">Criar Conta</button>
                </form>

                <p class="text-center mb-0 text-muted-custom">
                    Já tem conta? <a href="<?= $baseUrl ?>/login" class="text-gold text-decoration-none">Entrar</a>
                </p>
            </div>
        </div>
    </div>
</main>
