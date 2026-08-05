# Status do Projeto - AgendaBarber Web

**Última atualização**: 05/08/2026  
**Estado Atual**: Fase 7 (Áreas Restritas do Cliente e do Profissional) Concluída. Painel "Meus Agendamentos" com comprovante, reagendamento e cancelamento sob a regra de 24h, créditos internos, insígnias de fidelidade, avaliações e painel de operação do profissional. Baterias de teste: 134 (front-end) + 111 + 149 + 128 + 153 + 132 + 154 = **961 testes passando**.

### Aplicativo instalável (PWA) — 05/08/2026
O sistema pode ser instalado na tela inicial do celular e abre sem a barra do navegador.
- `manifest.webmanifest` gerado por PHP (`PwaController`), para que `start_url` e `scope` acompanhem o diretório de instalação;
- `sw.js` na raiz do projeto, com cache dos estáticos e página própria de "sem conexão". **Não** faz cache de HTML nem de API: agenda e pagamentos vêm sempre do servidor;
- Banner de convite que respeita a dispensa por 14 dias e some para quem já instalou;
- No iPhone, onde não existe instalador automático, exibe as instruções de "Compartilhar → Adicionar à Tela de Início".

**Requisito de produção**: Service Worker só funciona em HTTPS (localhost é exceção). Sem certificado, a instalação não é oferecida.

### Otimização de imagens — 05/08/2026
As insígnias e o logotipo eram PNGs de 1024px pesando 1,8 MB cada, exibidos a 96px e 40px. Redimensionados para 256px: **8,76 MB → 383 KB (96% menor)**. A bateria de front-end passou a impor um teto de 150 KB por imagem.

> **Correção crítica em 05/08/2026**: o `bootstrap.bundle.min.js` era um placeholder de 175 bytes desde a Fase 1, deixando inertes todos os botões dependentes de Bootstrap (cancelar, concluir atendimento, abas do checkout, menu mobile). Instalado o Bootstrap 5.3.3 real, criado o versionamento de assets contra cache e adicionada a bateria `tests/teste_frontend.php`. Detalhes em [ERROS.md](ERROS.md).

---

## Progresso das Fases (`docs/PLANO.md`)

- [x] **Fase 1 - Infraestrutura e Base do Projeto** *(Concluída em 02/08/2026)*
- [x] **Fase 2 - Banco de Dados e Sistema de Migrations** *(Concluída em 04/08/2026)*
- [x] **Fase 3 - Módulo de Autenticação, Sessões e Controle de Acesso (RBAC)** *(Concluída em 04/08/2026)*
- [x] **Fase 4 - Cadastros Administrativos Base (Serviços, Profissionais e Bloqueios)** *(Concluída em 04/08/2026)*
- [x] **Fase 5 - Motor de Disponibilidade e Fluxo de Agendamento Público** *(Concluída em 04/08/2026)*
- [x] **Fase 6 - Gateway de Pagamentos, Webhooks e Retenção Temporária** *(Concluída em 05/08/2026)*
- [x] **Fase 7 - Áreas Restritas do Cliente e do Profissional** *(Concluída em 05/08/2026)*
- [ ] **Fase 8 - Painel Administrativo Operacional, Financeiro e Promoções**
- [ ] **Fase 9 - Transversais: Logs, Auditoria, E-mails Assíncronos e Polimento Final**

---

## Detalhamento da Fase Atual e Próximo Passo

- **Fase Concluída**: Fase 7 - Áreas Restritas do Cliente e do Profissional
- **Fase Pendente**: **Fase 8 - Painel Administrativo Operacional, Financeiro e Promoções**
- **Próximo Passo Recomendado**: Implementar a agenda administrativa (diária, semanal e mensal), o agendamento manual com desconto justificado, o controle de despesas com comprovantes, o cálculo de lucro líquido, as promoções públicas e a exportação em CSV/Excel.

---

## Entregas da Fase 7 (05/08/2026)

### Arquivos criados
| Arquivo | Função |
|---|---|
| `app/models/CreditoModel.php` | Créditos internos com validade de 90 dias |
| `app/models/AvaliacaoModel.php` | Avaliações de 1 a 5 estrelas |
| `app/models/NotaClienteModel.php` | Observações privadas da equipe |
| `app/views/cliente/agendamentos.php` | Painel com próximos, histórico, créditos e fidelidade |
| `app/views/cliente/comprovante.php` | Comprovante com folha de estilo de impressão |
| `app/views/cliente/reagendar.php` | Reagendamento com troca de serviços |
| `app/views/profissional/agenda.php` | Operação diária dos atendimentos |
| `app/views/profissional/notas.php` | Notas privadas sobre o cliente |
| `tests/teste_fase7.php` | 154 testes de validação da Fase 7 |

