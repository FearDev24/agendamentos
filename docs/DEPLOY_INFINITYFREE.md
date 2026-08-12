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
3. Anote o que o painel mostrar em **MySQL Databases**:
   - *Database host* (algo como `sqlXXX.infinityfree.com` — **não** é `localhost`)
   - *Database name* (começa com `if0_`)
   - *Database user*
   - a senha que você definiu

## 2. Importar o banco

No painel, abra o **phpMyAdmin**, selecione o banco criado e vá em **Import**.
Envie o arquivo `banco.sql`. Ele traz a estrutura completa, o catálogo de
serviços, os dois profissionais e as contas de demonstração.

> As tabelas de log vão vazias de propósito: o histórico local era ruído de
> desenvolvimento e apareceria na tela de auditoria.

## 3. Preencher o config.php

Abra `htdocs/config/config.php` e preencha os campos marcados
`>>> PREENCHER <<<`: os quatro dados do banco e a `url` do site.

**Comece com `http://`**, não `https://`. O motivo está no passo 5.

A `chave_cripto` já vem preenchida com um valor exclusivo desta instalação —
não a substitua pela do ambiente local.

## 4. Enviar os arquivos

Envie **todo o conteúdo** de `htdocs/` para a pasta `htdocs` da hospedagem —
o conteúdo, não a pasta em si. O `index.php` precisa ficar na raiz de `htdocs`.

Use o gerenciador de arquivos do painel ou um cliente FTP (FileZilla) com os
dados que aparecem em *FTP Accounts*.

Confira que os arquivos ocultos subiram: **`.htaccess` existe em sete lugares**
(raiz, `app/`, `config/`, `database/`, `logs/`, `vendor/`, `public/uploads/`).
Muitos clientes de FTP escondem arquivos iniciados por ponto. Sem eles, o
roteamento não funciona e o código-fonte fica legível pelo navegador.

Em *PHP Configuration*, confirme que a versão é **8.0 ou superior**.

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
