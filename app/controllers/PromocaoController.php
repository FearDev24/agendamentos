<?php
// app/controllers/PromocaoController.php - Campanhas promocionais
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Promoções públicas (docs/FSD.md Módulo 7 e 12.10).
 *
 * Cada campanha tem uma página pública própria, feita para ser compartilhada
 * em redes sociais e mensageiros — por isso ela é acessível sem login e traz
 * as metatags Open Graph que geram a prévia do link.
 */
class PromocaoController extends BaseController {

    private PromocaoModel $promocoes;
    private ServicoModel $servicos;
    private AuditModel $auditoria;

    public function __construct() {
        parent::__construct();
        $this->promocoes = new PromocaoModel();
        $this->servicos = new ServicoModel();
        $this->auditoria = new AuditModel();
    }

    // =================================================================
    // Administração
    // =================================================================

    public function index(): void {
        AuthHelper::requireAdmin();

        // Mantém o status coerente com a validade antes de listar
        $this->promocoes->expirarVencidas();

        $idEdicao = (int)$this->get('editar', '0');
        $edicao = $idEdicao > 0 ? $this->promocoes->buscarPorId($idEdicao) : null;

        $this->render('admin/promocoes', [
            'pageTitle'    => 'Promoções',
            'promocoes'    => $this->promocoes->listar($this->get('status')),
            'statusFiltro' => $this->get('status'),
            'edicao'       => $edicao,
            'usos'         => $edicao ? $this->promocoes->usos($idEdicao) : [],
            'catalogo'     => $this->servicos->listar('', 'ativo'),
            'antigo'       => $this->consumirEntradaAntiga(),
        ]);
    }

    public function salvar(): void {
        AuthHelper::requireAdmin();
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');
        $dados = [
            'titulo'            => $this->post('titulo'),
            'descricao'         => $this->post('descricao'),
            'servico_id'        => $this->post('servico_id'),
            'tipo_desconto'     => $this->post('tipo_desconto', 'fixo'),
            'valor_desconto'    => str_replace(',', '.', $this->post('valor_desconto')),
            'data_inicio'       => $this->post('data_inicio'),
            'data_fim'          => $this->post('data_fim'),
            'limite_total_usos' => $this->post('limite_total_usos'),
            'status'            => $this->post('status', 'ativa'),
        ];

        $this->guardarEntrada($dados + ['id' => $id]);

        $erros = $this->validar($dados);

        if (!empty($erros)) {
            $this->comErro(implode(' ', $erros), '/admin/promocoes' . ($id > 0 ? '?editar=' . $id : ''));
        }

        if (isset($_FILES['imagem']) && ($_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $arquivo = UploadHelper::process($_FILES['imagem'], 'promocoes', ['jpg', 'jpeg', 'png', 'webp']);

            if ($arquivo === null) {
                $this->comErro('O banner enviado é inválido. Use JPG, PNG ou WEBP com até 5MB.', '/admin/promocoes');
            }

            $dados['imagem'] = $arquivo;
        }

        if ($id > 0) {
            $anterior = $this->promocoes->buscarPorId($id);

            if ($anterior === null) {
                $this->comErro('Promoção não encontrada.', '/admin/promocoes');
            }

            $this->promocoes->atualizar($id, $dados);
            $this->auditoria->registrar(AuditModel::ATUALIZAR, 'promocoes', $id, $anterior, $dados);

            SessionHelper::remove('form_antigo');
            $this->comSucesso('Promoção atualizada.', '/admin/promocoes?editar=' . $id);
        }

        $novoId = $this->promocoes->criar($dados);
        $this->auditoria->registrar(AuditModel::CRIAR, 'promocoes', $novoId, null, $dados);

        SessionHelper::remove('form_antigo');
        $this->comSucesso(
            'Promoção criada. Use o link de compartilhamento para divulgá-la.',
            '/admin/promocoes?editar=' . $novoId
        );
    }

    public function excluir(): void {
        AuthHelper::requireAdmin();
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');
        $promocao = $this->promocoes->buscarPorId($id);

        if ($promocao === null) {
            $this->comErro('Promoção não encontrada.', '/admin/promocoes');
        }

        if ((int)$promocao['usos_realizados'] > 0) {
            $this->comErro(
                'Esta promoção já foi utilizada e não pode ser excluída. Marque-a como inativa.',
                '/admin/promocoes'
            );
        }

        $this->promocoes->excluir($id);
        $this->auditoria->registrar(AuditModel::EXCLUIR, 'promocoes', $id, $promocao, null);

        $this->comSucesso('Promoção excluída.', '/admin/promocoes');
    }

    // =================================================================
    // Página pública de compartilhamento
    // =================================================================

    public function publica(string $id = ''): void {
        $promocao = $this->promocoes->buscarPorId((int)$id);

        if ($promocao === null) {
            (new HomeController())->naoEncontrado();
            return;
        }

        $this->render('public/promocao', [
            'pageTitle' => $promocao['titulo'],
            'promocao'  => $promocao,
            'vigente'   => $this->promocoes->estaVigente($promocao),
            'ajustes'   => (new SettingModel())->todos(),
        ]);
    }

    // =================================================================
    // Validação
    // =================================================================

    private function validar(array $dados): array {
        $erros = [];

        if (mb_strlen(trim($dados['titulo'])) < 3) {
            $erros[] = 'O título deve ter no mínimo 3 caracteres.';
        }

        if (!array_key_exists($dados['tipo_desconto'], PromocaoModel::TIPOS_DESCONTO)) {
            $erros[] = 'Tipo de desconto inválido.';
        }

        if (!is_numeric($dados['valor_desconto']) || (float)$dados['valor_desconto'] <= 0) {
            $erros[] = 'Informe um valor de desconto maior que zero.';
        } elseif ($dados['tipo_desconto'] === 'percentual' && (float)$dados['valor_desconto'] > 100) {
            $erros[] = 'O desconto percentual não pode passar de 100%.';
        }

        if (!$this->ehDataValida($dados['data_inicio']) || !$this->ehDataValida($dados['data_fim'])) {
            $erros[] = 'Informe as datas de início e término da campanha.';
        } elseif ($dados['data_inicio'] > $dados['data_fim']) {
            $erros[] = 'A data de término deve ser posterior à de início.';
        }

        if ($dados['limite_total_usos'] !== ''
            && (!ctype_digit($dados['limite_total_usos']) || (int)$dados['limite_total_usos'] < 1)) {
            $erros[] = 'O limite de usos deve ser um número inteiro positivo.';
        }

        if (!array_key_exists($dados['status'], PromocaoModel::STATUS)) {
            $erros[] = 'Status inválido.';
        }

        if (!empty($dados['servico_id'])) {
            $ids = array_map('intval', array_column($this->servicos->listar(), 'id'));

            if (!in_array((int)$dados['servico_id'], $ids, true)) {
                $erros[] = 'Serviço vinculado inválido.';
            }
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

    private function consumirEntradaAntiga(): array {
        $antigo = SessionHelper::get('form_antigo', []);
        SessionHelper::remove('form_antigo');

        return is_array($antigo) ? $antigo : [];
    }
}
