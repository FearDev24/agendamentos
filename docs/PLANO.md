# Plano de Implementação Incremental - AgendaBarber Web

Este plano detalha a construção incremental do sistema **AgendaBarber Web**, baseado estritamente no Documento de Especificação Funcional ([FSD.md](FSD.md)) e no Guia de Interface ([DESIGN.md](DESIGN.md)).

---

## Visão Geral das Fases

- **Fase 1**: Infraestrutura e Base do Projeto *(Concluída nesta etapa)*
- **Fase 2**: Banco de Dados e Sistema de Migrations
- **Fase 3**: Módulo de Autenticação, Sessões e Controle de Acesso (RBAC)
- **Fase 4**: Cadastros Administrativos Base (Serviços, Profissionais e Bloqueios)
- **Fase 5**: Motor de Disponibilidade e Fluxo de Agendamento Público
- **Fase 6**: Gateway de Pagamentos, Webhooks e Retenção Temporária
- **Fase 7**: Áreas Restritas do Cliente e do Profissional
- **Fase 8**: Painel Administrativo Operacional, Financeiro e Promoções
- **Fase 9**: Transversais: Logs, Auditoria, E-mails Assíncronos e Polimento Final

---

## Detalhamento das Fases

### Fase 1 - Infraestrutura e Base do Projeto
- **Objetivo**: Criar a estrutura base de diretórios, roteamento MVC nativo, proteção de arquivos sensíveis, helpers utilitários e cópia de insumos visuais públicos.
- **Checklist de tarefas**:
  - [x] Criar estrutura de diretórios (`config/`, `app/`, `database/`, `logs/`, `public/`, `vendor/`);
  - [x] Configurar Front Controller (`index.php`) e `.htaccess` raiz para URLs amigáveis;
  - [x] Criar arquivo de configuração técnica `config/config.php` sem credenciais expostas;
  - [x] Criar arquivos `.htaccess` de proteção para `config/`, `database/`, `logs/` e `public/uploads/`;
  - [x] Estruturar os helpers base (`SessionHelper`, `AuthHelper`, `MailHelper`, `UploadHelper`, `FormatHelper`);
  - [x] Organizar tokens do design system (*Elevated Grooming & Wellness*) em `public/assets/css/style.css`;
  - [x] Baixar/inserir Bootstrap 5 local em `public/assets/vendor/bootstrap/`;
  - [x] Copiar insumos de imagens públicas (`logo`, `insígnias de fidelidade`) para `public/assets/img/`.
- **Critérios de pronto**: Navegação apontando para `index.php` sem erros 404, estilos do design system carregados localmente, pastas restritas bloqueadas contra acesso direto via navegador.
- **Arquivos/Áreas alteradas**: `index.php`, `.htaccess`, `config/`, `app/helpers/`, `public/assets/`.
- **Observações de dependência**: Base necessária para todas as fases subsequentes.

---

### Fase 2 - Banco de Dados e Sistema de Migrations
- **Objetivo**: Estruturar a persistência de dados no MySQL/MariaDB com mecanismo nativo de migrations e seeding de dados iniciais.
- **Checklist de tarefas**:
  - [ ] Implementar classe/script de migração PHP (`database/migrate.php`) e tabela `schema_migrations`;
  - [ ] Escrever migration `001_create_system_tables.php` contendo todas as 20 tabelas com chaves primárias, estrangeiras e índices otimizados;
  - [ ] Escrever migration `002_seed_initial_data.php` com usuário administrador padrão, categorias de despesas e serviços base;
  - [ ] Testar execução CLI e rotina administrativa protegida de migration.
- **Critérios de pronto**: Banco de dados criado integralmente sem erros de chave estrangeira, dados iniciais inseridos com sucesso.
- **Arquivos/Áreas alteradas**: `database/migrations/`, `app/models/`.
- **Observações de dependência**: Depende da Fase 1.

---

