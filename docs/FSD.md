# DOCUMENTO DE ESPECIFICAÇÃO FUNCIONAL (FSD)

## 1. Visão Geral

O **AgendaBarber Web** é um sistema web integrado para agendamento de horários e gestão operacional e financeira voltado a barbearias e estabelecimentos de estética.

### Objetivo Principal
O sistema tem como objetivo automatizar o processo de agendamento e cobrança antecipada de serviços, oferecendo autonomia para o cliente escolher serviços, profissionais, datas e horários, enquanto elimina agendamentos manuais por mensagens, conflitos de agenda, atrasos e faltas sem aviso prévio.

### Resumo do Funcionamento
O cliente acessa a interface pública, seleciona um ou mais serviços, escolhe o profissional de sua preferência (ou qualquer profissional disponível), define data e horário livres, concorda com as políticas do estabelecimento e realiza o pagamento obrigatório via Pix ou Cartão de Crédito. Durante o checkout, o horário permanece reservado temporariamente por 10 minutos.

Uma vez aprovado o pagamento, o agendamento é confirmado e notificações de confirmação e comprovante são enviadas por e-mail e exibidas no painel. O sistema gerencia reagendamentos e cancelamentos respeitando a regra limite de 24 horas de antecedência. Na área administrativa e do profissional, o sistema oferece controle visual de agenda (visões diária, semanal e mensal), acompanhamento de atendimentos, lançamento de despesas, controle de faturamento e lucro, publicação de promoções públicas com links de compartilhamento, gestão de insígnias visuais de fidelidade (Bronze, Prata e Ouro), histórico de auditoria e relatórios exportáveis em planilha.

### Público Usuário
- **Cliente**: Maior de 18 anos que agenda, paga, consulta comprovantes, reagenda e avalia atendimentos;
- **Profissional**: Barbeiro ou esteticista que visualiza a própria agenda e registra a conclusão dos atendimentos;
- **Administrador Principal**: Proprietário do estabelecimento com acesso irrestrito a configurações, finanças, relatórios, cadastros e permissões;
- **Administrador Limitado**: Colaborador ou gerente com permissões administrativas parciais delimitadas pelo Administrador Principal.

### Contexto de Uso
O sistema será operado inicialmente em uma única unidade física. O acesso é totalmente web, sem necessidade de instalação de softwares locais por parte dos clientes ou profissionais.

---

## 2. Documentos do Projeto para Implementação

Para a implementação completa do sistema, a IA codificadora e os desenvolvedores deverão utilizar exclusivamente os seguintes documentos:

1. `docs/FSD.md` (Este documento de especificação funcional e técnica);
2. `docs/DESIGN.md` (Guia de interface, tokens de design, componentes e padrão visual *Elevated Grooming & Wellness*).

Este FSD consolida de forma autossuficiente todas as regras funcionais, requisitos de negócio, arquitetura, modelo de dados, rotas, segurança e diretrizes técnicas. Não é necessária a consulta a documentos anteriores ou transcrições de conversas para a execução da codificação.

---

## 3. Stack Definida

- **Linguagem de Programação**: PHP (versão 8.x estruturado com padrão arquitetural MVC nativo, sem uso de frameworks pesados de terceiros);
- **Banco de Dados**: MySQL 8.x / MariaDB;
- **Tecnologias de Interface**:
  - HTML5 semântico;
  - CSS3 customizado estruturado a partir dos tokens definidos no `docs/DESIGN.md` (paleta *Midnight Slate* `#0F1416` e *Bronze/Gold* `#E9C176`);
  - JavaScript Vanilla (ES6+) para interatividade, máscaras de formulário, contagem regressiva de checkout e requisições assíncronas (Fetch API);
  - Bootstrap 5 (arquivos CSS/JS armazenados localmente no repositório) para suporte ao sistema de grides e modais;
- **Bibliotecas Backend**:
  - **PHPMailer** (armazenado localmente em `vendor/` ou carregado via Composer) para envio de e-mails transacionais via SMTP;
  - **PhpSpreadsheet** ou exportador CSV nativo para geração de relatórios em planilha (`.csv` / `.xlsx`);
- **Padrão Arquitetural**: MVC (Model-View-Controller) nativo em PHP;
- **Restrições e Diretrizes de Bibliotecas**:
  - Todo o código CSS, JS e bibliotecas de terceiros devem ser armazenados localmente dentro do projeto, evitando dependência de CDNs externas para funcionamento offline ou em ambiente local.

---

## 4. Ambientes do Projeto

1. **Ambiente de Desenvolvimento Local**:
   - Executado em ambiente Windows via **XAMPP** (servidor web Apache, PHP 8.x e servidor MySQL/MariaDB);
   - Acesso via navegador através de URL local (ex: `http://localhost/agenda-barber/`).
2. **Ambiente de Testes / Homologação**:
   - Validação e execução de testes funcionais realizadas diretamente no ambiente local (XAMPP) antes da publicação.
3. **Ambiente de Produção**:
   - Hospedagem web Linux/Apache com suporte a PHP 8.x e MySQL na **Hostnet**;
   - O deploy consistirá no upload dos arquivos do repositório, configuração do arquivo `config/config.php` e execução controlada das migrations de banco de dados.

---

## 5. Arquitetura do Sistema

### Diretório do Projeto - Repositório
A referência estrutural do projeto é representada pelo rótulo `[Diretório do Projeto - Repositório]`. Este diretório corresponde à pasta raiz do projeto versionada no repositório Git e poderá ser alocada dentro de qualquer pasta pública web do ambiente final:
- No XAMPP: `htdocs/agenda-barber/` (ou subpasta equivalente);
- Na Hostnet: `www/agenda-barber/` ou `www/` (ou subpasta equivalente).

### Estrutura de Diretórios Sugerida
```
[Diretório do Projeto - Repositório]/
├── index.php                      # Ponto de entrada (Front Controller / Roteador)
├── .htaccess                      # Reescreve URLs amigáveis e bloqueia acesso a pastas internas
├── config/
│   ├── config.php                 # Credenciais de banco, SMTP, gateway e flags técnicas
│   └── .htaccess                  # Bloqueia acesso direto via navegador (Deny from all)
├── app/
│   ├── controllers/               # Controllers da aplicação (MVC)
│   │   ├── AuthController.php
│   │   ├── AgendamentoController.php
│   │   ├── ClienteController.php
│   │   ├── ProfissionalController.php
│   │   ├── AdminController.php
│   │   ├── CheckoutController.php
│   │   ├── FinanceiroController.php
│   │   ├── PromocaoController.php
│   │   └── WebhookController.php
│   ├── models/                    # Models da aplicação (Acesso ao MySQL via PDO)
│   │   ├── UsuarioModel.php
│   │   ├── ProfissionalModel.php
│   │   ├── ServicoModel.php
│   │   ├── AgendamentoModel.php
│   │   ├── PagamentoModel.php
│   │   ├── CreditoModel.php
│   │   ├── AvaliacaoModel.php
│   │   ├── PromocaoModel.php
│   │   ├── DespesaModel.php
│   │   ├── SettingModel.php
│   │   ├── AuditModel.php
│   │   └── LogModel.php
│   ├── views/                     # Views HTML / PHP (Templates da interface)
│   │   ├── layouts/
│   │   │   ├── header.php
│   │   │   ├── footer.php
│   │   │   └── sidebar.php
│   │   ├── public/
│   │   ├── cliente/
│   │   ├── profissional/
│   │   └── admin/
│   └── helpers/                   # Funções utilitárias (Sanitização, Sessão, Datas)
│       ├── SessionHelper.php
│       ├── AuthHelper.php
│       ├── MailHelper.php
│       ├── UploadHelper.php
│       └── FormatHelper.php
├── database/
│   ├── migrations/                # Scripts de criação e atualização do banco
│   │   ├── 001_create_system_tables.php
│   │   └── 002_seed_initial_data.php
│   └── .htaccess                  # Bloqueia acesso via URL
├── logs/                          # Logs em arquivo de texto (Fallback)
│   ├── error.log
│   └── .htaccess                  # Bloqueia acesso público via URL
├── public/                        # Arquivos públicos acessíveis diretamente
│   ├── assets/
│   │   ├── css/                   # Estilos compilados/customizados
│   │   ├── js/                    # Scripts JS Vanilla
│   │   ├── vendor/                # Bootstrap 5 local, ícones
│   │   └── img/                   # Logos, favicons, imagens estáticas
│   └── uploads/                   # Arquivos enviados por upload
│       ├── perfis/
│       ├── promocoes/
│       ├── comprovantes/
│       └── .htaccess              # Desativa execução de scripts PHP (php_flag engine off)
└── vendor/                        # PHPMailer e bibliotecas PHP locais
```

