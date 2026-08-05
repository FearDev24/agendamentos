<?php
/**
 * tests/teste_frontend.php - Validação dos arquivos estáticos e do carregamento
 * de scripts em todas as telas.
 *
 * Uso: php tests/teste_frontend.php
 *
 * Motivo desta bateria: as demais baterias validam o comportamento do servidor
 * (HTML devolvido, redirecionamentos, banco de dados). Elas não percebem quando
 * um arquivo CSS ou JS referenciado está ausente, vazio ou é um placeholder —
 * caso do `bootstrap.bundle.min.js`, que durante seis fases foi um stub de 175
 * bytes e deixava sem efeito todo botão dependente de Bootstrap.
 *
 * Pré-requisitos: MySQL e Apache do XAMPP em execução.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Este script só pode ser executado via linha de comando.');
}

define('APP_EXEC', true);
date_default_timezone_set('America/Sao_Paulo');

$config = require __DIR__ . '/../config/config.php';
$GLOBALS['config'] = $config;

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/../app/bootstrap.php';

$t = new TestRunner();
$baseUrl = $config['app']['url'];
$raizPublica = __DIR__ . '/../public/';

echo "\n======================================================\n";
echo " TESTES DE FRONT-END - Arquivos estáticos e scripts\n";
echo " Alvo HTTP: {$baseUrl}\n";
echo "======================================================\n";

// =====================================================================
$t->grupo('1. Bootstrap: o pacote é real, não um placeholder');

$bootstrapJs = $raizPublica . 'assets/vendor/bootstrap/js/bootstrap.bundle.min.js';
$bootstrapCss = $raizPublica . 'assets/vendor/bootstrap/css/bootstrap.min.css';

$t->verificar('bootstrap.bundle.min.js existe', is_file($bootstrapJs));
$t->verificar('bootstrap.bundle.min.js tem tamanho compatível (> 50 KB)',
    is_file($bootstrapJs) && filesize($bootstrapJs) > 50000,
    is_file($bootstrapJs) ? filesize($bootstrapJs) . ' bytes' : 'ausente');

$conteudoJs = is_file($bootstrapJs) ? file_get_contents($bootstrapJs) : '';

// Componentes efetivamente usados pelas telas do sistema
foreach (['Collapse', 'Tab', 'Modal', 'Dropdown'] as $componente) {
    $t->verificar("Bootstrap JS contém o componente {$componente}",
        strpos($conteudoJs, $componente) !== false);
}

$t->verificar('Bootstrap JS não é um stub de desenvolvimento',
    stripos($conteudoJs, 'stub') === false);
$t->verificar('Bootstrap JS declara a versão no cabeçalho',
    preg_match('/Bootstrap v(\d+\.\d+\.\d+)/', $conteudoJs, $m) === 1,
    $m[1] ?? 'não encontrada');

$t->verificar('bootstrap.min.css tem tamanho compatível (> 100 KB)',
    is_file($bootstrapCss) && filesize($bootstrapCss) > 100000,
    is_file($bootstrapCss) ? filesize($bootstrapCss) . ' bytes' : 'ausente');

$conteudoCss = is_file($bootstrapCss) ? file_get_contents($bootstrapCss) : '';
foreach (['.collapse', '.nav-tabs', '.row', '.col-md-6', '.form-check-input'] as $classe) {
    $t->verificar("Bootstrap CSS define `{$classe}`", strpos($conteudoCss, $classe) !== false);
}

// =====================================================================
$t->grupo('2. Scripts próprios do sistema');

$scriptsProprios = [
    'assets/js/main.js'        => ['aplicarMascaraTelefone', 'validarConfirmacaoDeSenha'],
    'assets/js/agendamento.js' => ['iniciarSomaDeServicos', 'iniciarGradeDeHorarios', 'escaparHtml'],
    'assets/js/checkout.js'    => ['iniciarCronometroCheckout', 'iniciarCopiaPix', 'iniciarVerificacaoDePagamento'],
];

foreach ($scriptsProprios as $caminho => $funcoes) {
    $arquivo = $raizPublica . $caminho;
    $t->verificar("`{$caminho}` existe e não está vazio",
        is_file($arquivo) && filesize($arquivo) > 100);

    $conteudo = is_file($arquivo) ? file_get_contents($arquivo) : '';

    foreach ($funcoes as $funcao) {
        $t->verificar("`{$caminho}` define {$funcao}()",
            strpos($conteudo, 'function ' . $funcao) !== false);
    }

    // Balanceamento de chaves: pega truncamento de arquivo
    $t->igual("`{$caminho}` tem chaves balanceadas",
        substr_count($conteudo, '{'), substr_count($conteudo, '}'));
}

// =====================================================================
$t->grupo('3. Imagens de insígnia e logotipo');

foreach (['assets/img/logojablonskiprincipal.png',
          'assets/img/fidelidade/insigniabronze.png',
          'assets/img/fidelidade/insigniaprata.png',
          'assets/img/fidelidade/insigniaouro.png'] as $imagem) {
    $arquivo = $raizPublica . $imagem;
    $t->verificar("`{$imagem}` existe", is_file($arquivo));

    if (is_file($arquivo)) {
        $assinatura = bin2hex(file_get_contents($arquivo, false, null, 0, 4));
        $t->igual("`{$imagem}` é um PNG válido", '89504e47', $assinatura);
    }
}

// =====================================================================
$t->grupo('4. Versionamento de assets (invalidação de cache)');

$t->verificar('FormatHelper::asset() existe', method_exists('FormatHelper', 'asset'));

$url = FormatHelper::asset('assets/js/main.js');
$t->verificar('URL do asset carrega o parâmetro de versão',
    preg_match('/\?v=\d+$/', $url) === 1, $url);
$t->verificar('Versão corresponde à data de modificação do arquivo',
    strpos($url, (string)filemtime($raizPublica . 'assets/js/main.js')) !== false);

$urlInexistente = FormatHelper::asset('assets/js/nao-existe.js');
$t->verificar('Arquivo inexistente ainda recebe versão (não quebra a página)',
    strpos($urlInexistente, '?v=') !== false);

// Nenhuma View pode referenciar asset sem versionamento
$viewsComReferenciaCrua = [];
foreach (glob(__DIR__ . '/../app/views/**/*.php') as $view) {
    $conteudo = file_get_contents($view);
    if (preg_match('#\$baseUrl \?>/public/assets/(css|js|vendor)#', $conteudo)) {
        $viewsComReferenciaCrua[] = basename($view);
    }
}
$t->verificar('Nenhuma View referencia CSS/JS sem versionamento',
    empty($viewsComReferenciaCrua), implode(', ', $viewsComReferenciaCrua));

