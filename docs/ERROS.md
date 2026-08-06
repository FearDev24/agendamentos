# Registro de Erros e Soluções - AgendaBarber Web

Este arquivo contém o registro histórico de erros encontrados e corrigidos durante o desenvolvimento do projeto **AgendaBarber Web**.

---

## Modelo de Registro

```text
## YYYY-MM-DD - <título curto do erro>

- Sintoma: <descrição do comportamento inesperado ou mensagem de erro>
- Causa: <diagnóstico da origem do problema>
- Solução aplicada: <detalhes da correção efetuada>
- Como evitar no futuro: <diretriz ou boas práticas a seguir>
```

---

## Histórico de Ocorrências

## 2026-08-06 - Cookie de sessão sem a flag Secure

- Sintoma: Nenhum. Falha silenciosa, encontrada na revisão de segurança anterior à publicação.
- Causa: `SessionHelper::init()` definia `httponly`, `use_only_cookies` e `samesite`, mas não `session.cookie_secure`. Sob HTTPS, bastaria uma única requisição `http://` para o identificador de sessão trafegar em texto claro e ser capturado na rede.
- Solução aplicada: `session.cookie_secure` passa a ser ativado quando a aplicação roda sob HTTPS. A verificação usa o esquema da `app.url` configurada, e **não** `$_SERVER['HTTPS']`: atrás de um túnel ou proxy reverso o PHP recebe a conexão interna em HTTP e não enxergaria o TLS. O `destroy()` também passou a expirar o cookie, que antes permanecia no navegador após o logout.
- Como evitar no futuro: Ao configurar cookies, tratar as quatro flags como um conjunto — `HttpOnly`, `Secure`, `SameSite` e `use_only_cookies`. A bateria `tests/teste_seguranca.php` agora confere cada uma, comparando com o protocolo em uso.

## 2026-08-06 - Redefinição de senha não expulsava sessões abertas

- Sintoma: Após redefinir a senha, sessões iniciadas antes da troca continuavam válidas.
- Causa: A autenticação verificava apenas a presença de `user_id` na sessão. Nada relacionava a sessão à senha vigente, então trocar a senha não tinha efeito sobre sessões já estabelecidas — justamente no cenário que motiva a troca (conta comprometida), o invasor permanecia com acesso.
- Solução aplicada: Migration 007 adiciona `usuarios.senha_alterada_em`, gravada em toda troca de senha. `AuthHelper::check()` compara essa marca com o `logado_em` guardado na sessão e descarta as anteriores. A consulta ocorre uma vez por requisição, com cache estático, porque `check()` é chamado várias vezes por página.
- Como evitar no futuro: Toda credencial revogável precisa de um ponto de comparação no servidor; o estado da sessão sozinho não é revogável.

## 2026-08-06 - Coluna nula tratada como conta inexistente derrubava usuários novos

- Sintoma: Logo após a correção acima, o teste `Sessão inicial está autenticada` falhou: uma conta recém-criada não conseguia permanecer autenticada.
- Causa: A migration preencheu `senha_alterada_em` apenas nas contas existentes. Contas criadas depois nasciam com a coluna nula, e a validação tratava `null` e `false` no mesmo `if` — nulo ("senha nunca trocada") era interpretado como "conta removida", invalidando a sessão.
- Solução aplicada: Os dois casos foram separados: `false` (sem linha) invalida, `null` (nunca trocada) é válido. `UsuarioModel::criar()` passou a gravar `senha_alterada_em = NOW()`, eliminando o estado nulo em contas novas.
- Como evitar no futuro: `fetchColumn()` devolve `false` para "sem linha" e `null` para "coluna nula" — são situações distintas e comparar com `==` ou agrupá-las num mesmo `if` apaga a diferença. Ao adicionar coluna por migration, cobrir também o caminho de criação de novos registros.

## 2026-08-06 - X-XSS-Protection permanecia no .htaccess após remoção no PHP

