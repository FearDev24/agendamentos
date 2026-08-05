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

$conferirAssetsDaPagina('/', 'vitrine');
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

// Regra do docs/FSD.md seção 3: nada pode vir de fora do repositório
$viewsComCdn = [];

foreach (glob(__DIR__ . '/../app/views/**/*.php') as $view) {
    $conteudo = file_get_contents($view);

    if (preg_match('#(src|href)="https?://(?!localhost)#i', $conteudo, $m)) {
        $viewsComCdn[] = basename($view);
    }
}

$t->verificar('Nenhuma View carrega recurso de domínio externo',
    empty($viewsComCdn), implode(', ', $viewsComCdn));

// =====================================================================
$http->encerrar();
exit($t->resumo());