// =====================================================================
$t->grupo('5. HTTP: todos os assets referenciados respondem');

$http = new HttpClient($baseUrl);

/**
 * Extrai as URLs de <script src> e <link rel=stylesheet href> de uma página
 * e confirma que cada uma responde 200 com conteúdo não trivial.
 */
$conferirAssetsDaPagina = function (string $rota, string $rotulo) use ($http, $t, $baseUrl) {
    $http->get($rota);

    if ($http->ultimoStatus !== 200) {
        $t->verificar("[{$rotulo}] página responde 200", false, 'HTTP ' . $http->ultimoStatus);
        return;
    }

    preg_match_all('/<script[^>]+src="([^"]+)"/i', $http->ultimoCorpo, $scripts);
    preg_match_all('/<link[^>]+href="([^"]+\.css[^"]*)"/i', $http->ultimoCorpo, $estilos);

    $urls = array_merge($scripts[1], $estilos[1]);
    $t->verificar("[{$rotulo}] declara CSS e JS", !empty($urls), count($urls) . ' arquivo(s)');

    foreach ($urls as $urlAsset) {
        $rotaAsset = str_replace($baseUrl, '', $urlAsset);

        $ch = curl_init($baseUrl . $rotaAsset);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $corpo = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $nome = basename(strtok($rotaAsset, '?'));
        $t->verificar("[{$rotulo}] {$nome} responde 200 com conteúdo",
            $codigo === 200 && strlen((string)$corpo) > 100,
            'HTTP ' . $codigo . ', ' . strlen((string)$corpo) . ' bytes');
    }
};