- Sintoma: O teste `X-XSS-Protection foi removido` falhou mesmo após a retirada do cabeçalho no `index.php`.
- Causa: O cabeçalho era emitido em dois lugares. O `.htaccess` da raiz também o definia, para os arquivos estáticos servidos direto pelo Apache sem passar pelo Front Controller.
- Solução aplicada: Removido também do `.htaccess`, que ganhou ainda o `Referrer-Policy` — os arquivos estáticos não o recebiam.
- Como evitar no futuro: Cabeçalhos HTTP neste projeto têm duas origens (PHP e Apache). Ao alterar um, verificar as duas. O teste que pegou isto consulta a resposta HTTP real, e não o código-fonte — é o único jeito de detectar configuração duplicada.

## 2026-08-05 - Cron falhava ao somar dois INTERVAL no MariaDB

- Sintoma: `database/cron_lembretes.php` abortava com erro 1064 de sintaxe SQL ao buscar os agendamentos da janela de lembrete.
- Causa: A consulta usava `DATE_ADD(NOW(), INTERVAL :horas HOUR + INTERVAL 30 MINUTE)`. O MariaDB não aceita a soma de dois `INTERVAL` dentro de um mesmo `DATE_ADD`.
- Solução aplicada: Uso de `DATE_ADD` aninhado: `DATE_ADD(DATE_ADD(NOW(), INTERVAL :horas HOUR), INTERVAL 30 MINUTE)`.
- Como evitar no futuro: Testar toda consulta nova executando-a de fato, e não apenas conferindo a sintaxe do PHP. O erro só apareceu porque o Cron foi executado durante o desenvolvimento.

## 2026-08-05 - Campos de formulário sem rótulo acessível

- Sintoma: A verificação de acessibilidade apontou seis campos sem `label` associado nem `aria-label`: os quatro horários da grade semanal do profissional, o campo do link de compartilhamento da promoção e o comentário da avaliação.
- Causa: Campos dentro de células de tabela e campos somente leitura foram construídos apoiados no cabeçalho da coluna ou no `placeholder`. Nenhum dos dois é lido como rótulo por leitores de tela.
- Solução aplicada: Acrescentado `aria-label` descritivo em cada um, incluindo o dia da semana nos horários da jornada.
- Como evitar no futuro: `placeholder` não é rótulo. Todo campo precisa de `<label for>` ou `aria-label`, e a bateria da Fase 9 passou a verificar isso em todas as Views.

## 2026-08-05 - Análise de HTML quebrada por tags PHP curtas

- Sintoma: A verificação de acessibilidade acusou imagens sem `alt` e campos sem `id` que, na prática, os possuíam.
- Causa: As expressões regulares usavam `[^>]*` para percorrer os atributos da tag. Como `<?= $var ?>` contém `>`, a análise encerrava a tag prematuramente e não enxergava os atributos seguintes.
- Solução aplicada: O conteúdo passa por uma remoção prévia dos blocos PHP antes de a estrutura HTML ser analisada.
- Como evitar no futuro: Ao inspecionar HTML dentro de arquivos PHP, sempre remover os blocos de código antes de aplicar expressões regulares sobre a marcação.

## 2026-08-05 - Estorno descontado duas vezes do lucro

- Sintoma: Ao estornar um pagamento, o lucro líquido caía pelo dobro do valor devolvido.
- Causa: `receitaNoPeriodo()` somava apenas pagamentos com status `aprovado`. Ao ser estornada, a linha muda para `estornado` e sai dessa soma — mas `resumoFinanceiro()` ainda subtraía o mesmo valor como estorno. O reembolso era abatido duas vezes.
- Solução aplicada: Criado `receitaBrutaNoPeriodo()`, que soma `aprovado` **e** `estornado` (tudo que chegou a ser compensado). Agora vale sempre `receita_liquida = receita_bruta − estornos`, e a receita líquida coincide com a soma dos pagamentos aprovados.
- Como evitar no futuro: Ao somar e subtrair a mesma grandeza em um cálculo, verificar se a subtração já não está implícita no filtro da soma.

## 2026-08-05 - Erro fatal de PHP respondia HTTP 200 e passava nos testes

