# DECISÕES TÉCNICAS DO PROJETO

## 1. Documentos recebidos

- **`PRD.md`**: Recebido e totalmente analisado (`c:\Users\CPU\Desktop\projeto agendas para barbearia\prd.md`).
- **`DESIGN.md`**: Recebido e totalmente analisado (`c:\Users\CPU\Desktop\projeto agendas para barbearia\stitch_aesthetic_barber_dashboard\elevated_grooming_wellness\DESIGN.md`).
- **Observações**: Os dois documentos fornecem a base funcional completa (PRD) e as especificações de design system e UI/UX (*Elevated Grooming & Wellness* - tema dark corporativo sofisticado em tons *Midnight Slate* `#0F1416` e destaques *Bronze/Gold* `#E9C176`).

---

## 2. Identificação do sistema

- **Nome do sistema**: AgendaBarber Web
- **Objetivo principal**: Facilitar o agendamento e o pagamento antecipado de serviços de barbearia/estética, eliminando processos manuais, mensagens, conflitos de horários e faltas sem aviso.
- **Público usuário**:
  - Clientes (maiores de 18 anos);
  - Profissionais do estabelecimento;
  - Administrador Principal (proprietário);
  - Administradores Limitados (gerentes/colaboradores com permissões específicas).
- **Contexto de uso**: Atendimento inicial a uma única unidade física de barbearia ou clínica estética. Acesso público via web para clientes realizarem agendamentos e área restrita autenticada para gestão operacional e financeira.
- **Resumo funcional**: O sistema oferece consulta de horários disponíveis com cálculo automatizado de durações e intervalos de 10 minutos, escolha de profissionais/serviços, retenção temporária de vaga por 10 minutos durante o checkout, pagamento obrigatório (Pix QR Code e Cartão de crédito), regras automáticas de cancelamento e reagendamento (janela limite de 24h), gerenciamento de créditos internos (validade de 90 dias), envio de notificações e lembretes (24h e 2h antes via e-mail e painel), programa visual de fidelidade (insígnias Bronze, Prata e Ouro), sistema de avaliações, controle de promoções públicas com compartilhamento, gestão de despesas e faturamento, auditoria de alterações e relatórios com exportação.

---

## 3. Decisões técnicas confirmadas

- **Stack tecnológica**:
  - **Linguagem / Backend**: PHP (versão 8.x estruturado com padrão arquitetural MVC);
  - **Banco de Dados**: MySQL 8.x / MariaDB;
  - **Frontend / Interface**: HTML5 semântico, CSS3 customizado (incorporando os tokens e o design system do `DESIGN.md`), JavaScript puro (Vanilla ES6+) e Bootstrap local para componentes utilitários e grides;
  - **Arquitetura**: Organização estrita inspirada em MVC (Model-View-Controller), garantindo separação total entre regras de acesso a banco de dados, regras de negócio e camada de exibição HTML.
- **Ambiente local de desenvolvimento**:
  - XAMPP no Windows (servidor Apache, PHP 8.x e MySQL/MariaDB com phpMyAdmin).
- **Ambiente de testes / homologação**:
  - Não haverá ambiente online intermediário obrigatório de testes para a v1. O sistema será exaustivamente testado e validado em ambiente local (XAMPP) antes da publicação.
- **Ambiente de produção**:
  - Hospedagem web com suporte a PHP e MySQL na Hostnet. (O procedimento detalhado de deploy e publicação será tratado em etapa posterior do projeto).
- **Autenticação e Segurança**:
  - Autenticação por e-mail e senha hashificada (`password_hash` com algoritmos seguros BCRYPT/Argon2);
  - Controle de Acesso Baseado em Papéis (RBAC) para os 4 perfis de usuário;
  - Proteção contra SQL Injection (uso exclusivo de Prepared Statements com PDO/MySQLi);
  - Proteção contra Cross-Site Scripting (XSS) via sanitização e escape de outputs na View;
  - Proteção contra Cross-Site Request Forgery (CSRF) com tokens únicos por formulário/sessão;
  - Gerenciamento de sessões HTTP seguras (parâmetros `HttpOnly` e `SameSite`).
