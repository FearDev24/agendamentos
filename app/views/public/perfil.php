<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main class="container py-5 flex-grow-1">
    <h1 class="h3 text-gold mb-4">Meu Perfil</h1>

    <div class="row g-4">
        <!-- Dados pessoais -->
        <div class="col-lg-6">
            <div class="style-card p-4 h-100">
                <h2 class="h5 text-gold mb-3">Dados Pessoais</h2>

                <form method="POST" action="<?= $baseUrl ?>/perfil/dados" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label for="nome" class="form-label label-caps text-muted-custom">NOME COMPLETO</label>
                        <input type="text" class="campo-form" id="nome" name="nome" required minlength="3"
                               value="<?= htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="telefone" class="form-label label-caps text-muted-custom">TELEFONE</label>
                        <input type="tel" class="campo-form" id="telefone" name="telefone" required
                               data-mascara="telefone"
                               value="<?= htmlspecialchars($usuario['telefone'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="foto" class="form-label label-caps text-muted-custom">FOTO DE PERFIL</label>
                        <input type="file" class="campo-form" id="foto" name="foto" accept=".jpg,.jpeg,.png,.webp">
                        <?php if (!empty($usuario['foto'])): ?>
                            <small class="text-muted-custom">Foto atual cadastrada. Envie outra para substituir.</small>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-gold px-4">Salvar Dados</button>
                </form>
            </div>
        </div>

        <!-- Alteração de senha -->
        <div class="col-lg-6">
            <div class="style-card p-4 h-100">
                <h2 class="h5 text-gold mb-3">Alterar Senha</h2>

                <form method="POST" action="<?= $baseUrl ?>/perfil/senha" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label for="senha_atual_s" class="form-label label-caps text-muted-custom">SENHA ATUAL</label>
                        <input type="password" class="campo-form" id="senha_atual_s" name="senha_atual" required
                               autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label for="nova_senha" class="form-label label-caps text-muted-custom">NOVA SENHA</label>
                        <input type="password" class="campo-form" id="nova_senha" name="senha" required minlength="8"
                               autocomplete="new-password">
                    </div>

                    <div class="mb-3">
                        <label for="nova_senha_c" class="form-label label-caps text-muted-custom">CONFIRMAR NOVA SENHA</label>
                        <input type="password" class="campo-form" id="nova_senha_c" name="senha_confirmacao" required
                               minlength="8" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-gold px-4">Alterar Senha</button>
                </form>
            </div>
        </div>

        <!-- Troca de e-mail -->
        <div class="col-lg-6">
            <div class="style-card p-4 h-100">
                <h2 class="h5 text-gold mb-3">E-mail de Acesso</h2>

                <p class="text-muted-custom">
                    E-mail atual: <strong class="text-on-surface"><?= htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8') ?></strong>
                </p>

                <?php if (!empty($usuario['email_pendente'])): ?>
                    <div class="alerta alerta-aviso mb-3">
                        Aguardando confirmação de <strong><?= htmlspecialchars($usuario['email_pendente'], ENT_QUOTES, 'UTF-8') ?></strong>.
                        Verifique a caixa de entrada do novo endereço.
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $baseUrl ?>/perfil/email" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="mb-3">
                        <label for="novo_email" class="form-label label-caps text-muted-custom">NOVO E-MAIL</label>
                        <input type="email" class="campo-form" id="novo_email" name="novo_email" required>
                    </div>

                    <div class="mb-3">
                        <label for="senha_atual_e" class="form-label label-caps text-muted-custom">CONFIRME SUA SENHA</label>
                        <input type="password" class="campo-form" id="senha_atual_e" name="senha_atual" required
                               autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn btn-gold px-4">Solicitar Alteração</button>
                    <p class="text-muted-custom mt-3 mb-0">
                        <small>O e-mail só muda depois que você clicar no link de confirmação enviado ao novo endereço.</small>
                    </p>
                </form>
            </div>
        </div>

        <!-- Exclusão de conta -->
        <div class="col-lg-6">
            <div class="style-card style-card-perigo p-4 h-100">
                <h2 class="h5 text-erro mb-3">Excluir Conta</h2>

                <?php if ($possuiAgendamentos): ?>
                    <div class="alerta alerta-aviso mb-0">
                        Você possui agendamentos futuros ativos. Cancele-os antes de excluir a conta.
                    </div>
                <?php else: ?>
                    <p class="text-muted-custom">
                        Esta ação desativa sua conta e você perde o acesso ao histórico de atendimentos.
                    </p>

                    <form method="POST" action="<?= $baseUrl ?>/perfil/excluir" novalidate
                          onsubmit="return confirm('Tem certeza que deseja excluir sua conta? Esta ação não pode ser desfeita por você.');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                        <div class="mb-3">
                            <label for="senha_atual_x" class="form-label label-caps text-muted-custom">CONFIRME SUA SENHA</label>
                            <input type="password" class="campo-form" id="senha_atual_x" name="senha_atual" required
                                   autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn btn-perigo px-4">Excluir Minha Conta</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
