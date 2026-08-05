<?php if (!defined('APP_EXEC')) { die('Acesso direto negado.'); } ?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-3 nao-imprimir">
                <a href="<?= $baseUrl ?>/cliente/agendamentos" class="btn btn-outline-gold btn-sm">Voltar</a>
                <button type="button" class="btn btn-gold btn-sm" onclick="window.print()">
                    Imprimir / Salvar em PDF
                </button>
            </div>

            <article class="style-card p-4 comprovante">
                <header class="text-center border-bottom border-secondary-dark pb-3 mb-3">
                    <img src="<?= $baseUrl ?>/public/assets/img/logojablonskiprincipal.png" alt="" style="max-height: 64px;">
                    <h1 class="h5 text-gold mt-2 mb-1">
                        <?= htmlspecialchars($ajustes['estabelecimento_nome'] ?? 'AgendaBarber', ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <p class="text-muted-custom mb-0"><small>Comprovante de Agendamento</small></p>
                </header>

                <div class="text-center mb-4">
                    <span class="label-caps text-muted-custom d-block">CÓDIGO DA RESERVA</span>
                    <strong class="dado-numerico fs-4 text-gold">
                        <?= htmlspecialchars($agendamento['codigo_reserva'], ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                    <div class="mt-2">
                        <span class="etiqueta etiqueta-<?= htmlspecialchars($agendamento['situacao'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(str_replace('_', ' ', $agendamento['situacao']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>

                <dl class="row mb-3">
                    <dt class="col-5 label-caps text-muted-custom">CLIENTE</dt>
                    <dd class="col-7"><?= htmlspecialchars($agendamento['cliente_nome'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-5 label-caps text-muted-custom">PROFISSIONAL</dt>
                    <dd class="col-7"><?= htmlspecialchars($agendamento['profissional_nome'], ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt class="col-5 label-caps text-muted-custom">DATA</dt>
                    <dd class="col-7 dado-numerico"><?= FormatHelper::dateBr($agendamento['data_agendamento']) ?></dd>

                    <dt class="col-5 label-caps text-muted-custom">HORÁRIO</dt>
                    <dd class="col-7 dado-numerico">
                        <?= substr($agendamento['hora_inicio'], 0, 5) ?> às <?= substr($agendamento['hora_fim'], 0, 5) ?>
                        (<?= (int)$agendamento['duracao_total_minutos'] ?> min)
                    </dd>

                    <?php if (!empty($agendamento['hora_inicio_real'])): ?>
                        <dt class="col-5 label-caps text-muted-custom">ATENDIMENTO REAL</dt>
                        <dd class="col-7 dado-numerico">
                            <?= substr($agendamento['hora_inicio_real'], 0, 5) ?>
                            <?php if (!empty($agendamento['hora_fim_real'])): ?>
                                às <?= substr($agendamento['hora_fim_real'], 0, 5) ?>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>
                </dl>

                <h2 class="h6 text-gold mb-2">Serviços</h2>
                <table class="table tabela-dados mb-3">
                    <tbody>
                        <?php foreach ($servicos as $s): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($s['nome'], ENT_QUOTES, 'UTF-8') ?>
                                    <small class="text-muted-custom">(<?= (int)$s['duracao_aplicada_minutos'] ?> min)</small>
                                </td>
                                <td class="text-end dado-numerico">
                                    <?= FormatHelper::currency((float)$s['preco_aplicado']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <dl class="row border-top border-secondary-dark pt-3 mb-0">
                    <?php if ((float)$agendamento['valor_desconto'] > 0): ?>
                        <dt class="col-7 text-muted-custom">Desconto aplicado</dt>
                        <dd class="col-5 text-end dado-numerico">
                            − <?= FormatHelper::currency((float)$agendamento['valor_desconto']) ?>
                        </dd>
                    <?php endif; ?>

                    <?php if ((float)$agendamento['credito_utilizado'] > 0): ?>
                        <dt class="col-7 text-muted-custom">Crédito interno utilizado</dt>
                        <dd class="col-5 text-end dado-numerico">
                            − <?= FormatHelper::currency((float)$agendamento['credito_utilizado']) ?>
                        </dd>
                    <?php endif; ?>

                    <dt class="col-7 h6">Total</dt>
                    <dd class="col-5 text-end">
                        <strong class="text-gold dado-numerico fs-5">
                            <?= FormatHelper::currency((float)$agendamento['valor_total']) ?>
                        </strong>
                    </dd>

                    <?php if ($pagamento !== null): ?>
                        <dt class="col-7 text-muted-custom">Forma de pagamento</dt>
                        <dd class="col-5 text-end">
                            <?= htmlspecialchars(PagamentoModel::ROTULOS_FORMA[$pagamento['forma_pagamento']] ?? $pagamento['forma_pagamento'], ENT_QUOTES, 'UTF-8') ?>
                        </dd>

                        <dt class="col-7 text-muted-custom">Pago em</dt>
                        <dd class="col-5 text-end dado-numerico">
                            <?= $pagamento['pago_em'] ? FormatHelper::dateTimeBr($pagamento['pago_em']) : '—' ?>
                        </dd>
                    <?php endif; ?>
                </dl>

                <footer class="border-top border-secondary-dark mt-4 pt-3 text-center text-muted-custom">
                    <small>
                        <?= htmlspecialchars($ajustes['estabelecimento_endereco'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                        <?= htmlspecialchars($ajustes['estabelecimento_telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        <br>Emitido em <?= date('d/m/Y H:i') ?>
                    </small>
                </footer>
            </article>
        </div>
    </div>
</main>
