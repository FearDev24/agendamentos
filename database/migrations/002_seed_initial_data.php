<?php
// database/migrations/002_seed_initial_data.php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Popula o banco com os dados iniciais mínimos para operação:
 *  - Usuário Administrador Principal padrão;
 *  - Categorias de despesas do módulo financeiro;
 *  - Serviços base do catálogo;
 *  - Configurações globais (`system_settings`) com os padrões do docs/FSD.md (seção 20).
 *
 * IMPORTANTE: a senha do administrador padrão deve ser trocada no primeiro acesso.
 */
return [
    'descricao' => '- Administrador padrão, categorias, serviços base e configurações globais',

    'up' => function (PDO $pdo): void {

        $pdo->beginTransaction();

        try {
            // -----------------------------------------------------------------
            // 1. Administrador Principal padrão
            // -----------------------------------------------------------------
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nome, email, senha_hash, telefone, perfil, status)
                VALUES (:nome, :email, :senha_hash, :telefone, 'admin_principal', 'ativo')
            ");
            $stmt->execute([
                ':nome'       => 'Administrador Principal',
                ':email'      => 'admin@agendabarber.local',
                ':senha_hash' => password_hash('Admin@123', PASSWORD_BCRYPT),
                ':telefone'   => '(00) 00000-0000',
            ]);

            // -----------------------------------------------------------------
            // 2. Categorias de despesas
            // -----------------------------------------------------------------
            $categorias = [
                'Aluguel',
                'Produtos e Insumos',
                'Energia Elétrica',
                'Água',
                'Internet e Telefonia',
                'Manutenção e Reparos',
                'Marketing e Publicidade',
                'Impostos e Taxas',
                'Salários e Encargos',
                'Outros',
            ];

            $stmt = $pdo->prepare('INSERT INTO categorias_despesas (nome) VALUES (:nome)');
            foreach ($categorias as $nome) {
                $stmt->execute([':nome' => $nome]);
            }

            // -----------------------------------------------------------------
            // 3. Serviços base do catálogo
            // -----------------------------------------------------------------
            $servicos = [
                ['Corte Masculino',      'Corte de cabelo masculino com máquina e tesoura, inclui finalização.', 30,  50.00, 'Venha com o cabelo lavado, se possível.'],
                ['Barba',                'Modelagem e aparo de barba com navalha e toalha quente.',             30,  40.00, 'Evite fazer a barba em casa nas 24h anteriores.'],
                ['Corte + Barba',        'Combo completo de corte masculino e barba.',                          60,  85.00, 'Reserve cerca de 1 hora para o atendimento.'],
                ['Sobrancelha',          'Design e limpeza de sobrancelha masculina.',                          15,  20.00, null],
                ['Pigmentação de Barba', 'Aplicação de pigmento para preencher falhas na barba.',               45,  70.00, 'Não aplicar produtos na barba no dia do atendimento.'],
                ['Hidratação Capilar',   'Tratamento de hidratação profunda para os fios.',                     40,  60.00, null],
            ];

            $stmt = $pdo->prepare("
                INSERT INTO servicos (nome, descricao, duracao_minutos, preco, orientacoes, status)
                VALUES (:nome, :descricao, :duracao, :preco, :orientacoes, 'ativo')
            ");
            foreach ($servicos as [$nome, $descricao, $duracao, $preco, $orientacoes]) {
                $stmt->execute([
                    ':nome'        => $nome,
                    ':descricao'   => $descricao,
                    ':duracao'     => $duracao,
                    ':preco'       => $preco,
                    ':orientacoes' => $orientacoes,
                ]);
            }

            // -----------------------------------------------------------------
            // 4. Configurações globais (system_settings)
            // -----------------------------------------------------------------
            $settings = [
                'estabelecimento_nome'        => 'AgendaBarber',
                'estabelecimento_telefone'    => '(00) 00000-0000',
                'estabelecimento_email'       => 'contato@agendabarber.local',
                'estabelecimento_endereco'    => 'Rua Exemplo, 123 - Centro',
                'estabelecimento_mapa_url'    => '',
                'horario_funcionamento'       => 'Segunda a Sábado, das 09:00 às 19:00',
                'redes_instagram'             => '',
                'redes_facebook'              => '',
                'agendamento_antecedencia_min_horas' => '2',
                'agendamento_antecedencia_max_dias'  => '30',
                'agendamento_intervalo_minutos'      => '10',
                'agendamento_max_ativos_por_cliente' => '4',
                'checkout_reserva_minutos'    => '10',
                'tolerancia_atraso_minutos'   => '10',
                'credito_validade_dias'       => '90',
                'cancelamento_janela_horas'   => '24',
                'reagendamento_limite'        => '2',
            ];

            $stmt = $pdo->prepare('INSERT INTO system_settings (chave, valor) VALUES (:chave, :valor)');
            foreach ($settings as $chave => $valor) {
                $stmt->execute([':chave' => $chave, ':valor' => $valor]);
            }

            $pdo->commit();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    },
];