### Funcionamento do Padrão MVC
1. **Front Controller (`index.php`)**: Recebe todas as requisições HTTP tratadas pelo `.htaccess`, inicializa o autoloader, carrega o arquivo `config/config.php`, gerencia a sessão e repassa a rota para o Controller correspondente.
2. **Controllers (`app/controllers/`)**: Verificam as permissões de acesso (RBAC), tratam e sanitizam os dados de entrada (`$_POST`, `$_GET`), executam a lógica de negócio acionando os Models e escolhem qual View renderizar ou qual resposta JSON retornar.
3. **Models (`app/models/`)**: Executam consultas SQL exclusivamente via conexões PDO ativas utilizando Prepared Statements. Tratam a manipulação das tabelas, regras de integridade, Soft Delete e registros de auditoria.
4. **Views (`app/views/`)**: Recebem arrays de dados preparados pelos Controllers e renderizam a interface HTML5 final aplicando as classes do Bootstrap local e os estilos do `docs/DESIGN.md`.

### Proteção de Pastas Internas
As pastas `config/`, `app/`, `database/`, `logs/` e `vendor/` ficam situadas dentro do diretório do projeto e são protegidas contra acesso direto pelo navegador através de arquivos `.htaccess` contendo a diretiva `Require all denied` (Apache 2.4+) ou `Deny from all`. O acesso às classes contidas nessas pastas ocorre estritamente por carregamento interno em PHP (`require_once` / `autoload`).

### Arquivo de Configuração em Código (`config/config.php`)
Não será utilizado arquivo `.env`. As credenciais e parâmetros técnicos serão armazenados em um arquivo PHP que retorna um array de configurações protegidas:
```php
<?php
// config/config.php - Não acessível via URL
if (!defined('APP_EXEC')) { die('Acesso direto negado.'); }

return [
    'app' => [
        'name' => 'AgendaBarber Web',
        'url' => 'http://localhost/agenda-barber',
        'env' => 'development', // 'development' ou 'production'
        'timezone' => 'America/Sao_Paulo'
    ],
    'db' => [
        'host' => 'localhost',
        'dbname' => 'agendabarber_db',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    'smtp' => [
        'host' => 'smtp.hostnet.com.br',
        'port' => 587,
        'user' => 'notificacoes@barbearia.com.br',
        'pass' => 'SenhaSmtpSegura123',
        'secure' => 'tls',
        'from_name' => 'AgendaBarber'
    ],
    'mercadopago' => [
        'access_token' => 'APP_USR-0000000000000000-000000-00000000000000000000000000000000-000000000',
        'public_key' => 'APP_USR-00000000-0000-0000-0000-000000000000',
        'sandbox_mode' => true // true para testes locais
    ]
];
```

---

## 6. Escopo Funcional da Primeira Versão

### Módulo 1: Apresentação Institucional e Cadastro Pública
- **Página Inicial Pública**: Apresenta dados do estabelecimento (nome, endereço, telefone, horário de funcionamento, redes sociais e botão interativo de mapa), vitrine de serviços com preços/durações, lista de profissionais, banners de promoções ativas com botões de compartilhamento e botão de agendamento.
- **Cadastro de Clientes**: Formulário público para novos clientes informando obrigatoriamente Nome, E-mail, Telefone, Senha e confirmação de idade (+18 anos). Foto de perfil é opcional.

### Módulo 2: Autenticação e Gestão de Perfil
- **Login Único por E-mail e Senha**: Autentica Clientes, Profissionais e Administradores, direcionando cada perfil para seu respectivo painel.
- **Recuperação de Senha**: Envio de link com token temporário (validade de 1 hora) para o e-mail cadastrado.
- **Perfil do Cliente**: Permite alterar telefone, foto de perfil e senha. A alteração de e-mail exige confirmação por link enviado ao novo endereço.
- **Exclusão de Conta**: Solicitação de exclusão pelo próprio cliente, permitida apenas quando não houver agendamentos futuros ativos.

### Módulo 3: Agendamento e Seleção de Serviços
- **Seleção Múltipla de Serviços**: O cliente pode selecionar um ou vários serviços no mesmo pedido. O sistema calcula automaticamente a soma dos preços e a soma das durações.
- **Escolha do Profissional**: Seleção de um profissional específico ou da opção "Qualquer profissional disponível".
- **Consulta de Horários Disponíveis**: Exibição de calendário e horários livres considerando os horários de trabalho dos profissionais, serviços selecionados, intervalo padrão de 10 minutos entre atendimentos, bloqueios, feriados e limite de até 30 dias de antecedência e no mínimo 2 horas de antecedência.
- **Limite de Agendamentos Ativos**: Restrição de no máximo 4 agendamentos futuros simultâneos por cliente.

### Módulo 4: Checkout e Pagamento Antecipado
- **Retenção Temporária de Vaga**: Ao iniciar o checkout, o horário fica bloqueado temporariamente por 10 minutos para outros clientes. Um cronômetro regressivo é exibido na tela.
- **Aceite Obrigatório de Políticas**: Checkbox obrigatório confirmando leitura das regras de pagamento antecipado, tolerância de atraso (10 min) e janela de cancelamento/reagendamento de 24h.
- **Formas de Pagamento (Mercado Pago)**:
  - **Pix**: Geração de QR Code dinâmico e código Copia e Cola com liquidação instantânea por Webhook;
  - **Cartão de Crédito**: Checkout transparente com envio de token seguro.
- **Comprovante de Agendamento**: Geração de comprovante com número de reserva exibido na tela e enviado por e-mail.

### Módulo 5: Gestão de Agendamentos (Área do Cliente)
- **Painel Meus Agendamentos**: Lista agendamentos futuros e passados com status (Aguardando Pagamento, Confirmado, Concluído, Cancelado, Falta, Reembolsado).
- **Reagendamento pelo Cliente**: Permitido com até 24h de antecedência (máximo de 2 reagendamentos por agendamento). O valor pago é reaproveitado; se o novo valor for maior, cobra a diferença; se for menor, gera crédito interno.
- **Cancelamento pelo Cliente**:
  - Cancelamento com 24h+ de antecedência: solicita reembolso integral;
  - Cancelamento com menos de 24h: sem reembolso.
