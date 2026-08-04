<?php
// app/controllers/AdminController.php - Painel administrativo, profissionais e bloqueios
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

/**
 * Painel Administrativo.
 *
 * Permissões (docs/FSD.md seção 8):
 *  - Dashboard: Administrador Principal e Limitado;
 *  - Profissionais e jornadas: exclusivo do Administrador Principal;
 *  - Bloqueios de agenda: ambos os perfis administrativos;
 *  - Auditoria: exclusivo do Administrador Principal.
 */
class AdminController extends BaseController {

    private ProfissionalModel $profissionais;
    private ServicoModel $servicos;
    private BloqueioModel $bloqueios;
    private AuditModel $auditoria;

    public function __construct() {
        parent::__construct();
        $this->profissionais = new ProfissionalModel();
        $this->servicos = new ServicoModel();
        $this->bloqueios = new BloqueioModel();
        $this->auditoria = new AuditModel();
    }

    public function dashboard(): void {
        AuthHelper::requireAdmin();

        $this->render('admin/dashboard', ['pageTitle' => 'Dashboard']);
    }

    public function auditoria(): void {
        AuthHelper::requireRole('admin_principal');

        $this->render('admin/auditoria', ['pageTitle' => 'Histórico de Auditoria']);
    }

    // =================================================================
    // PROFISSIONAIS
    // =================================================================

    public function profissionais(): void {
        AuthHelper::requireRole('admin_principal');

        $busca = $this->get('busca');
        $status = $this->get('status');
        $idEdicao = (int)$this->get('editar', '0');
        $edicao = $idEdicao > 0 ? $this->profissionais->buscarPorId($idEdicao) : null;

        $this->render('admin/profissionais', [
            'pageTitle'        => 'Profissionais',
            'profissionais'    => $this->profissionais->listar($busca, $status),
            'busca'            => $busca,
            'statusFiltro'     => $status,
            'edicao'           => $edicao,
            'servicosDisponiveis' => $this->servicos->listar('', 'ativo'),
            'servicosVinculados'  => $edicao ? $this->profissionais->idsServicosVinculados($idEdicao) : [],
            'grade'            => $edicao ? $this->profissionais->horariosPorDia($idEdicao) : [],
            'antigo'           => $this->consumirEntradaAntiga(),
        ]);
    }

    public function salvarProfissional(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');
        $dados = [
            'nome'     => $this->post('nome'),
            'email'    => mb_strtolower($this->post('email')),
            'telefone' => $this->post('telefone'),
            'senha'    => $_POST['senha'] ?? '',
            'limite_diario_atendimentos' => $this->post('limite_diario_atendimentos'),
            'status'   => $this->post('status', 'ativo'),
        ];

        $this->guardarEntrada(['nome' => $dados['nome'], 'email' => $dados['email'], 'telefone' => $dados['telefone']]);

        $erros = $this->validarProfissional($dados, $id > 0 ? $id : null);
        if (!empty($erros)) {
            $this->comErro(implode(' ', $erros), '/admin/profissionais' . ($id > 0 ? '?editar=' . $id : ''));
        }

        if (isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $arquivo = UploadHelper::process($_FILES['foto'], 'perfis', ['jpg', 'jpeg', 'png', 'webp']);

            if ($arquivo === null) {
                $this->comErro('A foto enviada é inválida. Use JPG, PNG ou WEBP com até 5MB.', '/admin/profissionais');
            }

            $dados['foto'] = $arquivo;
        }

        if ($id > 0) {
            $anterior = $this->profissionais->buscarPorId($id);

            if ($anterior === null) {
                $this->comErro('Profissional não encontrado.', '/admin/profissionais');
            }

            $this->profissionais->atualizar($id, $dados);
            $this->auditoria->registrar(AuditModel::ATUALIZAR, 'profissionais', $id, $anterior, $dados);

            SessionHelper::remove('form_antigo');
            $this->comSucesso('Profissional atualizado com sucesso.', '/admin/profissionais?editar=' . $id);
        }

        $novoId = $this->profissionais->criar($dados);
        $this->auditoria->registrar(AuditModel::CRIAR, 'profissionais', $novoId, null, $dados);

        SessionHelper::remove('form_antigo');
        $this->comSucesso(
            'Profissional cadastrado. Defina agora os serviços executados e a jornada semanal.',
            '/admin/profissionais?editar=' . $novoId
        );
    }

    public function excluirProfissional(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');
        $profissional = $this->profissionais->buscarPorId($id);

        if ($profissional === null) {
            $this->comErro('Profissional não encontrado.', '/admin/profissionais');
        }

        if ($this->profissionais->possuiAgendamentosFuturosAtivos($id)) {
            $this->comErro(
                'Este profissional possui agendamentos futuros ativos. Remaneje ou cancele os atendimentos antes de excluí-lo.',
                '/admin/profissionais'
            );
        }

        $this->profissionais->excluirLogicamente($id);
        $this->auditoria->registrar(AuditModel::EXCLUIR, 'profissionais', $id, $profissional, null);

        $this->comSucesso('Profissional excluído.', '/admin/profissionais');
    }

