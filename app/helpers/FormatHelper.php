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
}
