<?php
// app/helpers/UploadHelper.php - Processamento seguro de uploads
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

class UploadHelper {
    public static function process(array $file, string $targetFolder, array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'], int $maxSizeBytes = 5242880): ?string {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        if ($file['size'] > $maxSizeBytes) {
            return null;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return null;
        }

        // Validação real de MIME-type via finfo_file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/webp', 'application/pdf'
        ];

        if (!in_array($mimeType, $allowedMimes, true)) {
            return null;
        }

        // A pasta de destino hoje é sempre uma constante do código, mas basta
        // um chamador futuro repassar entrada do usuário para que "../" leve
        // o arquivo para fora de uploads/ — onde o .htaccess que bloqueia a
        // execução de PHP não vale. Só letras, números, hífen e sublinhado.
        $targetFolder = trim($targetFolder, '/');

        if ($targetFolder === '' || preg_match('/^[A-Za-z0-9_-]+$/', $targetFolder) !== 1) {
            return null;
        }

        // Renomeia o arquivo com nome aleatório imprevisível
        $newName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = __DIR__ . '/../../public/uploads/' . $targetFolder . '/' . $newName;

        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $newName;
        }

        return null;
    }
}
