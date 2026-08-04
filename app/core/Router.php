<?php
// app/core/Router.php - Resolução e despacho de rotas
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Roteador do Front Controller.
 *
 * Converte a URL requisitada em um caminho normalizado e despacha para o
 * par [Controller, método] correspondente na tabela de rotas.
 */
class Router {

    /**
     * Extrai o caminho da requisição, descontando o subdiretório de instalação
     * (ex: /sistemaBarbearia) e a query string.
     */
    public static function caminhoAtual(string $appUrl): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove a query string
        $caminho = strtok($uri, '?');
        if ($caminho === false) {
            $caminho = '/';
        }

        // Remove o subdiretório base da instalação
        $base = parse_url($appUrl, PHP_URL_PATH) ?? '';
        $base = rtrim((string)$base, '/');

        if ($base !== '' && strpos($caminho, $base) === 0) {
            $caminho = substr($caminho, strlen($base));
        }

        // Normaliza: sempre inicia com '/' e nunca termina com '/' (exceto a raiz)
        $caminho = '/' . trim($caminho, '/');

        // '/index.php' equivale à raiz
        if ($caminho === '/index.php') {
            $caminho = '/';
        }

        return $caminho;
    }

    /**
     * Localiza e executa o handler da rota. Se o caminho existir apenas em
     * outro método HTTP, responde 405; se não existir, responde 404.
     */
    public static function despachar(array $rotas, string $metodo, string $caminho): void {
        $chave = $metodo . ' ' . $caminho;

        if (isset($rotas[$chave])) {
            [$classe, $acao] = $rotas[$chave];
            (new $classe())->{$acao}();
            return;
        }

        // O caminho existe, mas não neste método HTTP: 405 em vez de 404
        $permitidos = self::metodosPermitidos($rotas, $caminho);

        if (!empty($permitidos)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $permitidos));
            echo 'Método não permitido para esta rota.';
            return;
        }

        (new HomeController())->naoEncontrado();
    }

    private static function metodosPermitidos(array $rotas, string $caminho): array {
        $metodos = [];

        foreach (array_keys($rotas) as $chave) {
            [$metodo, $rota] = explode(' ', $chave, 2);
            if ($rota === $caminho) {
                $metodos[] = $metodo;
            }
        }

        return $metodos;
    }
}
