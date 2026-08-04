<?php
// app/models/BloqueioModel.php - Indisponibilidades e bloqueios de agenda
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Bloqueios pontuais de agenda: folgas, feriados, bloqueios parciais e
 * fechamentos do estabelecimento.
 *
 * Um bloqueio com `profissional_id = NULL` vale para todo o estabelecimento.
 * Estes registros são consumidos pelo motor de disponibilidade (Fase 5).
 */
class BloqueioModel extends BaseModel {

    public const TIPOS = [
        'folga'            => 'Folga',
        'feriado'          => 'Feriado',
        'bloqueio_parcial' => 'Bloqueio Parcial',
        'fechamento'       => 'Fechamento do Estabelecimento',
    ];

    /**
     * Listagem com filtro opcional por profissional e por período.
     */
    public function listar(?int $profissionalId = null, string $de = '', string $ate = ''): array {
        $sql = 'SELECT b.*, u.nome AS profissional_nome
                FROM indisponibilidades_bloqueios b
                LEFT JOIN profissionais p ON p.id = b.profissional_id
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                WHERE 1=1';
        $parametros = [];

        if ($profissionalId !== null) {
            $sql .= ' AND b.profissional_id = :prof';
            $parametros[':prof'] = $profissionalId;
        }

        if ($de !== '') {
            $sql .= ' AND b.data_fim >= :de';
            $parametros[':de'] = $de . ' 00:00:00';
        }

        if ($ate !== '') {
            $sql .= ' AND b.data_inicio <= :ate';
            $parametros[':ate'] = $ate . ' 23:59:59';
        }

        $sql .= ' ORDER BY b.data_inicio DESC';

        return $this->buscarTodos($sql, $parametros);
    }

    public function buscarPorId(int $id): ?array {
        return $this->buscarUm(
            'SELECT * FROM indisponibilidades_bloqueios WHERE id = :id',
            [':id' => $id]
        );
    }

    public function criar(array $dados): int {
        $this->consultar(
            'INSERT INTO indisponibilidades_bloqueios (profissional_id, data_inicio, data_fim, motivo, tipo)
             VALUES (:prof, :inicio, :fim, :motivo, :tipo)',
            [
                ':prof'   => $dados['profissional_id'] ?: null,
                ':inicio' => $dados['data_inicio'],
                ':fim'    => $dados['data_fim'],
                ':motivo' => trim($dados['motivo']),
                ':tipo'   => $dados['tipo'],
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function excluir(int $id): bool {
        return $this->consultar(
            'DELETE FROM indisponibilidades_bloqueios WHERE id = :id',
            [':id' => $id]
        )->rowCount() > 0;
    }

    /**
     * Agendamentos ativos que cairiam dentro da janela de bloqueio.
     * Serve para alertar o administrador antes de bloquear a agenda.
     */
    public function agendamentosNoPeriodo(?int $profissionalId, string $inicio, string $fim): array {
        $sql = "SELECT a.codigo_reserva, a.data_agendamento, a.hora_inicio, u.nome AS cliente
                FROM agendamentos a
                INNER JOIN usuarios u ON u.id = a.cliente_id
                WHERE a.deleted_at IS NULL
                  AND a.situacao IN ('aguardando_pagamento', 'confirmado')
                  AND CONCAT(a.data_agendamento, ' ', a.hora_inicio) < :fim
                  AND CONCAT(a.data_agendamento, ' ', a.hora_fim) > :inicio";
        $parametros = [':inicio' => $inicio, ':fim' => $fim];

        if ($profissionalId !== null) {
            $sql .= ' AND a.profissional_id = :prof';
            $parametros[':prof'] = $profissionalId;
        }

        $sql .= ' ORDER BY a.data_agendamento ASC, a.hora_inicio ASC';

        return $this->buscarTodos($sql, $parametros);
    }
}
