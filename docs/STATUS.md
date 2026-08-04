# Status do Projeto - AgendaBarber Web

**Última atualização**: 04/08/2026  
**Estado Atual**: Fase 2 (Banco de Dados e Sistema de Migrations) Concluída. Banco `agendabarber_db` criado integralmente por migrations versionadas, com as 20 tabelas do FSD, chaves estrangeiras, índices e dados iniciais. Bateria de 111 testes automatizados passando.

---

## Progresso das Fases (`docs/PLANO.md`)

- [x] **Fase 1 - Infraestrutura e Base do Projeto** *(Concluída em 02/08/2026)*
- [x] **Fase 2 - Banco de Dados e Sistema de Migrations** *(Concluída em 04/08/2026)*
- [ ] **Fase 3 - Módulo de Autenticação, Sessões e Controle de Acesso (RBAC)**
- [ ] **Fase 4 - Cadastros Administrativos Base (Serviços, Profissionais e Bloqueios)**
- [ ] **Fase 5 - Motor de Disponibilidade e Fluxo de Agendamento Público**
- [ ] **Fase 6 - Gateway de Pagamentos, Webhooks e Retenção Temporária**
- [ ] **Fase 7 - Áreas Restritas do Cliente e do Profissional**
- [ ] **Fase 8 - Painel Administrativo Operacional, Financeiro e Promoções**
- [ ] **Fase 9 - Transversais: Logs, Auditoria, E-mails Assíncronos e Polimento Final**

---

## Detalhamento da Fase Atual e Próximo Passo

- **Fase Concluída**: Fase 2 - Banco de Dados e Sistema de Migrations
- **Fase Pendente**: **Fase 3 - Módulo de Autenticação, Sessões e Controle de Acesso (RBAC)**
- **Próximo Passo Recomendado**: Criar `UsuarioModel.php`, `AuthController.php`, rotas de login/cadastro/logout/recuperação de senha, middleware RBAC no `AuthHelper.php` e as Views públicas de autenticação.

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