$conferirAssetsDaPagina('/inicio', 'vitrine');
$conferirAssetsDaPagina('/login', 'login');
$conferirAssetsDaPagina('/cadastro', 'cadastro');
$conferirAssetsDaPagina('/agendar', 'wizard passo 1');

// =====================================================================
$t->grupo('6. Marcações que dependem de Bootstrap JS');

// Cada uma destas telas usa um componente JS do Bootstrap: se o pacote for
// um stub, o botão existe no HTML mas não faz nada ao ser clicado.
$http->get('/login');
$t->verificar('Menu de navegação usa collapse do Bootstrap',
    $http->corpoContem('data-bs-toggle="collapse"'));
$t->verificar('Bootstrap JS é carregado na página',
    $http->corpoContem('bootstrap.bundle.min.js'));

$t->verificar('View do painel do cliente usa collapse no cancelamento',
    strpos(file_get_contents(__DIR__ . '/../app/views/cliente/agendamentos.php'),
           'data-bs-toggle="collapse"') !== false);
$t->verificar('View da agenda do profissional usa collapse na conclusão',
    strpos(file_get_contents(__DIR__ . '/../app/views/profissional/agenda.php'),
           'data-bs-toggle="collapse"') !== false);
$t->verificar('View do checkout usa abas do Bootstrap',
    strpos(file_get_contents(__DIR__ . '/../app/views/public/checkout.php'),
           'data-bs-toggle="tab"') !== false);

// Todo alvo de collapse/tab precisa existir como elemento na mesma View
$viewsComAlvoQuebrado = [];

foreach (glob(__DIR__ . '/../app/views/**/*.php') as $view) {
    $conteudo = file_get_contents($view);
    preg_match_all('/data-bs-target="#([^"]+)"/', $conteudo, $alvos);

    foreach ($alvos[1] as $alvo) {
        // IDs dinâmicos (com PHP) são resolvidos em execução: basta o prefixo
        $prefixo = preg_replace('/<\?=.*$/', '', $alvo);

        if ($prefixo !== '' && strpos($conteudo, 'id="' . $prefixo) === false) {
            $viewsComAlvoQuebrado[] = basename($view) . ' -> #' . $alvo;
        }
    }
}

$t->verificar('Todo data-bs-target aponta para um elemento existente',
    empty($viewsComAlvoQuebrado), implode(' | ', $viewsComAlvoQuebrado));

// =====================================================================
$t->grupo('7. Ausência de dependência de CDN externa');

// Regra do docs/FSD.md seção 3: nenhum RECURSO pode ser carregado de fora.
// Links de navegação para sites externos (compartilhar no WhatsApp, abrir o
// mapa) são permitidos — o navegador não busca nada por conta deles.
$viewsComCdn = [];

foreach (glob(__DIR__ . '/../app/views/**/*.php') as $view) {
    $conteudo = file_get_contents($view);

    // <script src>, <img src>, <iframe src> e <link rel=stylesheet href>
    $carregaExterno = preg_match('#<(script|img|iframe)[^>]+src="https?://(?!localhost)#i', $conteudo)
        || preg_match('#<link[^>]+href="https?://(?!localhost)#i', $conteudo);

    if ($carregaExterno) {
        $viewsComCdn[] = basename($view);
    }
}

$t->verificar('Nenhuma View carrega recurso de domínio externo',
    empty($viewsComCdn), implode(', ', $viewsComCdn));

// Links de saída são aceitáveis, mas precisam de rel="noopener" por segurança:
// sem ele a página aberta ganha acesso a `window.opener`.
$semNoopener = [];

foreach (glob(__DIR__ . '/../app/views/**/*.php') as $view) {
    $conteudo = file_get_contents($view);

    if (preg_match_all('#<a[^>]+target="_blank"[^>]*>#i', $conteudo, $ancoras)) {
        foreach ($ancoras[0] as $ancora) {
            if (stripos($ancora, 'noopener') === false) {
                $semNoopener[] = basename($view);
                break;
            }
        }
    }
}

