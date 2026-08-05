<?php
// app/models/IntegracaoModel.php - Credenciais de serviços externos
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Credenciais das integrações, cadastradas pelo próprio estabelecimento.
 *
 * O `docs/FSD.md` seção 20.2 previa essas chaves apenas em `config/config.php`.
 * Elas passaram a ser configuráveis pelo painel para que o dono do
 * estabelecimento vincule a própria conta sem depender de acesso ao servidor.
 *
 * Compensações de segurança adotadas:
 *  - Os segredos são gravados cifrados (AES-256-GCM), com a chave em
 *    `config/config.php` — fora do banco e fora do controle de versão;
 *  - A tela nunca devolve o segredo completo, apenas os últimos caracteres;
 *  - Só o Administrador Principal acessa a configuração;
 *  - Toda alteração é registrada em `audit_logs`, sem o valor do segredo.
 *
 * O `config.php` continua valendo como contingência: se nada estiver
 * cadastrado no painel, o sistema usa o que estiver no arquivo.
 */
class IntegracaoModel extends BaseModel {

    /** Chaves em `system_settings` que guardam valor cifrado */
    public const CHAVES_SECRETAS = [
        'mp_access_token',
        'mp_webhook_secret',
    ];

    /** Chaves que podem ser lidas em texto puro */
    public const CHAVES_PUBLICAS = [
        'mp_public_key',
        'mp_sandbox',
        'mp_configurado_em',
    ];

    // -----------------------------------------------------------------
    // Leitura
    // -----------------------------------------------------------------

    /**
     * Credenciais do Mercado Pago em uso, já decifradas.
     *
     * Retorna o que está no painel; se não houver nada cadastrado, cai para
     * o `config/config.php`.
     *
     * @return array ['access_token', 'public_key', 'webhook_secret', 'sandbox_mode', 'origem']
     */
    public function mercadoPago(): array {
        $doPainel = $this->lerTodas();
        $doArquivo = $GLOBALS['config']['mercadopago'] ?? [];

        $token = $doPainel['mp_access_token'] ?? '';
        $chavePublica = $doPainel['mp_public_key'] ?? '';

        // O painel só assume quando há de fato um token cadastrado
        if ($token !== '') {
            return [
                'access_token'   => $token,
                'public_key'     => $chavePublica,
                'webhook_secret' => $doPainel['mp_webhook_secret'] ?? '',
                'sandbox_mode'   => ($doPainel['mp_sandbox'] ?? '1') === '1',
                'origem'         => 'painel',
            ];
        }

        return [
            'access_token'   => $doArquivo['access_token'] ?? '',
            'public_key'     => $doArquivo['public_key'] ?? '',
            'webhook_secret' => $doArquivo['webhook_secret'] ?? '',
            'sandbox_mode'   => !empty($doArquivo['sandbox_mode']),
            'origem'         => 'arquivo',
        ];
    }

    /**
     * Dados para exibição na tela: segredos aparecem mascarados.
     */
    public function paraExibicao(): array {
        $atual = $this->mercadoPago();

        return [
            'public_key'            => $atual['public_key'],
            'access_token_mascara'  => CriptografiaHelper::mascarar($atual['access_token']),
            'webhook_secret_mascara'=> CriptografiaHelper::mascarar($atual['webhook_secret']),
            'tem_access_token'      => $atual['access_token'] !== '',
            'tem_webhook_secret'    => $atual['webhook_secret'] !== '',
            'sandbox_mode'          => $atual['sandbox_mode'],
            'origem'                => $atual['origem'],
            'configurado_em'        => $this->lerTodas()['mp_configurado_em'] ?? null,
            'ambiente_detectado'    => $this->detectarAmbiente($atual['access_token']),
        ];
    }

    /**
     * Identifica se a credencial é de teste ou de produção pelo prefixo que o
     * próprio Mercado Pago aplica.
     */
    public function detectarAmbiente(string $token): string {
        if ($token === '') {
            return 'nenhum';
        }

        if (strpos($token, 'TEST-') === 0) {
            return 'teste';
        }

        if (strpos($token, 'APP_USR-') === 0) {
            return 'producao';
        }

        return 'desconhecido';
    }

    // -----------------------------------------------------------------
    // Escrita
    // -----------------------------------------------------------------

    /**
     * Grava as credenciais do Mercado Pago.
     *
     * Campos de segredo em branco preservam o valor já cadastrado — a tela
     * não devolve o segredo completo, então enviar vazio significa
     * "manter o que está lá", nunca "apagar".
     */
    public function salvarMercadoPago(array $dados): void {
        $atual = $this->lerTodas();

        $token = trim($dados['access_token'] ?? '');
        $segredo = trim($dados['webhook_secret'] ?? '');

        $valores = [
            'mp_public_key'      => trim($dados['public_key'] ?? ''),
            'mp_sandbox'         => !empty($dados['sandbox_mode']) ? '1' : '0',
            'mp_configurado_em'  => date('Y-m-d H:i:s'),
        ];

        if ($token !== '') {
            $valores['mp_access_token'] = CriptografiaHelper::cifrar($token);
        } elseif (isset($atual['mp_access_token'])) {
            $valores['mp_access_token'] = CriptografiaHelper::cifrar($atual['mp_access_token']);
        }

        if ($segredo !== '') {
            $valores['mp_webhook_secret'] = CriptografiaHelper::cifrar($segredo);
        } elseif (isset($atual['mp_webhook_secret'])) {
            $valores['mp_webhook_secret'] = CriptografiaHelper::cifrar($atual['mp_webhook_secret']);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO system_settings (chave, valor) VALUES (:chave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );

        foreach ($valores as $chave => $valor) {
            $stmt->execute([':chave' => $chave, ':valor' => $valor]);
        }

        SettingModel::limparCache();
    }

    /**
     * Remove o vínculo, fazendo o sistema voltar ao `config/config.php`.
     */
    public function desvincularMercadoPago(): void {
        $chaves = array_merge(self::CHAVES_SECRETAS, self::CHAVES_PUBLICAS);
        $marcadores = implode(',', array_fill(0, count($chaves), '?'));

        $this->consultar(
            "DELETE FROM system_settings WHERE chave IN ({$marcadores})",
            $chaves
        );

        SettingModel::limparCache();
    }

    // -----------------------------------------------------------------

    /**
     * Lê as chaves de integração, decifrando o que for segredo.
     */
    private function lerTodas(): array {
        $chaves = array_merge(self::CHAVES_SECRETAS, self::CHAVES_PUBLICAS);
        $marcadores = implode(',', array_fill(0, count($chaves), '?'));

        $linhas = $this->consultar(
            "SELECT chave, valor FROM system_settings WHERE chave IN ({$marcadores})",
            $chaves
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $resultado = [];

        foreach ($linhas as $chave => $valor) {
            $resultado[$chave] = in_array($chave, self::CHAVES_SECRETAS, true)
                ? CriptografiaHelper::decifrar((string)$valor)
                : (string)$valor;
        }

        return $resultado;
    }
}
