<?php
// database/migrations/001_create_system_tables.php
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Cria as 20 tabelas do sistema conforme o modelo de dados do docs/FSD.md (seção 11),
 * incluindo chaves primárias, estrangeiras e índices de consulta.
 *
 * As tabelas são criadas na ordem de dependência das chaves estrangeiras.
 */
return [
    'descricao' => '- Estrutura completa das 20 tabelas do sistema',

    'up' => function (PDO $pdo): void {

        $tabelas = [];

        // 1. Usuários (clientes, profissionais e administradores)
        $tabelas['usuarios'] = "
            CREATE TABLE usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                senha_hash VARCHAR(255) NOT NULL,
                telefone VARCHAR(20) NOT NULL,
                foto VARCHAR(255) NULL,
                perfil ENUM('cliente', 'profissional', 'admin_principal', 'admin_limitado') NOT NULL DEFAULT 'cliente',
                status ENUM('ativo', 'bloqueado', 'inativo') NOT NULL DEFAULT 'ativo',
                motivo_bloqueio TEXT NULL,
                token_recuperacao VARCHAR(100) NULL,
                token_expiracao DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                INDEX idx_usuarios_email (email),
                INDEX idx_usuarios_perfil (perfil),
                INDEX idx_usuarios_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 2. Profissionais
        $tabelas['profissionais'] = "
            CREATE TABLE profissionais (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL UNIQUE,
                limite_diario_atendimentos INT NULL DEFAULT NULL,
                status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                CONSTRAINT fk_profissionais_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 3. Serviços
        $tabelas['servicos'] = "
            CREATE TABLE servicos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                descricao TEXT NULL,
                duracao_minutos INT NOT NULL,
                preco DECIMAL(10,2) NOT NULL,
                imagem VARCHAR(255) NULL,
                orientacoes TEXT NULL,
                status ENUM('ativo', 'indisponivel', 'inativo') NOT NULL DEFAULT 'ativo',
                previsao_retorno DATE NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                INDEX idx_servicos_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 4. Pivô Profissional x Serviços (N:N)
        $tabelas['profissional_servicos'] = "
            CREATE TABLE profissional_servicos (
                profissional_id INT NOT NULL,
                servico_id INT NOT NULL,
                PRIMARY KEY (profissional_id, servico_id),
                CONSTRAINT fk_prof_servicos_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
                CONSTRAINT fk_prof_servicos_servico FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 5. Grade horária semanal do profissional
        $tabelas['horarios_trabalho'] = "
            CREATE TABLE horarios_trabalho (
                id INT AUTO_INCREMENT PRIMARY KEY,
                profissional_id INT NOT NULL,
                dia_semana TINYINT NOT NULL COMMENT '0=Dom, 1=Seg, ..., 6=Sab',
                hora_inicio TIME NOT NULL,
                hora_fim TIME NOT NULL,
                almoco_inicio TIME NULL,
                almoco_fim TIME NULL,
                CONSTRAINT fk_horarios_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
                INDEX idx_horarios_prof_dia (profissional_id, dia_semana)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 6. Indisponibilidades e bloqueios de agenda
        $tabelas['indisponibilidades_bloqueios'] = "
            CREATE TABLE indisponibilidades_bloqueios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                profissional_id INT NULL COMMENT 'NULL indica bloqueio geral do estabelecimento',
                data_inicio DATETIME NOT NULL,
                data_fim DATETIME NOT NULL,
                motivo VARCHAR(255) NOT NULL,
                tipo ENUM('folga', 'feriado', 'bloqueio_parcial', 'fechamento') NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_bloqueios_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
                INDEX idx_bloqueios_datas (data_inicio, data_fim),
                INDEX idx_bloqueios_prof (profissional_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 7. Agendamentos
        $tabelas['agendamentos'] = "
            CREATE TABLE agendamentos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo_reserva VARCHAR(20) NOT NULL UNIQUE,
                cliente_id INT NOT NULL,
                profissional_id INT NOT NULL,
                data_agendamento DATE NOT NULL,
                hora_inicio TIME NOT NULL,
                hora_fim TIME NOT NULL,
                duracao_total_minutos INT NOT NULL,
                valor_total DECIMAL(10,2) NOT NULL,
                valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                justificativa_desconto TEXT NULL,
                credito_utilizado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                situacao ENUM('aguardando_pagamento', 'confirmado', 'concluido', 'cancelado', 'falta', 'reembolsado') NOT NULL DEFAULT 'aguardando_pagamento',
                motivo_cancelamento TEXT NULL,
                reagendamentos_realizados TINYINT NOT NULL DEFAULT 0,
                reserva_expira_em DATETIME NULL,
                hora_inicio_real TIME NULL,
                hora_fim_real TIME NULL,
                confirmado_presenca TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                CONSTRAINT fk_agendamentos_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
                CONSTRAINT fk_agendamentos_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id),
                INDEX idx_agendamentos_busca (profissional_id, data_agendamento, situacao),
                INDEX idx_agendamentos_cliente (cliente_id),
                INDEX idx_agendamentos_situacao (situacao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 8. Serviços contratados no agendamento (preço/duração congelados)
        $tabelas['agendamento_servicos'] = "
            CREATE TABLE agendamento_servicos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                agendamento_id INT NOT NULL,
                servico_id INT NOT NULL,
                preco_aplicado DECIMAL(10,2) NOT NULL,
                duracao_aplicada_minutos INT NOT NULL,
                CONSTRAINT fk_agend_servicos_agend FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE,
                CONSTRAINT fk_agend_servicos_servico FOREIGN KEY (servico_id) REFERENCES servicos(id),
                INDEX idx_agend_servicos_agend (agendamento_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 9. Pagamentos
        $tabelas['pagamentos'] = "
            CREATE TABLE pagamentos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                agendamento_id INT NOT NULL,
                forma_pagamento ENUM('pix', 'cartao_credito') NOT NULL,
                valor DECIMAL(10,2) NOT NULL,
                status_pagamento ENUM('pendente', 'aprovado', 'recusado', 'estornado') NOT NULL DEFAULT 'pendente',
                transacao_id VARCHAR(100) NULL,
                qr_code_pix TEXT NULL,
                qr_code_pix_url TEXT NULL,
                pago_em DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_pagamentos_agend FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE,
                INDEX idx_pagamentos_transacao (transacao_id),
                INDEX idx_pagamentos_status (status_pagamento)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 10. Créditos internos (validade de 90 dias)
        $tabelas['creditos_internos'] = "
            CREATE TABLE creditos_internos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                agendamento_origem_id INT NULL,
                valor_original DECIMAL(10,2) NOT NULL,
                saldo_restante DECIMAL(10,2) NOT NULL,
                data_expiracao DATE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_creditos_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
                CONSTRAINT fk_creditos_agend FOREIGN KEY (agendamento_origem_id) REFERENCES agendamentos(id),
                INDEX idx_creditos_cliente (cliente_id, data_expiracao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 11. Avaliações (1 a 5 estrelas)
        $tabelas['avaliacoes'] = "
            CREATE TABLE avaliacoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                agendamento_id INT NOT NULL UNIQUE,
                cliente_id INT NOT NULL,
                profissional_id INT NOT NULL,
                nota TINYINT NOT NULL CHECK (nota BETWEEN 1 AND 5),
                comentario TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_avaliacoes_agend FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id),
                CONSTRAINT fk_avaliacoes_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
                CONSTRAINT fk_avaliacoes_prof FOREIGN KEY (profissional_id) REFERENCES profissionais(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 12. Promoções públicas
        $tabelas['promocoes'] = "
            CREATE TABLE promocoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(100) NOT NULL,
                descricao TEXT NULL,
                imagem VARCHAR(255) NULL,
                servico_id INT NULL,
                tipo_desconto ENUM('fixo', 'percentual') NOT NULL,
                valor_desconto DECIMAL(10,2) NOT NULL,
                data_inicio DATE NOT NULL,
                data_fim DATE NOT NULL,
                limite_total_usos INT NULL,
                usos_realizados INT NOT NULL DEFAULT 0,
                status ENUM('ativa', 'inativa', 'expirada') NOT NULL DEFAULT 'ativa',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_promocoes_servico FOREIGN KEY (servico_id) REFERENCES servicos(id),
                INDEX idx_promocoes_status (status, data_inicio, data_fim)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 13. Uso de promoções
        $tabelas['uso_promocoes'] = "
            CREATE TABLE uso_promocoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                promocao_id INT NOT NULL,
                cliente_id INT NOT NULL,
                agendamento_id INT NOT NULL,
                data_uso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_uso_promo_promocao FOREIGN KEY (promocao_id) REFERENCES promocoes(id),
                CONSTRAINT fk_uso_promo_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
                CONSTRAINT fk_uso_promo_agend FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id),
                UNIQUE KEY unq_promo_cliente (promocao_id, cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 14. Categorias de despesas
        $tabelas['categorias_despesas'] = "
            CREATE TABLE categorias_despesas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(50) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 15. Despesas
        $tabelas['despesas'] = "
            CREATE TABLE despesas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                categoria_id INT NOT NULL,
                descricao VARCHAR(255) NOT NULL,
                valor DECIMAL(10,2) NOT NULL,
                data_despesa DATE NOT NULL,
                comprovante VARCHAR(255) NULL,
                registrado_por INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                CONSTRAINT fk_despesas_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_despesas(id),
                CONSTRAINT fk_despesas_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
                INDEX idx_despesas_data (data_despesa)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 16. Notas internas privadas sobre clientes
        $tabelas['notas_clientes'] = "
            CREATE TABLE notas_clientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                autor_id INT NOT NULL,
                observacao TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_notas_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                CONSTRAINT fk_notas_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id),
                INDEX idx_notas_cliente (cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 17. Configurações globais (chave/valor)
        $tabelas['system_settings'] = "
            CREATE TABLE system_settings (
                chave VARCHAR(50) PRIMARY KEY,
                valor TEXT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 18. Trilha de auditoria
        $tabelas['audit_logs'] = "
            CREATE TABLE audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NULL,
                acao VARCHAR(50) NOT NULL,
                entidade VARCHAR(50) NOT NULL,
                entidade_id INT NULL,
                dados_anteriores JSON NULL,
                dados_novos JSON NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_audit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
                INDEX idx_audit_entidade (entidade, entidade_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 19. Log de erros da aplicação
        $tabelas['error_logs'] = "
            CREATE TABLE error_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mensagem TEXT NOT NULL,
                arquivo VARCHAR(255) NOT NULL,
                linha INT NOT NULL,
                stack_trace TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        // 20. Log de eventos de segurança
        $tabelas['security_logs'] = "
            CREATE TABLE security_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NULL,
                ip_address VARCHAR(45) NOT NULL,
                evento VARCHAR(100) NOT NULL,
                detalhes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_security_ip (ip_address)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        foreach ($tabelas as $nome => $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                throw new RuntimeException("Erro ao criar a tabela `{$nome}`: " . $e->getMessage(), 0, $e);
            }
        }
    },
];