- Sintoma: A tela `/admin/agenda` exibia apenas uma mensagem de erro fatal (`Call to undefined method AdminController::ehDataValida()`), mas a bateria de testes registrava a rota como acessível.
- Causa: Com `display_errors` ligado, um erro fatal é servido com status **200**. As asserções verificavam somente o código de status, então uma tela completamente quebrada passava como aprovada.
- Solução aplicada:
  1. Criado o método ausente `ehDataValida()` no `AdminController`;
  2. Adicionado `HttpClient::erroPhp()`, que detecta `Fatal error`, `Parse error`, `Uncaught`, `Warning`, `Notice` e `Deprecated` no corpo da resposta;
  3. Criado o grupo 10 em `tests/teste_frontend.php`, que percorre **todas** as telas com cada perfil e falha se encontrar qualquer aviso do PHP ou se a página vier com conteúdo suspeito de truncamento.
- Como evitar no futuro: Nenhuma asserção de "página acessível" deve se apoiar apenas no código HTTP. A varredura do grupo 10 cobre 27 telas e roda junto com as demais baterias.

## 2026-08-05 - Banner do PWA ficava invisível ao ser reexibido

- Sintoma: O convite de instalação aparecia na primeira vez, mas ao ser dispensado e exibido novamente na mesma sessão ficava preso no primeiro quadro da animação (`translateY(100%)`, `opacity: 0`) — ocupando espaço, porém invisível.
- Causa: A entrada era feita por `@keyframes` disparado pela troca do atributo `hidden`. Uma animação CSS não reexecuta de forma confiável nessa condição.
- Solução aplicada: Substituída por transição comandada pela classe `.convite-visivel`. A classe entra após um reflow forçado (`void banner.offsetHeight`), que registra o estado inicial e faz a transição acontecer.
- Detalhe adicional: a primeira tentativa usava `requestAnimationFrame` para aplicar a classe, o que também falha — o rAF **não dispara em abas em segundo plano**, e o banner ficaria permanentemente invisível para quem abrisse o site em outra aba. O reflow forçado é síncrono e não tem essa limitação.
- Como evitar no futuro: Para revelar elementos, usar transição sobre classe com reflow forçado, nunca `@keyframes` atrelado a `hidden` nem `requestAnimationFrame`.

## 2026-08-05 - Bootstrap JS era um stub: nenhum botão interativo funcionava

- Sintoma: Clicar em "Cancelar" no painel do cliente não abria o formulário de cancelamento. O mesmo ocorria com "Concluir Atendimento" na agenda do profissional, com as abas Pix/Cartão do checkout e com o menu de navegação no celular.
- Causa: O arquivo `public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js` criado na Fase 1 era um **placeholder de 175 bytes** que apenas escrevia no console. O Bootstrap CSS era real (232 KB), então as telas *pareciam* corretas, mas `window.bootstrap` ficava indefinido e todo componente JS (collapse, tab, modal, dropdown) era inerte.
- Por que passou despercebido por seis fases: as baterias de teste validavam o HTML devolvido pelo servidor. Elas confirmavam que o botão existia na página, jamais que ele **funcionava** ao ser clicado.
- Solução aplicada:
  1. Instalado o Bootstrap 5.3.3 real (80 KB);
  2. Criado `FormatHelper::asset()`, que acrescenta `?v=<filemtime>` a todo CSS/JS — sem isso o navegador continuava servindo o arquivo antigo do cache mesmo após a correção;
  3. Criada a bateria `tests/teste_frontend.php`, que verifica se os pacotes de terceiros são reais (tamanho, componentes esperados, ausência da palavra "stub"), se todo asset referenciado responde 200 com conteúdo e se nenhuma View depende de CDN externa;
  4. Verificado no navegador, com cliques reais, cada interação dependente de JavaScript.
- Como evitar no futuro: `tests/teste_frontend.php` roda junto com as demais baterias. A regressão foi confirmada de propósito: ao restaurar o stub, 6 testes falham; com o arquivo real, todos passam.

## 2026-08-05 - Término sugerido anterior ao início ao concluir atendimento