- **Auditoria de Dados**:
  - Registro de auditoria padrão (`created_at`, `created_by`, `updated_at`, `updated_by`) nos cadastros principais;
  - Tabela dedicada de Histórico/Auditoria (`audit_logs`) para registrar operações sensíveis administrativas (alterações de preços, serviços, horários, bloqueios de agenda, cancelamentos manuais, concessão de descontos e bloqueio de clientes).
- **Soft Delete (Exclusão Lógica)**:
  - Preservação de integridade histórica via coluna `deleted_at` em tabelas principais (Serviços, Profissionais, Clientes, Agendamentos, Promoções, Despesas).
  - Exclusão física definitiva apenas quando exigida por regulamentações estritas (LGPD) e sem pendências de agendamentos ou registros financeiros associados.
- **Logs de Erros e Segurança**:
  - **Log de Erros**: Armazenado em tabela `error_logs` no banco de dados, com contingência em arquivo de texto plano (`/logs/error.log`) localizado fora do diretório público web caso o banco esteja inacessível;
  - **Log de Segurança**: Tabela `security_logs` para auditar falhas repetidas de login, tentativas de acesso negado a rotas restritas, bloqueios automáticos de IP/conta e alterações de permissões de administradores.
- **Configurações Globais do Sistema**:
  - Tabela `system_settings` armazenando opções chave: dados do estabelecimento (nome, endereço, telefone, mapa), regras de antecedentes de agendamento (máx. 30 dias, mín. 2 horas), tolerância de atraso (10 min), tempo de retenção temporária do checkout (10 min), validade do crédito interno (90 dias), credenciais de SMTP e chaves de API do gateway de pagamento.
- **Gerenciamento de Uploads e Anexos**:
  - Armazenamento em diretório local `public/uploads/` com subpastas segregadas (`/perfis/`, `/promocoes/`, `/comprovantes/`);
  - Formatos permitidos: `.jpg`, `.jpeg`, `.png`, `.webp` (imagens) e `.pdf` (comprovantes);
  - Limite de tamanho: máximo de 5MB por arquivo;
  - Segurança de uploads: renomeação obrigatória de arquivos via Hash UUID/MD5, checagem estrita de MIME-type e bloqueio de execução de scripts PHP no diretório via arquivo `.htaccess`.
- **Relatórios e Exportação**:
  - Relatórios operacionais e financeiros em tela com filtros de período (dia, semana, mês e intervalo personalizado);
  - Exportação de dados em formatos estruturados CSV / Excel (`.xlsx`) para agendamentos e faturamento/despesas.
- **APIs e Integrações Externas**:
  - **Gateway de Pagamento**: Arquitetura modular voltada para **Mercado Pago** (geração de QR Code Pix dinâmico via Webhook e checkout transparente/redirect para Cartão de Crédito), acompanhado de classe de simulação/mock para testes locais no XAMPP;
  - **Serviço de E-mail**: Integração com **PHPMailer** via SMTP autenticado (Hostnet/customizado) + execução de script via **Cron Job** (Hostnet) ou Agendador de Tarefas (Local) para envio assíncrono de notificações e lembretes automáticos de 24h e 2h de antecedência.

---

## 4. Decisões adotadas por padrão

As decisões técnicas abaixo foram confirmadas a partir das recomendações padrão estabelecidas na metodologia do projeto:

- **Stack tecnológica**: PHP + MySQL + HTML5 + CSS/Bootstrap local + JS Vanilla;
- **Ambiente local**: XAMPP no Windows;
- **Ambiente de testes**: Validação realizada localmente antes da publicação;
- **Ambiente de produção**: Hospedagem PHP/MySQL na Hostnet;
- **Arquitetura obrigatória**: Padrão MVC estruturado;
- **Autenticação**: E-mail e senha com hash seguro e RBAC;
- **Auditoria**: Registro automático de autoria/data + tabela de histórico de alterações administrativas;
- **Soft Delete**: Aplicado em todas as entidades principais do sistema;
- **Logs de Erro e Segurança**: Tabela no banco de dados com contingência de erro em arquivo seguro;
- **Serviço de E-mail**: PHPMailer via SMTP + Cron Job para lembretes automáticos;
- **Gateway de Pagamento**: Mercado Pago (Pix QR Code e Cartão) com simulador Sandbox local;
- **Uploads**: Armazenamento local na hospedagem com sanitização e limite de 5MB.

---

## 5. Stack e ambientes

- **Linguagem Backend**: PHP 8.x
- **Banco de Dados**: MySQL 8.x / MariaDB
- **Tecnologias de Interface**: HTML5, CSS3 (com tokens do `DESIGN.md`), JavaScript Vanilla (ES6+), Bootstrap 5 local
- **Bibliotecas auxiliares backend**: PHPMailer (envio de e-mail), PhpSpreadsheet / Exportador CSV nativo (geração de planilhas)
- **Ambiente Local**: XAMPP (Apache + PHP + MySQL) em ambiente Windows
- **Ambiente de Homologação/Testes**: Execução e homologação local no XAMPP
- **Ambiente de Produção**: Hospedagem Linux/Apache com PHP e MySQL na Hostnet
- **Deploy**: O processo de implantação, migração do banco de dados e configuração de variáveis de ambiente na Hostnet será detalhado no plano de deploy posterior.

---

## 6. Arquitetura obrigatória

- O sistema deverá obrigatoriamente seguir a arquitetura **MVC (Model-View-Controller)**.
- **Model**: Concentra a conexão com o banco de dados MySQL via PDO/Prepared Statements, consultas SQL, validações de integridade e regras de dados.
- **View**: Telas HTML5 estruturadas com componentes Bootstrap e estilos do `DESIGN.md`. Não deve conter SQL nem regras de negócio complexas.
- **Controller**: Intermediário responsável por receber as requisições HTTP, validar permissões de acesso (RBAC), invocar os Models necessários, aplicar regras de negócio e definir a renderização da View ou retorno de resposta JSON (em requisições AJAX).
- A separação de responsabilidades deve ser rigorosamente mantida no `FSD.md`.

---

## 7. Recursos estruturais definidos

1. **Autenticação**: Por e-mail e senha, com lembre-me, recuperação via token enviado por e-mail e expiração de sessão.
2. **RBAC (Controle de Acesso por Perfil)**: Restrição de rotas e botões conforme o perfil do usuário logado.
3. **Auditoria**: Rastreamento de autoria (`created_by`, `updated_by`) e registro de alterações críticas em `audit_logs`.
4. **Soft Delete**: Marcas de exclusão lógica (`deleted_at IS NOT NULL`) em vez de exclusão física permanente.
5. **Log de Erros**: Gravação em `error_logs` no MySQL + fallback em `/logs/error.log` no sistema de arquivos.
6. **Log de Segurança**: Gravação em `security_logs` de falhas de autenticação, tentativas de intrusão e ações administrativas sensíveis.
7. **Configurações Globais**: Painel para editar parâmetros de funcionamento do estabelecimento e credenciais de APIs.
8. **Uploads e Anexos**: Upload seguro em `/uploads/` com restrição de extensões, limite de 5MB e renomeação UUID.
9. **Exportações**: Geração de arquivos CSV/Excel em relatórios financeiros e de agendamentos.
10. **APIs e Integrações**: Gateway de pagamento (Mercado Pago Pix/Cartão + Webhook) e envio de e-mails via SMTP autenticado.

---

## 8. Perfis e permissões em nível alto