$t->verificar('Todo link que abre em nova aba usa rel="noopener"',
    empty($semNoopener), implode(', ', $semNoopener));

// =====================================================================
$t->grupo('8. Aplicativo instalável (PWA)');

// --- Manifesto ---
$http->get('/manifest.webmanifest');
$t->igual('Manifesto responde 200', 200, $http->ultimoStatus);

$manifesto = json_decode($http->ultimoCorpo, true);
$t->verificar('Manifesto é JSON válido', is_array($manifesto));

$cabecalhosManifesto = implode(' ', $http->ultimosHeaders);
$t->verificar('Manifesto usa o Content-Type correto',
    stripos($cabecalhosManifesto, 'application/manifest+json') !== false);

$base = rtrim(parse_url($baseUrl, PHP_URL_PATH) ?? '', '/') . '/';

$t->verificar('Manifesto declara nome', !empty($manifesto['name']));
$t->igual('display é standalone (abre sem barra do navegador)', 'standalone', $manifesto['display'] ?? null);
$t->igual('start_url aponta para a raiz da instalação', $base, $manifesto['start_url'] ?? null);
$t->igual('scope cobre toda a aplicação', $base, $manifesto['scope'] ?? null);
$t->verificar('theme_color usa a paleta Midnight Slate',
    ($manifesto['theme_color'] ?? '') === '#0f1416');
$t->verificar('Idioma declarado como pt-BR', ($manifesto['lang'] ?? '') === 'pt-BR');

// --- Ícones ---
$icones = $manifesto['icones'] ?? $manifesto['icons'] ?? [];
$tamanhos = array_column($icones, 'sizes');
$proposito = array_column($icones, 'purpose');

$t->verificar('Manifesto declara ícone 192x192', in_array('192x192', $tamanhos, true));
$t->verificar('Manifesto declara ícone 512x512', in_array('512x512', $tamanhos, true));
$t->verificar('Manifesto declara ícone maskable (recorte do Android)',
    in_array('maskable', $proposito, true));

foreach ($icones as $icone) {
    $rota = str_replace($base, '/', $icone['src']);
    $ch = curl_init($baseUrl . $rota);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $corpo = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $nome = basename($icone['src']);
    $t->verificar("Ícone {$nome} ({$icone['sizes']}) responde 200",
        $codigo === 200 && strlen((string)$corpo) > 500, 'HTTP ' . $codigo);
    $t->verificar("Ícone {$nome} é um PNG válido",
        substr((string)$corpo, 0, 4) === "\x89PNG");
}

// --- Service Worker ---
$ch = curl_init($baseUrl . '/sw.js');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
$conteudoSw = curl_exec($ch);
$codigoSw = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$tipoSw = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

$t->igual('sw.js responde 200', 200, $codigoSw);
$t->verificar('sw.js é servido como JavaScript', stripos((string)$tipoSw, 'javascript') !== false);
$t->verificar('sw.js fica na raiz do projeto (escopo total da aplicação)',
    is_file(__DIR__ . '/../sw.js'));

foreach (['install', 'activate', 'fetch'] as $evento) {
    $t->verificar("Service Worker trata o evento `{$evento}`",
        strpos((string)$conteudoSw, "addEventListener('" . $evento . "'") !== false);
}

$t->verificar('Service Worker calcula o escopo a partir do registro (portável)',
    strpos((string)$conteudoSw, 'self.registration.scope') !== false);
$t->verificar('Service Worker responde offline nas navegações',
    strpos((string)$conteudoSw, 'paginaSemConexao') !== false);

// Regra de segurança: a agenda nunca pode vir de cache
$t->verificar('Service Worker NÃO faz cache de páginas HTML nem de API',
    strpos((string)$conteudoSw, 'ehEstatico') !== false
    && strpos((string)$conteudoSw, "fetch(requisicao).catch") !== false);

// --- Marcações no HTML ---
$http->get('/inicio');
$t->verificar('Página declara o manifesto', $http->corpoContem('rel="manifest"'));
$t->verificar('Página declara theme-color', $http->corpoContem('name="theme-color"'));
$t->verificar('Página declara apple-touch-icon', $http->corpoContem('rel="apple-touch-icon"'));
$t->verificar('Página declara suporte a web app no iOS',
    $http->corpoContem('apple-mobile-web-app-capable'));
