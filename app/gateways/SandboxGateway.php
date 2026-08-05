<?php
// app/gateways/SandboxGateway.php - Simulador local de pagamentos
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Simulador de gateway para desenvolvimento no XAMPP (docs/FSD.md seção 23.1).
 *
 * Não faz nenhuma chamada externa: gera identificadores e QR Codes fictícios e
 * mantém o estado das transações na própria tabela `pagamentos`. Isso permite
 * exercitar todo o fluxo de checkout e webhook sem credenciais reais.
 *
 * Ativado por `'sandbox_mode' => true` em config/config.php.
 */
class SandboxGateway implements GatewayPagamento {

    /**
     * Cartões de teste reconhecidos pelo simulador.
     * O token chega do frontend já no formato 'sandbox-<comportamento>-<random>'.
     */
    private const COMPORTAMENTOS = [
        'aprovado' => 'aprovado',
        'recusado' => 'recusado',
        'pendente' => 'pendente',
    ];

    public function nome(): string {
        return 'sandbox';
    }

    public function criarPix(array $dados): array {
        $transacaoId = 'SBX-PIX-' . strtoupper(bin2hex(random_bytes(6)));

        return [
            'transacao_id'   => $transacaoId,
            'status'         => 'pendente',
            'qr_code'        => $this->gerarCopiaECola($dados, $transacaoId),
            'qr_code_base64' => $this->gerarImagemQrCode($transacaoId),
            'expira_em'      => date('Y-m-d H:i:s', strtotime('+10 minutes')),
        ];
    }

    public function criarCartao(array $dados): array {
        $comportamento = $this->lerComportamento($dados['token'] ?? '');

        return [
            'transacao_id' => 'SBX-CARD-' . strtoupper(bin2hex(random_bytes(6))),
            'status'       => $comportamento,
            'detalhe'      => $comportamento === 'recusado'
                ? 'Pagamento recusado pelo emissor (simulado).'
                : 'Transação simulada em ambiente local.',
        ];
    }

    /**
     * No simulador a fonte de verdade é a própria tabela `pagamentos`:
     * o status muda quando o usuário aciona a simulação de pagamento.
     */
    public function consultar(string $transacaoId): array {
        $pagamentos = new PagamentoModel();
        $pagamento = $pagamentos->buscarPorTransacao($transacaoId);

        return [
            'transacao_id' => $transacaoId,
            'status'       => $pagamento['status_pagamento'] ?? 'pendente',
            'valor'        => isset($pagamento['valor']) ? (float)$pagamento['valor'] : 0.0,
        ];
    }

    /**
     * O simulador aceita qualquer notificação: não há segredo compartilhado
     * em ambiente local. Em produção quem valida é o MercadoPagoGateway.
     */
    public function validarAssinatura(array $cabecalhos, string $idRecurso): bool {
        return true;
    }

    // -----------------------------------------------------------------

    private function lerComportamento(string $token): string {
        foreach (self::COMPORTAMENTOS as $chave => $status) {
            if (strpos($token, 'sandbox-' . $chave) === 0) {
                return $status;
            }
        }

        // Token de formato desconhecido é tratado como recusa
        return 'recusado';
    }

    /**
     * Monta uma string no formato do Pix Copia e Cola apenas para exibição.
     * Não é um payload EMV válido: serve para exercitar a interface.
     */
    private function gerarCopiaECola(array $dados, string $transacaoId): string {
        $valor = number_format((float)($dados['valor'] ?? 0), 2, '.', '');
        $reserva = $dados['codigo_reserva'] ?? 'SEMCODIGO';

        return '00020126SIMULADO' . $transacaoId
            . '5204000053039865802BR5913AGENDABARBER6009SAO PAULO'
            . '62' . sprintf('%02d', strlen($reserva)) . $reserva
            . '54' . sprintf('%02d', strlen($valor)) . $valor
            . '6304SBX0';
    }

    /**
     * Gera um SVG quadriculado determinístico a partir do ID da transação,
     * embutido como data URI. Substitui visualmente o QR Code real.
     */
    private function gerarImagemQrCode(string $transacaoId): string {
        $hash = md5($transacaoId);
        $lado = 21;
        $celula = 8;
        $tamanho = $lado * $celula;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $tamanho . '" height="' . $tamanho . '">';
        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';

        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                $indice = ($y * $lado + $x) % 32;
                $valor = hexdec($hash[$indice]);

                if ((($valor + $x * $y) % 3) === 0) {
                    $svg .= '<rect x="' . ($x * $celula) . '" y="' . ($y * $celula)
                          . '" width="' . $celula . '" height="' . $celula . '" fill="#000000"/>';
                }
            }
        }

        // Marcadores de canto, para lembrar visualmente um QR Code
        foreach ([[0, 0], [14, 0], [0, 14]] as [$mx, $my]) {
            $svg .= '<rect x="' . ($mx * $celula) . '" y="' . ($my * $celula)
                  . '" width="' . (7 * $celula) . '" height="' . (7 * $celula)
                  . '" fill="none" stroke="#000000" stroke-width="' . $celula . '"/>';
        }

        $svg .= '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
