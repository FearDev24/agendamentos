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
        self::alinharFusoHorario(self::$conexao);

        return self::$conexao;
    }

    /**
     * Faz o MySQL usar o mesmo fuso do PHP.
     *
     * Metade das datas do sistema nasce no banco (`NOW()`, `DATE_ADD`) e a
     * outra metade no PHP (`time()`, `date()`), e as duas são comparadas entre
     * si — a expiração da reserva, a janela dos lembretes, o corte de "hoje".
     * No XAMPP isso passa despercebido porque banco e aplicação dividem a mesma
     * máquina; numa hospedagem o MySQL costuma ficar em outro servidor, com
     * outro fuso, e as comparações passam a mentir.
     *
     * O deslocamento é enviado em número (-03:00) e não pelo nome da zona:
     * nomes exigem as tabelas de fuso carregadas no MySQL, o que raramente
     * acontece em hospedagem compartilhada.
     */
    private static function alinharFusoHorario(PDO $conexao): void {
        $agora = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
        $deslocamento = $agora->format('P'); // ex.: -03:00

        try {
            $conexao->exec("SET time_zone = '{$deslocamento}'");
        } catch (Throwable $e) {
            // Servidor que recusa a mudança: seguimos com o fuso dele. Melhor
            // uma diferença de horário do que a aplicação inteira fora do ar.
        }
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