`AgendamentoModel` ganhou as operações de cancelamento, reagendamento, aplicação de crédito, fidelidade e agenda do profissional. `ClienteController` e `ProfissionalController` foram reescritos.

### Rotas adicionadas
`/cliente/agendamento/{codigo}/comprovante` · `/cliente/agendamento/{codigo}/reagendar` · `/cliente/reagendar` · `/cliente/cancelar` · `/cliente/avaliar` · `/cliente/credito` · `/profissional/presenca` · `/profissional/concluir` · `/profissional/falta` · `/profissional/cliente/{id}/notas` · `/profissional/nota`

### Regras de negócio implementadas
- **Cancelamento com 24h ou mais**: reembolso integral, com estorno acionado no gateway; a situação final passa a `reembolsado`;
- **Cancelamento com menos de 24h**: sem reembolso, situação `cancelado`;
- **Crédito interno utilizado sempre volta ao cliente**, mesmo no cancelamento fora do prazo, por não ser dinheiro do estabelecimento;
- **Reagendamento**: mínimo de 24h de antecedência e limite de 2 por agendamento; valor menor gera crédito, valor maior devolve o agendamento a `aguardando_pagamento` cobrando **apenas a diferença**;
- **Créditos**: validade de 90 dias, consumo parcial e sempre a partir do que vence primeiro;
- **Fidelidade**: Bronze 0-4, Prata 5-9, Ouro 10+ atendimentos concluídos;
- **Avaliação**: 1 a 5 estrelas, apenas em atendimentos concluídos e uma única vez;
- **Falta**: só pode ser registrada após esgotada a tolerância de atraso;
- **Notas privadas**: o profissional só acessa as de clientes que já atendeu.

### Como validar
```
C:\xampp\php\php.exe tests/teste_fase7.php
```

### Decisão de interpretação do FSD
O FSD (Fluxo 2) descreve o cancelamento com reembolso deixando o agendamento em `cancelado`. Como o ENUM prevê também `reembolsado`, o sistema passa a usar esse estado quando o estorno é efetivado, distinguindo o cancelamento com devolução do cancelamento sem direito a reembolso. Para tratar os dois casos como `cancelado`, o ajuste é pontual em `PagamentoModel::estornar()`.

---

## Entregas da Fase 6 (05/08/2026)

### Arquivos criados
| Arquivo | Função |
|---|---|
| `app/gateways/GatewayPagamento.php` | Contrato do provedor de pagamentos |
| `app/gateways/SandboxGateway.php` | Simulador local, sem chamadas externas |
| `app/gateways/MercadoPagoGateway.php` | API REST v1 por cURL, sem SDK externo |
| `app/gateways/GatewayFactory.php` | Escolhe o provedor conforme `sandbox_mode` |
| `app/models/PagamentoModel.php` | Transações e confirmação idempotente |
| `app/controllers/CheckoutController.php` | Pix, cartão, polling de status e simulação |
| `app/controllers/WebhookController.php` | Notificações do gateway |
| `public/assets/js/checkout.js` | Cronômetro, cópia do Pix, polling e tokenização |
| `tests/teste_fase6.php` | 132 testes de validação da Fase 6 |

### Rotas adicionadas
`/checkout/{codigo}` · `/checkout/{codigo}/pix` · `/checkout/{codigo}/cartao` · `/checkout/{codigo}/status` · `/checkout/{codigo}/simular` (só em sandbox) · `/webhook/mercadopago`

### Segurança do fluxo de pagamento
- O status **nunca** vem do corpo do webhook: é reconsultado na API do provedor, de modo que forjar um POST não confirma agendamento;
- Assinatura `x-signature` validada por HMAC-SHA256; sem `webhook_secret` configurado, toda notificação é recusada;
- Confirmação idempotente, decidida pelo estado do agendamento (o Mercado Pago reenvia a mesma notificação até receber 200);
- Dados do cartão tokenizados no navegador: número, CVV e validade nunca chegam ao servidor nem ao banco;
- Chave de idempotência enviada ao Mercado Pago, evitando cobrança duplicada por reenvio de requisição;
- Um cliente não acessa o checkout de outro (403 + registro em `security_logs`).

### Como testar o pagamento localmente
Com `'sandbox_mode' => true`, nenhum pagamento real é processado. Na tela de checkout:
1. **Pix**: clique em "Gerar QR Code Pix" e depois em "Simular Pagamento Aprovado";
2. **Cartão**: escolha o resultado desejado (aprovado, recusado ou em análise) e envie.

### Antes de ir para produção
1. Preencher `access_token`, `public_key` e `webhook_secret` em `config/config.php`;
2. Trocar `'sandbox_mode'` para `false`;
3. Cadastrar a URL `https://SEU-DOMINIO/webhook/mercadopago` no painel do Mercado Pago;
4. Inicializar o SDK JS do Mercado Pago no checkout para a tokenização real do cartão (ver pendência abaixo).

