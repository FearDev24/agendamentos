# Publicação na InfinityFree — AgendaBarber Web

Este pacote tem duas partes:

| O que | Para onde vai |
|---|---|
| `htdocs/` | conteúdo enviado para a pasta **htdocs** da hospedagem |
| `banco.sql` | importado pelo **phpMyAdmin** — **não** deve ser enviado por FTP |

---

## 1. Criar a conta e o site

Esta parte é sua: eu não crio contas nem preencho senhas.

1. Em **infinityfree.com**, crie a conta e depois um site novo (*Create Account*).
2. Escolha um subdomínio gratuito — algo como `agendabarber.rf.gd`.
3. **Guarde a senha da conta de hospedagem.** Ela também é a senha do banco de
   dados: a InfinityFree não cria uma senha separada para o MySQL.

## 2. Criar o banco de dados

Caminho no painel:

> **Client Area → Accounts → Manage → Control Panel → MySQL Databases**

Digite um nome curto (por exemplo `agenda`) e clique em **Create Database**.
O painel monta o nome final sozinho, com o prefixo da sua conta.

Depois de criar, a mesma tela mostra três dos quatro dados de conexão:

| Dado | Onde aparece | Formato |
|---|---|---|
| **Database name** | em *Current Databases* | `if0_00000000_agenda` |
| **Username** | na mesma seção | `if0_00000000` |
| **Hostname** | na mesma seção | `sqlXXX.infinityfree.com` |
| **Password** | *não aparece aqui* | é a senha da sua conta |

Dois pontos que costumam causar erro de conexão:

- O host **não é `localhost`**. Tem de ser o `sqlXXX.infinityfree.com` indicado.
- A senha é a da conta de hospedagem, visível na *Client Area* clicando em
  *Show* ao lado do campo de senha.

## 3. Importar o banco

No painel, abra o **phpMyAdmin**, selecione o banco recém-criado na coluna da
esquerda e vá na aba **Import**. Envie `banco.sql` e confirme.

São 21 tabelas, com o catálogo de serviços, os dois profissionais e as contas
de demonstração. O arquivo tem 72 KB, bem abaixo do limite de importação.

Confira ao final que as 21 tabelas aparecem na lista. Se o phpMyAdmin
reclamar de tempo esgotado, importe de novo: o arquivo não cria banco nem
apaga nada, então repetir é seguro.

> As tabelas de log vão vazias de propósito: o histórico local era ruído de
> desenvolvimento e apareceria na tela de auditoria do cliente.

## 4. Preencher o config.php

Abra `htdocs/config/config.php` **no seu computador**, antes de enviar, e
preencha os campos marcados `>>> PREENCHER <<<`: os quatro dados do banco e a
`url` do site.

**Comece com `http://`**, não `https://`. O motivo está no passo 6.

A `chave_cripto` já vem preenchida com um valor exclusivo desta instalação —
não a substitua pela do ambiente local.

## 5. Enviar os arquivos, nesta ordem

Envie **o conteúdo** de `htdocs/` para a pasta `htdocs` da hospedagem — o
conteúdo, não a pasta em si. Use o gerenciador de arquivos do painel ou um
cliente FTP (FileZilla), com os dados de *FTP Accounts*.

A ordem importa por um motivo de segurança: **enquanto não existir `index.php`
na raiz, o site não funciona** — e é justamente isso que se quer no começo.
Subir o `index.php` por último equivale a ligar a chave só depois que todas as
proteções já estão no lugar.

**Antes de tudo**, apague os arquivos que a InfinityFree cria sozinha em
`htdocs` (normalmente um `index2.html`). Se ficarem, o servidor pode entregá-los
no lugar do sistema.

Depois envie nesta sequência:

1. `.htaccess` (o da raiz)
2. `config/` — inclusive o `.htaccess` e o `config.php` já preenchido
3. `app/`
4. `database/`
5. `logs/`
6. `vendor/`
7. `public/`
8. `sw.js`
9. **`index.php` por último**

Entre o passo 7 e o 9, confira pelo navegador que
`SEU-SITE/config/config.php` responde **403**. Se abrir o conteúdo do arquivo,
o `.htaccess` daquela pasta não subiu — corrija antes de prosseguir, porque
esse arquivo tem a senha do seu banco.

### Cuidado com os arquivos ocultos

**`.htaccess` existe em sete lugares**: raiz, `app/`, `config/`, `database/`,
`logs/`, `vendor/` e `public/uploads/`. Muitos clientes de FTP não exibem
arquivos iniciados por ponto e simplesmente não os enviam. No FileZilla, ative
*Servidor → Forçar exibição de arquivos ocultos*.

Sem eles, o roteamento não funciona e o código-fonte fica legível pelo
navegador — incluindo o `config.php`.

Por fim, em *PHP Configuration*, confirme que a versão é **8.0 ou superior**.

## 5. Ativar o certificado e só então mudar para HTTPS

No painel, em **Free SSL Certificates**, emita e instale o certificado. Pode
levar de alguns minutos a algumas horas.

**Só depois que o site abrir em `https://`**, volte ao `config.php` e troque a
`url` para `https://`.

A ordem importa. Sob HTTPS o sistema marca o cookie de sessão como `Secure`, e
esse cookie não trafega em conexões `http://`. Se você declarar `https://` antes
de o certificado existir, ninguém consegue entrar: o login parece aceitar e a
sessão se perde na página seguinte.

Pelo mesmo motivo, o HSTS só é enviado quando a `url` é `https://`.

## 6. Conferir

1. Abra o endereço: deve cair na tela de login.
2. Entre como administrador e **troque a senha imediatamente** — a padrão está
   na documentação do projeto.
3. Confirme que estes endereços **não** entregam conteúdo:
   `/config/config.php`, `/app/models/UsuarioModel.php`, `/database/`, `/logs/`.
   Todos devem dar 403. Se algum abrir o arquivo, o `.htaccess` daquela pasta
   não subiu.
4. Faça um agendamento de ponta a ponta, incluindo *Simular Pagamento Aprovado*.

---

## Contas de demonstração

```
Administrador : admin@agendabarber.local        / Admin@123
Profissional  : joao@demo.agendabarber.local    / Demo@123
Profissional  : marina@demo.agendabarber.local  / Demo@123
Cliente       : cliente@demo.agendabarber.local / Demo@123
```

Troque a senha do administrador antes de divulgar o endereço.

---

## O que não vai funcionar neste plano — e por quê

Nada disso atrapalha a demonstração, mas é bom saber antes de o cliente
perguntar:

- **E-mails não são enviados.** O plano gratuito bloqueia conexões de saída,
  então nenhum SMTP funcionaria. O `smtp.host` vai vazio de propósito: assim o
  sistema desiste na hora em vez de travar a tela de confirmação esperando um
  servidor que nunca responde. A tela ainda diz "enviamos o comprovante para o
  seu e-mail" — nenhum e-mail sai.

- **Pagamentos são simulados.** `sandbox_mode` fica `true`, por dois motivos: é
  uma demonstração, e o mesmo bloqueio de saída impediria a chamada à API do
  Mercado Pago. O fluxo aparece inteiro — QR Code, contagem regressiva,
  confirmação —, só não há dinheiro envolvido.

- **Lembretes automáticos não disparam.** Dependem de cron, que o plano gratuito
  não oferece. O sistema funciona normalmente sem eles.

Para uso real pelo cliente, esses três pontos exigem hospedagem paga.

---

## Ao demonstrar

Escolha uma **data futura** na grade de horários. O dia corrente vai esvaziando
conforme as horas passam, e um cliente que abre à noite vê a agenda vazia.
