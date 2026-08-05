<?php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

$abas = [
    'auditoria' => 'Auditoria (' . count($registros) . ')',
    'seguranca' => 'Segurança (' . count($seguranca) . ')',
    'erros'     => 'Erros (' . count($erros) . ')',
    'excluidos' => 'Excluídos',
];

$query = http_build_query(array_filter([
    'de' => $filtros['de'], 'ate' => $filtros['ate'],
    'usuario' => $filtros['usuario_id'], 'entidade' => $filtros['entidade'], 'acao' => $filtros['acao'],
]));

/** Resume o JSON de auditoria em texto legível */
$resumirJson = function (?string $json): string {
    if (empty($json)) {
        return '—';
    }

    $dados = json_decode($json, true);

    if (!is_array($dados)) {
        return mb_strimwidth((string)$json, 0, 80, '...');
    }

    $partes = [];
    foreach ($dados as $chave => $valor) {
        if (is_array($valor)) {
            $valor = implode(', ', array_map('strval', $valor));
        }
        $partes[] = $chave . ': ' . mb_strimwidth((string)$valor, 0, 40, '...');
    }

    return implode(' · ', array_slice($partes, 0, 5));
};
?>

<main id="conteudo-principal" tabindex="-1" class="container py-5 flex-grow-1">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require __DIR__ . '/../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-9">
            <h1 class="h3 text-gold mb-1">Auditoria e Logs</h1>
            <p class="text-muted-custom mb-4">
                Registro de quem alterou o quê, eventos de segurança e erros da aplicação.
            </p>

            <!-- Abas -->
            <ul class="nav nav-tabs abas-pagamento mb-4" role="tablist">
                <?php foreach ($abas as $chave => $rotulo): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $aba === $chave ? 'active' : '' ?>"
                           href="<?= $baseUrl ?>/admin/auditoria?aba=<?= $chave ?>&<?= $query ?>">
                            <?= $rotulo ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Filtros (não se aplicam à aba de excluídos) -->
            <?php if ($aba !== 'excluidos'): ?>
                <form method="GET" action="<?= $baseUrl ?>/admin/auditoria" class="style-card p-3 mb-4">
                    <input type="hidden" name="aba" value="<?= htmlspecialchars($aba, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label for="de" class="form-label label-caps text-muted-custom">DE</label>
                            <input type="date" class="campo-form" id="de" name="de"
                                   value="<?= htmlspecialchars($filtros['de'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="ate" class="form-label label-caps text-muted-custom">ATÉ</label>
                            <input type="date" class="campo-form" id="ate" name="ate"
                                   value="<?= htmlspecialchars($filtros['ate'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <?php if ($aba === 'auditoria'): ?>
                            <div class="col-md-2">
                                <label for="usuario" class="form-label label-caps text-muted-custom">AUTOR</label>
                                <select class="campo-form" id="usuario" name="usuario">
                                    <option value="">Todos</option>
                                    <?php foreach ($disponivel['autores'] as $a): ?>
                                        <option value="<?= (int)$a['usuario_id'] ?>"
                                            <?= $filtros['usuario_id'] === (string)$a['usuario_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($a['nome'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="entidade" class="form-label label-caps text-muted-custom">ENTIDADE</label>
                                <select class="campo-form" id="entidade" name="entidade">
                                    <option value="">Todas</option>
                                    <?php foreach ($disponivel['entidades'] as $e): ?>
                                        <option value="<?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $filtros['entidade'] === $e ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="acao" class="form-label label-caps text-muted-custom">AÇÃO</label>
                                <select class="campo-form" id="acao" name="acao">
                                    <option value="">Todas</option>
                                    <?php foreach ($disponivel['acoes'] as $a): ?>
                                        <option value="<?= htmlspecialchars($a, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $filtros['acao'] === $a ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(str_replace('_', ' ', $a), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-outline-gold px-4">Filtrar</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Trilha de auditoria -->
            <?php if ($aba === 'auditoria'): ?>
                <div class="style-card p-4">
                    <?php if (empty($registros)): ?>
                        <p class="text-muted-custom mb-0">Nenhum registro de auditoria no período.</p>
                    <?php else: ?>
                        <div class="tabela-rolavel">
                            <table class="table tabela-dados align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Quando</th>
                                        <th scope="col">Autor</th>
                                        <th scope="col">Ação</th>
                                        <th scope="col">Entidade</th>
                                        <th scope="col">Antes</th>
                                        <th scope="col">Depois</th>
                                        <th scope="col">IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($registros as $r): ?>
                                    <tr>
                                        <td class="dado-numerico"><?= FormatHelper::dateTimeBr($r['created_at']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($r['autor'] ?? 'sistema', ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($r['autor_perfil'])): ?>
                                                <br><small class="text-muted-custom">
                                                    <?= htmlspecialchars(str_replace('_', ' ', $r['autor_perfil']), ENT_QUOTES, 'UTF-8') ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="etiqueta etiqueta-acao-<?= htmlspecialchars($r['acao'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars(str_replace('_', ' ', $r['acao']), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($r['entidade'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($r['entidade_id'] !== null): ?>
                                                <small class="text-muted-custom">#<?= (int)$r['entidade_id'] ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-muted-custom"><?= htmlspecialchars($resumirJson($r['dados_anteriores']), ENT_QUOTES, 'UTF-8') ?></small></td>
                                        <td><small class="text-muted-custom"><?= htmlspecialchars($resumirJson($r['dados_novos']), ENT_QUOTES, 'UTF-8') ?></small></td>
                                        <td class="dado-numerico"><small><?= htmlspecialchars($r['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <!-- Eventos de segurança -->
            <?php elseif ($aba === 'seguranca'): ?>
                <div class="style-card p-4">
                    <p class="text-muted-custom">
                        Tentativas de login, bloqueios, tokens CSRF inválidos e violações de permissão.
                    </p>

                    <?php if (empty($seguranca)): ?>
                        <p class="text-muted-custom mb-0">Nenhum evento de segurança no período.</p>
                    <?php else: ?>
                        <div class="tabela-rolavel">
                            <table class="table tabela-dados align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Quando</th>
                                        <th scope="col">Evento</th>
                                        <th scope="col">Usuário</th>
                                        <th scope="col">IP</th>
                                        <th scope="col">Detalhes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($seguranca as $s): ?>
                                    <?php
                                    $critico = in_array($s['evento'], [
                                        'acesso_negado_rbac', 'csrf_token_invalido',
                                        'login_bloqueado_tentativas', 'webhook_assinatura_invalida',
                                    ], true);
                                    ?>
                                    <tr>
                                        <td class="dado-numerico"><?= FormatHelper::dateTimeBr($s['created_at']) ?></td>
                                        <td>
                                            <span class="<?= $critico ? 'text-erro' : 'text-muted-custom' ?>">
                                                <?= htmlspecialchars(str_replace('_', ' ', $s['evento']), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($s['usuario_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="dado-numerico"><small><?= htmlspecialchars($s['ip_address'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                        <td><small class="text-muted-custom"><?= htmlspecialchars(mb_strimwidth((string)$s['detalhes'], 0, 90, '...'), ENT_QUOTES, 'UTF-8') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <!-- Erros da aplicação -->
            <?php elseif ($aba === 'erros'): ?>
                <div class="style-card p-4">
                    <p class="text-muted-custom">
                        Exceções capturadas pelo tratamento global. Em caso de falha do banco,
                        o registro cai em <code>logs/error.log</code>.
                    </p>

                    <?php if (empty($erros)): ?>
                        <p class="text-sucesso mb-0">Nenhum erro registrado. </p>
                    <?php else: ?>
                        <?php foreach ($erros as $e): ?>
                            <details class="bloco-erro">
                                <summary>
                                    <span class="dado-numerico text-muted-custom"><?= FormatHelper::dateTimeBr($e['created_at']) ?></span>
                                    <span class="text-erro ms-2"><?= htmlspecialchars(mb_strimwidth($e['mensagem'], 0, 110, '...'), ENT_QUOTES, 'UTF-8') ?></span>
                                </summary>
                                <p class="text-muted-custom mt-2 mb-1">
                                    <small><?= htmlspecialchars($e['arquivo'], ENT_QUOTES, 'UTF-8') ?>:<?= (int)$e['linha'] ?></small>
                                </p>
                                <pre class="pilha-erro"><?= htmlspecialchars((string)$e['stack_trace'], ENT_QUOTES, 'UTF-8') ?></pre>
                            </details>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <!-- Registros excluídos -->
            <?php else: ?>
                <div class="style-card p-4">
                    <p class="text-muted-custom">
                        Registros removidos por exclusão lógica. Restaurar devolve o item ao sistema.
                    </p>

                    <?php if (empty($excluidos)): ?>
                        <p class="text-muted-custom mb-0">Nenhum registro excluído.</p>
                    <?php else: ?>
                        <?php foreach ($excluidos as $tabela => $linhas): ?>
                            <h2 class="h6 text-gold mt-4 mb-2"><?= ucfirst($tabela) ?> (<?= count($linhas) ?>)</h2>

                            <div class="tabela-rolavel">
                                <table class="table tabela-dados align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Registro</th>
                                            <th scope="col">Excluído em</th>
                                            <th scope="col" class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($linhas as $linha): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars((string)($linha['rotulo'] ?? '#' . $linha['id']), ENT_QUOTES, 'UTF-8') ?>
                                                <small class="text-muted-custom">#<?= (int)$linha['id'] ?></small>
                                            </td>
                                            <td class="dado-numerico"><?= FormatHelper::dateTimeBr($linha['deleted_at']) ?></td>
                                            <td class="text-end">
                                                <form method="POST" action="<?= $baseUrl ?>/admin/auditoria/restaurar" class="m-0"
                                                      onsubmit="return confirm('Restaurar este registro?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                                    <input type="hidden" name="tabela" value="<?= htmlspecialchars($tabela, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$linha['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-gold btn-sm">Restaurar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
