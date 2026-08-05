<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

$rotulosAmbiente = [
    'teste'        => ['Credencial de teste', 'etiqueta-indisponivel'],
    'producao'     => ['Credencial de produção', 'etiqueta-ativo'],
    'desconhecido' => ['Formato não reconhecido', 'etiqueta-inativo'],
    'nenhum'       => ['Nenhuma credencial', 'etiqueta-inativo'],
];
[$rotuloAmbiente, $classeAmbiente] = $rotulosAmbiente[$mp['ambiente_detectado']] ?? $rotulosAmbiente['nenhum'];
?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-1">Integrações</h1>
            <p class="text-muted-custom mb-4">
                Vincule a conta que vai receber os pagamentos dos agendamentos.
            </p>

            <?php if (!$chaveCriptoOk): ?>
                <div class="alerta alerta-erro mb-4">
                    <strong class="d-block mb-1">Chave de criptografia ausente</strong>
                    As credenciais são guardadas cifradas. Antes de cadastrá-las, defina
                    <code>chave_cripto</code> em <code>config/config.php</code>. Gere uma com:
                    <br><code>php -r "echo base64_encode(random_bytes(32));"</code>
                </div>
            <?php endif; ?>

            <!-- Situação atual -->
            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3">Mercado Pago</h2>

                <dl class="row mb-0">
                    <dt class="col-sm-5 label-caps text-muted-custom">SITUAÇÃO</dt>
                    <dd class="col-sm-7">
                        <?php if ($mp['tem_access_token']): ?>
                            <span class="etiqueta <?= $classeAmbiente ?>"><?= $rotuloAmbiente ?></span>
                        <?php else: ?>
                            <span class="etiqueta etiqueta-inativo">Não configurado</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-5 label-caps text-muted-custom">MODO</dt>
                    <dd class="col-sm-7">
                        <?php if ($mp['sandbox_mode']): ?>
                            <span class="text-muted-custom">Simulação — nenhum pagamento real é processado</span>
                        <?php else: ?>
                            <span class="text-sucesso">Produção — pagamentos reais</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-5 label-caps text-muted-custom">ORIGEM</dt>
                    <dd class="col-sm-7">
                        <?= $mp['origem'] === 'painel'
                            ? 'Cadastrada neste painel'
                            : 'Arquivo de configuração do servidor' ?>
                    </dd>

                    <?php if ($mp['tem_access_token']): ?>
                        <dt class="col-sm-5 label-caps text-muted-custom">ACCESS TOKEN</dt>
                        <dd class="col-sm-7 dado-numerico"><?= htmlspecialchars($mp['access_token_mascara'], ENT_QUOTES, 'UTF-8') ?></dd>
                    <?php endif; ?>

                    <?php if ($mp['tem_webhook_secret']): ?>
                        <dt class="col-sm-5 label-caps text-muted-custom">SEGREDO DO WEBHOOK</dt>
                        <dd class="col-sm-7 dado-numerico"><?= htmlspecialchars($mp['webhook_secret_mascara'], ENT_QUOTES, 'UTF-8') ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($mp['configurado_em'])): ?>
                        <dt class="col-sm-5 label-caps text-muted-custom">ATUALIZADA EM</dt>
                        <dd class="col-sm-7 dado-numerico"><?= FormatHelper::dateTimeBr($mp['configurado_em']) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if ($resultadoTeste !== null): ?>
                    <div class="alerta <?= $resultadoTeste['ok'] ? 'alerta-sucesso' : 'alerta-erro' ?> mt-3 mb-0">
                        <?= htmlspecialchars($resultadoTeste['mensagem'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($resultadoTeste['ok']): ?>
                            <br><small><?= (int)$resultadoTeste['meios'] ?> meios de pagamento disponíveis na conta.</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($mp['tem_access_token']): ?>
                    <div class="d-flex gap-2 mt-3">
                        <form method="POST" action="<?= $baseUrl ?>/admin/integracoes/testar" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn btn-outline-gold px-4">Testar Conexão</button>
                        </form>

                        <?php if ($mp['origem'] === 'painel'): ?>
                            <form method="POST" action="<?= $baseUrl ?>/admin/integracoes/desvincular" class="m-0"
                                  onsubmit="return confirm('Desvincular a conta? O sistema voltará a usar as credenciais do servidor.');">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="btn btn-perigo px-4">Desvincular</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Passo a passo -->
            <details class="style-card p-4 mb-4">
                <summary class="text-gold">Como obter estas credenciais</summary>

                <ol class="text-muted-custom mt-3 mb-0">
                    <li class="mb-2">Acesse <strong>developers.mercadopago.com</strong> e entre com a conta
                        que vai receber os pagamentos.</li>
                    <li class="mb-2">Vá em <strong>Suas integrações</strong> e crie uma aplicação do tipo
                        <strong>Pagamentos online</strong>.</li>
                    <li class="mb-2">Em <strong>Credenciais de teste</strong> ou <strong>Credenciais de produção</strong>,
                        copie a <em>Public Key</em> e o <em>Access Token</em>.</li>
                    <li class="mb-2">Em <strong>Webhooks</strong>, cadastre a URL abaixo e copie a
                        <strong>assinatura secreta</strong> gerada.</li>
                </ol>

                <div class="row g-2 align-items-end mt-3">
                    <div class="col-md-9">
                        <label for="url-webhook" class="form-label label-caps text-muted-custom">URL DO WEBHOOK</label>
                        <input type="text" class="campo-form" id="url-webhook" readonly
                               value="<?= htmlspecialchars($urlWebhook, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-outline-gold w-100"
                                data-copiar data-alvo="url-webhook">Copiar</button>
                    </div>
                </div>

                <p class="text-muted-custom mt-3 mb-0">
                    <small>Comece pelas credenciais de <strong>teste</strong> (começam com <code>TEST-</code>):
                    elas permitem validar tudo sem movimentar dinheiro. Troque pelas de produção
                    quando estiver pronto para receber de verdade.</small>
                </p>
            </details>

            <!-- Formulário -->
            <div class="style-card p-4">
                <h2 class="h5 text-gold mb-1"><?= $mp['tem_access_token'] ? 'Atualizar Credenciais' : 'Vincular Conta' ?></h2>
                <p class="text-muted-custom mb-4">
                    Os segredos são gravados cifrados e nunca voltam a ser exibidos por completo.
                </p>

                <form method="POST" action="<?= $baseUrl ?>/admin/integracoes/mercadopago" novalidate autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="public_key" class="form-label label-caps text-muted-custom">PUBLIC KEY *</label>
                            <input type="text" class="campo-form" id="public_key" name="public_key" required
                                   placeholder="TEST-00000000-0000-0000-0000-000000000000"
                                   value="<?= htmlspecialchars($mp['public_key'], ENT_QUOTES, 'UTF-8') ?>">
                            <small class="text-muted-custom">Usada pelo navegador para proteger os dados do cartão. Não é secreta.</small>
                        </div>

                        <div class="col-12">
                            <label for="access_token" class="form-label label-caps text-muted-custom">
                                ACCESS TOKEN <?= $mp['tem_access_token'] ? '(deixe em branco para manter)' : '*' ?>
                            </label>
                            <input type="password" class="campo-form" id="access_token" name="access_token"
                                   autocomplete="new-password"
                                   placeholder="<?= $mp['tem_access_token'] ? 'Mantém o token atual' : 'APP_USR-...' ?>"
                                   <?= $mp['tem_access_token'] ? '' : 'required' ?>>
                            <small class="text-erro">Secreto. Quem tem este token movimenta a conta — nunca compartilhe.</small>
                        </div>

                        <div class="col-12">
                            <label for="webhook_secret" class="form-label label-caps text-muted-custom">
                                SEGREDO DO WEBHOOK <?= $mp['tem_webhook_secret'] ? '(deixe em branco para manter)' : '' ?>
                            </label>
                            <input type="password" class="campo-form" id="webhook_secret" name="webhook_secret"
                                   autocomplete="new-password"
                                   placeholder="<?= $mp['tem_webhook_secret'] ? 'Mantém o segredo atual' : 'Assinatura secreta gerada no painel' ?>">
                            <small class="text-muted-custom">
                                Garante que a confirmação de pagamento veio mesmo do Mercado Pago.
                                Obrigatório em modo produção.
                            </small>
                        </div>

                        <div class="col-12">
                            <span class="form-label label-caps text-muted-custom d-block">MODO DE OPERAÇÃO *</span>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="modo" id="modo_sandbox"
                                       value="sandbox" <?= $mp['sandbox_mode'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="modo_sandbox">
                                    <strong>Simulação</strong>
                                    <small class="text-muted-custom d-block">
                                        Nenhum pagamento real. Use para testar o fluxo completo com segurança.
                                    </small>
                                </label>
                            </div>

                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="modo" id="modo_producao"
                                       value="producao" <?= $mp['sandbox_mode'] ? '' : 'checked' ?>>
                                <label class="form-check-label" for="modo_producao">
                                    <strong>Produção</strong>
                                    <small class="text-muted-custom d-block">
                                        Cobranças reais, com dinheiro entrando na conta vinculada.
                                    </small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-gold px-4 mt-4">Salvar Integração</button>
                </form>
            </div>
        </div>
    </div>
</main>
