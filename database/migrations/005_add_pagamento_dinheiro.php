<?php
// database/migrations/005_add_pagamento_dinheiro.php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Acrescenta a forma de pagamento `dinheiro` à tabela `pagamentos`.
 *
 * Atendimentos criados pelo balcão são quitados presencialmente e não passam
 * pelo gateway. Sem registrar esse recebimento, o valor não entrava no
 * faturamento e o lucro do estabelecimento ficava subestimado.
 */
return [
    'descricao' => '- Forma de pagamento em dinheiro para atendimentos de balcão',

    'up' => function (PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE pagamentos
            MODIFY forma_pagamento ENUM('pix', 'cartao_credito', 'dinheiro') NOT NULL
        ");
    },
];