### Fase 3 - Módulo de Autenticação, Sessões e Controle de Acesso (RBAC)
- **Objetivo**: Implementar o controle de contas, login unificado com hash seguro, recuperação de senha e middleware de permissões por perfil.
- **Checklist de tarefas**:
  - [ ] Criar `UsuarioModel.php` com suporte a autenticação e Soft Delete;
  - [ ] Criar `AuthController.php` e rotas para Login, Cadastro (validação +18), Logout e Recuperação de Senha por token expirável (1 hora);
  - [ ] Implementar middleware RBAC no `AuthHelper.php` para restringir perfis (Cliente, Profissional, Admin Principal, Admin Limitado);
  - [ ] Desenvolver Views de login, cadastro público e perfil do usuário (com confirmação de e-mail e exclusão de conta).
- **Critérios de pronto**: Usuários conseguem cadastrar-se, logar e acessar apenas as áreas correspondentes a seus papéis.
- **Arquivos/Áreas alteradas**: `app/controllers/AuthController.php`, `app/models/UsuarioModel.php`, `app/views/public/`, `app/views/layouts/`.
- **Observações de dependência**: Depende das Fases 1 e 2.

---

### Fase 4 - Cadastros Administrativos Base (Serviços, Profissionais e Bloqueios)
- **Objetivo**: Permitir a gestão completa dos dados do estabelecimento necessários para o agendamento.
- **Checklist de tarefas**:
  - [ ] Desenvolver CRUD de Serviços (`ServicoModel.php`, `ServicoController.php`) com upload de imagem e orientações;
  - [ ] Desenvolver CRUD de Profissionais (`ProfissionalModel.php`), vinculação N:N com serviços e cadastro da grade horária semanal (`horarios_trabalho`);
  - [ ] Desenvolver gestão de Indisponividades e Bloqueios (`indisponibilidades_bloqueios`).
- **Critérios de pronto**: Administrador consegue cadastrar serviços, associar aos profissionais e definir horários de atendimento e folgas.
- **Arquivos/Áreas alteradas**: `app/controllers/AdminController.php`, `app/models/ServicoModel.php`, `app/models/ProfissionalModel.php`, `app/views/admin/`.
- **Observações de dependência**: Depende das Fases 2 e 3.

---

### Fase 5 - Motor de Disponibilidade e Fluxo de Agendamento Público
- **Objetivo**: Implementar o algoritmo de cálculo de horários livres e a interface pública de agendamento em passos (Wizard).
- **Checklist de tarefas**:
  - [ ] Construir o motor de consulta de horários (`AgendamentoModel.php`) considerando múltiplos serviços, duração total, intervalo obrigatório de 10 min entre atendimentos e bloqueios;
  - [ ] Desenvolver a Vitrine Pública (`/`) com serviços, profissionais, sobre e localização;
  - [ ] Criar Wizard de Agendamento (`/agendar`): Passo 1 (Serviços), Passo 2 (Profissional/Data/Horário via AJAX), Passo 3 (Revisão e Políticas).
- **Critérios de pronto**: Cliente consegue visualizar horários vagos calculados em tempo real sem sobreposição de agenda.
- **Arquivos/Áreas alteradas**: `app/controllers/AgendamentoController.php`, `app/views/public/`, `public/assets/js/agendamento.js`.
- **Observações de dependência**: Depende da Fase 4.

---

### Fase 6 - Gateway de Pagamentos, Webhooks e Retenção Temporária
- **Objetivo**: Integrar a retenção temporária de 10 minutos no checkout e o pagamento via Mercado Pago (Pix QR Code e Cartão).
- **Checklist de tarefas**:
  - [ ] Implementar a lógica de reserva temporária (status `aguardando_pagamento` com expiração em 10 min) e cronômetro regressivo;
  - [ ] Integrar SDK Mercado Pago para geração de Pix QR Code / Copia e Cola e Cartão de Crédito transparente;
  - [ ] Desenvolver classe Simuladora/Mock Sandbox para testes locais sem chamadas reais;
  - [ ] Desenvolver `WebhookController.php` para atualização automática e idempotente dos agendamentos confirmados.