- **Crédito Interno**: Saldo acumulado por reagendamentos de menor valor, com validade de 90 dias, aplicável em novos agendamentos.
- **Programa de Fidelidade Visual**: Exibição de insígnias visuais conforme atendimentos concluídos (Bronze: 0-4; Prata: 5-9; Ouro: 10+).
- **Avaliações**: Avaliação de 1 a 5 estrelas e comentário opcional após a conclusão do atendimento.

### Módulo 6: Painel do Profissional
- **Agenda Individual**: Visualização diária e semanal dos agendamentos atribuídos ao profissional logado.
- **Operação de Atendimentos**: Confirmação de presença, registro dos horários reais de início e término, e marcação do status "Concluído".
- **Notas de Clientes**: Visualização e cadastro de observações privadas do cliente.

### Módulo 7: Painel Administrativo Operacional e Financeiro
- **Visão Geral da Agenda**: Exibição em formatos Diário, Semanal e Mensal com filtros por profissional e status.
- **Agendamento Administrativo**: Agendamento manual pelo administrador com possibilidade de aplicação de desconto justificado (valor fixo ou percentual).
- **Cancelamento pelo Estabelecimento**: Notificação imediata ao cliente com opções de escolha: reagendamento, troca de profissional equivalente ou reembolso integral.
- **Gestão de Serviços e Profissionais**: CRUD completo de serviços (com preço, duração, imagem opcional e orientações) e profissionais (com jornada de trabalho, folgas e bloqueios).
- **Controle Financeiro**: Registro automático de receitas dos agendamentos concluídos e lançamento manual de despesas (com upload de comprovantes). Cálculo em tempo real de faturamento, despesas e lucro líquido.
- **Promoções Públicas**: Cadastro de promoções com título, imagem/banner, desconto, período de validade e link público de compartilhamento.
- **Gestão de Clientes**: Consulta a histórico, faltas, bloqueios manuais e inclusão de notas internas privadas.
- **Relatórios e Exportações**: Relatórios operacionais e financeiros por período com exportação em planilha CSV/Excel.
- **Histórico de Auditoria**: Painel de consulta ao log `audit_logs` mostrando alterações administrativas.

---

## 7. Fora de Escopo

Os seguintes recursos **NÃO fazem parte da primeira versão (v1)** do sistema:

1. Aplicativo mobile nativo para iOS ou Android (disponível apenas como web app responsivo);
2. Suporte a múltiplas unidades ou filiais (atendimento restrito a 1 unidade);
3. Envio de notificações ou integração com API do WhatsApp;
4. Fila de espera automática para horários ocupados;
5. Emissão automática de Nota Fiscal eletrônica (NFe/NFSe);
6. Benefícios ou descontos automáticos associados ao programa de fidelidade (as insígnias Bronze, Prata e Ouro são estritamente visuais);
7. Promoções segmentadas ou cupom exclusivo por grupo de clientes;
8. Módulo de controle de estoque de produtos;
9. Folha de pagamento e cálculo de comissões de profissionais;
10. Marketplace com busca de múltiplos estabelecimentos;
11. Recursos de Inteligência Artificial para recomendações de preços ou horários;
12. Precificação dinâmica por horário ou demanda;
13. Sincronização bidirecional com Google Calendar ou Outlook;
14. Venda de pacotes de serviços, planos mensais ou assinaturas recorrentes.

---

## 8. Perfis de Usuário e Permissões

### 1. Cliente
- **Descrição**: Usuário final consumidor dos serviços.
- **Áreas Acessíveis**: Vitrine pública, formulário de agendamento, checkout e área "Meus Agendamentos".
- **Permissões**: Gerenciar próprio perfil, agendar, pagar, consultar comprovantes, reagendar (até 2x com 24h+ de antecedência), cancelar (regra 24h), consultar créditos internos, visualizar fidelidade e avaliar atendimentos.
- **Restrições**: Não acessa dados de outros clientes, agendas globais ou rotas administrativas.

### 2. Profissional
- **Descrição**: Prestador de serviço (barbeiro/esteticista).
- **Áreas Acessíveis**: Área restrita do Profissional (`/profissional/`).
- **Permissões**: Visualizar própria agenda, consultar detalhes dos atendimentos sob sua responsabilidade, marcar presença, registrar horários reais de atendimento, marcar atendimento como "Concluído" e cadastrar/visualizar notas internas privadas sobre o cliente.
- **Restrições**: Não visualiza agenda de outros profissionais, relatórios financeiros globais, configurações do sistema ou cadastros de serviços.

### 3. Administrador Principal
- **Descrição**: Proprietário do estabelecimento.
- **Áreas Acessíveis**: Acesso irrestrito a todo o sistema administrativo (`/admin/`).
- **Permissões**: Gestão total de serviços, preços, profissionais, jornadas, bloqueios de agenda, agendamentos manuais, descontos com justificativa, exceções de reembolso, despesas, relatórios, exportações, promoções, auditoria, configurações globais e gestão de administradores limitados.

### 4. Administrador Limitado
- **Descrição**: Gerente ou recepcionista com permissões concedidas pelo Administrador Principal.
- **Áreas Acessíveis**: Subconjunto de telas dentro de `/admin/`.
- **Permissões**: Definidas individualmente via matriz de acessos (ex: permite agendar e concluir atendimentos, mas bloqueia edição de preços de serviços, relatórios de lucro ou exclusão de registros).

### Matriz de Permissões (RBAC)

| Módulo / Funcionalidade | Cliente | Profissional | Admin Limitado | Admin Principal |
| :--- | :---: | :---: | :---: | :---: |
| Agendar / Pagar Serviço Próprio | **SIM** | - | - | - |
| Visualizar Meus Agendamentos / Comprovantes | **SIM** | - | - | - |
| Reagendar / Cancelar (Regra 24h) | **SIM** | - | **SIM** | **SIM** |
| Visualizar Própria Agenda | - | **SIM** | **SIM** | **SIM** |
| Confirmar Presença / Concluir Atendimento | - | **SIM** | **SIM** | **SIM** |
| Visualizar / Criar Notas Privadas de Clientes | - | **SIM** | **SIM** | **SIM** |
| Visualizar Agenda Global (Todos Profissionais) | - | - | Configurável | **SIM** |
| Agendar Manualmente / Aplicar Desconto | - | - | Configurável | **SIM** |
| Gerenciar Serviços, Preços e Profissionais | - | - | - | **SIM** |
| Lançar Despesas e Ver Lucro | - | - | Configurável | **SIM** |
| Gerenciar Promoções e Banners | - | - | Configurável | **SIM** |
| Gerenciar Configurações Globais | - | - | - | **SIM** |
| Consultar Histórico de Auditoria (`audit_logs`) | - | - | - | **SIM** |
| Gerenciar Permissões de Admins Limitados | - | - | - | **SIM** |

---

## 9. Recursos Estruturais do Sistema

