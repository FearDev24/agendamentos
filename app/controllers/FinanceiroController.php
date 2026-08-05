<?php
// app/controllers/FinanceiroController.php - Controle financeiro e relatórios
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Módulo Financeiro e Relatórios (docs/FSD.md Módulos 7 e seção 22).
 *
 * Permissão: o FSD (seção 8) marca "Lançar Despesas e Ver Lucro" como
 * configurável para o Administrador Limitado. Enquanto a matriz de permissões
 * individuais não existe, o acesso segue restrito ao Administrador Principal,
 * que é o comportamento mais conservador para dados financeiros.
 */
class FinanceiroController extends BaseController {

    private DespesaModel $despesas;
    private RelatorioModel $relatorios;
    private ProfissionalModel $profissionais;
    private ServicoModel $servicos;
    private AuditModel $auditoria;

    public function __construct() {
        parent::__construct();
        $this->despesas = new DespesaModel();
        $this->relatorios = new RelatorioModel();
        $this->profissionais = new ProfissionalModel();
        $this->servicos = new ServicoModel();
        $this->auditoria = new AuditModel();
    }

    // =================================================================
    // Painel financeiro
    // =================================================================

    public function index(): void {
        AuthHelper::requireRole('admin_principal');

        [$de, $ate] = $this->periodoSolicitado();
        $categoriaId = $this->get('categoria');

        $this->render('admin/financeiro', [
            'pageTitle'     => 'Financeiro',
            'de'            => $de,
            'ate'           => $ate,
            'resumo'        => $this->relatorios->resumoFinanceiro($de, $ate),
            'receitaPorDia' => $this->relatorios->receitaPorDia($de, $ate),
            'porForma'      => $this->relatorios->receitaPorFormaDePagamento($de, $ate),
            'porCategoria'  => $this->despesas->totalPorCategoria($de, $ate),
            'lancamentos'   => $this->despesas->listar($de, $ate, $categoriaId !== '' ? (int)$categoriaId : null),
            'categorias'    => $this->despesas->categorias(),
            'categoriaFiltro' => $categoriaId,
        ]);
    }

    // =================================================================
    // Despesas
    // =================================================================