$t->verificar('Página declara favicon', $http->corpoContem('favicon-32.png'));
$t->verificar('Página carrega o script de instalação', $http->corpoContem('pwa.js'));

// --- Banner de convite ---
$t->verificar('Banner de instalação existe no HTML', $http->corpoContem('id="convite-instalacao"'));
$t->verificar('Banner começa oculto (só aparece se a instalação for possível)',
    preg_match('/id="convite-instalacao"[^>]*\shidden/', $http->ultimoCorpo) === 1);
$t->verificar('Banner oferece o botão de instalar', $http->corpoContem('id="btn-instalar-app"'));
$t->verificar('Banner oferece dispensar', $http->corpoContem('id="btn-dispensar-instalacao"'));
$t->verificar('Banner traz instruções para iPhone', $http->corpoContem('id="instrucoes-ios"'));
$t->verificar('Instruções de iPhone começam ocultas',
    preg_match('/id="instrucoes-ios"[^>]*\shidden/', $http->ultimoCorpo) === 1);

// --- Script de instalação ---
$pwaJs = file_get_contents($raizPublica . 'assets/js/pwa.js');

foreach (['registrarServiceWorker', 'prepararConviteDeInstalacao', 'mostrarConvite',
          'jaEstaInstalado', 'ehIos'] as $funcao) {
    $t->verificar("pwa.js define {$funcao}()", strpos($pwaJs, 'function ' . $funcao) !== false);
}

$t->verificar('pwa.js escuta beforeinstallprompt', strpos($pwaJs, 'beforeinstallprompt') !== false);
$t->verificar('pwa.js escuta appinstalled', strpos($pwaJs, 'appinstalled') !== false);
$t->verificar('pwa.js respeita a dispensa do usuário', strpos($pwaJs, 'CHAVE_DISPENSA') !== false);
// Procura a chamada (com parêntese), não a palavra: ela aparece no comentário
// que explica justamente por que o rAF foi descartado aqui.
$t->verificar('pwa.js não chama requestAnimationFrame para exibir o banner',
    strpos($pwaJs, 'requestAnimationFrame(') === false);
$t->verificar('pwa.js força reflow antes da transição',
    strpos($pwaJs, 'void banner.offsetHeight') !== false);

// --- CSS do banner ---
$estilo = file_get_contents($raizPublica . 'assets/css/style.css');
$t->verificar('CSS define o estado visível do banner por classe',
    strpos($estilo, '.convite-instalacao.convite-visivel') !== false);
$t->verificar('CSS esconde o banner de quem já instalou',
    strpos($estilo, '(display-mode: standalone)') !== false);
$t->verificar('CSS respeita quem prefere menos animação',
    strpos($estilo, 'prefers-reduced-motion') !== false);
$t->verificar('Banner não aparece na impressão do comprovante',
    strpos($estilo, '.convite-instalacao {') !== false
    && preg_match('/@media print.*?\.convite-instalacao/s', $estilo) === 1);

// =====================================================================
$t->grupo('9. Peso dos arquivos servidos ao cliente');

// Imagens pesadas encarecem o carregamento no celular, justamente onde o PWA
// mais importa. As insígnias são exibidas a 96px e o logotipo a 40px.
$limites = [
    'assets/img/logojablonskiprincipal.png'        => 150,
    'assets/img/fidelidade/insigniabronze.png'     => 150,
    'assets/img/fidelidade/insigniaprata.png'      => 150,
    'assets/img/fidelidade/insigniaouro.png'       => 150,
    'assets/img/fidelidade/insigniadiamante.png'   => 150,
];

foreach ($limites as $arquivo => $limiteKb) {
    $caminho = $raizPublica . $arquivo;
    $tamanhoKb = is_file($caminho) ? filesize($caminho) / 1024 : 0;
    $t->verificar(basename($arquivo) . " abaixo de {$limiteKb} KB",
        $tamanhoKb > 0 && $tamanhoKb < $limiteKb, round($tamanhoKb, 1) . ' KB');
}