### Pendência conhecida
A tokenização real do cartão exige o SDK JavaScript do Mercado Pago, que **precisa** ser carregado do domínio deles — não pode ser hospedado localmente. Isso conflita com a regra do `docs/FSD.md` seção 3 de não depender de CDN. É a única exceção necessária, e vale apenas para a aba de cartão em produção; Pix e todo o restante do sistema seguem 100% locais.

### Como validar
```
C:\xampp\php\php.exe tests/teste_fase6.php
```

---

## Entregas da Fase 5 (04/08/2026)

### Arquivos criados
| Arquivo | Função |
|---|---|
| `app/models/AgendamentoModel.php` | Motor de disponibilidade e criação da reserva temporária |
| `app/models/SettingModel.php` | Leitura em cache de `system_settings` com padrões |
| `app/controllers/AgendamentoController.php` | Vitrine, wizard em 3 passos, API de horários e checkout |
| `app/views/public/home.php` | Vitrine com serviços, equipe, endereço e contato |
| `app/views/public/agendar_servicos.php` | Passo 1 com soma dinâmica de valor e duração |
| `app/views/public/agendar_horario.php` | Passo 2 com grade de horários via AJAX |
| `app/views/public/agendar_revisao.php` | Passo 3 com resumo, orientações e políticas |
| `app/views/public/checkout.php` | Comprovante da reserva e cronômetro regressivo |
| `public/assets/js/agendamento.js` | Soma dinâmica, Fetch da grade e cronômetro |
| `database/migrations/004_add_passo_agendamento.php` | Passo da grade de horários (15 min) |
| `tests/teste_fase5.php` | 153 testes de validação da Fase 5 |

### Rotas adicionadas
`/agendar` · `/agendar/servicos` · `/agendar/horario` · `/agendar/revisao` · `/agendar/confirmar` · `/api/horarios` · `/checkout/{codigo}`

O roteador passou a aceitar **parâmetros dinâmicos** (`{codigo}`), necessários para a URL de checkout definida no FSD.

### Regras do motor de disponibilidade
- Soma das durações dos serviços selecionados;
- Intervalo obrigatório de 10 minutos entre atendimentos (nos dois sentidos);
- Jornada semanal com o intervalo de almoço recortado da janela;
- Bloqueios individuais e de todo o estabelecimento, inclusive parciais;
- Reservas ainda no prazo ocupam horário; reservas vencidas liberam automaticamente;
- Limite diário de atendimentos por profissional;
- Antecedência mínima de 2 horas e máxima de 30 dias;
- "Qualquer profissional disponível" atribui o primeiro livre a cada horário;
- Apenas profissionais que executam **todos** os serviços escolhidos são oferecidos;
- Preços e durações são **congelados** em `agendamento_servicos` na contratação.

### Como validar
```
C:\xampp\php\php.exe tests/teste_fase5.php
```

### Pendência conhecida
Para o fluxo público gerar horários é necessário **cadastrar ao menos um profissional** com jornada semanal e serviços vinculados em `/admin/profissionais`. O banco de desenvolvimento hoje possui apenas os 6 serviços do seeding e o administrador padrão.

---

## Entregas da Fase 4 (04/08/2026)

### Arquivos criados
| Arquivo | Função |
|---|---|
| `app/models/ServicoModel.php` | CRUD de serviços, Soft Delete, restauração e vitrine |
| `app/models/ProfissionalModel.php` | Profissionais, vínculo N:N com serviços e jornada semanal |
| `app/models/BloqueioModel.php` | Bloqueios de agenda e detecção de conflitos |
| `app/models/AuditModel.php` | Gravação em `audit_logs` com autoria, IP e antes/depois |
| `app/controllers/ServicoController.php` | Telas e ações do CRUD de serviços |
| `app/controllers/AdminController.php` | Ampliado com profissionais, jornada e bloqueios |
| `app/views/admin/servicos.php` | Formulário, filtros, tabela e restauração |
| `app/views/admin/profissionais.php` | Cadastro, serviços executados e grade semanal |
| `app/views/admin/bloqueios.php` | Cadastro e listagem de bloqueios |
| `tests/teste_fase4.php` | 128 testes de validação da Fase 4 |

### Rotas adicionadas
`/admin/servicos` (+ `salvar`, `excluir`, `restaurar`) · `/admin/profissionais` (+ `salvar`, `excluir`, `servicos`, `horarios`) · `/admin/bloqueios` (+ `salvar`, `excluir`)

### Decisão de permissão (matriz do FSD seção 8)
- **Serviços e Profissionais**: exclusivos do Administrador Principal;
- **Bloqueios de agenda**: liberados também para o Administrador Limitado.

