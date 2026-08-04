<?php
// tests/TestRunner.php - Micro-framework de testes para validação das fases
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Executor mínimo de asserções para os testes de validação de cada fase.
 * Não substitui PHPUnit: serve para checagens funcionais rápidas via CLI.
 */
class TestRunner {

    private array $resultados = [];
    private string $grupoAtual = '';

    public function grupo(string $nome): void {
        $this->grupoAtual = $nome;
        echo "\n" . str_repeat('-', 62) . "\n {$nome}\n" . str_repeat('-', 62) . "\n";
    }

    /**
     * Registra o resultado de uma asserção booleana.
     */
    public function verificar(string $descricao, bool $condicao, string $detalhe = ''): bool {
        $this->resultados[] = ['grupo' => $this->grupoAtual, 'descricao' => $descricao, 'ok' => $condicao];

        $marca = $condicao ? '  [PASSOU]' : '  [FALHOU]';
        echo "{$marca} {$descricao}";
        if ($detalhe !== '') {
            echo "  -> {$detalhe}";
        }
        echo "\n";

        return $condicao;
    }

    public function igual(string $descricao, $esperado, $obtido): bool {
        $ok = ($esperado == $obtido);
        return $this->verificar(
            $descricao,
            $ok,
            $ok ? (string)$obtido : "esperado: " . var_export($esperado, true) . " | obtido: " . var_export($obtido, true)
        );
    }

    /**
     * Verifica que a closure informada lança uma exceção (teste de restrição).
     */
    public function lancaExcecao(string $descricao, callable $acao): bool {
        try {
            $acao();
            return $this->verificar($descricao, false, 'nenhuma exceção foi lançada');
        } catch (Throwable $e) {
            return $this->verificar($descricao, true, 'bloqueado pelo banco');
        }
    }

    public function resumo(): int {
        $total = count($this->resultados);
        $passou = count(array_filter($this->resultados, fn($r) => $r['ok']));
        $falhou = $total - $passou;

        echo "\n" . str_repeat('=', 62) . "\n";
        echo " RESULTADO: {$passou}/{$total} testes passaram";
        echo $falhou > 0 ? " | {$falhou} FALHA(S)\n" : " | Nenhuma falha\n";
        echo str_repeat('=', 62) . "\n";

        if ($falhou > 0) {
            echo "\nTestes que falharam:\n";
            foreach ($this->resultados as $r) {
                if (!$r['ok']) {
                    echo "  - [{$r['grupo']}] {$r['descricao']}\n";
                }
            }
            echo "\n";
        }

        return $falhou === 0 ? 0 : 1;
    }
}
