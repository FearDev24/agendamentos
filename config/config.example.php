<?php
/**
 * config/config.example.php - Modelo de configuração técnica
 *
 * Copie este arquivo para `config/config.php` e preencha com as credenciais
 * reais do ambiente. O `config.php` NÃO é versionado (ver .gitignore) para
 * que senhas e chaves de API nunca entrem no histórico do repositório.
 */
if (!defined('APP_EXEC')) {
    http_response_code(403);
    die('Acesso direto negado.');
}

return [
    'app' => [
        'name' => 'AgendaBarber Web',
        // Local (XAMPP): http://localhost/sistemaBarbearia
        // Produção (Hostnet): https://seudominio.com.br
        'url' => 'http://localhost/sistemaBarbearia',
        'env' => 'development', // 'development' ou 'production'
        'timezone' => 'America/Sao_Paulo'
    ],
    'db' => [
        'host' => 'localhost',
        'dbname' => 'agendabarber_db',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    'smtp' => [
        'host' => 'smtp.exemplo.com.br',
        'port' => 587,
        'user' => 'notificacoes@exemplo.com.br',
        'pass' => 'SUA_SENHA_SMTP_AQUI',
        'secure' => 'tls',
        'from_name' => 'AgendaBarber'
    ],
    'mercadopago' => [
        'access_token' => 'SEU_ACCESS_TOKEN_AQUI',
        'public_key' => 'SUA_PUBLIC_KEY_AQUI',
        // Segredo da assinatura do webhook (painel do Mercado Pago > Webhooks).
        // Obrigatório em produção: sem ele o webhook recusa toda notificação.
        'webhook_secret' => 'SEU_WEBHOOK_SECRET_AQUI',
        'sandbox_mode' => true // true para testes locais simulados no XAMPP
    ]
];
