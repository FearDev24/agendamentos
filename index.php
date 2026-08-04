<?php
/**
 * AgendaBarber Web - Front Controller & Roteador Principal
 * 
 * Ponto de entrada único da aplicação. Recebe todas as requisições HTTP,
 * inicializa as configurações globais, sessões, helpers e despacha a rota.
 */

// Define constante de segurança para proibir acesso direto a arquivos internos
define('APP_EXEC', true);

// Define o fuso horário padrão do sistema
date_default_timezone_set('America/Sao_Paulo');

// Carrega as configurações principais do sistema
$config = require_once __DIR__ . '/config/config.php';

// Inicia o gerenciador de sessão seguro
require_once __DIR__ . '/app/helpers/SessionHelper.php';
SessionHelper::init();

// Roteamento básico simples para tratamento da URL inicial
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$basePath = parse_url($config['app']['url'], PHP_URL_PATH) ?? '';
$route = str_replace($basePath, '', strtok($requestUri, '?'));
$route = '/' . trim($route, '/');

// Resposta base da infraestrutura (Fase 1)
if ($route === '/' || $route === '/index.php') {
    $pageTitle = $config['app']['name'] . " - Página Inicial";
    require_once __DIR__ . '/app/views/layouts/header.php';
    ?>
    <main class="container py-5 text-center">
        <div class="p-5 mb-4 rounded-3 style-card-hero">
            <div class="container-fluid py-3">
                <img src="<?= $config['app']['url'] ?>/public/assets/img/logojablonskiprincipal.png" alt="AgendaBarber Logo" class="mb-4" style="max-height: 120px;">
                <h1 class="display-5 fw-bold text-gold">Bem-vindo ao <?= htmlspecialchars($config['app']['name']) ?></h1>
                <p class="fs-5 text-muted-custom">Sistema de Agendamento e Gestão Integrada para Barbearias e Estética</p>
                <div class="badge bg-bronze p-2 mb-3">Infraestrutura e Base de Projeto Prontas (Fase 1)</div>
            </div>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/app/views/layouts/footer.php';
    exit;
}

// Em caso de rota não mapeada nesta fase inicial
http_response_code(404);
require_once __DIR__ . '/app/views/layouts/header.php';
echo "<div class='container py-5 text-center'><h2 class='text-gold'>404 - Página Não Encontrada</h2><p class='text-muted-custom'>A rota solicitada está em desenvolvimento nas fases seguintes.</p></div>";
require_once __DIR__ . '/app/views/layouts/footer.php';