### Auditoria
Alterações de preço, jornada, vínculos de serviço e bloqueios gravam em `audit_logs` com o estado anterior e o novo. A tela de consulta vem na Fase 9.

### Como validar
```
C:\xampp\php\php.exe tests/teste_fase4.php
```

### Pendências conhecidas
Os itens de menu `/admin/agenda` e `/admin/financeiro` já aparecem na sidebar mas respondem 404 — serão implementados nas Fases 5 e 8.

---

## Entregas da Fase 3 (04/08/2026)

### Arquivos criados
| Arquivo | Função |
|---|---|
| `app/bootstrap.php` | Autoloader nativo por convenção de diretório |
| `app/core/Router.php` | Normalização de rotas e despacho (404 / 405) |
| `app/controllers/BaseController.php` | Render, redirect, flash, CSRF e sanitização |
| `app/controllers/AuthController.php` | Login, cadastro, logout, recuperação e confirmação de e-mail |
| `app/controllers/PerfilController.php` | Dados, senha, troca de e-mail e exclusão de conta |
| `app/controllers/HomeController.php` | Vitrine pública e 404 |
| `app/controllers/{Cliente,Profissional,Admin}Controller.php` | Painéis por perfil protegidos por RBAC |
| `app/models/BaseModel.php` | Acesso PDO comum aos Models |
| `app/models/UsuarioModel.php` | Contas, hash de senha, tokens e Soft Delete |
| `app/models/LogModel.php` | `security_logs`, `error_logs` e bloqueio por tentativas |
| `app/helpers/AuthHelper.php` | Middleware RBAC (reescrito) |
| `app/.htaccess` | Proteção da pasta `app/` (faltava desde a Fase 1) |
| `database/migrations/003_add_email_change_tokens.php` | Colunas de confirmação de troca de e-mail |
| Views | `public/`, `cliente/`, `profissional/`, `admin/` e `errors/` |
| `tests/HttpClient.php` | Cliente HTTP com sessão para testes de integração |
| `tests/teste_fase3.php` | 149 testes de validação da Fase 3 |

### Rotas disponíveis
`/` · `/login` · `/cadastro` · `/logout` · `/recuperar-senha` · `/redefinir-senha` · `/confirmar-email` · `/perfil` (+ `/perfil/dados`, `/perfil/senha`, `/perfil/email`, `/perfil/excluir`) · `/cliente/agendamentos` · `/profissional/agenda` · `/admin/dashboard` · `/admin/auditoria`

### Regras de segurança implementadas
- Hash BCRYPT com `password_hash()` / `password_verify()`;
- `session_regenerate_id(true)` no login (anti Session Fixation);
- Token CSRF obrigatório em 100% dos POST;
- Bloqueio de 15 minutos após 5 tentativas de login malsucedidas;
- Token de recuperação de senha e de troca de e-mail com validade de 1 hora e uso único;
- Mensagens genéricas no login e na recuperação (não revelam se o e-mail existe);
- Proteção contra open redirect no retorno pós-login;
- Registro em `security_logs` de login, falhas, CSRF inválido e violações de RBAC.

### Como validar
```
C:\xampp\php\php.exe tests/teste_fase3.php
```
Requer **Apache e MySQL do XAMPP em execução** (os testes fazem requisições HTTP reais).

---

## Entregas da Fase 2 (04/08/2026)

### Arquivos criados
| Arquivo | Função |
|---|---|
| `app/helpers/Database.php` | Conexão PDO Singleton (ERRMODE_EXCEPTION, emulação de prepares desligada) |
| `database/MigrationRunner.php` | Motor de migrations com controle via `schema_migrations` |
| `database/migrate.php` | Entrada CLI (`php database/migrate.php` e `--status`), bloqueada para navegador |
| `database/migrations/001_create_system_tables.php` | As 20 tabelas do FSD com PKs, FKs e índices |
| `database/migrations/002_seed_initial_data.php` | Admin padrão, 10 categorias, 6 serviços e 17 configurações globais |
| `tests/TestRunner.php` | Micro-framework de asserções para os testes de fase |
| `tests/teste_fase2.php` | 111 testes de validação da Fase 2 |
| `tests/.htaccess` | Bloqueio de acesso público à pasta de testes |

### Credenciais iniciais (trocar no primeiro acesso)
- **E-mail**: `admin@agendabarber.local`
- **Senha**: `Admin@123` (gravada com `password_hash()` BCRYPT)

### Como validar
```
C:\xampp\php\php.exe database/migrate.php --status
C:\xampp\php\php.exe tests/teste_fase2.php
```

### Ambiente
- MariaDB 10.4.32 (XAMPP) — precisa estar iniciado antes de rodar as migrations.
- PHP 8.0.30 em `C:\xampp\php\php.exe` (não está no PATH do sistema).