- Sintoma: Na agenda do profissional, abrir "Concluir Atendimento" de um atendimento que não fosse do horário corrente preenchia o campo "término real" com a hora atual do relógio, resultando em um horário **anterior** ao início. Concluir aceitando o padrão era recusado com "o horário de término deve ser posterior ao de início".
- Causa: O campo usava `date('H:i')` incondicionalmente.
- Solução aplicada: O término sugerido passou a ser o relógio atual apenas quando o atendimento é do dia e já começou; caso contrário, sugere o horário de término previsto do agendamento.
- Como evitar no futuro: Valores padrão de formulário derivados do relógio precisam ser coerentes com o registro exibido, não com o instante da renderização.

## 2026-08-05 - Cartão aprovado não confirmava o agendamento

- Sintoma: No pagamento com cartão aprovado na hora, o agendamento permanecia em `aguardando_pagamento`, apesar de o pagamento constar como aprovado.
- Causa: `PagamentoModel::registrar()` grava o status devolvido pelo gateway, então o cartão aprovado já nascia com `status_pagamento = 'aprovado'`. Logo depois, `aprovar()` verificava a idempotência olhando o **pagamento**, encontrava-o já aprovado e retornava `ja_processado` sem nunca confirmar o agendamento. O Pix não sofria disso porque nasce pendente.
- Solução aplicada: A idempotência passou a ser decidida pelo estado do **agendamento**, não do pagamento. Se o agendamento já está `confirmado`, devolve `ja_processado`; se está `aguardando_pagamento`, confirma — independentemente de o pagamento já ter nascido aprovado.
- Como evitar no futuro: Em fluxos idempotentes, ancorar a verificação no estado que a operação pretende alcançar (o agendamento confirmado), e não em um efeito colateral intermediário.

## 2026-08-05 - `recusar()` devolvia falso para cartão negado na hora

- Sintoma: `PagamentoModel::recusar()` retornava `false` quando o cartão já vinha recusado do gateway.
- Causa: O método só atualizava linhas com status `pendente` e devolvia o `rowCount()`. Como o registro já nascia `recusado`, nada era alterado e o retorno era falso, sugerindo falha onde não havia.
- Solução aplicada: O método passou a garantir o estado final e devolver `true` sempre que a transação termina recusada, mantendo a regra de nunca rebaixar um pagamento já aprovado.
- Como evitar no futuro: Métodos de transição de estado devem reportar o **estado alcançado**, não a quantidade de linhas alteradas.

## 2026-08-04 - Retorno pós-login perdia o fluxo de agendamento

- Sintoma: O cliente não logado que preenchia todo o wizard e clicava em "Ir para Pagamento" era levado ao login e, após autenticar, caía em `/cliente/agendamentos` em vez de voltar à revisão do agendamento.
- Causa: `AgendamentoController::confirmar()` gravava a URL **absoluta** (`http://localhost/sistemaBarbearia/agendar/revisao`) em `url_pretendida`. A proteção contra open redirect do `AuthController::rotaRelativa()` descarta corretamente qualquer URL absoluta, então o destino era ignorado.
- Solução aplicada: Passou-se a gravar a rota em formato relativo (`/agendar/revisao`), como já fazia o `AuthHelper::requireAuth()`.
- Como evitar no futuro: `url_pretendida` só aceita caminhos relativos. Nunca concatenar `$this->baseUrl` ao gravar essa chave.

## 2026-08-04 - Testes quebravam a cada nova migration ou configuração

- Sintoma: O teste da Fase 2 falhou duas vezes sem que houvesse defeito: ao adicionar a migration 003 (contagem fixa de 2 migrations) e a 004 (contagem fixa de 17 configurações).
- Causa: Asserções com valores absolutos sobre dados que crescem naturalmente a cada fase.
- Solução aplicada: A contagem de migrations passou a comparar com os arquivos disponíveis, e a de configurações passou a exigir um mínimo em vez de igualdade.
- Como evitar no futuro: Em dados incrementais, testar propriedades (presença das chaves obrigatórias, mínimo esperado) e não totais fixos.