    /**
     * Substitui a lista de serviços que o profissional executa.
     */
    public function salvarServicosDoProfissional(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');

        if ($this->profissionais->buscarPorId($id) === null) {
            $this->comErro('Profissional não encontrado.', '/admin/profissionais');
        }

        $anteriores = $this->profissionais->idsServicosVinculados($id);
        $selecionados = array_map('intval', $_POST['servicos'] ?? []);

        $this->profissionais->sincronizarServicos($id, $selecionados);
        $this->auditoria->registrar(
            AuditModel::ATUALIZAR, 'profissional_servicos', $id,
            ['servicos' => $anteriores], ['servicos' => $selecionados]
        );

        $this->comSucesso('Serviços do profissional atualizados.', '/admin/profissionais?editar=' . $id);
    }

    /**
     * Substitui a grade horária semanal do profissional.
     */
    public function salvarHorarios(): void {
        AuthHelper::requireRole('admin_principal');
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');

        if ($this->profissionais->buscarPorId($id) === null) {
            $this->comErro('Profissional não encontrado.', '/admin/profissionais');
        }

        $ativos = $_POST['dia_ativo'] ?? [];
        $dias = [];
        $erros = [];

        foreach (array_keys(ProfissionalModel::DIAS_SEMANA) as $dia) {
            if (!isset($ativos[$dia])) {
                continue; // dia não marcado = folga
            }

            $inicio = $_POST['hora_inicio'][$dia] ?? '';
            $fim    = $_POST['hora_fim'][$dia] ?? '';
            $almocoInicio = $_POST['almoco_inicio'][$dia] ?? '';
            $almocoFim    = $_POST['almoco_fim'][$dia] ?? '';

            $nomeDia = ProfissionalModel::DIAS_SEMANA[$dia];

            if (!$this->ehHoraValida($inicio) || !$this->ehHoraValida($fim)) {
                $erros[] = "{$nomeDia}: informe o horário de início e término.";
                continue;
            }

            if ($inicio >= $fim) {
                $erros[] = "{$nomeDia}: o horário de término deve ser posterior ao de início.";
                continue;
            }

            // O intervalo de almoço é opcional, mas se informado precisa ser completo e coerente
            $temAlmoco = $almocoInicio !== '' || $almocoFim !== '';

            if ($temAlmoco) {
                if (!$this->ehHoraValida($almocoInicio) || !$this->ehHoraValida($almocoFim)) {
                    $erros[] = "{$nomeDia}: preencha o início e o fim do intervalo de almoço.";
                    continue;
                }

                if ($almocoInicio >= $almocoFim) {
                    $erros[] = "{$nomeDia}: o fim do almoço deve ser posterior ao início.";
                    continue;
                }

                if ($almocoInicio < $inicio || $almocoFim > $fim) {
                    $erros[] = "{$nomeDia}: o intervalo de almoço deve estar dentro da jornada.";
                    continue;
                }
            }

            $dias[$dia] = [
                'hora_inicio'   => $inicio,
                'hora_fim'      => $fim,
                'almoco_inicio' => $temAlmoco ? $almocoInicio : null,
                'almoco_fim'    => $temAlmoco ? $almocoFim : null,
            ];
        }

        if (!empty($erros)) {
            $this->comErro(implode(' ', $erros), '/admin/profissionais?editar=' . $id);
        }

        $anterior = $this->profissionais->horariosPorDia($id);
        $this->profissionais->salvarHorarios($id, $dias);
        $this->auditoria->registrar(AuditModel::ATUALIZAR, 'horarios_trabalho', $id, $anterior, $dias);

        $this->comSucesso('Jornada semanal atualizada.', '/admin/profissionais?editar=' . $id);
    }

    // =================================================================
    // BLOQUEIOS DE AGENDA
    // =================================================================

    public function bloqueios(): void {
        AuthHelper::requireAdmin();

        $filtroProfissional = $this->get('profissional');

        $this->render('admin/bloqueios', [
            'pageTitle'     => 'Bloqueios de Agenda',
            'bloqueios'     => $this->bloqueios->listar(
                $filtroProfissional !== '' ? (int)$filtroProfissional : null,
                $this->get('de'),
                $this->get('ate')
            ),
            'profissionais' => $this->profissionais->listar('', 'ativo'),
            'filtroProfissional' => $filtroProfissional,
            'de'            => $this->get('de'),
            'ate'           => $this->get('ate'),
        ]);
    }