1. **Autenticação Segura**: E-mail e senha com suporte a redefinição por token expirável (1 hora) e controle de sessão HTTP via cookies `HttpOnly` e `SameSite=Lax`.
2. **Controle de Acesso Baseado em Papéis (RBAC)**: Middleware/função central no Controller que valida o papel do usuário logado antes de executar qualquer ação.
3. **Auditoria Geral (`audit_logs`)**: Registro automático de autoria (`created_by`, `updated_by`) e de eventos críticos (alterações de preços, descontos, cancelamentos administrativos e bloqueios de clientes).
4. **Soft Delete (Exclusão Lógica)**: Preservação de registros com marcação da coluna `deleted_at`. As listagens operacionais filtram por `deleted_at IS NULL`.
5. **Log de Erros com Contingência**: Gravação de exceções na tabela `error_logs` do banco de dados. Se houver falha de conexão com o banco, grava em arquivo de texto plano `/logs/error.log`.
6. **Log de Segurança (`security_logs`)**: Gravação de falhas repetidas de login, tentativas de violação de acesso RBAC e bloqueios de IP/conta.
7. **Configurações Globais (`system_settings`)**: Gerenciamento em banco de parâmetros funcionais (nome, horários, prazos de reserva e regras de antecedência).
8. **Uploads e Anexos Seguros**: Validação de extensão e MIME-type real, limite de 5MB, renomeação por UUID e desativação de execução de scripts PHP no diretório `/public/uploads/`.
9. **Relatórios e Exportações**: Visualização dinâmica em tela e exportação para arquivos `.csv` / `.xlsx`.
10. **Integração de Pagamentos**: Provedor modular para Mercado Pago (Pix QR Code com Webhook e Cartão de Crédito) com classe simuladora Sandbox local.
11. **Notificações por E-mail**: Utilização do PHPMailer via SMTP + Cron Job para execução assíncrona dos lembretes de 24h e 2h antes.

---

## 10. Entidades do Sistema

1. **`usuarios`**: Armazena todas as contas do sistema (clientes, profissionais e administradores).
2. **`profissionais`**: Dados complementares dos profissionais (limite diário de atendimentos e status).
3. **`servicos`**: Serviços oferecidos (nome, preço, duração em minutos, imagem, orientações pré-atendimento e status).
4. **`profissional_servicos`**: Tabela pivô de relacionamento N:N indicando quais serviços cada profissional realiza.
5. **`horarios_trabalho`**: Grade horária semanal de cada profissional (dias da semana, horário de início, término e intervalo de almoço).
6. **`indisponibilidades_bloqueios`**: Bloqueios pontuais de agenda (folgas, feriados ou bloqueios de horários/datas).
7. **`agendamentos`**: Registro dos atendimentos agendados (código de reserva, cliente, profissional, data, horário início/fim, valor total, situação).
8. **`agendamento_servicos`**: Serviços vinculados a um agendamento específico com congelamento do preço e duração vigentes na data da contratação.
9. **`pagamentos`**: Transações financeiras dos agendamentos (forma de pagamento, valor, status do pagamento, transação ID do gateway e QR Code Pix).
10. **`creditos_internos`**: Saldo de créditos gerados por reagendamentos de menor valor (cliente, valor, saldo restante e data de expiração de 90 dias).
11. **`avaliacoes`**: Nota de 1 a 5 estrelas e comentário deixados pelo cliente após o atendimento.
12. **`promocoes`**: Promoções públicas (título, banner/imagem, tipo de desconto, valor do desconto, validade e limites de uso).
13. **`uso_promocoes`**: Registro de utilizações de promoções por cliente.
14. **`despesas`**: Lançamentos manuais de despesas do estabelecimento (categoria, descrição, valor, data e comprovante).
15. **`categorias_despesas`**: Categorias das despesas (ex: Aluguel, Produtos, Energia, Manutenção).
16. **`notas_clientes`**: Observações privadas cadastradas pela equipe sobre um determinado cliente.
17. **`system_settings`**: Tabela de chave/valor para configurações globais.
18. **`audit_logs`**: Trilha de auditoria para ações administrativas sensíveis.
19. **`error_logs`**: Registro de exceções e erros de execução do sistema.
20. **`security_logs`**: Registro de eventos de segurança e tentativas de intrusão.

---

## 11. Modelo de Dados Proposto e Migrations

### Estrutura das Tabelas (MySQL 8.x)