- **Cliente**:
  - Acesso à área pública (serviços, profissionais, promoções) e área restrita do cliente;
  - Pode criar conta, editar dados pessoais (com confirmação ao alterar e-mail), solicitar exclusão da conta;
  - Pode agendar serviços, pagar (Pix/Cartão), visualizar comprovantes, reagendar (até 2x com 24h de antecedência), cancelar (com/sem reembolso conforme regra de 24h);
  - Pode consultar créditos internos, visualizar insígnias de fidelidade e avaliar atendimentos concluídos.
- **Profissional**:
  - Acesso à área restrita do profissional;
  - Pode visualizar a própria agenda diária/semanal e detalhes dos clientes agendados com ele;
  - Pode marcar presença, iniciar atendimento, registrar horários reais de início/término e marcar como concluído;
  - Não possui acesso às finanças gerais, configurações globais ou agendas de outros profissionais.
- **Administrador Principal**:
  - Proprietário do estabelecimento com acesso irrestrito a todas as funcionalidades do sistema;
  - Gerencia serviços, preços, profissionais, horários, bloqueios de agenda e exceções de reembolso;
  - Gerencia clientes (bloqueios, desativações, notas internas privadas);
  - Gerencia promoções, faturamento, despesas financeiras, relatórios, auditoria, configurações globais e permissões dos administradores limitados.
- **Administrador Limitado**:
  - Colaborador/gerente com acesso restrito apenas às funcionalidades administrativas explicitamente concedidas pelo Administrador Principal (ex: gerenciar agendamentos e registrar despesas, mas sem acesso a configurações avançadas ou alteração de permissões).

---

## 9. Entidades prováveis em nível alto

O `FSD.md` detalhará os atributos e o modelo relacional completo. As entidades principais identificadas são:

- `usuarios` (id, nome, email, senha_hash, telefone, foto, perfil, status, created_at, updated_at, deleted_at)
- `profissionais` (id, usuario_id, limite_diario_atendimentos, status, deleted_at)
- `servicos` (id, nome, descricao, duracao_minutos, preco, imagem, orientacoes, status, deleted_at)
- `profissional_servicos` (profissional_id, servico_id)
- `horarios_trabalho` (id, profissional_id, dia_semana, hora_inicio, hora_fim, hora_almoco_inicio, hora_almoco_fim)
- `indisponibilidades_bloqueios` (id, profissional_id, data_inicio, data_fim, motivo, tipo)
- `agendamentos` (id, codigo_reserva, cliente_id, profissional_id, data_agendamento, hora_inicio, hora_fim, duracao_total, valor_total, situacao, criado_em, atualizado_em, deleted_at)
- `agendamento_servicos` (agendamento_id, servico_id, preco_aplicado, duracao_aplicada)
- `pagamentos` (id, agendamento_id, forma_pagamento, valor, status_pagamento, transacao_id, qr_code_pix, criado_em, pago_em)
- `creditos_internos` (id, cliente_id, valor, saldo, data_expiracao, agendamento_origem_id)
- `avaliacoes` (id, agendamento_id, cliente_id, profissional_id, nota, comentario, criado_em)
- `promocoes` (id, titulo, descricao, imagem, servico_id, tipo_desconto, valor_desconto, data_inicio, data_fim, limite_total_usos, status)
- `uso_promocoes` (promocao_id, cliente_id, agendamento_id, data_uso)
- `despesas` (id, categoria_id, descricao, valor, data_despesa, comprovante, registrado_por, criado_em)
- `categorias_despesas` (id, nome)
- `system_settings` (chave, valor)
- `audit_logs` (id, usuario_id, acao, entidade, entidade_id, dados_anteriores, dados_novos, data_hora)
- `error_logs` (id, erro_mensagem, arquivo, linha, stack_trace, data_hora)
- `security_logs` (id, usuario_id, ip_address, evento, detalhes, data_hora)

---

## 10. Módulos, telas e fluxos esperados em nível alto

O FSD deverá estruturar e detalhar os seguintes módulos funcionais:

1. **Módulo Institucional & Apresentação**:
   - Página inicial pública com dados do estabelecimento, serviços, profissionais, promoções ativas e localização interativa com botão de mapas.
