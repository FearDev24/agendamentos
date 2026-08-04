<?php
// app/controllers/ServicoController.php - CRUD administrativo de serviços
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Gestão de Serviços (docs/FSD.md Módulo 7).
 *
 * Conforme a matriz de permissões (seção 8), "Gerenciar Serviços, Preços e
 * Profissionais" é exclusivo do Administrador Principal.
 * Alterações são registradas em `audit_logs`.
 */
class ServicoController extends BaseController {

    private ServicoModel $servicos;
    private AuditModel $auditoria;

    public function __construct() {
        parent::__construct();
        $this->servicos = new ServicoModel();
        $this->auditoria = new AuditModel();
    }

    public function index(): void {
        AuthHelper::requireRole('admin_principal');

        $busca = $this->get('busca');
        $status = $this->get('status');

        $this->render('admin/servicos', [
            'pageTitle'  => 'Serviços',
            'servicos'   => $this->servicos->listar($busca, $status),
            'excluidos'  => $this->servicos->listarExcluidos(),
            'busca'      => $busca,
            'statusFiltro' => $status,
            'edicao'     => $this->carregarParaEdicao(),
            'antigo'     => $this->consumirEntradaAntiga(),
        ]);
    }

    public function salvar(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');
        $dados = [
            'nome'             => $this->post('nome'),
            'descricao'        => $this->post('descricao'),
            'duracao_minutos'  => $this->post('duracao_minutos'),
            'preco'            => str_replace(',', '.', $this->post('preco')),
            'orientacoes'      => $this->post('orientacoes'),
            'status'           => $this->post('status', 'ativo'),
            'previsao_retorno' => $this->post('previsao_retorno'),
        ];

        $this->guardarEntrada($dados + ['id' => $id]);

        $erros = $this->validar($dados, $id > 0 ? $id : null);
        if (!empty($erros)) {
            $this->comErro(implode(' ', $erros), '/admin/servicos' . ($id > 0 ? '?editar=' . $id : ''));
        }

        // Upload opcional da imagem ilustrativa
        if (isset($_FILES['imagem']) && ($_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $arquivo = UploadHelper::process($_FILES['imagem'], 'servicos', ['jpg', 'jpeg', 'png', 'webp']);

            if ($arquivo === null) {
                $this->comErro('A imagem enviada é inválida. Use JPG, PNG ou WEBP com até 5MB.', '/admin/servicos');
            }

            $dados['imagem'] = $arquivo;
        }

        if ($id > 0) {
            $anterior = $this->servicos->buscarPorId($id);

            if ($anterior === null) {
                $this->comErro('Serviço não encontrado.', '/admin/servicos');
            }

            $this->servicos->atualizar($id, $dados);
            $this->auditoria->registrar(AuditModel::ATUALIZAR, 'servicos', $id, $anterior, $dados);

            $aviso = ((float)$anterior['preco'] !== (float)$dados['preco'])
                ? ' A alteração de preço foi registrada na auditoria.'
                : '';

            $this->comSucesso('Serviço atualizado com sucesso.' . $aviso, '/admin/servicos');
        }

        $novoId = $this->servicos->criar($dados);
        $this->auditoria->registrar(AuditModel::CRIAR, 'servicos', $novoId, null, $dados);

        SessionHelper::remove('form_antigo');
        $this->comSucesso('Serviço cadastrado com sucesso.', '/admin/servicos');
    }

    public function excluir(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');
        $servico = $this->servicos->buscarPorId($id);

        if ($servico === null) {
            $this->comErro('Serviço não encontrado.', '/admin/servicos');
        }

        $this->servicos->excluirLogicamente($id);
        $this->auditoria->registrar(AuditModel::EXCLUIR, 'servicos', $id, $servico, null);

        $observacao = $this->servicos->possuiHistorico($id)
            ? ' O histórico de agendamentos foi preservado.'
            : '';

        $this->comSucesso('Serviço excluído.' . $observacao, '/admin/servicos');
    }

    public function restaurar(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');

        if (!$this->servicos->restaurar($id)) {
            $this->comErro('Não foi possível restaurar o serviço.', '/admin/servicos');
        }

        $this->auditoria->registrar(AuditModel::RESTAURAR, 'servicos', $id);
        $this->comSucesso('Serviço restaurado com sucesso.', '/admin/servicos');
    }

    // -----------------------------------------------------------------

    /**
     * Regras de validação do cadastro de serviços.
     */
    private function validar(array $dados, ?int $id): array {
        $erros = [];

        if (mb_strlen($dados['nome']) < 3) {
            $erros[] = 'O nome do serviço deve ter no mínimo 3 caracteres.';
        } elseif ($this->servicos->nomeEmUso($dados['nome'], $id)) {
            $erros[] = 'Já existe um serviço com este nome.';
        }

        $duracao = (int)$dados['duracao_minutos'];
        if ($duracao < 5 || $duracao > 480) {
            $erros[] = 'A duração deve estar entre 5 e 480 minutos.';
        }

        if (!is_numeric($dados['preco']) || (float)$dados['preco'] < 0) {
            $erros[] = 'Informe um preço válido.';
        }

        if (!in_array($dados['status'], ServicoModel::STATUS, true)) {
            $erros[] = 'Status inválido.';
        }

        if ($dados['previsao_retorno'] !== '' && !$this->ehDataValida($dados['previsao_retorno'])) {
            $erros[] = 'A previsão de retorno deve ser uma data válida.';
        }

        return $erros;
    }

    private function ehDataValida(string $data): bool {
        $d = DateTime::createFromFormat('Y-m-d', $data);
        return $d !== false && $d->format('Y-m-d') === $data;
    }

    private function carregarParaEdicao(): ?array {
        $id = (int)$this->get('editar', '0');
        return $id > 0 ? $this->servicos->buscarPorId($id) : null;
    }

    private function consumirEntradaAntiga(): array {
        $antigo = SessionHelper::get('form_antigo', []);
        SessionHelper::remove('form_antigo');
        return is_array($antigo) ? $antigo : [];
    }
}