    public function salvarBloqueio(): void {
        AuthHelper::requireAdmin();
        $this->validarCsrf();

        $profissionalId = $this->post('profissional_id');
        $dados = [
            'profissional_id' => $profissionalId !== '' ? (int)$profissionalId : null,
            'data_inicio'     => str_replace('T', ' ', $this->post('data_inicio')) . ':00',
            'data_fim'        => str_replace('T', ' ', $this->post('data_fim')) . ':00',
            'motivo'          => $this->post('motivo'),
            'tipo'            => $this->post('tipo'),
        ];

        $erros = $this->validarBloqueio($dados);
        if (!empty($erros)) {
            $this->comErro(implode(' ', $erros), '/admin/bloqueios');
        }

        // Alerta (não impeditivo) sobre agendamentos que já ocupam a janela
        $conflitos = $this->bloqueios->agendamentosNoPeriodo(
            $dados['profissional_id'], $dados['data_inicio'], $dados['data_fim']
        );

        $id = $this->bloqueios->criar($dados);
        $this->auditoria->registrar(AuditModel::CRIAR, 'indisponibilidades_bloqueios', $id, null, $dados);

        $aviso = !empty($conflitos)
            ? ' ATENÇÃO: ' . count($conflitos) . ' agendamento(s) já existente(s) caem nesta janela e precisam ser remanejados.'
            : '';

        $this->comSucesso('Bloqueio cadastrado.' . $aviso, '/admin/bloqueios');
    }

    public function excluirBloqueio(): void {
        AuthHelper::requireAdmin();
        $this->validarCsrf();

        $id = (int)$this->post('id', '0');
        $bloqueio = $this->bloqueios->buscarPorId($id);

        if ($bloqueio === null) {
            $this->comErro('Bloqueio não encontrado.', '/admin/bloqueios');
        }

        $this->bloqueios->excluir($id);
        $this->auditoria->registrar(AuditModel::EXCLUIR, 'indisponibilidades_bloqueios', $id, $bloqueio, null);

        $this->comSucesso('Bloqueio removido. O horário volta a ficar disponível.', '/admin/bloqueios');
    }

    // =================================================================
    // Validações
    // =================================================================

    private function validarProfissional(array $dados, ?int $id): array {
        $erros = [];
        $usuarios = new UsuarioModel();

        if (mb_strlen($dados['nome']) < 3) {
            $erros[] = 'Informe o nome completo do profissional.';
        }

        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Informe um e-mail válido.';
        } else {
            $usuarioId = null;

            if ($id !== null) {
                $profissional = $this->profissionais->buscarPorId($id);
                $usuarioId = $profissional ? (int)$profissional['usuario_id'] : null;
            }

            if ($usuarios->emailEmUso($dados['email'], $usuarioId)) {
                $erros[] = 'Este e-mail já está em uso por outra conta.';
            }
        }

        if (strlen(preg_replace('/\D/', '', $dados['telefone'])) < 10) {
            $erros[] = 'Informe um telefone válido com DDD.';
        }

        // Senha é obrigatória no cadastro e opcional na edição
        if ($id === null) {
            if (strlen($dados['senha']) < 8) {
                $erros[] = 'A senha de acesso deve ter no mínimo 8 caracteres.';
            }
        } elseif ($dados['senha'] !== '' && strlen($dados['senha']) < 8) {
            $erros[] = 'A nova senha deve ter no mínimo 8 caracteres.';
        }

        $limite = $dados['limite_diario_atendimentos'];
        if ($limite !== '' && (!ctype_digit($limite) || (int)$limite < 1)) {
            $erros[] = 'O limite diário de atendimentos deve ser um número inteiro positivo.';
        }

        if (!in_array($dados['status'], ['ativo', 'inativo'], true)) {
            $erros[] = 'Status inválido.';
        }

        return $erros;
    }

    private function validarBloqueio(array $dados): array {
        $erros = [];

        if (!$this->ehDataHoraValida($dados['data_inicio']) || !$this->ehDataHoraValida($dados['data_fim'])) {
            $erros[] = 'Informe a data e hora de início e término do bloqueio.';
            return $erros;
        }

        if ($dados['data_inicio'] >= $dados['data_fim']) {
            $erros[] = 'O término do bloqueio deve ser posterior ao início.';
        }

        if (mb_strlen($dados['motivo']) < 3) {
            $erros[] = 'Descreva o motivo do bloqueio.';
        }

        if (!array_key_exists($dados['tipo'], BloqueioModel::TIPOS)) {
            $erros[] = 'Tipo de bloqueio inválido.';
        }

        if ($dados['profissional_id'] !== null && $this->profissionais->buscarPorId($dados['profissional_id']) === null) {
            $erros[] = 'Profissional inválido.';
        }

        return $erros;
    }

    private function ehHoraValida(string $hora): bool {
        return $hora !== '' && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) === 1;
    }

    private function ehDataHoraValida(string $valor): bool {
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $valor);
        return $d !== false && $d->format('Y-m-d H:i:s') === $valor;
    }

    private function consumirEntradaAntiga(): array {
        $antigo = SessionHelper::get('form_antigo', []);
        SessionHelper::remove('form_antigo');
        return is_array($antigo) ? $antigo : [];
    }
}
