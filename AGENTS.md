# Diretrizes de Contexto e Operação para Agentes de IA - AgendaBarber Web

Este arquivo orienta qualquer agente de IA que atue no desenvolvimento do projeto **AgendaBarber Web**.

---

## 1. Idioma e Comunicação
- Responda sempre em **português do Brasil**.
- Mantenha tom técnico, objetivo, claro e profissional.

---

## 2. Stack Tecnológica e Arquitetura (baseado no `docs/FSD.md`)
- **Linguagem / Backend**: PHP 8.x estruturado com padrão arquitetural MVC nativo (Model-View-Controller), sem frameworks pesados de terceiros.
- **Banco de Dados**: MySQL 8.x / MariaDB.
- **Frontend / Interface**: HTML5 semântico, CSS3 customizado fundamentado nos tokens do `docs/DESIGN.md`, JavaScript Vanilla (ES6+) para dinamismo/AJAX Fetch API e Bootstrap 5 (CSS/JS locais).
- **Bibliotecas Backend**: PHPMailer (em `vendor/` ou carregado localmente) e PhpSpreadsheet/exportador CSV nativo.
- **Padrão Arquitetural**: MVC nativo em PHP com Front Controller (`index.php`) e Prepared Statements PDO em 100% das operações de banco de dados.

---

## 3. Ambientes do Projeto
- **Desenvolvimento Local**: Windows executado no XAMPP (Apache, PHP 8.x, MySQL/MariaDB) sob a URL local `http://localhost/agenda-barber/`.
- **Homologação / Testes**: Validações funcionais realizadas localmente no XAMPP.
- **Produção**: Hospedagem Linux/Apache com PHP 8.x e MySQL na Hostnet.

---

## 4. Estrutura de Diretórios da Aplicação
- `index.php`: Ponto de entrada único (Front Controller).
- `config/`: Arquivo de configurações PHP `config.php` e `.htaccess` (acesso bloqueado).
- `app/controllers/`: Controllers da aplicação (tratam requisições, RBAC e renderização).
- `app/models/`: Models da aplicação (acesso exclusivo a dados via PDO).
- `app/views/`: Views HTML/PHP organizadas em `layouts/`, `public/`, `cliente/`, `profissional/` e `admin/`.
- `app/helpers/`: Classes utilitárias (`SessionHelper`, `AuthHelper`, `MailHelper`, `UploadHelper`, `FormatHelper`).
- `database/`: Scripts de migração em `migrations/` e runner `migrate.php`.
- `logs/`: Arquivos de log local (`error.log`) protegidos por `.htaccess`.
- `public/`: Arquivos estáticos acessíveis diretamente (`assets/css/`, `assets/js/`, `assets/vendor/`, `assets/img/`, `uploads/`).
- `vendor/`: Dependências locais em PHP.

---

## 5. Regras Específicas de Segurança
- **SQL Injection**: Uso obrigatório e irrestrito de Prepared Statements PDO com bind de parâmetros em todas as consultas.
- **XSS**: Aplicação de `htmlspecialchars($dado, ENT_QUOTES, 'UTF-8')` em toda saída de dado dinâmico renderizado em Views HTML.
- **CSRF**: Inclusão de token CSRF (`<input type="hidden" name="csrf_token">`) em todos os formulários HTTP `POST` e validação nos Controllers.
- **Armazenamento de Senhas**: Hash seguro com `password_hash()` (BCRYPT ou Argon2) e verificação com `password_verify()`.
- **Sessões HTTP**: Configuração estrita de cookies de sessão com atributos `HttpOnly`, `SameSite=Lax` e renovação de ID no login.
- **Controle de Acesso (RBAC)**: Validação centralizada no Controller/Helper do papel do usuário (`cliente`, `profissional`, `admin_principal`, `admin_limitado`).
- **Proteção de Diretórios Internos**: Arquivos `.htaccess` com `Require all denied` nas pastas `config/`, `app/`, `database/`, `logs/` e `vendor/`.
- **Uploads Seguros**: Restrição de extensoes (.jpg, .jpeg, .png, .webp, .pdf), validação real de MIME-type via `finfo_file`, limite de 5MB, renomeação por Hash UUID e proteção via `.htaccess` (`php_flag engine off`) em `public/uploads/`.
- **Tratamento de Logs**: Gravação em `error_logs` no MySQL com fallback em `/logs/error.log` no sistema de arquivos. Dados sensíveis (senhas, tokens) jamais devem ser gravados em texto limpo.

---

## 6. Interface e Design System
- Seguir estritamente o guia `docs/DESIGN.md`.
- Usar a paleta *Midnight Slate* (`#0F1416`) para superfícies dark corporativas e *Bronze/Gold* (`#E9C176`) para acentos e ações primárias.
- Utilizar a tipografia **Inter** e Bootstrap 5 local para a estrutura de grides e modais.

---

## 7. Protocolo dos Arquivos Vivos

Antes de iniciar qualquer trabalho:
1. Ler `docs/FSD.md`.
2. Ler `docs/DESIGN.md`.
3. Ler `docs/INSUMOS.md`.
4. Ler `docs/PLANO.md`.
5. Ler `docs/STATUS.md`.
6. Ler `docs/ERROS.md`.

Use sempre caminhos relativos à raiz do projeto.
Não transformar estes caminhos em links absolutos.
Não usar links `file:///`.
Não registrar caminhos locais da máquina atual dentro do `AGENTS.md`.

Ao terminar qualquer trabalho:
1. Atualizar `docs/STATUS.md`.
2. Registrar erros e soluções em `docs/ERROS.md`, se houver.
3. Informar ao usuário o que foi feito.
4. Informar como testar ou validar a entrega.

---

## 8. Boas Práticas de Código
- Código limpo, legível e modular.
- Respeitar a separação arquitetural do MVC nativo.
- Nomes explicativos em português para variáveis e métodos quando apropriado ao domínio do sistema.
- Não implementar nada fora do escopo definido no `docs/FSD.md`.
