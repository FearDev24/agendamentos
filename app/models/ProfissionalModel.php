<?php
// app/models/ProfissionalModel.php - Profissionais, serviços executados e jornada
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Profissionais do estabelecimento.
 *
 * Cada profissional é composto por um registro em `usuarios` (perfil
 * `profissional`, usado para login) e um registro em `profissionais`
 * (dados operacionais). O vínculo com serviços é N:N via
 * `profissional_servicos`, e a jornada semanal fica em `horarios_trabalho`.
 */
class ProfissionalModel extends BaseModel {

    public const DIAS_SEMANA = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];

    // -----------------------------------------------------------------
    // Consultas
    // -----------------------------------------------------------------

    public function listar(string $busca = '', string $status = ''): array {
        $sql = 'SELECT p.*, u.nome, u.email, u.telefone, u.foto, u.status AS status_usuario
                FROM profissionais p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                WHERE p.deleted_at IS NULL AND u.deleted_at IS NULL';
        $parametros = [];

        if ($busca !== '') {
            $sql .= ' AND (u.nome LIKE :busca OR u.email LIKE :busca2)';
            $parametros[':busca'] = '%' . $busca . '%';
            $parametros[':busca2'] = '%' . $busca . '%';
        }

        if ($status === 'ativo' || $status === 'inativo') {
            $sql .= ' AND p.status = :status';
            $parametros[':status'] = $status;
        }

        $sql .= ' ORDER BY u.nome ASC';

        return $this->buscarTodos($sql, $parametros);
    }

    public function buscarPorId(int $id): ?array {
        return $this->buscarUm(
            'SELECT p.*, u.nome, u.email, u.telefone, u.foto, u.status AS status_usuario
             FROM profissionais p
             INNER JOIN usuarios u ON u.id = p.usuario_id
             WHERE p.id = :id AND p.deleted_at IS NULL AND u.deleted_at IS NULL',
            [':id' => $id]
        );
    }

    /**
     * Localiza o registro de profissional a partir da conta de login.
     * Usado no painel do profissional (Fase 7).
     */
    public function buscarPorUsuarioId(int $usuarioId): ?array {
        return $this->buscarUm(
            'SELECT * FROM profissionais WHERE usuario_id = :id AND deleted_at IS NULL',
            [':id' => $usuarioId]
        );
    }

    // -----------------------------------------------------------------
    // Escrita
    // -----------------------------------------------------------------

    /**
     * Cria a conta de login e o registro operacional em uma única transação.
     */
    public function criar(array $dados): int {
        $this->db->beginTransaction();

        try {
            $usuarios = new UsuarioModel();
            $usuarioId = $usuarios->criar([
                'nome'     => $dados['nome'],
                'email'    => $dados['email'],
                'telefone' => $dados['telefone'],
                'senha'    => $dados['senha'],
                'foto'     => $dados['foto'] ?? null,
                'perfil'   => 'profissional',
            ]);

            $this->consultar(
                'INSERT INTO profissionais (usuario_id, limite_diario_atendimentos, status)
                 VALUES (:usuario_id, :limite, :status)',
                [
                    ':usuario_id' => $usuarioId,
                    ':limite'     => $dados['limite_diario_atendimentos'] ?: null,
                    ':status'     => $dados['status'] ?? 'ativo',
                ]
            );

            $id = (int)$this->db->lastInsertId();
            $this->db->commit();

            return $id;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function atualizar(int $id, array $dados): bool {
        $profissional = $this->buscarPorId($id);
        if ($profissional === null) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            $this->consultar(
                'UPDATE usuarios SET nome = :nome, email = :email, telefone = :telefone
                 WHERE id = :id AND deleted_at IS NULL',
                [
                    ':nome'     => trim($dados['nome']),
                    ':email'    => mb_strtolower(trim($dados['email'])),
                    ':telefone' => trim($dados['telefone']),
                    ':id'       => (int)$profissional['usuario_id'],
                ]
            );

            if (!empty($dados['foto'])) {
                $this->consultar(
                    'UPDATE usuarios SET foto = :foto WHERE id = :id',
                    [':foto' => $dados['foto'], ':id' => (int)$profissional['usuario_id']]
                );
            }

            // Troca de senha é opcional na edição
            if (!empty($dados['senha'])) {
                (new UsuarioModel())->atualizarSenha((int)$profissional['usuario_id'], $dados['senha']);
            }

            $this->consultar(
                'UPDATE profissionais SET limite_diario_atendimentos = :limite, status = :status
                 WHERE id = :id AND deleted_at IS NULL',
                [
                    ':limite' => $dados['limite_diario_atendimentos'] ?: null,
                    ':status' => $dados['status'],
                    ':id'     => $id,
                ]
            );

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Exclusão lógica do profissional e da respectiva conta de login.
     */
    public function excluirLogicamente(int $id): bool {
        $profissional = $this->buscarPorId($id);
        if ($profissional === null) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            $this->consultar('UPDATE profissionais SET deleted_at = NOW() WHERE id = :id', [':id' => $id]);
            $this->consultar(
                "UPDATE usuarios SET deleted_at = NOW(), status = 'inativo' WHERE id = :id",
                [':id' => (int)$profissional['usuario_id']]
            );

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Indica se o profissional possui agendamentos futuros ativos.
     * Impede a exclusão que deixaria clientes sem atendimento.
     */
    public function possuiAgendamentosFuturosAtivos(int $id): bool {
        return (int)$this->buscarValor(
            "SELECT COUNT(*) FROM agendamentos
             WHERE profissional_id = :id
               AND deleted_at IS NULL
               AND situacao IN ('aguardando_pagamento', 'confirmado')
               AND CONCAT(data_agendamento, ' ', hora_inicio) > NOW()",
            [':id' => $id]
        ) > 0;
    }

    // -----------------------------------------------------------------
    // Vínculo N:N com serviços
    // -----------------------------------------------------------------

    public function servicosVinculados(int $profissionalId): array {
        return $this->buscarTodos(
            'SELECT s.*
             FROM profissional_servicos ps
             INNER JOIN servicos s ON s.id = ps.servico_id
             WHERE ps.profissional_id = :id AND s.deleted_at IS NULL
             ORDER BY s.nome ASC',
            [':id' => $profissionalId]
        );
    }

    public function idsServicosVinculados(int $profissionalId): array {
        return array_map('intval', $this->consultar(
            'SELECT servico_id FROM profissional_servicos WHERE profissional_id = :id',
            [':id' => $profissionalId]
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Substitui a lista de serviços executados pelo profissional.
     */
    public function sincronizarServicos(int $profissionalId, array $servicoIds): void {
        $this->db->beginTransaction();

        try {
            $this->consultar(
                'DELETE FROM profissional_servicos WHERE profissional_id = :id',
                [':id' => $profissionalId]
            );

            $stmt = $this->db->prepare(
                'INSERT INTO profissional_servicos (profissional_id, servico_id) VALUES (:p, :s)'
            );

            foreach (array_unique(array_map('intval', $servicoIds)) as $servicoId) {
                if ($servicoId > 0) {
                    $stmt->execute([':p' => $profissionalId, ':s' => $servicoId]);
                }
            }

            $this->db->commit();

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // -----------------------------------------------------------------
    // Grade horária semanal
    // -----------------------------------------------------------------

    public function horarios(int $profissionalId): array {
        return $this->buscarTodos(
            'SELECT * FROM horarios_trabalho WHERE profissional_id = :id ORDER BY dia_semana ASC, hora_inicio ASC',
            [':id' => $profissionalId]
        );
    }

    /**
     * Grade indexada por dia da semana, pronta para renderizar o formulário.
     */
    public function horariosPorDia(int $profissionalId): array {
        $grade = [];

        foreach ($this->horarios($profissionalId) as $linha) {
            $grade[(int)$linha['dia_semana']] = $linha;
        }

        return $grade;
    }

    /**
     * Substitui integralmente a grade semanal do profissional.
     *
     * $dias no formato:
     *   [0 => ['hora_inicio'=>'09:00','hora_fim'=>'18:00','almoco_inicio'=>'12:00','almoco_fim'=>'13:00'], ...]
     * Dias ausentes do array são tratados como folga.
     */
    public function salvarHorarios(int $profissionalId, array $dias): void {
        $this->db->beginTransaction();

        try {
            $this->consultar(
                'DELETE FROM horarios_trabalho WHERE profissional_id = :id',
                [':id' => $profissionalId]
            );

            $stmt = $this->db->prepare(
                'INSERT INTO horarios_trabalho (profissional_id, dia_semana, hora_inicio, hora_fim, almoco_inicio, almoco_fim)
                 VALUES (:p, :dia, :inicio, :fim, :almoco_inicio, :almoco_fim)'
            );

            foreach ($dias as $dia => $horario) {
                $dia = (int)$dia;

                if (!isset(self::DIAS_SEMANA[$dia])) {
                    continue;
                }

                $stmt->execute([
                    ':p'             => $profissionalId,
                    ':dia'           => $dia,
                    ':inicio'        => $horario['hora_inicio'],
                    ':fim'           => $horario['hora_fim'],
                    ':almoco_inicio' => $horario['almoco_inicio'] ?: null,
                    ':almoco_fim'    => $horario['almoco_fim'] ?: null,
                ]);
            }

            $this->db->commit();

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
