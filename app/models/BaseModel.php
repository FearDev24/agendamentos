<?php
// app/models/BaseModel.php - Base comum a todos os Models
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Classe base dos Models. Concentra o acesso à conexão PDO para que
 * nenhum Controller ou View toque diretamente no banco de dados.
 *
 * Regra do projeto: 100% das consultas usam Prepared Statements.
 */
abstract class BaseModel {

    protected PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Executa uma consulta preparada e devolve o statement.
     */
    protected function consultar(string $sql, array $parametros = []): PDOStatement {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        return $stmt;
    }

    /**
     * Retorna a primeira linha da consulta ou null.
     */
    protected function buscarUm(string $sql, array $parametros = []): ?array {
        $linha = $this->consultar($sql, $parametros)->fetch();
        return $linha === false ? null : $linha;
    }

    /**
     * Retorna todas as linhas da consulta.
     */
    protected function buscarTodos(string $sql, array $parametros = []): array {
        return $this->consultar($sql, $parametros)->fetchAll();
    }

    /**
     * Retorna o valor da primeira coluna da primeira linha.
     */
    protected function buscarValor(string $sql, array $parametros = []) {
        return $this->consultar($sql, $parametros)->fetchColumn();
    }
}
