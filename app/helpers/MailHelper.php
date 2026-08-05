<?php
// app/helpers/MailHelper.php - Envio de e-mails transacionais
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Envio de e-mails via SMTP autenticado com PHPMailer (docs/FSD.md seção 23.2).
 *
 * Comportamento por ambiente:
 *  - `development`: nada é enviado. A mensagem é gravada em `logs/emails/`
 *    como arquivo .html, permitindo conferir o conteúdo no navegador sem
 *    depender de servidor de e-mail nem incomodar endereços reais;
 *  - `production`: envio real por SMTP. Falhas são registradas em
 *    `error_logs` e o método devolve false, sem interromper a operação em
 *    curso — um agendamento não deve falhar porque o e-mail não saiu.
 */
class MailHelper {

    /** Corpo padrão aplicado a todas as mensagens */
    private const LARGURA_MAXIMA = 600;

    public static function send(string $to, string $subject, string $bodyHtml): bool {
        $config = $GLOBALS['config'];

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::registrarFalha("Endereço inválido: {$to}");
            return false;
        }

        $html = self::montarLayout($subject, $bodyHtml);

        if (($config['app']['env'] ?? 'production') === 'development') {
            return self::gravarParaConferencia($to, $subject, $html);
        }

        return self::enviarPorSmtp($to, $subject, $html);
    }

    // -----------------------------------------------------------------
    // Envio real
    // -----------------------------------------------------------------

    private static function enviarPorSmtp(string $to, string $subject, string $html): bool {
        $smtp = $GLOBALS['config']['smtp'];

        if (empty($smtp['host']) || empty($smtp['user'])) {
            self::registrarFalha('SMTP não configurado em config/config.php.');
            return false;
        }

        try {
            self::carregarPhpMailer();

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->Port       = (int)$smtp['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['user'];
            $mail->Password   = $smtp['pass'];
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';

            // 'tls' usa STARTTLS na porta 587; 'ssl' usa SMTPS na 465
            if (!empty($smtp['secure'])) {
                $mail->SMTPSecure = $smtp['secure'] === 'ssl'
                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom($smtp['user'], $smtp['from_name'] ?? 'AgendaBarber');
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = self::versaoTexto($html);

            $mail->send();

            return true;

        } catch (Throwable $e) {
            // A falha de e-mail nunca pode derrubar a operação que a disparou
            self::registrarFalha('Falha ao enviar para ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }

    private static function carregarPhpMailer(): void {
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return;
        }

        $base = __DIR__ . '/../../vendor/PHPMailer/';

        foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $arquivo) {
            $caminho = $base . $arquivo;

            if (!is_file($caminho)) {
                throw new RuntimeException("PHPMailer ausente: {$arquivo} não encontrado em vendor/PHPMailer/.");
            }

            require_once $caminho;
        }
    }

    // -----------------------------------------------------------------
    // Ambiente de desenvolvimento
    // -----------------------------------------------------------------

    /**
     * Grava a mensagem em `logs/emails/` para conferência visual.
     */
    private static function gravarParaConferencia(string $to, string $subject, string $html): bool {
        $pasta = __DIR__ . '/../../logs/emails';

        if (!is_dir($pasta) && !@mkdir($pasta, 0755, true) && !is_dir($pasta)) {
            error_log("[MailHelper] Não foi possível criar {$pasta}");
            return false;
        }

        $nome = date('Ymd-His') . '-' . substr(md5($to . $subject . microtime()), 0, 6) . '.html';

        $cabecalho = '<!-- Para: ' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8')
                   . ' | Assunto: ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8')
                   . ' | ' . date('d/m/Y H:i:s') . ' -->' . PHP_EOL;

        $gravou = @file_put_contents($pasta . '/' . $nome, $cabecalho . $html) !== false;

        error_log("[MailHelper] E-mail para {$to} gravado em logs/emails/{$nome} | {$subject}");

        return $gravou;
    }

    // -----------------------------------------------------------------
    // Formatação
    // -----------------------------------------------------------------

    /**
     * Envolve o conteúdo no layout do estabelecimento.
     *
     * Usa tabela e estilos inline porque clientes de e-mail (sobretudo o
     * Outlook) ignoram folhas de estilo externas e boa parte do CSS moderno.
     */
    private static function montarLayout(string $assunto, string $conteudo): string {
        $config = $GLOBALS['config'];
        $nome = htmlspecialchars($config['app']['name'] ?? 'AgendaBarber', ENT_QUOTES, 'UTF-8');
        $url = $config['app']['url'] ?? '';
        $largura = self::LARGURA_MAXIMA;

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$assunto}</title>
</head>
<body style="margin:0;padding:24px 12px;background-color:#0f1416;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"
       style="width:100%;max-width:{$largura}px;background-color:#1b2022;border:1px solid #4e4639;border-radius:4px;">
    <tr>
        <td style="padding:24px;text-align:center;border-bottom:1px solid #4e4639;">
            <span style="color:#e9c176;font-size:20px;font-weight:bold;letter-spacing:0.02em;">{$nome}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:24px;color:#dfe3e6;font-size:15px;line-height:22px;">
            {$conteudo}
        </td>
    </tr>
    <tr>
        <td style="padding:16px 24px;border-top:1px solid #4e4639;color:#d1c5b4;font-size:12px;line-height:18px;text-align:center;">
            <a href="{$url}" style="color:#e9c176;text-decoration:none;">Acessar minha conta</a>
            <br><br>
            Esta é uma mensagem automática. Não responda a este e-mail.
        </td>
    </tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * Versão em texto puro, exigida por clientes que não exibem HTML.
     */
    private static function versaoTexto(string $html): string {
        $texto = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $texto = preg_replace('/<\/(p|div|tr|h[1-6]|li)>/i', "\n", (string)$texto);
        $texto = strip_tags((string)$texto);
        $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
        $texto = preg_replace("/\n{3,}/", "\n\n", (string)$texto);

        return trim(preg_replace('/[ \t]{2,}/', ' ', (string)$texto) ?? '');
    }

    /**
     * Registra a falha sem interromper o fluxo que originou o envio.
     */
    private static function registrarFalha(string $mensagem): void {
        error_log('[MailHelper] ' . $mensagem);

        if (class_exists('LogModel')) {
            LogModel::erro(new RuntimeException('[MailHelper] ' . $mensagem));
        }
    }
}
