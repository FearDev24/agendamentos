<?php
// app/helpers/Database.php - Conexão PDO única (Singleton) com o MySQL/MariaDB
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

class Database {
    private static ?PDO $conexao = null;

    /**
     * Retorna a conexão PDO ativa, criando-a na primeira chamada.
     * Todas as consultas do sistema devem usar Prepared Statements sobre esta conexão.
     */
    public static function getConnection(): PDO {
        if (self::$conexao instanceof PDO) {
            return self::$conexao;
        }

        $db = $GLOBALS['config']['db'];
        $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";

        self::$conexao = new PDO($dsn, $db['user'], $db['pass'], self::opcoesPadrao());

        return self::$conexao;
    }

    /**
     * Conexão sem banco selecionado. Usada apenas pelo runner de migrations
     * para criar o schema (`CREATE DATABASE`) na primeira execução.
     */
    public static function getServerConnection(): PDO {
        $db = $GLOBALS['config']['db'];
        $dsn = "mysql:host={$db['host']};charset={$db['charset']}";

        return new PDO($dsn, $db['user'], $db['pass'], self::opcoesPadrao());
    }

    /**
     * Verifica se o banco de dados configurado já existe no servidor.
     */
    public static function databaseExists(): bool {
        $db = $GLOBALS['config']['db'];
        $stmt = self::getServerConnection()->prepare(
            'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :nome'
        );
        $stmt->execute([':nome' => $db['dbname']]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Reinicia a conexão em memória (usado após criar o banco pelo runner).
     */
    public static function reset(): void {
        self::$conexao = null;
    }

    private static function opcoesPadrao(): array {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }
}