2. **Módulo Autenticação & Conta do Cliente**:
   - Telas de Login, Cadastro de Cliente (validação de idade +18), Recuperação de Senha e Perfil Pessoal (foto, dados, troca de e-mail e exclusão de conta).
3. **Módulo Agendamento & Checkout de Pagamento**:
   - Fluxo de seleção de serviços múltiplos (soma de preço/duração), escolha de profissional/data/horário disponível;
   - Tela de confirmação com política de cancelamento/reagendamento e retenção temporária de 10 min;
   - Tela de Checkout com Pix (QR Code dinâmico + Copia e Cola) e Cartão de Crédito com atualização via Webhook/Simulador.
4. **Módulo Meus Agendamentos (Área do Cliente)**:
   - Listagem de agendamentos (próximos e passados), visualização de comprovantes, ações de reagendamento (até 2x) e cancelamento (com/sem reembolso conforme regra de 24h), saldo de créditos internos e insígnias de fidelidade.
5. **Módulo Painel do Profissional**:
   - Agenda diária/semanal do profissional, confirmação de presença, registro de horários reais de início/fim e conclusão de atendimentos.
6. **Módulo Agenda & Atendimento Administrativo**:
   - Agenda administrativa em visões Diária, Semanal e Mensal;
   - Agendamento e reagendamento manual com aplicação de desconto justificado;
   - Cancelamento formal com justificativa e acionamento de reembolso/troca de profissional.
7. **Módulo Gestão de Serviços & Profissionais**:
   - Cadastros, edições, preços, durações, imagens de serviços, vinculação serviço-profissional, horários de trabalho e bloqueios de agenda.
8. **Módulo Promoções & Fidelidade**:
   - Criação de promoções públicas com banners e links de compartilhamento;
   - Acompanhamento dos níveis de fidelidade dos clientes (Bronze, Prata, Ouro).
9. **Módulo Gestão de Clientes**:
   - Listagem de clientes, histórico de agendamentos/faltas, notas internas privadas e bloqueio/desativação justificada de contas.
10. **Módulo Controle Financeiro & Despesas**:
    - Registro de receitas automáticas e lançamento de despesas com comprovantes;
    - Cálculo de faturamento, total de despesas e lucro do período.
11. **Módulo Relatórios, Exportações & Auditoria**:
    - Dashboards resumidos do dia;
    - Consultas de relatórios financeiros e agendamentos com exportação CSV/Excel;
    - Painel de consulta ao histórico de alterações (`audit_logs`).
12. **Módulo ConfiguraçõesGlobais & Logs**:
    - Parâmetros do sistema, credenciais de APIs (SMTP/Gateway) e visualização de `error_logs` e `security_logs`.

---

## 11. Alertas para relatórios, consultas, exportações e desempenho

O FSD deverá projetar a criação de **índices de banco de dados (`INDEX`)** para prevenir gargalos de desempenho nas seguintes consultas frequentes:

- **Busca de horários disponíveis**: Índice composto em `agendamentos(profissional_id, data_agendamento, situacao)` para agilizar a checagem de horários ocupados.
- **Bloqueios e indisponibilidades**: Índice em `indisponibilidades_bloqueios(profissional_id, data_inicio, data_fim)`.
- **Histórico do Cliente**: Índices em `agendamentos(cliente_id, data_agendamento)` e `creditos_internos(cliente_id, data_expiracao)`.
- **Dashboards e Relatórios Financeiros**: Índices em `agendamentos(data_agendamento, situacao)` e `despesas(data_despesa)`.
- **Registros de Pagamento**: Índice em `pagamentos(transacao_id)` para recepção ágil de Webhooks do Mercado Pago.
- **Registros com Soft Delete**: Índice nas colunas `deleted_at` das tabelas principais.

---

## 12. Alertas para uploads, anexos e arquivos

O FSD deverá explicitar as seguintes diretivas de segurança para manipulação de arquivos:

- **Estrutura de diretórios**: Pastas locais `public/uploads/perfis/`, `public/uploads/promocoes/` e `public/uploads/comprovantes/`.
- **Sanitização de nomes**: Todo arquivo enviado deve ser renomeado via algoritmo de Hash único (UUID/MD5 + timestamp) para evitar sobrescritas ou ataques de Path Traversal.
- **Validação Server-Side**: O backend PHP deve verificar obrigatoriamente a extensão e o tipo MIME real do arquivo (`finfo_file`), rejeitando arquivos executáveis (ex: `.php`, `.exe`, `.sh`, `.js`).
- **Proteção do diretório**: Inclusão de arquivo `.htaccess` dentro do diretório `/uploads/` desativando a execução de scripts PHP (`php_flag engine off` ou bloqueio de handlers).

---

## 13. Alertas para logs, auditoria e segurança

- **Log de Erros com Contingência**: O FSD deve prever que a função/classe de log tente gravar primeiro na tabela `error_logs`. Caso ocorra exceção na conexão com o MySQL, a classe deve redirecionar a mensagem para a gravação no arquivo seguro `/logs/error.log`.
- **Ocultação de dados sensíveis em logs**: Nenhuma senha, token de cartão ou chave secreta pode ser gravada em texto limpo nos arquivos ou tabelas de log.
- **Eventos de Log de Segurança**: Monitoramento e registro obrigatório para:
  - Falha de login por e-mail/senha;
  - Tentativas de acesso a rotas sem permissão no RBAC;
  - Bloqueio automático de IP/conta por excesso de tentativas incorretas;
  - Alterações no perfil de administradores ou concessão de permissões.

---

## 14. Itens que não devem ser inventados (Fora de Escopo Técnico da v1)

Para assegurar o cumprimento do escopo estipulado no `PRD.md`, os itens abaixo **NÃO devem ser incluídos ou inventados** no `FSD.md`:

- Aplicativo mobile nativo (iOS/Android);
- Multi-unidades / suporte a múltiplas filiais;
- Integração de notificações ou envio via WhatsApp;
- Lista de espera automática para horários ocupados;
- Emissão automática de Nota Fiscal (NFe/NFSe);
- Benefícios ou descontos automáticos ligados ao programa de fidelidade (as insígnias Bronze, Prata e Ouro são estritamente visuais nesta v1);
- Promoções segmentadas ou exclusivas para públicos específicos;
- Módulo de controle de estoque de produtos;
- Cálculo de comissão de profissionais ou folha de pagamento;
- Marketplace ou busca entre múltiplos estabelecimentos;
- Recursos de Inteligência Artificial para sugestão de horários/preços;
- Precificação dinâmica baseada em demanda ou horário;
- Sincronização com calendários externos (Google Calendar / Outlook);
- Venda de planos, pacotes de serviços ou assinaturas recorrentes.

---

## 15. Pendências não bloqueantes

Não foram identificadas pendências não bloqueantes para a criação do FSD. Todas as dúvidas de negócio e definições técnicas essenciais foram alinhadas e consolidadas.

---

## 16. Pronto para o FSD

As decisões técnicas e estruturais do projeto **AgendaBarber Web** estão totalmente consolidadas e preparadas para a próxima fase.

O próximo passo do fluxo de desenvolvimento será a geração do documento de especificação funcional detalhado:

`docs/FSD.md`

O `FSD.md` será construído utilizando como insumos obrigatórios:
1. `PRD.md` (`c:\Users\CPU\Desktop\projeto agendas para barbearia\prd.md`)
2. `DECISOES_TECNICAS.md` (`c:\Users\CPU\Desktop\projeto agendas para barbearia\DECISOES_TECNICAS.md`)
3. `DESIGN.md` (`c:\Users\CPU\Desktop\projeto agendas para barbearia\stitch_aesthetic_barber_dashboard\elevated_grooming_wellness\DESIGN.md`)
