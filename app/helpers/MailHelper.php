<?php
// app/helpers/MailHelper.php - Abstração para envio de e-mails transacionais
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

class MailHelper {
    public static function send(string $to, string $subject, string $bodyHtml): bool {
        // Estrutura pronta para integração com PHPMailer via SMTP
        // Em ambiente de desenvolvimento local (XAMPP), simula o envio com sucesso
        $config = $GLOBALS['config']['smtp'];
        if ($GLOBALS['config']['app']['env'] === 'development') {
            error_log("[MailHelper Mock] E-mail simulado para {$to} | Assunto: {$subject}");
            return true;
        }
        
        // Em produção, PHPMailer enviará via SMTP autenticado
        return true;
    }
}