```sql
-- 1. Tabela de Usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    foto VARCHAR(255) NULL,
    perfil ENUM('cliente', 'profissional', 'admin_principal', 'admin_limitado') NOT NULL DEFAULT 'cliente',
    status ENUM('ativo', 'bloqueado', 'inativo') NOT NULL DEFAULT 'ativo',
    motivo_bloqueio TEXT NULL,
    token_recuperacao VARCHAR(100) NULL,
    token_expiracao DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_usuarios_email (email),
    INDEX idx_usuarios_perfil (perfil),
    INDEX idx_usuarios_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabela de Profissionais
CREATE TABLE profissionais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    limite_diario_atendimentos INT NULL DEFAULT NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabela de Serviços
CREATE TABLE servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    duracao_minutos INT NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    imagem VARCHAR(255) NULL,
    orientacoes TEXT NULL,
    status ENUM('ativo', 'indisponivel', 'inativo') NOT NULL DEFAULT 'ativo',
    previsao_retorno DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_servicos_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabela Pivô Profissional x Serviços
CREATE TABLE profissional_servicos (
    profissional_id INT NOT NULL,
    servico_id INT NOT NULL,
    PRIMARY KEY (profissional_id, servico_id),
    FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
    FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Horários de Trabalho do Profissional
CREATE TABLE horarios_trabalho (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profissional_id INT NOT NULL,
    dia_semana TINYINT NOT NULL COMMENT '0=Dom, 1=Seg, ..., 6=Sab',
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    almocho_inicio TIME NULL,
    almocho_fim TIME NULL,
    FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE,
    INDEX idx_horarios_prof_dia (profissional_id, dia_semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Indisponibilidades e Bloqueios
CREATE TABLE indisponibilidades_bloqueios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profissional_id INT NULL COMMENT 'NULL indica bloqueio geral do estabelecimento',
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    tipo ENUM('folga', 'feriado', 'bloqueio_parcial', 'fechamento') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bloqueios_datas (data_inicio, data_fim),
    INDEX idx_bloqueios_prof (profissional_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabela de Agendamentos
CREATE TABLE agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_reserva VARCHAR(20) NOT NULL UNIQUE,
    cliente_id INT NOT NULL,
    profissional_id INT NOT NULL,
    data_agendamento DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    duracao_total_minutos INT NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    justificativa_desconto TEXT NULL,
    credito_utilizado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    situacao ENUM('aguardando_pagamento', 'confirmado', 'concluido', 'cancelado', 'falta', 'reembolsado') NOT NULL DEFAULT 'aguardando_pagamento',
    motivo_cancelamento TEXT NULL,
    reagendamentos_realizados TINYINT NOT NULL DEFAULT 0,
    reserva_expira_em DATETIME NULL,
    hora_inicio_real TIME NULL,
    hora_fim_real TIME NULL,
    confirmado_presenca TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    FOREIGN KEY (profissional_id) REFERENCES profissionais(id),
    INDEX idx_agendamentos_busca (profissional_id, data_agendamento, situacao),
    INDEX idx_agendamentos_cliente (cliente_id),
    INDEX idx_agendamentos_situacao (situacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tabela de Itens de Agendamento (Serviços Contratados)
CREATE TABLE agendamento_servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agendamento_id INT NOT NULL,
    servico_id INT NOT NULL,
    preco_aplicado DECIMAL(10,2) NOT NULL,
    duracao_aplicada_minutos INT NOT NULL,
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Tabela de Pagamentos
CREATE TABLE pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agendamento_id INT NOT NULL,
    forma_pagamento ENUM('pix', 'cartao_credito') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    status_pagamento ENUM('pendente', 'aprovado', 'recusado', 'estornado') NOT NULL DEFAULT 'pendente',
    transacao_id VARCHAR(100) NULL,
    qr_code_pix TEXT NULL,
    qr_code_pix_url TEXT NULL,
    pago_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE,
    INDEX idx_pagamentos_transacao (transacao_id),
    INDEX idx_pagamentos_status (status_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Tabela de Créditos Internos
CREATE TABLE creditos_internos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    agendamento_origem_id INT NULL,
    valor_original DECIMAL(10,2) NOT NULL,
    saldo_restante DECIMAL(10,2) NOT NULL,
    data_expiracao DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    FOREIGN KEY (agendamento_origem_id) REFERENCES agendamentos(id),
    INDEX idx_creditos_cliente (cliente_id, data_expiracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Tabela de Avaliações
CREATE TABLE avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agendamento_id INT NOT NULL UNIQUE,
    cliente_id INT NOT NULL,
    profissional_id INT NOT NULL,
    nota TINYINT NOT NULL CHECK (nota BETWEEN 1 AND 5),
    comentario TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id),
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    FOREIGN KEY (profissional_id) REFERENCES profissionais(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Tabela de Promoções
CREATE TABLE promocoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    imagem VARCHAR(255) NULL,
    servico_id INT NULL,
    tipo_desconto ENUM('fixo', 'percentual') NOT NULL,
    valor_desconto DECIMAL(10,2) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    limite_total_usos INT NULL,
    usos_realizados INT NOT NULL DEFAULT 0,
    status ENUM('ativa', 'inativa', 'expirada') NOT NULL DEFAULT 'ativa',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (servico_id) REFERENCES servicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Tabela de Uso de Promoções
CREATE TABLE uso_promocoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promocao_id INT NOT NULL,
    cliente_id INT NOT NULL,
    agendamento_id INT NOT NULL,
    data_uso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promocao_id) REFERENCES promocoes(id),
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id),
    UNIQUE KEY unq_promo_cliente (promocao_id, cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Tabela de Categorias de Despesas
CREATE TABLE categorias_despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Tabela de Despesas
CREATE TABLE despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_despesa DATE NOT NULL,
    comprovante VARCHAR(255) NULL,
    registrado_por INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias_despesas(id),
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    INDEX idx_despesas_data (data_despesa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Tabela de Notas Internas Privadas sobre Clientes
CREATE TABLE notas_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    autor_id INT NOT NULL,
    observacao TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Configurações Globais
CREATE TABLE system_settings (
    chave VARCHAR(50) PRIMARY KEY,
    valor TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. Audit Logs
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    acao VARCHAR(50) NOT NULL,
    entidade VARCHAR(50) NOT NULL,
    entidade_id INT NULL,
    dados_anteriores JSON NULL,
    dados_novos JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_audit_entidade (entidade, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Error Logs
CREATE TABLE error_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mensagem TEXT NOT NULL,
    arquivo VARCHAR(255) NOT NULL,
    linha INT NOT NULL,
    stack_trace TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Security Logs
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    evento VARCHAR(100) NOT NULL,
    detalhes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Estratégia de Migrations
Para evitar a criação manual de tabelas e garantir o versionamento do banco de dados, o projeto utilizará um mecanismo simples nativo de **Migrations em PHP**.

- **Localização dos Arquivos**: `database/migrations/` (protegida por `.htaccess` contra acesso web direto).
- **Mecanismo de Controle**: Uma tabela de controle chamada `schema_migrations` registrará cada script de migration já executado:
  ```sql
  CREATE TABLE IF NOT EXISTS schema_migrations (
      id INT AUTO_INCREMENT PRIMARY KEY,
      migration VARCHAR(255) NOT NULL UNIQUE,
      executada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  );
  ```
- **Execução Controlada**: As migrations serão executadas de duas formas seguras:
  1. Via linha de comando (CLI): `php database/migrate.php`;
  2. Via rotina administrativa no painel (`/admin/configuracoes/migrar`), restrita ao Administrador Principal e exigindo confirmação de senha. Em nenhuma hipótese haverá URL pública desprotegida para rodar migrations.

---

## 12. Módulos e Telas

A interface do sistema deve seguir os padrões visuais, paleta de cores e tipografia definidos no `docs/DESIGN.md` (*Elevated Grooming & Wellness*).

### 1. Vitrine Pública (`/`)
- **Objetivo**: Apresentação do estabelecimento e atração de clientes.
- **Usuários**: Visitantes e Clientes.
- **Componentes**: Header transparente com logo e menu de navegação, Hero Banner com frase impactante e botão CTA "Agendar Horário", carrossel de promoções ativas, grade de serviços com cards em elevação *Midnight Slate*, lista de profissionais com foto/especialidades, seção "Sobre Nós" com mapa e contatos.

### 2. Formação e Seleção do Agendamento (`/agendar`)
- **Objetivo**: Wizard interativo em passos para montagem do pedido de agendamento.
- **Passo 1 (Serviços)**: Seleção múltipla de serviços com soma dinâmica do valor e tempo total exibida em barra inferior fixa.
- **Passo 2 (Profissional e Data)**: Seleção do profissional e exibição de calendário dinâmico (grade de horários livres calculada via AJAX).
- **Passo 3 (Revisão e Políticas)**: Resumo do agendamento, campo para cupom/crédito interno e checkbox de aceite das políticas.

### 3. Tela de Checkout (`/checkout/{codigo}`)
- **Objetivo**: Pagamento imediato do agendamento com retenção temporária de 10 minutos.
- **Componentes**: Cronômetro regressivo destacado em Bronze (`#E9C176`), aba Pix com exibição do QR Code gerado via API Mercado Pago + botão "Copiar Código Pix", aba Cartão de Crédito com formulário seguro.

### 4. Área do Cliente (`/cliente/agendamentos`)
- **Objetivo**: Gestão de histórico, comprovantes e ações sobre agendamentos.
- **Telas**: Lista de Agendamentos (com abas Próximos / Histórico), Modal de Comprovante impresso/PDF, Modal de Reagendamento, Modal de Cancelamento com cálculo de reembolso, Painel de Créditos Internos e Insígnia de Fidelidade.

### 5. Agenda do Profissional (`/profissional/agenda`)
- **Objetivo**: Operação diária do barbeiro/esteticista.
- **Componentes**: Seletor de data, cards de atendimento com horário, cliente, serviços contratados, botão de "Confirmar Presença", campo de horários reais (início/fim), botão "Concluir Atendimento" e modal para adicionar Nota Privada ao cliente.

### 6. Painel Administrativo Geral (`/admin/dashboard`)
- **Objetivo**: Visão consolidada da operação diária do estabelecimento.
- **Componentes**: Cards de métricas rápidas (Agendamentos do dia, Faturamento do dia, Próximos clientes, Cancelamentos), visão de agenda diária em linha do tempo e alertas de atendimentos pendentes de conclusão.

### 7. Gestão Administrativa de Agenda (`/admin/agenda`)
- **Objetivo**: Controle global de horários e agendamento manual.
- **Componentes**: Alternador de visão (Diária, Semanal, Mensal), filtro por profissional, modal de agendamento manual com aplicação de desconto fixo/percentual e justificativa obrigatória, e modal de bloqueio de horários/datas.

### 8. Gestão de Serviços e Profissionais (`/admin/servicos` e `/admin/profissionais`)
- **Objetivo**: Manutenção dos cadastros base do sistema.
- **Componentes**: Tabelas de dados com busca, filtros de status, modal de cadastro/edição de serviços (com upload de imagem e orientações) e formulário de profissionais com definição de horários de trabalho e limites diários.

