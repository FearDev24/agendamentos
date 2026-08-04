<?php
// database/MigrationRunner.php - Motor de execução das migrations versionadas
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

require_once __DIR__ . '/../app/helpers/Database.php';

/**
 * Runner nativo de migrations.
 *
 * Controla quais scripts de `database/migrations/` já foram aplicados através
 * da tabela `schema_migrations`, garantindo execução idempotente (rodar duas
 * vezes não reaplica nada) e versionamento do banco de dados.
 *
 * Uso permitido apenas por:
 *  - CLI: `php database/migrate.php`
 *  - Rotina administrativa protegida (Administrador Principal)
 */
class MigrationRunner {

    private PDO $pdo;
    private array $log = [];

    public function __construct() {
        $this->criarBancoSeNecessario();
        $this->pdo = Database::getConnection();
        $this->criarTabelaControle();
    }

    /**
     * Executa todas as migrations pendentes em ordem alfabética de arquivo.
     * Retorna o log detalhado da execução.
     */
    public function run(): array {
        $pendentes = $this->pendentes();

        if (empty($pendentes)) {
            $this->registrar('info', 'Nenhuma migration pendente. Banco de dados já está atualizado.');
            return $this->log;
        }

        foreach ($pendentes as $arquivo) {
            $this->executar($arquivo);
        }

        return $this->log;
    }

    /**
     * Lista os arquivos de migration ainda não aplicados.
     */
    public function pendentes(): array {
        $arquivos = $this->arquivosDisponiveis();
        $aplicadas = $this->aplicadas();

        return array_values(array_diff($arquivos, $aplicadas));
    }

    /**
     * Nomes das migrations já registradas em `schema_migrations`.
     */
    public function aplicadas(): array {
        $stmt = $this->pdo->query('SELECT migration FROM schema_migrations ORDER BY migration ASC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Nomes dos arquivos presentes em `database/migrations/`.
     */
    public function arquivosDisponiveis(): array {
        $caminhos = glob(__DIR__ . '/migrations/*.php') ?: [];
        $arquivos = array_map('basename', $caminhos);
        sort($arquivos, SORT_NATURAL);

        return $arquivos;
    }

    public function getLog(): array {
        return $this->log;
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private function executar(string $arquivo): void {
        $caminho = __DIR__ . '/migrations/' . $arquivo;
        $migration = require $caminho;

        if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up'])) {
            throw new RuntimeException("Migration inválida: {$arquivo} deve retornar um array com a chave 'up' executável.");
        }

        $inicio = microtime(true);

        try {
            ($migration['up'])($this->pdo);
        } catch (Throwable $e) {
            $this->registrar('erro', "Falha em {$arquivo}: " . $e->getMessage());
            throw $e;
        }

        $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $stmt->execute([':migration' => $arquivo]);

        $duracao = round((microtime(true) - $inicio) * 1000);
        $descricao = $migration['descricao'] ?? '';
        $this->registrar('ok', "Aplicada: {$arquivo} ({$duracao}ms) {$descricao}");
    }

    private function criarBancoSeNecessario(): void {
        if (Database::databaseExists()) {
            return;
        }

        $db = $GLOBALS['config']['db'];
        // O nome do banco vem de config/config.php (arquivo protegido, não de entrada do usuário),
        // mas ainda assim é validado para conter apenas caracteres seguros de identificador.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $db['dbname'])) {
            throw new RuntimeException('Nome de banco de dados inválido em config/config.php.');
        }

        Database::getServerConnection()->exec(
            "CREATE DATABASE `{$db['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        Database::reset();

        $this->registrar('ok', "Banco de dados `{$db['dbname']}` criado.");
    }

    private function criarTabelaControle(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function registrar(string $tipo, string $mensagem): void {
        $this->log[] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }
}