- **Critérios de pronto**: Agendamento retém o horário por 10 min e é confirmado automaticamente com o retorno do webhook ou do mock local.
- **Arquivos/Áreas alteradas**: `app/controllers/CheckoutController.php`, `app/controllers/WebhookController.php`, `app/models/PagamentoModel.php`, `app/views/public/checkout.php`.
- **Observações de dependência**: Depende da Fase 5.

---

### Fase 7 - Áreas Restritas do Cliente e do Profissional
- **Objetivo**: Entregar as interfaces restritas operacionais do cliente e dos prestadores de serviço.
- **Checklist de tarefas**:
  - [ ] Desenvolver painel "Meus Agendamentos" para clientes com comprovantes em PDF/impressão, reagendamento (regra 24h com geração de créditos internos) e cancelamento (regra 24h);
  - [ ] Implementar visualização de créditos internos acumulados e insígnias do Programa de Fidelidade Visual (Bronze, Prata, Ouro);
  - [ ] Implementar formulário de avaliações (1 a 5 estrelas);
  - [ ] Desenvolver Painel do Profissional (`/profissional/agenda`) com confirmação de presença, registro de horários reais de início/fim, conclusão de atendimento e cadastro de notas privadas sobre clientes.
- **Critérios de pronto**: Clientes gerenciam seus agendamentos e créditos; profissionais operam seus atendimentos do dia.
- **Arquivos/Áreas alteradas**: `app/controllers/ClienteController.php`, `app/controllers/ProfissionalController.php`, `app/views/cliente/`, `app/views/profissional/`.
- **Observações de dependência**: Depende da Fase 6.

---

### Fase 8 - Painel Administrativo Operacional, Financeiro e Promoções
- **Objetivo**: Disponibilizar o controle completo da barbearia para Administradores Principais e Limitados.
- **Checklist de tarefas**:
  - [ ] Implementar Agenda Administrativa em visões Diária, Semanal e Mensal com agendamento manual e desconto justificado;
  - [ ] Implementar controle de despesas financeiras com upload de comprovantes;
  - [ ] Implementar cálculo em tempo real de receitas, despesas e lucro líquido;
  - [ ] Implementar cadastro de Promoções públicas com banners e links públicos de compartilhamento;
  - [ ] Implementar relatórios em tela e exportação de dados em CSV / Excel (`.xlsx`).
- **Critérios de pronto**: Dashboard completo com agenda gráfica, controle financeiro de margem de lucro e exportação funcional.
- **Arquivos/Áreas alteradas**: `app/controllers/AdminController.php`, `app/controllers/FinanceiroController.php`, `app/controllers/PromocaoController.php`, `app/views/admin/`.
- **Observações de dependência**: Depende das Fases 4, 6 e 7.

---

### Fase 9 - Transversais: Logs, Auditoria, E-mails Assíncronos e Polimento Final
- **Objetivo**: Finalizar componentes transversais de segurança, auditoria, automação e refinar a experiência visual.
- **Checklist de tarefas**:
  - [ ] Implementar gravação de `audit_logs`, `security_logs` e `error_logs` (com contingência em arquivo texto `/logs/error.log`);
  - [ ] Configurar PHPMailer com SMTP e script Cron Job `database/cron_lembretes.php` para lembretes de 24h e 2h;
  - [ ] Aplicar todas as diretrizes de acessibilidade e responsividade do [DESIGN.md](DESIGN.md);
  - [ ] Executar bateria de validações contra os 12 Critérios de Aceitação Técnica e Funcional do FSD.
- **Critérios de pronto**: Sistema 100% aderente ao FSD.md e DESIGN.md, sem erros em logs, pronto para deploy no ambiente de produção.
- **Arquivos/Áreas alteradas**: `app/helpers/MailHelper.php`, `database/cron_lembretes.php`, `logs/`, `docs/STATUS.md`.
- **Observações de dependência**: Depende de todas as fases anteriores.
