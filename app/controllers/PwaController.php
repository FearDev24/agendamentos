<?php
// app/controllers/PwaController.php - Recursos do aplicativo instalável (PWA)
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Serve o manifesto do Progressive Web App.
 *
 * É gerado por PHP, e não como arquivo estático, porque `start_url` e `scope`
 * dependem do diretório onde a aplicação está instalada — que muda entre o
 * XAMPP local (`/sistemaBarbearia/`) e a hospedagem de produção.
 */
class PwaController extends BaseController {

    public function manifesto(): void {
        $settings = new SettingModel();
        $nome = $settings->get('estabelecimento_nome', 'AgendaBarber');

        // Caminho base da instalação, sempre terminado em barra
        $base = rtrim(parse_url($this->baseUrl, PHP_URL_PATH) ?? '', '/') . '/';

        $manifesto = [
            'name'             => $nome,
            'short_name'       => mb_substr($nome, 0, 12),
            'description'      => 'Agende seus atendimentos em poucos toques.',
            'lang'             => 'pt-BR',
            'dir'              => 'ltr',
            'start_url'        => $base,
            'scope'            => $base,
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#0f1416',
            'theme_color'      => '#0f1416',
            'categories'       => ['lifestyle', 'business'],
            'icons'            => [
                [
                    'src'     => $base . 'public/assets/img/pwa/icone-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => $base . 'public/assets/img/pwa/icone-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src'     => $base . 'public/assets/img/pwa/icone-mascaravel-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src'     => $base . 'public/assets/img/pwa/icone-mascaravel-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts'        => [
                [
                    'name'       => 'Agendar horário',
                    'short_name' => 'Agendar',
                    'url'        => $base . 'agendar',
                    'icons'      => [['src' => $base . 'public/assets/img/pwa/icone-192.png', 'sizes' => '192x192']],
                ],
                [
                    'name'       => 'Meus agendamentos',
                    'short_name' => 'Agenda',
                    'url'        => $base . 'cliente/agendamentos',
                    'icons'      => [['src' => $base . 'public/assets/img/pwa/icone-192.png', 'sizes' => '192x192']],
                ],
            ],
        ];

        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($manifesto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
