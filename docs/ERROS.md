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