### 9. Controle Financeiro e Despesas (`/admin/financeiro`)
- **Objetivo**: Acompanhamento de receitas, despesas e margem de lucro.
- **Componentes**: Resumo financeiro (Receita Total, Despesas Totais, Lucro Líquido), gráfico de evolução do faturamento, tabela de lançamentos de despesas com modal de cadastro ( upload de comprovante) e filtro por período.

### 10. Promoções públicas (`/admin/promocoes`)
- **Objetivo**: Gestão de campanhas e vitrine de ofertas.
- **Componentes**: Cadastro de promoção (título, banner, desconto, validade), link de compartilhamento rápido gerado pelo sistema e relatório de usos por promoção.

---

## 13. Fluxos Funcionais

### Fluxo 1: Agendamento e Pagamento por Pix pelo Cliente
1. O cliente acessa `/agendar`, escolhe 2 serviços ("Corte" R$ 50 - 30min e "Barba" R$ 40 - 30min).
2. O sistema calcula automaticamente: Valor R$ 90,00 | Duração: 60 minutos | Intervalo exigido: 10 minutos | Tempo total de bloqueio na agenda: 70 minutos.
3. O cliente seleciona o barbeiro "João" e escolhe a data de amanhã às 14:00.
4. O sistema valida que João possui janela livre das 14:00 às 15:10, não ultrapassou seu limite diário e não possui bloqueios.
5. O cliente faz login (ou cadastra-se), aceita os termos e clica em "Ir para Pagamento".
6. O Controller gera o agendamento no banco com status `aguardando_pagamento`, define `reserva_expira_em` para `NOW() + 10 minutos` e cria o Pix no Mercado Pago.
7. O cliente é direcionado para a tela de Checkout com o QR Code Pix e a contagem de 10 minutos.
8. O cliente paga pelo app do banco. O Mercado Pago dispara um Webhook para `/webhook/mercadopago`.
9. O WebhookController valida a assinatura da requisição, localiza o pagamento e atualiza o agendamento para `confirmado`.
10. O sistema envia e-mail de confirmação com o comprovante e redireciona a tela do cliente para a página de sucesso.

### Fluxo 2: Cancelamento pelo Cliente com Reembolso (24h+)
1. O cliente entra em "Meus Agendamentos", localiza um agendamento confirmado para daqui a 3 dias e clica em "Cancelar Agendamento".
2. O sistema verifica que o horário atual é anterior a 24 horas do atendimento.
3. A tela exibe o alerta: "Cancelamento dentro do prazo. O reembolso de R$ 90,00 será processado pelo meio de pagamento utilizado."
4. O cliente confirma o cancelamento e informa o motivo.
5. O Controller altera o status para `cancelado`, aciona o gateway para estorno (ou gera pendência de reembolso no painel administrativo), altera o status do pagamento para `estornado`, libera a vaga na agenda e envia e-mail de confirmação.

### Fluxo 3: Reagendamento pelo Cliente gerando Crédito Interno
1. O cliente possui um agendamento de R$ 90,00 para daqui a 2 dias e clica em "Reagendar".
2. O sistema valida que o agendamento ainda não atingiu o limite de 2 reagendamentos e está a mais de 24h do atendimento.
3. O cliente troca o serviço por apenas "Corte" (R$ 50,00) para nova data.
4. O sistema calcula a diferença: Pago R$ 90,00 - Novo Valor R$ 50,00 = Saldo de R$ 40,00.
5. O agendamento é atualizado para a nova data e a contagem de reagendamentos sobe para 1.
6. O Controller gera um registro na tabela `creditos_internos` de R$ 40,00 vinculado ao cliente, com validade de 90 dias a partir da data atual.

---

## 14. Validações e Regras de Negócio

1. **Idade Mínima**: O cadastro de clientes deve validar a data de nascimento ou checkbox declaratório confirmando idade superior a 18 anos.
2. **Antecedência de Agendamento**: Mínimo de 2 horas e máximo de 30 dias a partir do momento atual.
3. **Limite de Agendamentos Ativos**: Um cliente não pode ter mais de 4 agendamentos com situacao `aguardando_pagamento` ou `confirmado` simultaneamente.
4. **Reserva Temporária de Checkout**: Se o pagamento não for aprovado em 10 minutos, o agendamento passa para `cancelado` e o horário é liberado.
5. **Regra de Cancelamento e Reagendamento**:
   - Com 24h ou mais de antecedência: permite cancelamento com reembolso integral ou reagendamento (máx. 2 vezes);
   - Com menos de 24h de antecedência: permite cancelamento SEM reembolso e bloqueia reagendamento pelo cliente.
6. **Tolerância de Atraso**: Tolerância máxima de 10 minutos no horário agendado. Após este período, o estabelecimento pode marcar como `falta`, sem direito a reembolso.
7. **Validade de Crédito Interno**: Créditos gerados por reagendamentos possuem expiração automática em 90 dias corridos.
8. **Validação de Sobreposição de Agendamento**: Duas reservas não podem ocupar a mesma janela de tempo do mesmo profissional (`hora_inicio` < `existente_hora_fim` AND `hora_fim` > `existente_hora_inicio`).

---

## 15. Autenticação e Sessão

- **Mecanismo de Autenticação**: Form em HTML POST enviando e-mail e senha. O Controller busca o usuário por e-mail no `UsuarioModel`, verifica se o status é `ativo` e valida a senha usando `password_verify($senha, $usuario['senha_hash'])`.
- **Gerenciamento de Sessão**:
  - `session_start()` com configurações personalizadas no PHP: `cookie_httponly = true`, `cookie_samesite = 'Lax'`, `use_only_cookies = true`;
  - Ao autenticar, salva na sessão `$_SESSION['user_id']`, `$_SESSION['user_nome']`, `$_SESSION['user_email']` e `$_SESSION['user_perfil']`;
  - Regeneração do ID de sessão (`session_regenerate_id(true)`) a cada login bem-sucedido para evitar ataques de Session Fixation.
- **Proteção de Rotas**: Função utilitária `AuthHelper::requireLogin([permissões_permitidas])` executada no início de cada método de Controller. Se o usuário não estiver logado, redireciona para `/login`. Se não tiver permissão, exibe página 403 Forbidden.
- **Recuperação de Senha**: O usuário informa o e-mail cadastrado. O sistema gera um token pseudo-aleatório seguro (`bin2hex(random_bytes(32))`), salva em `usuarios.token_recuperacao` com validade de 1 hora (`token_expiracao`) e envia um e-mail com o link `/redefinir-senha?token=XXXX`.

---

## 16. Controle de Acesso

- **Validação Server-Side**: Todas as verificações de acesso são feitas estritamente no código PHP backend (Controllers), independentemente de botões estarem ocultos ou visíveis na View.
- **Menus Dinâmicos**: O arquivo `app/views/layouts/sidebar.php` renderiza opções de menu filtradas pelo papel armazenado em `$_SESSION['user_perfil']`.
- **Mensagem para Acesso Negado**: Tentativas de acesso direto por URL a rotas não autorizadas disparam um registro em `security_logs` e redirecionam o usuário para uma View limpa de erro (`app/views/errors/403.php`) informando: *"Acesso negado. Você não possui permissão para acessar esta funcionalidade."*

---

## 17. Auditoria e Histórico

