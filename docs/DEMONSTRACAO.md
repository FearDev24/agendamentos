# Demonstração para o Cliente sem Hospedagem

Procedimento para apresentar o sistema a um cliente remoto usando a própria
máquina de desenvolvimento, publicada por um túnel HTTPS da Cloudflare.

A vantagem sobre hospedar: é o único ambiente onde a suíte de 1.460 testes
comprovadamente passa. Nenhuma versão de PHP diferente, nenhum caminho novo,
nenhuma permissão diferente — portanto nenhum bug novo no dia da apresentação.

---

## ⚠️ O erro que não pode ser cometido

**Nunca aponte o túnel para `http://localhost`.**

Os *aliases* do XAMPP são declarados no escopo global do Apache
(`conf/extra/httpd-xampp.conf`) e, por isso, valem dentro de **qualquer**
VirtualHost:

```
Alias /phpmyadmin  "C:/xampp/phpMyAdmin/"
Alias /webalizer   "C:/xampp/webalizer/"
Alias /licenses    "C:/xampp/licenses/"
ScriptAlias /php-cgi/ "C:/xampp/php/"
```

Publicar a raiz do localhost exporia o **phpMyAdmin na internet**, com o MySQL
do XAMPP em `root` sem senha — controle total do banco para quem tivesse o link.
Definir um `DocumentRoot` diferente **não** resolve: os aliases continuam valendo.
É preciso bloqueá-los explicitamente, como faz o VirtualHost abaixo.

---

## 1. VirtualHost dedicado (uma vez só)

Acrescente ao final de `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
Listen 8080

<VirtualHost *:8080>
    ServerName agendabarber.demo
    DocumentRoot "C:/xampp/htdocs/sistemaBarbearia"

    <Directory "C:/xampp/htdocs/sistemaBarbearia">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Location "/phpmyadmin">   Require all denied </Location>
    <Location "/webalizer">    Require all denied </Location>
    <Location "/licenses">     Require all denied </Location>
    <Location "/php-cgi">      Require all denied </Location>
    <Location "/server-status"> Require all denied </Location>
    <Location "/server-info">  Require all denied </Location>
</VirtualHost>
```

Valide e reinicie o Apache:

```
C:\xampp\apache\bin\httpd.exe -t
```

O Apache do XAMPP não roda como serviço do Windows: reinicie pelo Control Panel,
ou encerre o processo `httpd` e execute `C:\xampp\apache\bin\httpd.exe` de novo.

## 2. Conferência de exposição

Antes de abrir o túnel, confirme na porta 8080:

| Endereço | Esperado |
|---|---|
| `/` | 200 |
| `/phpmyadmin/`, `/webalizer/`, `/licenses/` | 404 |
| `/config/config.php` | 403 |
| `/app/models/UsuarioModel.php` | 403 |
| `/docs/`, `/tests/` | 403 |

## 3. Dados e configuração

```
C:\xampp\php\php.exe database/seed_demo.php
```

O seed **exige** `env => 'development'` — rode-o antes de trocar para produção.

Depois ajuste `config/config.php`:

| Campo | Valor na demonstração | Por quê |
|---|---|---|
| `app.url` | endereço do túnel | senão links e imagens quebram |
| `app.env` | `production` | em `development` os erros viram *stack trace* na tela |
| `smtp.host` | `''` (vazio) | ver abaixo |
| `mercadopago.sandbox_mode` | `true` | mostra o fluxo de pagamento sem cobrar ninguém |

**Sobre o SMTP**: com `env` em `production` o `MailHelper` passa a enviar de
verdade. Se o host configurado for fictício ou inacessível, o PHPMailer fica
minutos tentando conectar e **trava a tela de confirmação na frente do cliente**.
Com o host vazio, o `MailHelper` desiste imediatamente e apenas registra a falha,
sem interromper o agendamento.

## 4. Túnel

```
cloudflared tunnel --url http://localhost:8080
```

O endereço `https://<algo>.trycloudflare.com` aparece na saída do comando.
Ele **muda a cada execução** — reflita o novo valor em `app.url`.

Com HTTPS, o PWA volta a funcionar: o cliente abre o link no celular e instala
o sistema como aplicativo. Em HTTP puro (rede local) isso não é oferecido.

## 5. Contas de demonstração

```
Administrador : admin@agendabarber.local        / Admin@123
Profissional  : joao@demo.agendabarber.local    / Demo@123
Profissional  : marina@demo.agendabarber.local  / Demo@123
Cliente       : cliente@demo.agendabarber.local / Demo@123
```

## 6. Ao encerrar

1. Feche o processo `cloudflared` — o endereço deixa de existir;
2. Restaure `config/config.php`: `url` para `http://localhost/sistemaBarbearia`,
   `env` para `development` e o `smtp.host` original;
3. Se não for demonstrar de novo, remova o bloco do `httpd-vhosts.conf`
   (backup em `httpd-vhosts.conf.bak-agendabarber`) e reinicie o Apache.

---

## Roteiro sugerido

1. **Página inicial** — catálogo de serviços com preços e durações;
2. **Agendamento** — serviços → profissional e data → horário. Escolha uma data
   futura: a grade do próprio dia esvazia conforme as horas passam;
3. **Pagamento** — gerar o Pix, mostrar o QR Code e o contador de 10 minutos,
   e usar *Simular Pagamento Aprovado* para exibir a confirmação automática;
4. **Área do cliente** — comprovante, fidelidade, reagendamento e cancelamento;
5. **Painel administrativo** — agenda global, financeiro, relatórios e auditoria;
6. **Instalar como aplicativo** — peça para o cliente abrir o link no celular.
