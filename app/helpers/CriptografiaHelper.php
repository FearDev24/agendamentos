<?php
// app/helpers/CriptografiaHelper.php - Cifragem de dados sensíveis em repouso
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Cifra credenciais de integração antes de gravá-las no banco.
 *
 * Usa AES-256-GCM, que além de cifrar autentica o conteúdo: qualquer
 * alteração no texto cifrado é detectada na decifragem, e não devolve dado
 * corrompido silenciosamente.
 *
 * A chave vive em `config/config.php` (`app.chave_cripto`), que fica fora do
 * controle de versão. Isso significa que um vazamento do banco de dados,
 * sozinho, não expõe nenhum token — o atacante precisaria também do arquivo
 * de configuração do servidor.
 */
class CriptografiaHelper {

    private const CIFRA = 'aes-256-gcm';
    private const PREFIXO = 'enc:v1:';

    /**
     * Cifra um texto. Retorna string vazia se a entrada for vazia.
     *
     * @throws RuntimeException se a chave não estiver configurada
     */
    public static function cifrar(string $texto): string {
        if ($texto === '') {
            return '';
        }

        $chave = self::chave();
        $iv = random_bytes(12); // GCM recomenda 96 bits
        $tag = '';

        $cifrado = openssl_encrypt($texto, self::CIFRA, $chave, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cifrado === false) {
            throw new RuntimeException('Falha ao cifrar o dado.');
        }

        return self::PREFIXO . base64_encode($iv . $tag . $cifrado);
    }

    /**
     * Decifra um texto produzido por `cifrar()`.
     *
     * Valores sem o prefixo são devolvidos como estão: isso permite migrar de
     * credenciais gravadas em texto puro sem quebrar o sistema.
     *
     * @return string Texto original, ou string vazia se a decifragem falhar
     */
    public static function decifrar(string $armazenado): string {
        if ($armazenado === '') {
            return '';
        }

        if (strpos($armazenado, self::PREFIXO) !== 0) {
            return $armazenado;
        }

        $dados = base64_decode(substr($armazenado, strlen(self::PREFIXO)), true);

        if ($dados === false || strlen($dados) < 29) {
            return '';
        }

        $iv = substr($dados, 0, 12);
        $tag = substr($dados, 12, 16);
        $cifrado = substr($dados, 28);

        $texto = openssl_decrypt($cifrado, self::CIFRA, self::chave(), OPENSSL_RAW_DATA, $iv, $tag);

        // false indica adulteração do conteúdo ou chave trocada
        return $texto === false ? '' : $texto;
    }

    /**
     * Indica se o valor já está cifrado.
     */
    public static function estaCifrado(string $valor): bool {
        return strpos($valor, self::PREFIXO) === 0;
    }

    /**
     * Exibe apenas o final de um segredo, para conferência na tela.
     * Ex.: "APP_USR-1234...cdef" vira "••••••cdef".
     */
    public static function mascarar(string $segredo, int $visiveis = 4): string {
        if ($segredo === '') {
            return '';
        }

        if (mb_strlen($segredo) <= $visiveis) {
            return str_repeat('•', 6);
        }

        return str_repeat('•', 6) . mb_substr($segredo, -$visiveis);
    }

    /**
     * Gera uma chave nova, para o administrador colar em config/config.php.
     */
    public static function gerarChave(): string {
        return base64_encode(random_bytes(32));
    }

    public static function chaveConfigurada(): bool {
        $chave = $GLOBALS['config']['app']['chave_cripto'] ?? '';

        return is_string($chave) && strlen($chave) >= 32;
    }

    // -----------------------------------------------------------------

    /**
     * Chave binária de 32 bytes derivada do valor em config/config.php.
     */
    private static function chave(): string {
        $configurada = $GLOBALS['config']['app']['chave_cripto'] ?? '';

        if (!is_string($configurada) || strlen($configurada) < 32) {
            throw new RuntimeException(
                'Chave de criptografia ausente. Defina "chave_cripto" em config/config.php '
                . 'com um valor gerado por CriptografiaHelper::gerarChave().'
            );
        }

        // hash com raw output garante exatamente os 32 bytes que o AES-256 exige
        return hash('sha256', $configurada, true);
    }
}