$totalImagens = 0;
foreach (glob($raizPublica . 'assets/img/*.png') as $img) { $totalImagens += filesize($img); }
foreach (glob($raizPublica . 'assets/img/fidelidade/*.png') as $img) { $totalImagens += filesize($img); }

$t->verificar('Conjunto de imagens da interface abaixo de 1 MB',
    $totalImagens < 1048576, round($totalImagens / 1024, 1) . ' KB');

// =====================================================================
$t->grupo('10. Nenhuma tela contém erro de PHP');

/**
 * Um erro fatal de PHP é servido com HTTP 200 quando `display_errors` está
 * ligado. Conferir só o código de status deixa passar tela quebrada — foi o
 * que aconteceu na Fase 8, em que a Agenda Global respondia 200 exibindo
 * apenas a mensagem de erro. Esta varredura abre cada tela com o perfil
 * adequado e falha se encontrar qualquer aviso do PHP.
 */
const SENHA_VARREDURA = 'SenhaVarredura@123';
const PREFIXO_VARREDURA = 'varredura_front_';

$pdo = Database::getConnection();

$limparVarredura = function () use ($pdo) {
    $prefixo = PREFIXO_VARREDURA . '%';
    $pdo->prepare("DELETE FROM security_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM audit_logs WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM profissionais WHERE usuario_id IN (SELECT id FROM usuarios WHERE email LIKE ?)")
        ->execute([$prefixo]);
    $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?")->execute([$prefixo]);
};

$limparVarredura();

$usuarios = new UsuarioModel();

$contas = [
    'admin_principal' => PREFIXO_VARREDURA . 'principal@local.test',
    'admin_limitado'  => PREFIXO_VARREDURA . 'limitado@local.test',
    'cliente'         => PREFIXO_VARREDURA . 'cliente@local.test',
];

foreach ($contas as $perfil => $email) {
    $usuarios->criar([
        'nome' => 'Varredura ' . $perfil, 'email' => $email,
        'telefone' => '(11) 90000-0000', 'senha' => SENHA_VARREDURA, 'perfil' => $perfil,
    ]);
}

/** Telas acessíveis a cada perfil */
$telasPorPerfil = [
    'admin_principal' => [
        '/admin/dashboard', '/admin/agenda', '/admin/agenda?visao=semana',
        '/admin/agenda?visao=mes', '/admin/bloqueios', '/admin/servicos',
        '/admin/profissionais', '/admin/financeiro', '/admin/relatorios',
        '/admin/promocoes', '/admin/auditoria', '/perfil',
    ],
    'admin_limitado' => [
        '/admin/dashboard', '/admin/agenda', '/admin/bloqueios', '/admin/promocoes', '/perfil',
    ],
    'cliente' => [
        '/cliente/agendamentos', '/perfil', '/agendar',
    ],
];

foreach ($telasPorPerfil as $perfil => $telas) {
    $cliente = new HttpClient($baseUrl);
    $csrf = $cliente->csrfDe('/login');
    $cliente->post('/login', ['csrf_token' => $csrf, 'email' => $contas[$perfil], 'senha' => SENHA_VARREDURA]);

    foreach ($telas as $tela) {
        $erro = $cliente->conferirPagina($tela);

        $t->verificar("[{$perfil}] {$tela} sem erro de PHP", $erro === null, (string)$erro);
        $t->verificar("[{$perfil}] {$tela} devolve conteúdo",
            $cliente->ultimoStatus === 200 && strlen($cliente->ultimoCorpo) > 1500,
            'HTTP ' . $cliente->ultimoStatus . ', ' . strlen($cliente->ultimoCorpo) . ' bytes');
    }

    $cliente->encerrar();
}

// Telas públicas
$publico = new HttpClient($baseUrl);

foreach (['/', '/login', '/cadastro', '/recuperar-senha', '/agendar', '/rota-inexistente'] as $tela) {
    $erro = $publico->conferirPagina($tela);
    $t->verificar("[público] {$tela} sem erro de PHP", $erro === null, (string)$erro);
}

$publico->encerrar();
$limparVarredura();

// =====================================================================
$http->encerrar();
exit($t->resumo());
