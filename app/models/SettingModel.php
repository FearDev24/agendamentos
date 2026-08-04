<?php
// app/models/SettingModel.php - Configurações globais em banco (system_settings)
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Leitura e escrita das configurações operacionais (docs/FSD.md seção 20).
 *
 * Os valores são carregados uma única vez por requisição e mantidos em memória,
 * evitando consultas repetidas durante o cálculo de disponibilidade.
 */
class SettingModel extends BaseModel {

    private static ?array $cache = null;

    /** Valores usados caso a chave ainda não exista no banco */
    private const PADROES = [
        'agendamento_antecedencia_min_horas' => '2',
        'agendamento_antecedencia_max_dias'  => '30',
        'agendamento_intervalo_minutos'      => '10',
        'agendamento_passo_minutos'          => '15',
        'agendamento_max_ativos_por_cliente' => '4',
        'checkout_reserva_minutos'           => '10',
        'tolerancia_atraso_minutos'          => '10',
        'credito_validade_dias'              => '90',
        'cancelamento_janela_horas'          => '24',
        'reagendamento_limite'               => '2',
    ];

    public function todos(): array {
        if (self::$cache === null) {
            $linhas = $this->consultar('SELECT chave, valor FROM system_settings')
                           ->fetchAll(PDO::FETCH_KEY_PAIR);
            self::$cache = array_merge(self::PADROES, $linhas ?: []);
        }

        return self::$cache;
    }

    public function get(string $chave, ?string $padrao = null): ?string {
        $todos = $this->todos();
        return $todos[$chave] ?? $padrao ?? self::PADROES[$chave] ?? null;
    }

    public function getInt(string $chave): int {
        return (int)$this->get($chave);
    }

    public function set(string $chave, string $valor): void {
        $this->consultar(
            'INSERT INTO system_settings (chave, valor) VALUES (:chave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [':chave' => $chave, ':valor' => $valor]
        );

        self::limparCache();
    }

    public static function limparCache(): void {
        self::$cache = null;
    }
}