- **Ações Auditadas**: Alteração de preços de serviços, alteração de jornada/horários de profissionais, bloqueios manuais de agenda, concessão de descontos manuais, cancelamentos/reagendamentos feitos pelo administrador, alteração de status de cliente (bloqueio/desativação) e exclusão lógica de registros.
- **Estrutura de Registro (`audit_logs`)**: Grava a ID do usuário responsável, nome da ação, nome da entidade alterada, ID do registro afetado, JSON contendo os dados anteriores, JSON contendo os novos dados, endereço IP do solicitante e data/hora atual.
- **Visualização**: Tela exclusiva no painel administrativo (`/admin/auditoria`) com filtros por usuário, entidade e período.

---

## 18. Soft Delete e Exclusões

- **Mecanismo**: A exclusão de serviços, profissionais, clientes, agendamentos, promoções ou despesas atualiza a coluna `deleted_at = CURRENT_TIMESTAMP`.
- **Filtro Padrão nos Models**: Todas as consultas de leitura nos Models incluem a condição `WHERE deleted_at IS NULL`.
- **Preservação de Registros Vinculados**: Não é permitida a exclusão física de registros que possuam vínculos históricos com agendamentos ou lançamentos financeiros.
- **Restauração**: Apenas o Administrador Principal pode visualizar itens excluídos logicamente e executar a ação de restauração (`deleted_at = NULL`) através da tela de auditoria.

---

## 19. Logs

### 1. Log de Erros
- **Funcionamento**: A classe `LogModel` ou uma função global de tratamento de exceções (`set_exception_handler`) captura todos os erros não tratados do PHP.
- **Gravação Primária**: Tenta inserir um registro na tabela `error_logs` contendo mensagem do erro, arquivo, linha, stack trace e data/hora.
- **Contingência em Arquivo**: Caso ocorra falha de conexão com o banco de dados MySQL ou erro ao executar a SQL de log, a classe aciona um mecanismo de fallback gravando a mensagem no arquivo seguro `/logs/error.log` usando `error_log()`.
- **Exibição para o Usuário**: O usuário final visualiza apenas uma mensagem amigável (*"Ocorreu um erro inesperado. Nossa equipe foi notificada."*), enquanto os detalhes técnicos ficam restritos aos logs.

### 2. Log de Segurança
- **Eventos Registrados**: Tentativas de login com senha incorreta, requisições com tokens CSRF inválidos, tentativas de acesso a URLs restritas do RBAC, bloqueio por excesso de tentativas incorretas (5 tentativas inválidas bloqueiam o IP/e-mail por 15 minutos) e alterações de permissões administrativas.
- **Gravação**: Tabela `security_logs` contendo ID do usuário (se identificado), endereço IP, nome do evento, detalhes e data/hora.

---

## 20. Configurações Globais

### 1. Configurações Operacionais (`system_settings`)
Gerenciadas via interface no painel `/admin/configuracoes`:
- Nome e dados de contato do estabelecimento;
- Endereço e URL de mapas;
- Horário geral de funcionamento da casa;
- Prazos de antecedência de agendamento (mínimo de horas e máximo de dias);
- Tempo de reserva do checkout (padrão: 10 minutos);
- Tolerância máxima de atraso (padrão: 10 minutos);
- Validade dos créditos internos (padrão: 90 dias).

### 2. Configurações Técnicas (`config/config.php`)
Gerenciadas diretamente no arquivo em código PHP (não editáveis via interface gráfica por motivos de segurança):
- Parâmetros de conexão PDO com o MySQL;
- Servidor SMTP, portas e credenciais de e-mail;
- Chaves de integração do Mercado Pago (Public Key e Access Token);
- Flag do ambiente (`development` ou `production`).

---

## 21. Uploads, Anexos e Arquivos

1. **Locais de Uso**: Foto de perfil do cliente, imagens e banners de promoções, imagens ilustrativas de serviços e comprovantes de despesas financeiras.
2. **Estrutura de Armazenamento**:
   - `public/uploads/perfis/`
   - `public/uploads/promocoes/`
   - `public/uploads/comprovantes/`
3. **Regras e Validações**:
   - **Tipos Permitidos**: Imagens (`.jpg`, `.jpeg`, `.png`, `.webp`) e Documentos de comprovante (`.pdf`, `.jpg`, `.png`);
   - **Tamanho Máximo**: 5 MB por arquivo;
   - **Renomeação Obrigatória**: Todo arquivo salvo é renomeado para um Hash UUID (`v4()`) ou `md5(uniqid(rand(), true))` mantendo apenas a extensão validada;
   - **Validação de MIME-type**: Checagem através da extensão PHP `finfo_file(FILEINFO_MIME_TYPE)`;
   - **Segurança de Servidor**: Arquivo `public/uploads/.htaccess` configurado com `php_flag engine off` e bloqueio de execução de arquivos com extensão `.php`, `.phtml`, `.php3` ou `.sh`.

---

## 22. Relatórios, Consultas e Exportações

1. **Relatórios em Tela**:
   - **Relatório Operacional de Agendamentos**: Listagem filtrável por período, profissional, serviço e situação;
   - **Relatório Financeiro de Faturamento e Despesas**: Consolidação de receitas de atendimentos concluídos versus despesas lançadas no período, exibindo o lucro líquido;
   - **Relatório de Desempenho por Profissional**: Quantidade de atendimentos realizados, taxa de ocupação e faturamento gerado.
2. **Exportação de Dados (CSV / Excel)**:
   - Botão "Exportar Planilha" presente nas telas de relatórios;
   - O arquivo gerado (.csv codificado em UTF-8 com BOM para correta abertura no Excel) respeita exatamente os mesmos filtros aplicados na tela;
   - As colunas exportadas incluem: Código da Reserva, Data, Horário, Cliente, Profissional, Serviços, Valor Total, Desconto, Situação e Forma de Pagamento.

---

## 23. APIs e Integrações Externas

### 1. Gateway de Pagamento Mercado Pago
- **Geração de Pix**: Envio de requisição `POST /v1/payments` com os dados do agendamento. Retorna a imagem do QR Code em Base64 e a string do código Pix Copia e Cola.
- **Checkout Cartão de Crédito**: Recebimento do `token` do cartão gerado no frontend via SDK JS do Mercado Pago e envio do pagamento para aprovação.
- **Webhook de Notificações (`/webhook/mercadopago`)**: Rota pública acionada pelo Mercado Pago. O Controller valida o cabeçalho `X-Signature`, busca o status da transação na API do Mercado Pago e atualiza o status do agendamento e pagamento de forma idempotente.
- **Classe Mock / Sandbox**: O sistema possui uma chave de configuração `'sandbox_mode' => true` em `config/config.php` que permite simular a aprovação instantânea de Pix e Cartão no ambiente local (XAMPP) sem acionar chamadas reais externas.

### 2. Envio de E-mails via PHPMailer e Cron Job
- **Envio Transacional**: Disparo imediato via SMTP em ações como cadastro, recuperação de senha, confirmação de agendamento e cancelamento.
- **Envio de Lembretes Assíncronos (Cron Job)**:
  - Script em PHP `database/cron_lembretes.php` configurado para rodar a cada 15 minutos;
  - Busca no banco agendamentos com status `confirmado` que estejam exatamente a 24 horas ou 2 horas do horário agendado e envia o e-mail de lembrete com botão de confirmação de presença.

---

## 24. Segurança Funcional

