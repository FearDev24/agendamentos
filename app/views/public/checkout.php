<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

$situacao = $agendamento['situacao'];
$confirmado = $situacao === 'confirmado';
$aguardando = $situacao === 'aguardando_pagamento' && !$expirado;
$metodoAtivo = ($_GET['metodo'] ?? '') === 'cartao' ? 'cartao' : 'pix';
$temPix = $pagamento !== null && $pagamento['forma_pagamento'] === 'pix' && !empty($pagamento['qr_code_pix']);
?>

<main class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 text-gold mb-4">Pagamento</h1>

            <?php if ($confirmado): ?>
                <div class="alerta alerta-sucesso mb-4">
                    <strong>Agendamento confirmado!</strong> Enviamos o comprovante para o seu e-mail.
                </div>
            <?php elseif ($expirado || $situacao === 'cancelado'): ?>
                <div class="alerta alerta-erro mb-4">
                    Esta reserva expirou e o horário foi liberado. Faça um novo agendamento.
                </div>
            <?php elseif ($aguardando): ?>
                <div class="style-card p-4 mb-4 text-center">
                    <span class="label-caps text-muted-custom d-block mb-1">TEMPO RESTANTE PARA PAGAR</span>
                    <strong class="cronometro text-gold" id="cronometro"
                            data-expira="<?= htmlspecialchars($agendamento['reserva_expira_em'], ENT_QUOTES, 'UTF-8') ?>">--:--</strong>
                    <p class="text-muted-custom mb-0 mt-2">Após esse prazo o horário volta a ficar disponível.</p>
                </div>
            <?php endif; ?>

            <!-- Resumo da reserva -->
            <div class="style-card p-4 mb-4">
                <h2 class="h5 text-gold mb-3">
                    Reserva <?= htmlspecialchars($agendamento['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <dl class="row mb-3">
                    <dt class="col-sm-4 label-caps text-muted-custom">CLIENTE</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($agendamento['cliente_nome'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-sm-4 label-caps text-muted-custom">PROFISSIONAL</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($agendamento['profissional_nome'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-sm-4 label-caps text-muted-custom">DATA E HORA</dt>
                    <dd class="col-sm-8 dado-numerico">
                        <?= FormatHelper::dateBr($agendamento['data_agendamento']) ?>
                        às <?= substr($agendamento['hora_inicio'], 0, 5) ?>
                        (até <?= substr($agendamento['hora_fim'], 0, 5) ?>)
                    </dd>

                    <dt class="col-sm-4 label-caps text-muted-custom">SITUAÇÃO</dt>
                    <dd class="col-sm-8">
                        <span class="etiqueta etiqueta-<?= htmlspecialchars($situacao, ENT_QUOTES, 'UTF-8') ?>" id="etiqueta-situacao">
                            <?= htmlspecialchars(str_replace('_', ' ', $situacao), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </dd>
                </dl>

                <h3 class="h6 text-gold mb-2">Serviços contratados</h3>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($servicos as $s): ?>
                        <li class="d-flex justify-content-between border-bottom border-secondary-dark py-2">
                            <span>
                                <?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>
                                <small class="text-muted-custom">(<?= (int)$s['duracao_aplicada_minutos'] ?> min)</small>
                            </span>
                            <span class="dado-numerico"><?= FormatHelper::currency((float)$s['preco_aplicado']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <dl class="row mb-0">
                    <dt class="col-7 text-muted-custom">Subtotal</dt>
                    <dd class="col-5 text-end dado-numerico">
                        <?= FormatHelper::currency((float)$agendamento['valor_total']) ?>
                    </dd>

                    <?php if ((float)$agendamento['credito_utilizado'] > 0): ?>
                        <dt class="col-7 text-muted-custom">Crédito interno aplicado</dt>
                        <dd class="col-5 text-end dado-numerico text-success">
                            − <?= FormatHelper::currency((float)$agendamento['credito_utilizado']) ?>
                        </dd>
                    <?php endif; ?>

                    <?php $jaPago = (float)$agendamento['valor_total'] - (float)$agendamento['credito_utilizado'] - $valorDevido; ?>
                    <?php if ($jaPago > 0.009): ?>
                        <dt class="col-7 text-muted-custom">Já pago</dt>
                        <dd class="col-5 text-end dado-numerico text-success">
                            − <?= FormatHelper::currency($jaPago) ?>
                        </dd>
                    <?php endif; ?>

                    <dt class="col-7 h6 mb-0">A pagar</dt>
                    <dd class="col-5 text-end mb-0">
                        <strong class="text-gold dado-numerico fs-4"><?= FormatHelper::currency($valorDevido) ?></strong>
                    </dd>
                </dl>
            </div>

            <!-- Crédito interno disponível -->
            <?php if ($aguardando && $saldoCredito > 0 && $valorDevido > 0): ?>
                <div class="style-card p-4 mb-4">
                    <h2 class="h6 text-gold mb-2">Usar Crédito Interno</h2>
                    <p class="text-muted-custom mb-3">
                        Você tem <strong class="text-gold"><?= FormatHelper::currency($saldoCredito) ?></strong> em créditos.
                    </p>

                    <form method="POST" action="<?= $baseUrl ?>/cliente/credito" class="row g-2 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="codigo" value="<?= htmlspecialchars($agendamento['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="col-sm-7">
                            <label for="valor-credito" class="form-label label-caps text-muted-custom">VALOR A APLICAR</label>
                            <input type="text" class="campo-form" id="valor-credito" name="valor" inputmode="decimal"
                                   value="<?= number_format(min($saldoCredito, $valorDevido), 2, ',', '') ?>">
                        </div>
                        <div class="col-sm-5">
                            <button type="submit" class="btn btn-outline-gold w-100">Aplicar Crédito</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($aguardando): ?>
                <!-- Formas de pagamento -->
                <div class="style-card p-4">
                    <ul class="nav nav-tabs abas-pagamento mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $metodoAtivo === 'pix' ? 'active' : '' ?>" id="aba-pix"
                                    data-bs-toggle="tab" data-bs-target="#painel-pix" type="button" role="tab"
                                    aria-controls="painel-pix" aria-selected="<?= $metodoAtivo === 'pix' ? 'true' : 'false' ?>">
                                Pix
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $metodoAtivo === 'cartao' ? 'active' : '' ?>" id="aba-cartao"
                                    data-bs-toggle="tab" data-bs-target="#painel-cartao" type="button" role="tab"
                                    aria-controls="painel-cartao" aria-selected="<?= $metodoAtivo === 'cartao' ? 'true' : 'false' ?>">
                                Cartão de Crédito
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Pix -->
                        <div class="tab-pane fade <?= $metodoAtivo === 'pix' ? 'show active' : '' ?>"
                             id="painel-pix" role="tabpanel" aria-labelledby="aba-pix">

                            <?php if ($temPix): ?>
                                <div class="text-center mb-4">
                                    <img src="<?= htmlspecialchars($pagamento['qr_code_pix_url'], ENT_QUOTES, 'UTF-8') ?>"
                                         alt="QR Code para pagamento via Pix" class="qr-code-pix">
                                    <p class="text-muted-custom mt-3 mb-0">
                                        Abra o app do seu banco, escolha Pix e leia o QR Code.
                                    </p>
                                </div>

                                <label for="codigo-pix" class="form-label label-caps text-muted-custom">PIX COPIA E COLA</label>
                                <textarea class="campo-form mb-2" id="codigo-pix" rows="3" readonly><?= htmlspecialchars($pagamento['qr_code_pix'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                <button type="button" class="btn btn-outline-gold w-100" id="btn-copiar-pix"
                                        data-alvo="codigo-pix">Copiar Código Pix</button>

                                <p class="text-muted-custom text-center mt-3 mb-0">
                                    <small>A confirmação é automática assim que o pagamento cair.</small>
                                </p>

                                <?php if ($emSandbox): ?>
                                    <div class="alerta alerta-aviso mt-4">
                                        <strong class="d-block mb-2">Ambiente de simulação</strong>
                                        O gateway está em modo sandbox: nenhum pagamento real é processado.
                                        Use o botão abaixo para simular a aprovação e testar a confirmação automática.

                                        <form method="POST" action="<?= $baseUrl ?>/checkout/<?= urlencode($agendamento['codigo_reserva']) ?>/simular" class="mt-3">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <button type="submit" class="btn btn-gold w-100">Simular Pagamento Aprovado</button>
                                        </form>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <p class="text-muted-custom">
                                    Gere o código Pix para pagar. O valor a pagar é
                                    <strong class="text-gold"><?= FormatHelper::currency($valorDevido) ?></strong>.
                                </p>

                                <form method="POST" action="<?= $baseUrl ?>/checkout/<?= urlencode($agendamento['codigo_reserva']) ?>/pix">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="btn btn-gold w-100 py-2">Gerar QR Code Pix</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Cartão -->
                        <div class="tab-pane fade <?= $metodoAtivo === 'cartao' ? 'show active' : '' ?>"
                             id="painel-cartao" role="tabpanel" aria-labelledby="aba-cartao">

                            <form method="POST" action="<?= $baseUrl ?>/checkout/<?= urlencode($agendamento['codigo_reserva']) ?>/cartao"
                                  id="form-cartao" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <!-- Preenchido pelo tokenizador antes do envio -->
                                <input type="hidden" name="token" id="token-cartao" value="">
                                <input type="hidden" name="metodo" id="metodo-cartao" value="">

                                <?php if ($emSandbox): ?>
                                    <div class="alerta alerta-aviso mb-3">
                                        <strong class="d-block mb-1">Ambiente de simulação</strong>
                                        Nenhum dado real de cartão é solicitado ou transmitido. Escolha o
                                        resultado que deseja testar.
                                    </div>

                                    <label for="resultado-simulado" class="form-label label-caps text-muted-custom">RESULTADO SIMULADO</label>
                                    <select class="campo-form mb-3" id="resultado-simulado">
                                        <option value="aprovado">Pagamento aprovado</option>
                                        <option value="recusado">Pagamento recusado</option>
                                        <option value="pendente">Pagamento em análise</option>
                                    </select>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="parcelas" class="form-label label-caps text-muted-custom">PARCELAS</label>
                                            <select class="campo-form" id="parcelas" name="parcelas">
                                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                                    <option value="<?= $i ?>">
                                                        <?= $i ?>x de <?= FormatHelper::currency($valorDevido / $i) ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>

                                <?php else: ?>
                                    <div class="alerta alerta-aviso mb-3">
                                        Os dados do cartão são enviados diretamente ao Mercado Pago pelo
                                        navegador e nunca passam por este servidor.
                                    </div>

                                    <!-- Campos controlados pelo SDK do Mercado Pago -->
                                    <div id="campos-cartao-mp" data-public-key="<?= htmlspecialchars($publicKey, ENT_QUOTES, 'UTF-8') ?>"></div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label for="cpf" class="form-label label-caps text-muted-custom">CPF DO TITULAR</label>
                                            <input type="text" class="campo-form" id="cpf" name="cpf" inputmode="numeric">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="parcelas" class="form-label label-caps text-muted-custom">PARCELAS</label>
                                            <select class="campo-form" id="parcelas" name="parcelas">
                                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                                    <option value="<?= $i ?>">
                                                        <?= $i ?>x de <?= FormatHelper::currency($valorDevido / $i) ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-gold w-100 py-2">
                                    Pagar <?= FormatHelper::currency($valorDevido) ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php elseif ($confirmado): ?>
                <div class="style-card p-4 text-center">
                    <a href="<?= $baseUrl ?>/cliente/agendamentos" class="btn btn-gold px-4">Ver Meus Agendamentos</a>
                </div>
            <?php else: ?>
                <div class="style-card p-4 text-center">
                    <a href="<?= $baseUrl ?>/agendar" class="btn btn-gold px-4">Fazer Novo Agendamento</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    window.AGENDABARBER_CHECKOUT = {
        urlStatus: '<?= $baseUrl ?>/checkout/<?= urlencode($agendamento['codigo_reserva']) ?>/status',
        aguardando: <?= $aguardando ? 'true' : 'false' ?>,
        sandbox: <?= $emSandbox ? 'true' : 'false' ?>
    };
</script>
<script src="<?= FormatHelper::asset('assets/js/checkout.js') ?>"></script>