    public function salvarDespesa(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $dados = [
            'categoria_id' => $this->post('categoria_id'),
            'descricao'    => $this->post('descricao'),
            'valor'        => str_replace(',', '.', $this->post('valor')),
            'data_despesa' => $this->post('data_despesa'),
        ];

        $erros = $this->validarDespesa($dados);

        if (!empty($erros)) {
            $this->comErro(implode(' ', $erros), '/admin/financeiro');
        }

        // Comprovante aceita imagem ou PDF
        if (isset($_FILES['comprovante']) && ($_FILES['comprovante']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $arquivo = UploadHelper::process($_FILES['comprovante'], 'comprovantes', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);

            if ($arquivo === null) {
                $this->comErro('O comprovante enviado é inválido. Use JPG, PNG, WEBP ou PDF com até 5MB.', '/admin/financeiro');
            }

            $dados['comprovante'] = $arquivo;
        }

        $id = $this->despesas->criar($dados, AuthHelper::id());
        $this->auditoria->registrar(AuditModel::CRIAR, 'despesas', $id, null, $dados);

        $this->comSucesso('Despesa lançada com sucesso.', '/admin/financeiro?de=' . $this->get('de') . '&ate=' . $this->get('ate'));
    }

    public function excluirDespesa(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');
        $despesa = $this->despesas->buscarPorId($id);

        if ($despesa === null) {
            $this->comErro('Lançamento não encontrado.', '/admin/financeiro');
        }

        $this->despesas->excluirLogicamente($id);
        $this->auditoria->registrar(AuditModel::EXCLUIR, 'despesas', $id, $despesa, null);

        $this->comSucesso('Lançamento excluído.', '/admin/financeiro');
    }

    public function salvarCategoria(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        try {
            $this->despesas->criarCategoria($this->post('nome'));
        } catch (InvalidArgumentException $e) {
            $this->comErro($e->getMessage(), '/admin/financeiro');
        } catch (PDOException $e) {
            $this->comErro('Já existe uma categoria com este nome.', '/admin/financeiro');
        }

        $this->comSucesso('Categoria criada.', '/admin/financeiro');
    }

    // =================================================================
    // Relatórios
    // =================================================================

    public function relatorios(): void {
        AuthHelper::requireRole('admin_principal');

        [$de, $ate] = $this->periodoSolicitado();
        $filtros = $this->filtrosDeRelatorio($de, $ate);

        $this->render('admin/relatorios', [
            'pageTitle'     => 'Relatórios',
            'de'            => $de,
            'ate'           => $ate,
            'filtros'       => $filtros,
            'agendamentos'  => $this->relatorios->agendamentos($filtros),
            'desempenho'    => $this->relatorios->desempenhoDosProfissionais($de, $ate),
            'servicos'      => $this->relatorios->servicosMaisContratados($de, $ate),
            'resumo'        => $this->relatorios->resumoFinanceiro($de, $ate),
            'profissionais' => $this->profissionais->listar('', 'ativo'),
            'catalogo'      => $this->servicos->listar(),
        ]);
    }

    /**
     * Exporta o relatório de agendamentos respeitando exatamente os filtros
     * aplicados na tela (docs/FSD.md seção 22.2).
     */
    public function exportar(): void {
        AuthHelper::requireRole('admin_principal');

        [$de, $ate] = $this->periodoSolicitado();
        $filtros = $this->filtrosDeRelatorio($de, $ate);
        $formato = $this->get('formato') === 'xls' ? 'xls' : 'csv';

        $linhas = [];

        foreach ($this->relatorios->agendamentos($filtros) as $a) {
            $linhas[] = [
                $a['codigo_reserva'],
                FormatHelper::dateBr($a['data_agendamento']),
                substr($a['hora_inicio'], 0, 5) . ' - ' . substr($a['hora_fim'], 0, 5),
                $a['cliente_nome'],
                $a['cliente_telefone'],
                $a['profissional_nome'],
                $a['servicos'] ?? '',
                number_format((float)$a['valor_total'], 2, ',', ''),
                number_format((float)$a['valor_desconto'], 2, ',', ''),
                number_format((float)$a['credito_utilizado'], 2, ',', ''),
                str_replace('_', ' ', $a['situacao']),
                $a['forma_pagamento'] ? str_replace('_', ' ', $a['forma_pagamento']) : '',
                $a['justificativa_desconto'] ?? '',
            ];
        }

        $cabecalhos = [
            'Código da Reserva', 'Data', 'Horário', 'Cliente', 'Telefone', 'Profissional',
            'Serviços', 'Valor Total', 'Desconto', 'Crédito Utilizado', 'Situação',
            'Forma de Pagamento', 'Justificativa do Desconto',
        ];

        $nome = 'agendamentos_' . $de . '_a_' . $ate;

        $this->auditoria->registrar(
            'exportar_relatorio', 'agendamentos', null, null,
            ['periodo' => $de . ' a ' . $ate, 'formato' => $formato, 'linhas' => count($linhas)]
        );

        if ($formato === 'xls') {
            ExportHelper::xls($nome, $cabecalhos, $linhas, "Agendamentos de {$de} a {$ate}");
        }

        ExportHelper::csv($nome, $cabecalhos, $linhas);
    }

    /**
     * Exporta o resumo financeiro do período.
     */
    public function exportarFinanceiro(): void {
        AuthHelper::requireRole('admin_principal');

        [$de, $ate] = $this->periodoSolicitado();
        $resumo = $this->relatorios->resumoFinanceiro($de, $ate);

        $linhas = [
            ['Receita bruta', number_format($resumo['receita_bruta'], 2, ',', '')],
            ['Estornos', number_format($resumo['estornos'], 2, ',', '')],
            ['Receita líquida', number_format($resumo['receita_liquida'], 2, ',', '')],
            ['Despesas', number_format($resumo['despesas'], 2, ',', '')],
            ['Lucro líquido', number_format($resumo['lucro_liquido'], 2, ',', '')],
            ['Margem (%)', number_format($resumo['margem'], 1, ',', '')],
            ['', ''],
            ['DESPESAS POR CATEGORIA', ''],
        ];

        foreach ($this->despesas->totalPorCategoria($de, $ate) as $categoria) {
            $linhas[] = [$categoria['nome'], number_format((float)$categoria['total'], 2, ',', '')];
        }

        $linhas[] = ['', ''];
        $linhas[] = ['RECEITA POR FORMA DE PAGAMENTO', ''];

        foreach ($this->relatorios->receitaPorFormaDePagamento($de, $ate) as $forma) {
            $linhas[] = [
                str_replace('_', ' ', $forma['forma_pagamento']),
                number_format((float)$forma['total'], 2, ',', ''),
            ];
        }

        ExportHelper::csv('financeiro_' . $de . '_a_' . $ate, ['Item', 'Valor (R$)'], $linhas);
    }

    // =================================================================
    // Auxiliares
    // =================================================================

    /**
     * Período consultado, com o mês corrente como padrão.
     */
    private function periodoSolicitado(): array {
        $de = $this->get('de');
        $ate = $this->get('ate');

        if (!$this->ehDataValida($de)) {
            $de = date('Y-m-01');
        }

        if (!$this->ehDataValida($ate)) {
            $ate = date('Y-m-t');
        }

        // Intervalo invertido é corrigido em vez de recusado
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }

        return [$de, $ate];
    }

    private function filtrosDeRelatorio(string $de, string $ate): array {
        return [
            'de'              => $de,
            'ate'             => $ate,
            'profissional_id' => $this->get('profissional'),
            'situacao'        => in_array($this->get('situacao'), AgendamentoModel::SITUACOES_TODAS, true)
                                 ? $this->get('situacao') : '',
            'servico_id'      => $this->get('servico'),
        ];
    }

    private function validarDespesa(array $dados): array {
        $erros = [];

        $categorias = array_map('intval', array_column($this->despesas->categorias(), 'id'));

        if (!in_array((int)$dados['categoria_id'], $categorias, true)) {
            $erros[] = 'Selecione uma categoria válida.';
        }

        if (mb_strlen(trim($dados['descricao'])) < 3) {
            $erros[] = 'Descreva a despesa com pelo menos 3 caracteres.';
        }

        if (!is_numeric($dados['valor']) || (float)$dados['valor'] <= 0) {
            $erros[] = 'Informe um valor maior que zero.';
        }

        if (!$this->ehDataValida($dados['data_despesa'])) {
            $erros[] = 'Informe uma data válida para a despesa.';
        } elseif ($dados['data_despesa'] > date('Y-m-d')) {
            $erros[] = 'A data da despesa não pode estar no futuro.';
        }

        return $erros;
    }

    private function ehDataValida(string $data): bool {
        if ($data === '') {
            return false;
        }

        $d = DateTime::createFromFormat('Y-m-d', $data);

        return $d !== false && $d->format('Y-m-d') === $data;
    }
}
