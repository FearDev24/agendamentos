<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }
/**
 * Indicador de progresso do wizard de agendamento.
 * Espera a variável $passo (1, 2 ou 3).
 */
$etapas = [1 => 'Serviços', 2 => 'Data e Horário', 3 => 'Revisão'];
?>
<nav class="passos-wizard mb-4" aria-label="Progresso do agendamento">
    <ol class="list-unstyled d-flex gap-2 mb-0">
        <?php foreach ($etapas as $numero => $rotulo): ?>
            <?php
            $estado = $numero < $passo ? 'concluido' : ($numero === $passo ? 'atual' : 'pendente');
            ?>
            <li class="passo passo-<?= $estado ?>" <?= $estado === 'atual' ? 'aria-current="step"' : '' ?>>
                <span class="passo-numero"><?= $numero ?></span>
                <span class="passo-rotulo"><?= $rotulo ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
