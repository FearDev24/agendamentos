<?php
// app/helpers/FormatHelper.php - Utilitários de formatação de moeda, datas e sanitização
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

class FormatHelper {
    public static function currency(float $val): string {
        return 'R$ ' . number_format($val, 2, ',', '.');
    }

    public static function dateBr(string $dateSql): string {
        $timestamp = strtotime($dateSql);
        return $timestamp ? date('d/m/Y', $timestamp) : '';
    }

    public static function dateTimeBr(string $dateTimeSql): string {
        $timestamp = strtotime($dateTimeSql);
        return $timestamp ? date('d/m/Y H:i', $timestamp) : '';
    }

    public static function escape(?string $string): string {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Mês e ano por extenso, ex: "agosto de 2026".
     *
     * Escrito à mão porque `strftime()` depende do locale do sistema e no
     * Windows não devolve português de forma confiável.
     */
    public static function mesAnoBr(string $data): string {
        $meses = [
            1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
        ];

        $timestamp = strtotime($data);

        if ($timestamp === false) {
            return '';
        }

        return $meses[(int)date('n', $timestamp)] . ' de ' . date('Y', $timestamp);
    }

    /**
     * Nome abreviado do dia da semana, ex: "Seg".
     */
    public static function diaSemanaBr(string $data): string {
        $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $timestamp = strtotime($data);

        return $timestamp === false ? '' : $dias[(int)date('w', $timestamp)];
    }

    /**
     * URL de um arquivo estático com versão baseada na data de modificação.
     *
     * Sem isso o navegador continua servindo CSS e JS antigos do cache depois
     * de uma atualização, e o usuário vê a interface quebrada até limpar o
     * cache manualmente.
     *
     * @param string $caminho Caminho relativo a partir de `public/`,
     *                        ex: 'assets/js/main.js'
     */
    public static function asset(string $caminho): string {
        $baseUrl = $GLOBALS['config']['app']['url'] ?? '';
        $caminho = ltrim($caminho, '/');
        $arquivo = __DIR__ . '/../../public/' . $caminho;

        $versao = is_file($arquivo) ? filemtime($arquivo) : time();

        return $baseUrl . '/public/' . $caminho . '?v=' . $versao;
    }
}
