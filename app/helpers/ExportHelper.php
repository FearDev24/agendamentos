<?php
// app/helpers/ExportHelper.php - Exportação de relatórios em planilha
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Geração de arquivos de planilha (docs/FSD.md seção 22.2).
 *
 * O CSV é escrito com BOM UTF-8 e separador ponto e vírgula, que é o que o
 * Excel em português espera: sem isso os acentos aparecem corrompidos e todas
 * as colunas caem em uma só.
 *
 * O formato `.xls` é gerado como uma tabela HTML, reconhecida pelo Excel sem
 * exigir a biblioteca PhpSpreadsheet — mantendo o projeto sem dependências
 * externas, conforme a diretriz de bibliotecas do FSD.
 */
class ExportHelper {

    /**
     * Envia um CSV para download e encerra a requisição.
     *
     * @param string $nomeArquivo Sem extensão
     * @param array  $cabecalhos  Rótulos das colunas
     * @param array  $linhas      Lista de arrays na mesma ordem dos cabeçalhos
     */
    public static function csv(string $nomeArquivo, array $cabecalhos, array $linhas): void {
        $nome = self::nomeSeguro($nomeArquivo) . '.csv';

        self::enviarCabecalhos('text/csv; charset=UTF-8', $nome);

        $saida = fopen('php://output', 'w');

        // BOM: faz o Excel reconhecer o arquivo como UTF-8
        fwrite($saida, "\xEF\xBB\xBF");

        fputcsv($saida, $cabecalhos, ';');

        foreach ($linhas as $linha) {
            fputcsv($saida, array_map([self::class, 'normalizarCelula'], $linha), ';');
        }

        fclose($saida);
        exit;
    }

    /**
     * Envia uma planilha .xls (tabela HTML) para download e encerra a requisição.
     */
    public static function xls(string $nomeArquivo, array $cabecalhos, array $linhas, string $titulo = ''): void {
        $nome = self::nomeSeguro($nomeArquivo) . '.xls';

        self::enviarCabecalhos('application/vnd.ms-excel; charset=UTF-8', $nome);

        echo "\xEF\xBB\xBF";
        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head>'
           . '<meta charset="UTF-8"></head><body>';

        if ($titulo !== '') {
            echo '<h3>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h3>';
        }

        echo '<table border="1"><thead><tr>';
        foreach ($cabecalhos as $cabecalho) {
            echo '<th>' . htmlspecialchars((string)$cabecalho, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($linhas as $linha) {
            echo '<tr>';
            foreach ($linha as $celula) {
                // mso-number-format impede o Excel de reinterpretar códigos
                // e telefones como número, o que comeria zeros à esquerda
                echo '<td style="mso-number-format:\'\@\'">'
                   . htmlspecialchars((string)self::normalizarCelula($celula), ENT_QUOTES, 'UTF-8')
                   . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></body></html>';
        exit;
    }

    // -----------------------------------------------------------------

    private static function enviarCabecalhos(string $tipo, string $nomeArquivo): void {
        if (!headers_sent()) {
            header('Content-Type: ' . $tipo);
            header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        }
    }

    /**
     * Impede que o nome do arquivo carregue caminho ou caracteres inválidos.
     */
    private static function nomeSeguro(string $nome): string {
        $nome = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nome);
        return trim((string)$nome, '_') ?: 'relatorio';
    }

    /**
     * Neutraliza fórmulas: uma célula iniciada por =, +, - ou @ é executada
     * pelo Excel ao abrir o arquivo (CSV injection).
     */
    private static function normalizarCelula($valor): string {
        if ($valor === null) {
            return '';
        }

        $texto = (string)$valor;

        if ($texto !== '' && strpos("=+-@\t\r", $texto[0]) !== false) {
            return "'" . $texto;
        }

        return $texto;
    }
}
