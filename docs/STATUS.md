# Status do Projeto - AgendaBarber Web

**Última atualização**: 04/08/2026  
**Estado Atual**: Fase 3 (Autenticação, Sessões e RBAC) Concluída. Sistema com roteamento MVC completo, login unificado, cadastro público, recuperação de senha, perfil do usuário e controle de acesso por perfil. Baterias de teste: 111 (Fase 2) + 149 (Fase 3) = **260 testes passando**.

---

## Progresso das Fases (`docs/PLANO.md`)

- [x] **Fase 1 - Infraestrutura e Base do Projeto** *(Concluída em 02/08/2026)*
- [x] **Fase 2 - Banco de Dados e Sistema de Migrations** *(Concluída em 04/08/2026)*
- [x] **Fase 3 - Módulo de Autenticação, Sessões e Controle de Acesso (RBAC)** *(Concluída em 04/08/2026)*
- [ ] **Fase 4 - Cadastros Administrativos Base (Serviços, Profissionais e Bloqueios)**
- [ ] **Fase 5 - Motor de Disponibilidade e Fluxo de Agendamento Público**
- [ ] **Fase 6 - Gateway de Pagamentos, Webhooks e Retenção Temporária**
- [ ] **Fase 7 - Áreas Restritas do Cliente e do Profissional**
- [ ] **Fase 8 - Painel Administrativo Operacional, Financeiro e Promoções**
- [ ] **Fase 9 - Transversais: Logs, Auditoria, E-mails Assíncronos e Polimento Final**

---

## Detalhamento da Fase Atual e Próximo Passo

- **Fase Concluída**: Fase 3 - Módulo de Autenticação, Sessões e Controle de Acesso (RBAC)
- **Fase Pendente**: **Fase 4 - Cadastros Administrativos Base (Serviços, Profissionais e Bloqueios)**
- **Próximo Passo Recomendado**: Criar `ServicoModel.php` / `ProfissionalModel.php`, o CRUD de serviços com upload de imagem, o vínculo N:N profissional-serviços, a grade horária semanal (`horarios_trabalho`) e a gestão de bloqueios (`indisponibilidades_bloqueios`).

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
