<?php
// tests/HttpClient.php - Cliente HTTP com sessão para testes de integração
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Cliente HTTP mínimo baseado em cURL, com cookie jar próprio.
 *
 * Permite testar o sistema como um navegador real: mantém a sessão PHP entre
 * requisições, o que é indispensável para validar login, RBAC e CSRF de fato.
 */
class HttpClient {

    private string $baseUrl;
    private string $cookieJar;

    public int $ultimoStatus = 0;
    public array $ultimosHeaders = [];
    public string $ultimoCorpo = '';

    public function __construct(string $baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'agb_cookies_');
    }

    public function get(string $rota, bool $seguirRedirecionamento = false): self {
        return $this->requisitar('GET', $rota, [], $seguirRedirecionamento);
    }

    public function post(string $rota, array $dados, bool $seguirRedirecionamento = false): self {
        return $this->requisitar('POST', $rota, $dados, $seguirRedirecionamento);
    }

    /**
     * Abre uma página e extrai o token CSRF do formulário, simulando o que
     * um navegador faria antes de enviar o POST.
     */
    public function csrfDe(string $rota): string {
        $this->get($rota);

        if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $this->ultimoCorpo, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Valor do cabeçalho Location da última resposta (sem a base URL).
     */
    public function destinoRedirecionamento(): ?string {
        foreach ($this->ultimosHeaders as $linha) {
            if (stripos($linha, 'Location:') === 0) {
                $destino = trim(substr($linha, 9));
                return str_replace($this->baseUrl, '', $destino);
            }
        }

        return null;
    }

    public function corpoContem(string $trecho): bool {
        return strpos($this->ultimoCorpo, $trecho) !== false;
    }

    /**
     * Descarta os cookies, equivalente a abrir uma janela anônima.
     */
    public function novaSessao(): void {
        @unlink($this->cookieJar);
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'agb_cookies_');
    }

    public function encerrar(): void {
        @unlink($this->cookieJar);
    }

    // -----------------------------------------------------------------

    private function requisitar(string $metodo, string $rota, array $dados, bool $seguir): self {
        $ch = curl_init($this->baseUrl . $rota);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => $seguir,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dados));
        }

        $resposta = curl_exec($ch);

        if ($resposta === false) {
            throw new RuntimeException('Falha na requisição HTTP: ' . curl_error($ch));
        }

        $tamanhoHeader = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->ultimoStatus  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->ultimosHeaders = array_filter(explode("\r\n", substr($resposta, 0, $tamanhoHeader)));
        $this->ultimoCorpo   = substr($resposta, $tamanhoHeader);

        curl_close($ch);

        return $this;
    }
}