1. **Proteção CSRF**: Todos os formulários `POST` devem conter um campo `<input type="hidden" name="csrf_token" value="...">`. O Controller valida o token antes de processar a requisição.
2. **Prevenção de SQL Injection**: Uso exclusivo de consultas preparadas PDO (`$stmt->prepare()`) com bind seguro de parâmetros.
3. **Prevenção de XSS**: Todo dado dinâmico exibido nas Views HTML passa pela função `htmlspecialchars($dado, ENT_QUOTES, 'UTF-8')`.
4. **Proteção de Uploads**: Sanitização rigorosa, renomeação por Hash, verificação de tipo MIME real e bloqueio de execução de código na pasta de arquivos.
5. **Cabeçalhos de Segurança HTTP**: O Front Controller adiciona cabeçalhos de segurança básicos (`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection: 1; mode=block`).

---

## 25. Organização Sugerida da Etapa de Implementação

A IA codificadora deverá construir o sistema de forma incremental e testável, seguindo a ordem de fases abaixo:

1. **Fase 1: Preparação da Estrutura e Configurações Base**
   - Criar a estrutura de diretórios do repositório `[Diretório do Projeto - Repositório]`;
   - Configurar o arquivo de entrada `index.php` e arquivos `.htaccess` de proteção;
   - Criar o arquivo de configuração `config/config.php` e helpers de utilidades (sessão, conexões PDO, sanitização).
2. **Fase 2: Banco de Dados e Sistema de Migrations**
   - Criar a classe/script de Runner de Migrations em PHP e a tabela `schema_migrations`;
   - Escrever e executar a migration inicial de criação de todas as 20 tabelas, chaves primárias, estrangeiras e índices;
   - Inserir dados iniciais de teste (seeding de categorias, usuário administrador inicial e serviços base).
3. **Fase 3: Módulo de Autenticação, RBAC e Perfis**
   - Construir os Models de Usuário e os Controllers de Login, Cadastro, Logout e Recuperação de Senha;
   - Implementar os middlewares de proteção de rotas e verificação de papéis (RBAC);
   - Desenvolver as Views de Login, Cadastro e Perfil do Usuário.
4. **Fase 4: Cadastros Administrativos Base**
   - Implementar o CRUD completo de Serviços (com suporte a upload de imagem);
   - Implementar o CRUD de Profissionais, definição de serviços realizados e cadastro da grade horária semanal;
   - Implementar a gestão de Bloqueios de Agenda e Feriados.
5. **Fase 5: Motor de Disponibilidade e Fluxo de Agendamento Público**
   - Construir o algoritmo de cálculo de horários vagos (considerando serviços múltiplos, duração, intervalo de 10 min e bloqueios);
   - Criar as Views da vitrine pública e o Wizard de agendamento em passos;
   - Implementar a lógica de reserva temporária por 10 minutos.
6. **Fase 6: Gateway de Pagamento e Webhooks**
   - Integrar a SDK/API do Mercado Pago (Pix QR Code e Cartão);
   - Criar a classe Mock/Sandbox local para testes de pagamento no XAMPP;
   - Desenvolver o Controller de Webhook e confirmação automática de agendamento.
7. **Fase 7: Áreas Restritas do Cliente e Profissional**
   - Desenvolver o painel "Meus Agendamentos", geração de comprovantes, fluxo de reagendamento (com regra de 24h e geração de créditos) e cancelamento;
   - Desenvolver a interface da Agenda do Profissional, confirmação de presença, registro de horários reais e notas privadas de clientes.
8. **Fase 8: Painel Administrativo Operacional, Financeiro e Promoções**
   - Criar as visões da Agenda Administrativa (Diária, Semanal, Mensal) e agendamento manual com desconto justificado;
   - Implementar o Módulo Financeiro (lançamento de despesas com upload e cálculo de lucro líquido);
   - Desenvolver a gestão de Promoções públicas, Banners e relatórios exportáveis em CSV/Excel.
9. **Fase 9: Logs, Auditoria, E-mails e Polimento Final**
   - Implementar a gravação de `audit_logs`, `security_logs` e `error_logs` (com contingência em arquivo texto);
   - Configurar o PHPMailer e o script Cron Job de lembretes automáticos;
   - Aplicar os refinamentos de UI/UX do `docs/DESIGN.md`, testar responsividade e validar todas as regras funcionais.

---

## 26. Critérios de Aceitação Técnica e Funcional

O sistema será considerado completo e pronto para produção quando atender integralmente aos seguintes critérios:

1. **Arquitetura e Código**: Separação estrita de MVC mantida em todas as funcionalidades, com 100% das chamadas a banco de dados isoladas nos Models e utilizando Prepared Statements PDO.
2. **Sem Arquivos `.env`**: Todo o parâmetro técnico sensível armazenado em `config/config.php` protegido contra acesso via navegador.
3. **Proteção de Diretórios**: Tentativas de acesso direto por URL às pastas `config/`, `app/`, `database/` e `logs/` resultam em bloqueio de acesso (HTTP 403 / 404).
4. **Migrations Automatizadas**: Criação e atualização de todo o banco de dados realizada via script de migration versionado em PHP.
5. **Agendamento e Horários**: O motor de busca calcula corretamente a soma de durações e respeita o intervalo de 10 minutos entre atendimentos, não permitindo sob nenhuma hipótese a sobreposição de reservas para o mesmo profissional.
6. **Checkout e Retenção**: A reserva de 10 minutos expira automaticamente caso o pagamento Pix/Cartão não seja concluído a tempo.
7. **Pagamento e Webhook**: Agendamentos são confirmados automaticamente com o recebimento do retorno do gateway (ou via simulador Sandbox em ambiente local).
8. **Regra de 24 Horas**: Cancelamentos e reagendamentos com mais de 24h aplicam as regras de reembolso e crédito interno corretamente, enquanto solicitações com menos de 24h bloqueiam o reembolso e reagendamento do cliente.
9. **Uploads Seguros**: Arquivos salvos exclusivamente em `public/uploads/`, renomeados com UUID, limitados a 5MB, com validação real de MIME-type e bloqueio de execução PHP.
10. **Logs e Contingência**: Erros de aplicação gravados na tabela `error_logs` e, em caso de queda do banco, gravados com sucesso no arquivo `/logs/error.log`.
11. **Fidelidade e Avaliações**: Insígnias visuais (Bronze, Prata, Ouro) calculadas corretamente com base em atendimentos concluídos e avaliações restritas a clientes que finalizaram o serviço.
12. **Fidelidade Visual à UI**: Telas administrativas e públicas aderentes às especificações do `docs/DESIGN.md`.

---

## 27. Pontos Pendentes e Decisões Futuras

Não foram identificadas pendências para iniciar a codificação com base neste FSD. Todas as dúvidas funcionais, regras de negócio, modelo de dados e definições de arquitetura foram 100% resolvidas e consolidadas.

---

## 28. Conclusão

Este Documento de Especificação Funcional (`FSD.md`) está totalmente concluído e apto a guiar a IA codificadora e a equipe de desenvolvimento na implementação do sistema **AgendaBarber Web**.

Para a fase de codificação, devem ser fornecidos exclusivamente à IA codificadora os seguintes arquivos do repositório:
- `docs/FSD.md`
- `docs/DESIGN.md`

Não é necessária a apresentação do `PRD.md`, `DECISOES_TECNICAS.md` ou histórico de chats, uma vez que todas as diretrizes funcionais e técnicas foram consolidadas integralmente neste FSD.
