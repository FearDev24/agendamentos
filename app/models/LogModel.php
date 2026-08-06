<?php
// app/models/LogModel.php - Gravação de logs de segurança e de erros
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Registra eventos em `security_logs` e `error_logs` (docs/FSD.md seção 19).
 *
 * Se a gravação no banco falhar (ex: MySQL fora do ar), aciona a contingência
 * gravando em `/logs/error.log`. Nenhum dado sensível (senha, token) deve ser
 * passado para estes métodos.
 */
class LogModel {

    /** Eventos de segurança padronizados */
    public const LOGIN_FALHOU        = 'login_falhou';
    public const LOGIN_SUCESSO       = 'login_sucesso';
    public const LOGIN_BLOQUEADO     = 'login_bloqueado_tentativas';
    public const CONTA_BLOQUEADA     = 'login_conta_bloqueada';
    public const CSRF_INVALIDO       = 'csrf_token_invalido';
    public const ACESSO_NEGADO_RBAC  = 'acesso_negado_rbac';
    public const SENHA_REDEFINIDA    = 'senha_redefinida';
    public const RECUPERACAO_PEDIDA  = 'recuperacao_senha_solicitada';
    public const RECUPERACAO_BARRADA = 'recuperacao_senha_bloqueada';
    public const CONTA_EXCLUIDA      = 'conta_excluida';

    /** Máximo de tentativas de login antes do bloqueio temporário */
    public const MAX_TENTATIVAS = 5;
    /** Duração do bloqueio, em minutos */
    public const MINUTOS_BLOQUEIO = 15;

    /** Pedidos de recuperação de senha tolerados por IP a cada hora. */
    public const MAX_RECUPERACOES = 5;
    public const MINUTOS_RECUPERACAO = 60;

    /**
     * Grava um evento em `security_logs`.
     */
    public static function seguranca(string $evento, ?string $detalhes = null, ?int $usuarioId = null): void {
        try {
            $stmt = Database::getConnection()->prepare(
                'INSERT INTO security_logs (usuario_id, ip_address, evento, detalhes)
                 VALUES (:usuario_id, :ip, :evento, :detalhes)'
            );
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':ip'         => self::ip(),
                ':evento'     => $evento,
                ':detalhes'   => $detalhes,
            ]);
        } catch (Throwable $e) {
            self::contingenciaArquivo("[security_logs] {$evento} | {$detalhes} | " . $e->getMessage());
        }
    }

    /**
     * Grava uma exceção em `error_logs`, com contingência em arquivo.
     */
    public static function erro(Throwable $e): void {
        try {
            $stmt = Database::getConnection()->prepare(
                'INSERT INTO error_logs (mensagem, arquivo, linha, stack_trace)
                 VALUES (:mensagem, :arquivo, :linha, :stack)'
            );
            $stmt->execute([
                ':mensagem' => $e->getMessage(),
                ':arquivo'  => $e->getFile(),
                ':linha'    => $e->getLine(),
                ':stack'    => $e->getTraceAsString(),
            ]);
        } catch (Throwable $falhaBanco) {
            self::contingenciaArquivo(
                "[error_logs] {$e->getMessage()} em {$e->getFile()}:{$e->getLine()}"
            );
        }
    }

    /**
     * Conta tentativas de login malsucedidas recentes para um e-mail/IP.
     * Base da regra de bloqueio de 5 tentativas por 15 minutos.
     */
    public static function tentativasRecentes(string $email): int {
        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT COUNT(*) FROM security_logs
                 WHERE evento = :evento
                   AND ip_address = :ip
                   AND detalhes = :detalhes
                   AND created_at > DATE_SUB(NOW(), INTERVAL :minutos MINUTE)'
            );
            $stmt->bindValue(':evento', self::LOGIN_FALHOU);
            $stmt->bindValue(':ip', self::ip());
            $stmt->bindValue(':detalhes', mb_strtolower(trim($email)));
            $stmt->bindValue(':minutos', self::MINUTOS_BLOQUEIO, PDO::PARAM_INT);
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            self::contingenciaArquivo('[security_logs] Falha ao contar tentativas: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Indica se o e-mail/IP está temporariamente bloqueado por excesso de tentativas.
     */
    public static function estaBloqueado(string $email): bool {
        return self::tentativasRecentes($email) >= self::MAX_TENTATIVAS;
    }

    /**
     * Quantas vezes este IP disparou um evento na janela informada.
     *
     * Diferente de tentativasRecentes(), não olha o e-mail: serve para conter
     * abuso em que o alvo muda a cada requisição — como pedir redefinição de
     * senha para uma lista de endereços.
     */
    public static function eventosRecentesDoIp(string $evento, int $minutos): int {
        try {
            $stmt = Database::getConnection()->prepare(
                'SELECT COUNT(*) FROM security_logs
                 WHERE evento = :evento
                   AND ip_address = :ip
                   AND created_at > DATE_SUB(NOW(), INTERVAL :minutos MINUTE)'
            );
            $stmt->bindValue(':evento', $evento);
            $stmt->bindValue(':ip', self::ip());
            $stmt->bindValue(':minutos', $minutos, PDO::PARAM_INT);
            $stmt->execute();

            return (int)$stmt->fetchColumn();

        } catch (Throwable $e) {
            self::contingenciaArquivo('[security_logs] Falha ao contar eventos: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Limpa o histórico de falhas após um login bem-sucedido.
     */
    public static function limparTentativas(string $email): void {
        try {
            $stmt = Database::getConnection()->prepare(
                'DELETE FROM security_logs
                 WHERE evento = :evento AND ip_address = :ip AND detalhes = :detalhes'
            );
            $stmt->execute([
                ':evento'   => self::LOGIN_FALHOU,
                ':ip'       => self::ip(),
                ':detalhes' => mb_strtolower(trim($email)),
            ]);
        } catch (Throwable $e) {
            self::contingenciaArquivo('[security_logs] Falha ao limpar tentativas: ' . $e->getMessage());
        }
    }

    public static function ip(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Contingência: grava a mensagem em /logs/error.log.
     */
    private static function contingenciaArquivo(string $mensagem): void {
        $arquivo = __DIR__ . '/../../logs/error.log';
        $linha = '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL;
        @file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);
    }
}