## 2026-08-04 - Pasta `app/` sem `.htaccess` de proteção

- Sintoma: Os arquivos de `app/controllers/`, `app/models/` e `app/helpers/` ficavam acessíveis por URL direta, contrariando o `docs/FSD.md` seção 5.
- Causa: A Fase 1 criou `.htaccess` em `config/`, `database/`, `logs/` e `public/uploads/`, mas esqueceu `app/`.
- Solução aplicada: Criado `app/.htaccess` com `Require all denied`. O guard `APP_EXEC` já impedia a execução, mas sem o `.htaccess` o código-fonte podia ser lido em servidores mal configurados.
- Como evitar no futuro: O teste da Fase 3 verifica por HTTP se cada diretório interno responde 403/404.

## 2026-08-04 - Roteador devolvia 404 no lugar de 405

- Sintoma: Requisitar uma rota existente com o método HTTP errado (ex: `GET /perfil/senha`, que só aceita POST) retornava 404.
- Causa: Em `Router::despachar()`, o `http_response_code(405)` era definido e logo em seguida `naoEncontrado()` sobrescrevia com 404.
- Solução aplicada: O ramo de 405 passou a responder e encerrar antes de cair no 404, mantendo o cabeçalho `Allow`.
- Como evitar no futuro: Nunca definir um código de status e seguir chamando outro handler que também define status.

## 2026-08-04 - Falso negativo no teste dos flags do cookie de sessão

- Sintoma: Os testes "Cookie de sessão HttpOnly" e "SameSite" falhavam, apesar de o cookie estar correto.
- Causa: O cliente HTTP do teste reaproveitava uma sessão já aberta, e o PHP só envia `Set-Cookie` na primeira requisição.
- Solução aplicada: A verificação passou a usar uma instância nova de `HttpClient`, sem cookies prévios.
- Como evitar no futuro: Testes de cabeçalho `Set-Cookie` exigem sempre uma sessão limpa.

## 2026-08-04 - Typo `almocho_inicio` / `almocho_fim` na tabela `horarios_trabalho`

- Sintoma: O DDL do `docs/FSD.md` (seção 11, tabela 5) define as colunas do intervalo de almoço como `almocho_inicio` e `almocho_fim`.
- Causa: Erro de digitação na especificação ("almocho" em vez de "almoco").
- Solução aplicada: A migration `001_create_system_tables.php` cria as colunas com o nome correto `almoco_inicio` e `almoco_fim`. O teste da Fase 2 valida esses nomes.
- Como evitar no futuro: Ao consumir DDL do FSD, revisar nomes de colunas antes de replicar. Os Models da Fase 4 devem usar `almoco_*`.

## 2026-08-04 - Falso negativo no teste de `PDO::ATTR_EMULATE_PREPARES`

- Sintoma: O teste "Prepared Statements reais (emulação desligada)" falhava mesmo com a opção corretamente configurada em `Database.php`.
- Causa: A asserção comparava com `=== false`, mas `PDO::getAttribute(PDO::ATTR_EMULATE_PREPARES)` devolve um inteiro (`0`), não um booleano.
- Solução aplicada: Asserção alterada para `(int)$pdo->getAttribute(...) === 0`.
- Como evitar no futuro: Não assumir o tipo de retorno de `PDO::getAttribute()`; inspecionar com `var_dump()` antes de escrever a asserção.

## 2026-08-04 - Falso negativo no teste do guard `APP_EXEC`

- Sintoma: O teste que verifica se as migrations recusam execução direta falhava, apesar do guard funcionar corretamente quando testado manualmente.
- Causa: A string do comando `exec()` montava aspas simples e duplas aninhadas manualmente, o que quebra no `cmd.exe` do Windows.
- Solução aplicada: Uso de `escapeshellarg()` para o binário e para o código PHP, com `var_export()` no caminho do arquivo. O teste foi ampliado para cobrir os 5 arquivos protegidos.
- Como evitar no futuro: Nunca concatenar aspas manualmente em comandos de shell; usar sempre `escapeshellarg()`, principalmente em ambiente Windows.
