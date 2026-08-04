# DOCUMENTO DE REQUISITOS DO PRODUTO (PRD)

## 1. Visão Geral do Produto

### Nome provisório

**AgendaBarber Web**

O nome é provisório e poderá ser alterado futuramente.

### Descrição resumida

O AgendaBarber Web será um sistema web de agendamento de horários para uma barbearia ou pequeno estabelecimento de estética.

O sistema permitirá que clientes consultem serviços, preços, profissionais e horários disponíveis, realizem o agendamento por conta própria e façam o pagamento no momento da reserva.

O estabelecimento poderá organizar sua agenda, cadastrar serviços e profissionais, definir preços e horários, publicar promoções, acompanhar pagamentos, controlar receitas e despesas e gerenciar os atendimentos.

### Público principal

O sistema será utilizado por:

- clientes do estabelecimento;
- profissionais que realizam os atendimentos;
- proprietário ou administrador principal;
- administradores auxiliares com permissões limitadas.

### Principal benefício esperado

Permitir que os clientes agendem e paguem pelos serviços sem depender do atendimento manual do estabelecimento, reduzindo mensagens, ligações, conflitos de horários, esquecimentos e esforço administrativo.

### Contexto de uso

O sistema será inicialmente utilizado por uma única unidade de uma barbearia ou pequeno negócio de estética.

Os clientes acessarão o sistema pela internet para consultar e contratar serviços. Os profissionais e administradores utilizarão áreas restritas para controlar agendas, atendimentos, clientes, promoções e informações financeiras.

---

## 2. Problema que o Sistema Resolve

Sem o sistema, o agendamento tende a depender de mensagens, ligações ou conversas presenciais.

Esse processo pode causar:

- demora para responder aos clientes;
- necessidade de conferir manualmente horários disponíveis;
- conflitos entre agendamentos;
- marcação do mesmo horário para mais de uma pessoa;
- dificuldade para reagendar ou cancelar atendimentos;
- perda de tempo do proprietário ou profissional;
- esquecimentos por parte dos clientes;
- faltas sem aviso;
- dificuldade para cobrar antecipadamente;
- falta de regras claras sobre cancelamento e reembolso;
- dificuldade para acompanhar receitas, despesas e lucro;
- informações espalhadas em mensagens, cadernos ou planilhas;
- dificuldade para saber quem alterou preços, horários ou agendamentos;
- pouca visibilidade sobre o movimento diário do estabelecimento;
- dificuldade para divulgar promoções de forma organizada.

O sistema pretende centralizar essas atividades em um único ambiente, permitindo que o cliente realize o processo de agendamento de forma autônoma e que o estabelecimento mantenha o controle da operação.

---

## 3. Objetivos do Sistema

### Objetivo principal

Facilitar o agendamento e o pagamento antecipado dos serviços, reduzindo o trabalho manual do estabelecimento e oferecendo mais autonomia ao cliente.

### Objetivos específicos

- Permitir que o cliente consulte horários realmente disponíveis.
- Permitir que o cliente escolha um ou vários serviços.
- Permitir que o cliente escolha um profissional específico ou qualquer profissional disponível.
- Calcular automaticamente o tempo e o valor total do atendimento.
- Exigir o pagamento no momento do agendamento.
- Aceitar pagamentos por Pix e cartão de crédito.
- Evitar agendamentos duplicados.
- Enviar confirmações e lembretes sobre os atendimentos.
- Permitir cancelamentos e reagendamentos dentro das regras estabelecidas.
- Informar claramente a política de cancelamento e reembolso antes do pagamento.
- Permitir que administradores organizem serviços, preços, profissionais e horários.
- Permitir que profissionais consultem a própria agenda.
- Registrar atendimentos concluídos, cancelamentos, faltas e reembolsos.
- Controlar receitas, despesas e lucro do estabelecimento.
- Publicar promoções com imagens ou banners.
- Criar um programa simples de fidelidade com insígnias.
- Manter histórico das principais alterações administrativas.
- Disponibilizar relatórios e exportações para acompanhamento do negócio.

---

## 4. Personas e Perfis de Usuário

| Perfil | Descrição simples | Principais ações no sistema | Permissões básicas |
| ------ | ----------------- | --------------------------- | ------------------ |
| Cliente | Pessoa maior de 18 anos que deseja contratar um serviço do estabelecimento. | Criar conta, consultar serviços, preços, promoções e horários, escolher profissional, agendar, pagar, reagendar, cancelar, consultar comprovantes, acompanhar fidelidade e avaliar o atendimento. | Acessar e alterar os próprios dados, visualizar informações públicas e controlar apenas os próprios agendamentos. |
| Profissional | Pessoa que realiza os serviços oferecidos pelo estabelecimento. | Consultar a própria agenda, visualizar os serviços que realiza, confirmar presença e registrar a conclusão dos atendimentos. | Acessar somente informações necessárias aos próprios atendimentos e clientes relacionados à sua agenda. |
| Administrador principal | Proprietário ou responsável principal pelo estabelecimento. | Gerenciar serviços, preços, profissionais, agendas, clientes, pagamentos, promoções, despesas, relatórios, permissões e configurações. | Acesso completo às funções administrativas confirmadas para a primeira versão. |
| Administrador limitado | Gerente ou colaborador autorizado pelo administrador principal. | Executar apenas as atividades administrativas que forem liberadas. | Permissões específicas e limitadas, definidas pelo administrador principal. |

---

## 5. Escopo da Primeira Versão

### 5.1. Cadastro e acesso

#### Cadastro de clientes

O cliente poderá criar uma conta antes de realizar um agendamento.

O cadastro deverá conter obrigatoriamente:

- nome;
- e-mail;
- telefone;
- senha.

A foto será opcional.

Essa funcionalidade será utilizada pelo cliente e permitirá manter seu histórico, pagamentos, comprovantes, agendamentos e fidelidade.

#### Login por e-mail e senha

Clientes, profissionais e administradores acessarão suas áreas por meio de e-mail e senha.

Cada perfil visualizará somente as funções permitidas.

#### Recuperação de senha

O usuário poderá solicitar um link de recuperação enviado ao e-mail cadastrado.

#### Alteração dos dados pessoais

O cliente poderá atualizar telefone, foto e demais informações do perfil.

A alteração do e-mail deverá exigir confirmação.

#### Exclusão da conta

O cliente poderá solicitar a exclusão da própria conta.

A exclusão somente poderá ser finalizada depois que todos os agendamentos ativos forem concluídos ou cancelados.

O sistema poderá preservar informações que precisem ser mantidas por obrigação legal.

#### Cadastro restrito a maiores de idade

Na primeira versão, somente pessoas maiores de 18 anos poderão criar uma conta.

---

### 5.2. Cadastro e apresentação do estabelecimento

O administrador poderá cadastrar e atualizar as informações públicas do estabelecimento, incluindo:

- nome;
- endereço;
- telefone;
- horário de funcionamento;
- redes sociais;
- localização no mapa.

O cliente poderá utilizar um botão para abrir a localização em um serviço de mapas.

---

### 5.3. Cadastro e gestão de profissionais

O administrador poderá cadastrar profissionais e definir:

- os serviços que cada profissional realiza;
- os dias e horários de trabalho;
- os períodos de folga ou indisponibilidade;
- um limite diário opcional de atendimentos.

Cada profissional terá sua própria agenda.

O cliente poderá selecionar um profissional específico ou escolher qualquer profissional disponível.

---

### 5.4. Cadastro e gestão de serviços

O administrador poderá cadastrar, editar, ativar e desativar serviços.

Cada serviço deverá possuir:

- nome;
- preço;
- duração prevista.

Um serviço poderá ficar temporariamente indisponível, com previsão opcional de retorno.

Alterações de preço não modificarão agendamentos já pagos. O cliente manterá o valor confirmado no momento da contratação.

A possibilidade de cadastrar imagem, descrição detalhada e orientações do serviço ainda depende de confirmação.

---

### 5.5. Configuração da agenda

O administrador poderá definir:

- dias de atendimento;
- horários de funcionamento;
- horários de cada profissional;
- intervalo padrão de 10 minutos entre atendimentos;
- feriados;
- fechamentos;
- horários especiais;
- bloqueios de horários;
- bloqueios de dias inteiros;
- bloqueios por intervalo de datas;
- limite diário opcional por profissional.

A agenda deverá considerar a duração dos serviços selecionados e o intervalo entre os atendimentos.

Um horário somente poderá aparecer para o cliente quando houver tempo suficiente e um profissional habilitado disponível.

---

### 5.6. Consulta de horários disponíveis

O cliente poderá consultar o calendário e visualizar apenas horários disponíveis para agendamento.

A disponibilidade deverá considerar:

- serviço ou serviços escolhidos;
- duração total;
- intervalo de 10 minutos;
- profissional escolhido;
- horário de trabalho do profissional;
- bloqueios;
- feriados;
- horários especiais;
- limite diário do profissional;
- agendamentos existentes;
- antecedência mínima e máxima.

---

### 5.7. Agendamento pelo cliente

O cliente poderá selecionar um ou vários serviços no mesmo agendamento.

O sistema deverá:

- somar os preços;
- somar as durações;
- considerar o intervalo entre atendimentos;
- verificar a disponibilidade;
- apresentar o valor total;
- exigir o aceite da política;
- encaminhar o cliente ao pagamento.

O cliente poderá agendar:

- com no máximo 30 dias de antecedência;
- com no mínimo 2 horas de antecedência.

Cada cliente poderá manter no máximo quatro agendamentos futuros ativos.

---

### 5.8. Reserva temporária do horário

Quando o cliente iniciar o pagamento, o horário ficará reservado temporariamente por 10 minutos.

Durante esse período, outro cliente não poderá reservar o mesmo horário.

Caso o pagamento não seja aprovado dentro do prazo:

- o cliente poderá tentar novamente enquanto houver tempo;
- o horário será liberado automaticamente ao fim dos 10 minutos.

O agendamento somente será confirmado após a aprovação do pagamento.

---

### 5.9. Pagamento do agendamento

O pagamento será obrigatório no momento do agendamento.

As formas de pagamento confirmadas são:

- Pix;
- cartão de crédito.

O cliente deverá pagar o valor total dos serviços selecionados.

O sistema deverá gerar um comprovante de pagamento e:

- disponibilizá-lo na área do cliente;
- enviá-lo por e-mail.

A emissão de nota fiscal não fará parte da primeira versão.

---

### 5.10. Aceite das políticas

Antes do pagamento, o cliente deverá marcar uma caixa de confirmação declarando que leu e aceitou as regras.

A tela deverá apresentar um link para a política completa.

A política deverá informar claramente:

- pagamento antecipado;
- prazo de cancelamento;
- condições de reembolso;
- regras de atraso;
- tratamento de faltas;
- condições de reagendamento;
- prazo do meio de pagamento para devolver valores.

---

### 5.11. Confirmação e situações do agendamento

Os agendamentos poderão possuir as seguintes situações:

- aguardando pagamento;
- confirmado;
- concluído;
- cancelado;
- falta;
- reembolsado.

O sistema deverá informar ao cliente quando o pagamento for aprovado e o agendamento for confirmado.

---

### 5.12. Área “Meus agendamentos”

O cliente poderá consultar:

- próximos agendamentos;
- agendamentos anteriores;
- situação de cada agendamento;
- serviços contratados;
- profissional;
- data e horário;
- valor pago;
- comprovantes;
- possibilidade de cancelar ou reagendar, quando permitido.

---

### 5.13. Cancelamento pelo cliente

O cliente poderá cancelar o agendamento.

Quando o cancelamento ocorrer com pelo menos 24 horas de antecedência, deverá ser solicitado reembolso.

Quando ocorrer com menos de 24 horas de antecedência, não haverá reembolso.

A regra deverá ser exibida claramente antes do pagamento.

O administrador poderá autorizar exceções.

O prazo de recebimento do valor dependerá do Pix ou do cartão de crédito.

---

### 5.14. Reagendamento pelo cliente

O reagendamento será permitido até 24 horas antes do atendimento.

O cliente poderá reagendar o mesmo atendimento no máximo duas vezes.

O pagamento já realizado será reaproveitado.

Quando o novo valor for maior:

- o cliente deverá pagar a diferença.

Quando o novo valor for menor:

- o sistema deverá gerar crédito interno.

O reagendamento deverá continuar respeitando:

- disponibilidade;
- antecedência mínima;
- antecedência máxima;
- limite de agendamentos ativos;
- agenda do profissional.

---

### 5.15. Crédito interno

O crédito interno poderá ser gerado quando um reagendamento resultar em valor menor.

O crédito:

- poderá ser usado apenas em novos agendamentos;
- não poderá ser sacado;
- terá validade de 90 dias;
- poderá ser utilizado juntamente com uma promoção.

A validade deverá ser informada no momento em que o crédito for gerado.

---

### 5.16. Cancelamento pelo estabelecimento

Quando o estabelecimento ou profissional não puder realizar o atendimento, o cliente deverá ser avisado imediatamente.

O cliente poderá escolher entre:

- outro profissional equivalente;
- reagendamento;
- reembolso integral.

Se o cliente não aceitar a troca de profissional, deverá poder escolher entre reagendamento e reembolso integral.

Um horário ocupado não poderá ser simplesmente apagado. O administrador deverá realizar um cancelamento formal e informar o motivo.

---

### 5.17. Atrasos e faltas

Será permitida tolerância de até 10 minutos de atraso.

Depois desse período:

- o atendimento poderá ser cancelado;
- não haverá reembolso.

Quando o cliente não comparecer:

- o sistema registrará a falta;
- não haverá reembolso;
- o administrador poderá bloquear o cliente em caso de repetição.

---

### 5.18. Confirmação de presença

O sistema enviará um lembrete com opção para o cliente confirmar presença.

A confirmação não será obrigatória para manter o agendamento.

---

### 5.19. Conclusão do atendimento

O administrador ou o profissional responsável poderá marcar o atendimento como concluído.

A conclusão será utilizada para:

- registrar a receita;
- atualizar o histórico;
- contabilizar a fidelidade;
- liberar a avaliação.

O profissional poderá registrar o horário real de início e término.

Esse registro não alterará automaticamente a duração padrão do serviço.

Caso ninguém atualize o atendimento:

- o administrador será avisado;
- o sistema poderá encerrá-lo automaticamente após 24 horas;
- o administrador poderá corrigir a situação posteriormente.

---

### 5.20. Agendamento e reagendamento administrativos

O administrador poderá:

- criar agendamentos manualmente;
- reagendar clientes;
- cancelar agendamentos;
- selecionar cliente, serviços, profissional, data e horário;
- aplicar desconto manual;
- registrar justificativas;
- acompanhar o pagamento e a situação.

O desconto manual poderá ser:

- valor fixo;
- percentual.

A justificativa do desconto será obrigatória.

---

### 5.21. Agenda administrativa

O administrador poderá visualizar a agenda nos formatos:

- diário;
- semanal;
- mensal.

A visualização deverá identificar:

- cliente;
- serviço;
- profissional;
- data e horário;
- situação do agendamento.

O painel deverá permitir acompanhar os próximos atendimentos e os horários livres.

---

### 5.22. Área do profissional

Cada profissional poderá:

- acessar a própria agenda;
- consultar os serviços relacionados aos seus atendimentos;
- visualizar os clientes agendados;
- confirmar a realização do atendimento;
- registrar início e término reais.

O profissional não poderá acessar configurações gerais, finanças completas ou agendas de outros profissionais, salvo autorização administrativa específica.

---

### 5.23. Notificações e lembretes

O sistema enviará lembretes:

- 24 horas antes do atendimento;
- 2 horas antes do atendimento.

Os canais confirmados são:

- notificação dentro do sistema;
- e-mail.

As notificações poderão informar:

- confirmação do agendamento;
- lembretes;
- cancelamento;
- reagendamento;
- reembolso;
- alterações feitas pelo estabelecimento.

Lembretes relacionados a um agendamento serão necessários para o funcionamento do serviço.

Mensagens promocionais dependerão da autorização do cliente.

---

### 5.24. Notificações administrativas

O administrador poderá receber avisos sobre:

- novo agendamento;
- cancelamento;
- pagamento recusado;
- pedido ou processamento de reembolso;
- avaliação negativa.

O administrador poderá desativar tipos específicos de aviso.

---

### 5.25. Promoções

O administrador poderá criar promoções contendo:

- título;
- descrição;
- imagem ou banner;
- serviço relacionado;
- valor fixo ou percentual de desconto;
- data de início;
- data de término;
- limite total de utilizações;
- limite de uma utilização por cliente.

Na primeira versão, as promoções serão públicas para todos os clientes.

O sistema deverá gerar uma página pública da promoção com:

- imagem ou banner;
- informações da promoção;
- período de validade;
- botão de compartilhamento.

O compartilhamento poderá ser feito por link ou pelas opções de compartilhamento disponíveis no dispositivo do usuário.

---

### 5.26. Consentimento para promoções

O cliente poderá escolher se deseja receber promoções.

O cliente deverá poder cancelar essa autorização posteriormente.

A recusa em receber promoções não poderá impedir o recebimento de informações necessárias sobre agendamentos já contratados.

---

### 5.27. Programa de fidelidade

O sistema terá os níveis:

- Bronze;
- Prata;
- Ouro.

A evolução será baseada apenas em atendimentos concluídos.

As insígnias terão função visual na primeira versão.

Os benefícios reais, como descontos automáticos ou vantagens exclusivas, não fazem parte da primeira versão.

A quantidade exata de atendimentos necessária para cada nível ainda precisa ser definida.

---

### 5.28. Avaliações

Após a conclusão do atendimento, o cliente poderá avaliar a experiência.

A avaliação terá:

- nota de 1 a 5 estrelas;
- comentário opcional.

A média das avaliações poderá ser exibida publicamente.

Os comentários individuais ficarão visíveis apenas para o administrador.

---

### 5.29. Cadastro e controle de clientes

O administrador poderá:

- consultar clientes;
- visualizar histórico de agendamentos;
- verificar faltas;
- verificar bloqueios;
- consultar nível de fidelidade;
- bloquear temporariamente;
- desativar contas.

Todo bloqueio ou desativação deverá possuir um motivo registrado.

A possibilidade de registrar observações privadas sobre os clientes ainda depende de confirmação.

---

### 5.30. Controle financeiro

O sistema deverá registrar automaticamente as receitas provenientes dos atendimentos pagos.

O administrador poderá registrar despesas manualmente, incluindo:

- categoria;
- descrição;
- valor;
- data;
- comprovante opcional.

O sistema deverá calcular:

- faturamento;
- total de despesas;
- lucro do período.

---

### 5.31. Relatórios e exportação

O administrador poderá consultar relatórios por:

- dia;
- semana;
- mês;
- intervalo personalizado.

Será possível exportar em formato de planilha:

- relatórios financeiros;
- agendamentos.

---

### 5.32. Painel administrativo

O painel inicial do administrador deverá apresentar um resumo do dia com:

- agendamentos do dia;
- próximos clientes;
- horários livres;
- faturamento;
- cancelamentos.

---

### 5.33. Histórico de alterações

O sistema deverá registrar as principais alterações administrativas, especialmente:

- mudanças de preços;
- mudanças em serviços;
- mudanças de horários;
- bloqueios;
- alterações em agendamentos;
- descontos;
- cancelamentos;
- bloqueios de clientes.

O histórico deverá indicar:

- usuário responsável;
- data;
- horário;
- informação alterada.

---

### 5.34. Administradores com permissões limitadas

O sistema permitirá:

- um administrador principal;
- outros administradores com permissões limitadas.

O administrador principal controlará quais funções cada administrador auxiliar poderá utilizar.

---

## 6. Funcionalidades Fora de Escopo

### Aplicativo mobile nativo

A primeira versão será um sistema web. Um aplicativo instalado pelas lojas de aplicativos não foi confirmado para esta versão.

### Várias unidades ou filiais

A primeira versão atenderá apenas uma unidade.

### WhatsApp

As notificações serão enviadas pelo próprio sistema e por e-mail. Integração com WhatsApp poderá ser avaliada futuramente.

### Lista de espera

Não haverá fila automática para clientes interessados em horários ocupados.

Esse recurso foi adiado porque exige regras adicionais de prioridade, prazo de resposta e envio de notificações.

### Emissão automática de nota fiscal

A primeira versão fornecerá comprovantes de pagamento e reembolso, mas não emitirá nota fiscal automaticamente.

### Benefícios automáticos de fidelidade

As insígnias Bronze, Prata e Ouro serão visuais. Descontos ou benefícios automáticos poderão ser adicionados futuramente.

### Promoções segmentadas

As promoções serão públicas para todos os clientes.

Campanhas direcionadas por nível, comportamento ou tempo sem agendar ficam para uma versão futura.

### Controle de estoque

Não fará parte da primeira versão.

### Comissão e folha de pagamento

Não fará parte da primeira versão.

### Marketplace de estabelecimentos

O sistema não permitirá buscar várias barbearias ou estabelecimentos próximos.

### Inteligência artificial

Não haverá recomendação automática de horários, serviços, preços ou promoções.

### Preços variáveis por demanda

O preço não mudará automaticamente conforme o horário ou a procura.

### Integrações avançadas com calendários externos

Não foram confirmadas para a primeira versão.

### Planos, pacotes e assinaturas

Não foram confirmados para a primeira versão.

### Previsão automática de faltas

O sistema registrará faltas, mas não utilizará modelos de previsão.

### Contabilidade completa

O controle financeiro será voltado a receitas, despesas e lucro, sem substituir um sistema contábil profissional.

---

## 7. Regras de Negócio

### 7.1. Regras de cadastro

1. O cliente deverá criar uma conta antes de agendar.
2. Nome, e-mail, telefone e senha serão obrigatórios.
3. A foto será opcional.
4. Apenas maiores de 18 anos poderão criar conta na primeira versão.
5. A mudança de e-mail exigirá confirmação.
6. A exclusão da conta somente poderá ocorrer após a conclusão ou cancelamento de todos os agendamentos ativos.

### 7.2. Regras de serviços

1. Cada serviço terá preço e duração próprios.
2. O administrador poderá alterar os preços.
3. Mudanças de preço não afetarão agendamentos já pagos.
4. Um serviço poderá ser temporariamente desativado.
5. Cada profissional poderá executar somente os serviços para os quais estiver habilitado.
6. O cliente poderá contratar vários serviços no mesmo agendamento.
7. O sistema somará duração e preço dos serviços selecionados.

### 7.3. Regras de disponibilidade

1. Cada profissional terá agenda própria.
2. Haverá intervalo de 10 minutos entre os atendimentos.
3. O sistema deverá respeitar horários, folgas, bloqueios e feriados.
4. O administrador poderá cadastrar horários especiais.
5. O administrador poderá definir limite diário opcional por profissional.
6. Um horário somente poderá aparecer quando comportar todos os serviços selecionados.
7. O cliente poderá escolher um profissional ou qualquer profissional disponível.

### 7.4. Regras de agendamento

1. O cliente poderá agendar com até 30 dias de antecedência.
2. O agendamento deverá ser feito com pelo menos 2 horas de antecedência.
3. O cliente poderá manter no máximo quatro agendamentos futuros ativos.
4. O horário ficará reservado por 10 minutos durante o pagamento.
5. O agendamento somente será confirmado após a aprovação do pagamento.
6. Caso o pagamento não seja aprovado no prazo, o horário será liberado.
7. Dois clientes não poderão confirmar o mesmo horário.

### 7.5. Regras de pagamento

1. O pagamento será obrigatório no ato do agendamento.
2. Serão aceitos Pix e cartão de crédito.
3. O cliente pagará o valor total.
4. O cliente deverá aceitar a política antes de pagar.
5. O sistema deverá gerar comprovante.
6. O comprovante ficará disponível no sistema e será enviado por e-mail.
7. O valor de um agendamento já pago não será alterado por mudanças posteriores no preço.

### 7.6. Regras de cancelamento e reembolso

1. Cancelamentos realizados com pelo menos 24 horas de antecedência terão direito a reembolso.
2. Cancelamentos feitos com menos de 24 horas não terão reembolso.
3. O administrador poderá autorizar exceções.
4. Cancelamentos realizados pelo estabelecimento permitirão reagendamento ou reembolso integral.
5. O prazo para o cliente receber o valor dependerá do meio de pagamento.
6. Um horário ocupado não poderá ser apagado diretamente.
7. Todo cancelamento administrativo deverá registrar o motivo.

### 7.7. Regras de reagendamento

1. O cliente poderá reagendar até 24 horas antes.
2. Cada atendimento poderá ser reagendado no máximo duas vezes.
3. O pagamento original será reaproveitado.
4. Quando o novo valor for maior, o cliente pagará a diferença.
5. Quando o novo valor for menor, o sistema gerará crédito interno.
6. O novo horário deverá respeitar todas as regras de disponibilidade.

### 7.8. Regras de crédito interno

1. O crédito será usado somente em novos agendamentos.
2. O crédito não poderá ser sacado.
3. O crédito terá validade de 90 dias.
4. O cliente deverá ser informado sobre a validade.
5. O crédito poderá ser utilizado junto com uma promoção.

### 7.9. Regras de atraso e falta

1. A tolerância de atraso será de 10 minutos.
2. Após esse período, o atendimento poderá ser cancelado sem reembolso.
3. A falta sem aviso não dará direito a reembolso.
4. As faltas serão registradas.
5. O administrador poderá bloquear clientes com faltas repetidas.

### 7.10. Regras de atendimento

1. O administrador ou profissional responsável confirmará a conclusão.
2. Poderão ser registrados o início e o término reais.
3. A duração real não modificará automaticamente a duração padrão.
4. Um atendimento não atualizado poderá ser encerrado automaticamente após 24 horas.
5. O administrador será avisado e poderá corrigir o registro.

### 7.11. Regras de promoções

1. A promoção poderá aplicar valor fixo ou percentual de desconto.
2. Terá data de início e término.
3. Poderá possuir limite total de utilizações.
4. Cada cliente poderá usar a mesma promoção uma vez.
5. Na primeira versão, as promoções serão públicas.
6. O cliente poderá usar promoção junto com crédito interno.
7. O recebimento de promoções dependerá de consentimento.

### 7.12. Regras de fidelidade

1. Somente atendimentos concluídos contarão para fidelidade.
2. Os níveis serão Bronze, Prata e Ouro.
3. Na primeira versão, as insígnias serão apenas visuais.
4. Cancelamentos e faltas não contarão como atendimentos concluídos.

### 7.13. Regras de avaliações

1. A avaliação somente será liberada após a conclusão.
2. A nota será de 1 a 5 estrelas.
3. O comentário será opcional.
4. A média poderá ser pública.
5. Os comentários individuais serão restritos ao administrador.

### 7.14. Regras administrativas

1. Haverá um administrador principal.
2. Poderão existir administradores com permissões limitadas.
3. Descontos manuais exigirão justificativa.
4. Bloqueios e desativações de clientes exigirão motivo.
5. Alterações administrativas importantes deverão gerar histórico.
6. O administrador poderá bloquear horários, dias ou períodos.
7. O administrador poderá cadastrar feriados e horários especiais.

---

## 8. Informações que o Sistema Precisa Controlar

| Informação | Para que serve no sistema | Observações importantes |
| ---------- | ------------------------- | ----------------------- |
| Contas de usuários | Permitir acesso ao sistema e identificar cada pessoa. | Deve diferenciar cliente, profissional, administrador principal e administrador limitado. |
| Dados do cliente | Identificar e contatar o cliente. | Nome, e-mail e telefone serão obrigatórios; foto opcional. |
| Consentimento promocional | Registrar se o cliente aceita receber promoções. | Não interfere nos avisos obrigatórios do agendamento. |
| Dados do estabelecimento | Exibir informações públicas e localização. | Inclui endereço, contatos, horários e redes sociais. |
| Profissionais | Organizar quem realiza cada atendimento. | Cada profissional terá agenda e serviços próprios. |
| Serviços | Informar o que pode ser contratado. | Deve controlar preço, duração, situação e disponibilidade. |
| Horários de funcionamento | Definir quando o estabelecimento atende. | Deve considerar horários normais e especiais. |
| Agenda dos profissionais | Controlar disponibilidade individual. | Deve considerar folgas, bloqueios e limite diário. |
| Agendamentos | Registrar a contratação do serviço. | Deve guardar cliente, serviço, profissional, data, horário, valor e situação. |
| Serviços de cada agendamento | Registrar um ou vários serviços contratados. | O sistema deverá calcular preço e duração total. |
| Reserva temporária | Impedir conflito durante o pagamento. | Validade de 10 minutos. |
| Pagamentos | Controlar valores pagos e aprovação. | Pix e cartão de crédito. |
| Comprovantes | Permitir consulta do pagamento ou reembolso. | Disponíveis no sistema e enviados por e-mail. |
| Cancelamentos | Registrar cancelamentos e motivos. | Deve controlar antecedência e direito a reembolso. |
| Reagendamentos | Registrar alterações de data, horário ou serviço. | Limite de duas remarcações. |
| Reembolsos | Acompanhar valores devolvidos. | O prazo final depende do meio de pagamento. |
| Créditos internos | Controlar saldos gerados por diferença de valor. | Validade de 90 dias e uso apenas em agendamentos. |
| Faltas e atrasos | Acompanhar ocorrências dos clientes. | Podem motivar bloqueios administrativos. |
| Promoções | Divulgar descontos e campanhas. | Deve controlar banner, validade e limites de uso. |
| Utilização de promoções | Impedir uso acima dos limites. | Uma utilização por cliente. |
| Notificações | Informar clientes e administradores. | Deve controlar tipo, destinatário e situação de envio. |
| Fidelidade | Controlar atendimentos concluídos e insígnias. | Níveis Bronze, Prata e Ouro. |
| Avaliações | Acompanhar a satisfação dos clientes. | Média pública e comentários restritos. |
| Receitas | Registrar entradas financeiras dos atendimentos. | Geradas a partir dos pagamentos. |
| Despesas | Controlar gastos do estabelecimento. | Registro manual com comprovante opcional. |
| Categorias de despesas | Organizar os gastos. | Exemplos deverão ser definidos pelo administrador. |
| Relatórios | Apoiar o acompanhamento do negócio. | Períodos diário, semanal, mensal e personalizado. |
| Limites de atendimento | Evitar sobrecarga dos profissionais. | Configuração opcional por profissional. |
| Bloqueios de agenda | Impedir agendamentos em períodos indisponíveis. | Pode abranger horário, dia ou intervalo de datas. |
| Bloqueios de clientes | Restringir contas em situações justificadas. | Deve registrar motivo e tipo de bloqueio. |
| Histórico de alterações | Identificar ações administrativas importantes. | Deve registrar responsável, data, horário e alteração. |
| Permissões administrativas | Limitar funções de administradores auxiliares. | Controladas pelo administrador principal. |

---

## 9. Fluxos Principais de Uso

### 9.1. Cadastro de cliente

1. O cliente acessa a opção de cadastro.
2. O sistema solicita os dados obrigatórios.
3. O cliente informa nome, e-mail, telefone e senha.
4. O cliente declara possuir a idade mínima exigida.
5. O sistema valida as informações.
6. O sistema registra a conta.
7. O cliente pode acessar sua área.

### 9.2. Consulta e criação de agendamento

1. O cliente acessa a área de serviços.
2. O cliente escolhe um ou vários serviços.
3. O cliente escolhe um profissional ou qualquer profissional disponível.
4. O sistema calcula o valor e a duração total.
5. O sistema mostra as datas e horários disponíveis.
6. O cliente escolhe um horário.
7. O sistema verifica novamente a disponibilidade.
8. O horário é reservado temporariamente por 10 minutos.
9. O cliente visualiza e aceita a política.
10. O cliente inicia o pagamento.
11. O sistema confirma o agendamento após a aprovação.
12. O cliente recebe a confirmação e o comprovante.

### 9.3. Falha no pagamento

1. O cliente inicia o pagamento.
2. O pagamento não é aprovado.
3. O sistema informa o problema.
4. O cliente poderá tentar novamente dentro do período reservado.
5. Caso o prazo de 10 minutos termine, o sistema libera o horário.
6. O agendamento não é confirmado.

### 9.4. Cancelamento com direito a reembolso

1. O cliente acessa seus agendamentos.
2. O cliente seleciona um atendimento confirmado.
3. O sistema verifica a antecedência.
4. O cliente confirma o cancelamento.
5. O sistema registra o cancelamento.
6. O sistema solicita o reembolso.
7. O sistema informa que o prazo depende do meio de pagamento.
8. O horário volta a ficar disponível.

### 9.5. Cancelamento sem direito a reembolso

1. O cliente acessa seus agendamentos.
2. O cliente solicita o cancelamento.
3. O sistema identifica que faltam menos de 24 horas.
4. O sistema informa que não haverá reembolso.
5. O cliente confirma a decisão.
6. O sistema registra o cancelamento.
7. O horário volta a ficar disponível.

### 9.6. Reagendamento

1. O cliente acessa seus agendamentos.
2. O cliente escolhe a opção de reagendar.
3. O sistema verifica o prazo e a quantidade de remarcações.
4. O cliente escolhe novos serviços, profissional, data ou horário.
5. O sistema calcula eventuais diferenças de valor.
6. O cliente paga a diferença, quando necessário.
7. O sistema gera crédito quando o novo valor é menor.
8. O sistema confirma o novo horário.
9. O cliente recebe a atualização.

### 9.7. Cancelamento pelo estabelecimento

1. O administrador acessa o agendamento.
2. O administrador informa que o atendimento não poderá ser realizado.
3. O sistema solicita o motivo.
4. O sistema oferece profissional equivalente, reagendamento ou reembolso.
5. O cliente escolhe uma opção.
6. O sistema atualiza o agendamento.
7. O cliente recebe a notificação correspondente.

### 9.8. Conclusão do atendimento

1. O profissional ou administrador acessa o agendamento.
2. O responsável registra o início e término, quando aplicável.
3. O responsável marca o atendimento como concluído.
4. O sistema registra a receita.
5. O sistema atualiza a fidelidade.
6. O sistema libera a avaliação.
7. O atendimento aparece no histórico do cliente.

### 9.9. Publicação de promoção

1. O administrador acessa a área de promoções.
2. O administrador informa os dados da promoção.
3. O administrador adiciona uma imagem ou banner.
4. O administrador define serviço, desconto, validade e limites.
5. O sistema valida as informações.
6. O sistema publica a promoção.
7. O sistema gera uma página pública compartilhável.

### 9.10. Registro de despesa

1. O administrador acessa a área financeira.
2. O administrador escolhe registrar uma despesa.
3. O sistema solicita categoria, descrição, valor e data.
4. O administrador poderá anexar um comprovante.
5. O administrador confirma.
6. O sistema registra a despesa.
7. Os relatórios e o cálculo de lucro são atualizados.

### 9.11. Bloqueio de agenda

1. O administrador acessa a agenda.
2. O administrador seleciona um horário, dia ou período.
3. O administrador informa o bloqueio.
4. O sistema verifica agendamentos existentes.
5. O administrador resolve eventuais atendimentos afetados.
6. O sistema registra o bloqueio.
7. O período deixa de aparecer como disponível.

### 9.12. Avaliação do atendimento

1. O cliente acessa um atendimento concluído.
2. O sistema oferece a opção de avaliação.
3. O cliente escolhe uma nota de 1 a 5.
4. O cliente pode escrever um comentário.
5. O cliente confirma.
6. O sistema registra a avaliação.
7. A média pública é atualizada.

### 9.13. Exclusão de conta

1. O cliente acessa as configurações da conta.
2. O cliente solicita a exclusão.
3. O sistema verifica se existem agendamentos ativos.
4. Caso existam, o sistema informa que devem ser concluídos ou cancelados.
5. Quando não houver pendências, o cliente confirma a solicitação.
6. O sistema processa a exclusão.
7. Informações legalmente obrigatórias poderão ser preservadas.

---

## 10. Histórias de Usuário

### Cliente

- Como cliente, eu quero criar uma conta para manter meus agendamentos e pagamentos organizados.
- Como cliente, eu quero consultar os serviços e preços para escolher o atendimento desejado.
- Como cliente, eu quero escolher vários serviços para realizar tudo em um único horário.
- Como cliente, eu quero escolher um profissional para ser atendido pela pessoa de minha preferência.
- Como cliente, eu quero visualizar apenas horários disponíveis para não solicitar uma data ocupada.
- Como cliente, eu quero pagar por Pix ou cartão para confirmar meu horário.
- Como cliente, eu quero receber um comprovante para ter segurança sobre o pagamento.
- Como cliente, eu quero consultar meus próximos agendamentos para lembrar dos compromissos.
- Como cliente, eu quero receber lembretes para reduzir o risco de esquecer o horário.
- Como cliente, eu quero cancelar dentro do prazo para receber o reembolso.
- Como cliente, eu quero reagendar dentro das regras para escolher outro horário.
- Como cliente, eu quero utilizar meu crédito interno para reduzir o valor de um novo agendamento.
- Como cliente, eu quero visualizar promoções para aproveitar descontos.
- Como cliente, eu quero compartilhar uma promoção para enviá-la a outras pessoas.
- Como cliente, eu quero acompanhar minha insígnia para visualizar meu nível de fidelidade.
- Como cliente, eu quero avaliar um atendimento concluído para informar minha experiência.
- Como cliente, eu quero controlar o recebimento de promoções para não receber mensagens indesejadas.
- Como cliente, eu quero excluir minha conta quando não desejar mais utilizar o sistema.

### Profissional

- Como profissional, eu quero consultar minha agenda para saber quais clientes atenderei.
- Como profissional, eu quero visualizar os serviços marcados para me preparar para o atendimento.
- Como profissional, eu quero registrar início e término para manter o histórico do atendimento.
- Como profissional, eu quero concluir um atendimento para atualizar o financeiro e a fidelidade do cliente.

### Administrador

- Como administrador, eu quero cadastrar serviços para manter a oferta do estabelecimento atualizada.
- Como administrador, eu quero alterar preços para acompanhar as decisões comerciais.
- Como administrador, eu quero cadastrar profissionais e horários para organizar a agenda.
- Como administrador, eu quero bloquear horários para registrar folgas e indisponibilidades.
- Como administrador, eu quero criar agendamentos manualmente para atender clientes que precisem de ajuda.
- Como administrador, eu quero reagendar ou cancelar atendimentos para resolver mudanças na operação.
- Como administrador, eu quero registrar motivos de cancelamento para manter o histórico.
- Como administrador, eu quero criar promoções com banners para divulgar ofertas.
- Como administrador, eu quero controlar limites de promoções para evitar uso indevido.
- Como administrador, eu quero registrar despesas para calcular o lucro.
- Como administrador, eu quero consultar relatórios para acompanhar o desempenho do negócio.
- Como administrador, eu quero exportar informações para analisá-las fora do sistema.
- Como administrador, eu quero consultar o resumo do dia para organizar o atendimento.
- Como administrador, eu quero bloquear clientes em situações justificadas para proteger a operação.
- Como administrador principal, eu quero limitar as permissões de outros administradores para controlar o acesso às funções.
- Como administrador, eu quero consultar o histórico de alterações para identificar quem realizou uma ação importante.

---

## 11. Critérios de Aceitação

### 11.1. Cadastro e acesso

- [ ] O cliente consegue criar uma conta com nome, e-mail, telefone e senha.
- [ ] O sistema impede o cadastro quando um dado obrigatório não é informado.
- [ ] O sistema informa que a primeira versão é destinada a maiores de 18 anos.
- [ ] O usuário consegue entrar com e-mail e senha válidos.
- [ ] O usuário consegue solicitar recuperação de senha por e-mail.
- [ ] O cliente consegue alterar seus dados pessoais.
- [ ] A alteração do e-mail exige confirmação.
- [ ] Cada perfil visualiza somente as funções permitidas.

### 11.2. Serviços e profissionais

- [ ] O administrador consegue cadastrar um serviço com preço e duração.
- [ ] O administrador consegue editar e desativar um serviço.
- [ ] A mudança de preço não altera agendamentos já pagos.
- [ ] O administrador consegue relacionar serviços aos profissionais.
- [ ] O sistema não oferece um profissional para um serviço que ele não realiza.
- [ ] O administrador consegue configurar horários e limite diário por profissional.

### 11.3. Disponibilidade e agendamento

- [ ] O cliente consegue selecionar um ou vários serviços.
- [ ] O sistema soma corretamente os preços e as durações.
- [ ] O cliente consegue escolher um profissional ou qualquer profissional disponível.
- [ ] O sistema mostra apenas horários compatíveis com os serviços.
- [ ] O sistema respeita bloqueios, folgas, feriados e horários especiais.
- [ ] O sistema respeita o intervalo de 10 minutos.
- [ ] O sistema impede agendamentos com menos de 2 horas de antecedência.
- [ ] O sistema impede agendamentos com mais de 30 dias de antecedência.
- [ ] O sistema impede que o cliente ultrapasse quatro agendamentos futuros ativos.
- [ ] O sistema impede dois agendamentos confirmados para o mesmo horário e profissional.

### 11.4. Reserva e pagamento

- [ ] O horário fica reservado por 10 minutos durante o pagamento.
- [ ] Outro cliente não consegue reservar o mesmo horário durante esse período.
- [ ] O sistema aceita Pix e cartão de crédito.
- [ ] O agendamento somente é confirmado após pagamento aprovado.
- [ ] O horário é liberado quando o prazo termina sem aprovação.
- [ ] O cliente precisa aceitar a política antes de pagar.
- [ ] O cliente recebe confirmação clara após o pagamento.
- [ ] O comprovante fica disponível na área do cliente.
- [ ] O comprovante é enviado por e-mail.

### 11.5. Cancelamento e reembolso

- [ ] O sistema verifica automaticamente a antecedência do cancelamento.
- [ ] Cancelamentos com pelo menos 24 horas iniciam o processo de reembolso.
- [ ] Cancelamentos com menos de 24 horas informam claramente que não haverá reembolso.
- [ ] O sistema solicita confirmação antes de cancelar.
- [ ] O agendamento passa para a situação correta.
- [ ] O horário cancelado volta a ficar disponível.
- [ ] O administrador consegue autorizar uma exceção.
- [ ] O sistema informa que o prazo de devolução depende do meio de pagamento.

### 11.6. Reagendamento

- [ ] O cliente consegue reagendar quando faltam pelo menos 24 horas.
- [ ] O sistema impede mais de duas remarcações.
- [ ] O sistema verifica a disponibilidade do novo horário.
- [ ] O valor já pago é reaproveitado.
- [ ] O sistema cobra a diferença quando o novo valor é maior.
- [ ] O sistema gera crédito quando o novo valor é menor.
- [ ] O cliente recebe confirmação do novo agendamento.

### 11.7. Créditos

- [ ] O cliente consegue visualizar seu crédito e a data de validade.
- [ ] O crédito pode ser usado em um novo agendamento.
- [ ] O crédito não pode ser solicitado como saque.
- [ ] O crédito expira após 90 dias.
- [ ] O sistema permite utilizar crédito e promoção no mesmo agendamento.

### 11.8. Agenda administrativa

- [ ] O administrador consegue consultar a agenda diária.
- [ ] O administrador consegue consultar a agenda semanal.
- [ ] O administrador consegue consultar a agenda mensal.
- [ ] A agenda identifica cliente, serviço, profissional e situação.
- [ ] O administrador consegue bloquear horários, dias e períodos.
- [ ] Um agendamento ocupado não pode ser apagado diretamente.
- [ ] Cancelamentos administrativos exigem motivo.

### 11.9. Atendimento, atraso e falta

- [ ] O profissional ou administrador consegue concluir um atendimento.
- [ ] O sistema contabiliza o atendimento na fidelidade.
- [ ] O sistema registra a receita após a conclusão conforme a regra financeira definida.
- [ ] O profissional consegue registrar início e término reais.
- [ ] O sistema permite registrar falta.
- [ ] A falta não gera reembolso.
- [ ] O sistema permite aplicar a tolerância de 10 minutos.
- [ ] O administrador é avisado sobre atendimentos não encerrados.
- [ ] O atendimento pode ser corrigido após encerramento automático.

### 11.10. Promoções

- [ ] O administrador consegue cadastrar título, descrição e banner.
- [ ] O administrador consegue definir desconto e período de validade.
- [ ] O administrador consegue definir limite total de uso.
- [ ] O sistema impede mais de uma utilização por cliente.
- [ ] O sistema não aplica promoções fora da validade.
- [ ] A promoção possui uma página pública.
- [ ] O cliente consegue compartilhar a promoção.
- [ ] O cliente consegue cancelar o consentimento promocional.

### 11.11. Fidelidade e avaliações

- [ ] Atendimentos concluídos atualizam a fidelidade.
- [ ] Faltas e cancelamentos não aumentam o nível.
- [ ] O sistema apresenta os níveis Bronze, Prata e Ouro.
- [ ] O cliente consegue avaliar apenas atendimentos concluídos.
- [ ] A nota aceita valores de 1 a 5.
- [ ] O comentário é opcional.
- [ ] A média pode ser exibida publicamente.
- [ ] Comentários individuais ficam restritos ao administrador.

### 11.12. Financeiro e relatórios

- [ ] O sistema registra receitas dos atendimentos.
- [ ] O administrador consegue registrar uma despesa.
- [ ] Categoria, descrição, valor e data podem ser informados.
- [ ] O comprovante da despesa é opcional.
- [ ] O sistema calcula faturamento, despesas e lucro.
- [ ] O administrador consegue filtrar por dia, semana, mês e período personalizado.
- [ ] O administrador consegue exportar relatórios financeiros.
- [ ] O administrador consegue exportar agendamentos.

### 11.13. Permissões e histórico

- [ ] O administrador principal consegue limitar permissões de administradores auxiliares.
- [ ] Um administrador limitado não acessa funções não autorizadas.
- [ ] O profissional não acessa informações administrativas completas.
- [ ] Alterações importantes registram usuário, data e horário.
- [ ] Bloqueios e desativações de clientes exigem motivo.
- [ ] Descontos manuais exigem justificativa.

---

## 12. Consultas, Relatórios e Indicadores

### 12.1. Consultas do cliente

O cliente deverá consultar:

- serviços disponíveis;
- preços;
- duração;
- profissionais;
- datas e horários disponíveis;
- promoções;
- próximos agendamentos;
- histórico de atendimentos;
- pagamentos;
- comprovantes;
- reembolsos;
- créditos internos;
- nível de fidelidade;
- avaliações realizadas.

### 12.2. Consultas do profissional

O profissional deverá consultar:

- agenda diária;
- próximos atendimentos;
- cliente relacionado ao atendimento;
- serviços contratados;
- situação do agendamento;
- registro de início e término;
- histórico dos próprios atendimentos.

### 12.3. Consultas administrativas

O administrador deverá consultar:

- agenda diária, semanal e mensal;
- próximos clientes;
- horários livres;
- agendamentos por situação;
- pagamentos aprovados ou recusados;
- cancelamentos;
- reembolsos;
- faltas;
- avaliações negativas;
- promoções e quantidade de utilizações;
- clientes;
- bloqueios;
- fidelidade;
- receitas;
- despesas;
- lucro;
- histórico de alterações.

### 12.4. Filtros importantes

Os relatórios e consultas deverão permitir, conforme o conteúdo:

- período;
- profissional;
- serviço;
- cliente;
- situação do agendamento;
- forma de pagamento;
- categoria de despesa;
- promoção.

### 12.5. Indicadores do painel administrativo

O painel deverá apresentar:

- quantidade de agendamentos do dia;
- próximos clientes;
- horários livres;
- faturamento do dia;
- cancelamentos do dia.

### 12.6. Relatórios financeiros

O administrador poderá acompanhar:

- faturamento;
- despesas;
- lucro;
- resultados diários;
- resultados semanais;
- resultados mensais;
- intervalo personalizado.

### 12.7. Exportações

O administrador poderá exportar:

- agendamentos;
- relatórios financeiros.

---

## 13. Permissões e Segurança Funcional

| Perfil | Pode fazer | Não pode fazer | Observações |
| ------ | ---------- | -------------- | ----------- |
| Cliente | Criar conta, atualizar perfil, consultar serviços, profissionais, promoções e horários, agendar, pagar, reagendar, cancelar, consultar comprovantes, usar créditos, acompanhar fidelidade e avaliar. | Alterar preços, serviços, horários de profissionais, dados de outros clientes, relatórios administrativos ou permissões. | Controla somente a própria conta e seus próprios agendamentos. |
| Profissional | Consultar a própria agenda, visualizar clientes dos próprios atendimentos, registrar início e término e concluir atendimentos. | Alterar preços, promoções, finanças, permissões, dados gerais do estabelecimento ou agenda de outros profissionais. | O acesso deverá se limitar às atividades autorizadas pelo estabelecimento. |
| Administrador limitado | Executar funções administrativas liberadas pelo administrador principal. | Acessar funções não autorizadas ou alterar as próprias permissões. | As permissões específicas ainda deverão ser detalhadas antes do FSD. |
| Administrador principal | Gerenciar serviços, profissionais, agenda, clientes, promoções, finanças, relatórios, permissões, bloqueios e histórico. | Não deverá apagar registros importantes sem respeitar cancelamentos, justificativas e históricos definidos. | Possui o maior nível de acesso funcional. |

---

## 14. Limitações da Primeira Versão

- O sistema atenderá apenas uma unidade.
- O produto será inicialmente um sistema web, sem aplicativo mobile nativo confirmado.
- As notificações utilizarão o próprio sistema e e-mail.
- Não haverá integração com WhatsApp.
- Não haverá lista de espera.
- Não haverá emissão automática de nota fiscal.
- Não haverá controle completo de estoque.
- Não haverá cálculo de comissão ou folha de pagamento.
- Não haverá marketplace de estabelecimentos.
- Não haverá inteligência artificial.
- Não haverá preços automáticos conforme demanda.
- Não haverá previsão automática de faltas.
- Não haverá contabilidade completa.
- O programa de fidelidade não oferecerá benefícios automáticos.
- As promoções não serão segmentadas por grupos de clientes.
- O sistema não substituirá serviços profissionais de contabilidade ou validação jurídica.
- O compartilhamento de promoções será feito por página pública e recursos disponíveis no dispositivo.
- A lista de categorias de despesas poderá ser administrada de forma simples, sem estrutura contábil avançada.

---

## 15. Pontos Pendentes Antes do FSD

### 15.1. Nome definitivo do sistema

O nome AgendaBarber Web é provisório.

É necessário definir se esse nome será mantido ou substituído.

### 15.2. Quantidade de atendimentos para as insígnias

Os níveis Bronze, Prata e Ouro foram confirmados, mas não foi definida a quantidade de atendimentos necessária para alcançar cada nível.

Exemplo que precisa ser decidido:

- Bronze: quantidade de atendimentos;
- Prata: quantidade de atendimentos;
- Ouro: quantidade de atendimentos.

### 15.3. Imagens e descrições dos serviços

Ainda não foi confirmado se cada serviço terá:

- imagem;
- descrição detalhada;
- orientações antes do atendimento;
- cuidados posteriores.

Essa pergunta foi apresentada, mas não recebeu resposta.

### 15.4. Perfil público dos profissionais

Ainda não foi confirmado se o cliente visualizará:

- foto;
- apresentação;
- especialidades;
- média de avaliações do profissional.

Também não foi definido se as avaliações serão associadas ao estabelecimento, ao profissional ou aos dois.

### 15.5. Observações privadas sobre clientes

Ainda não foi confirmado se administradores e profissionais poderão registrar observações internas sobre clientes.

Caso seja aprovado, será necessário definir:

- quais perfis poderão visualizar;
- quais perfis poderão editar;
- que tipos de informação poderão ser registrados;
- como evitar registros inadequados ou excessivos.

### 15.6. Permissões dos administradores limitados

Foi aprovado que haverá administradores auxiliares com permissões limitadas, mas ainda é necessário definir quais permissões poderão ser concedidas.

Exemplos:

- gerenciar agenda;
- gerenciar clientes;
- gerenciar promoções;
- consultar finanças;
- registrar despesas;
- exportar relatórios;
- gerenciar profissionais;
- visualizar histórico.

### 15.7. Regra jurídica de cancelamento

A regra comercial de reembolso foi definida como:

- reembolso com pelo menos 24 horas de antecedência;
- sem reembolso após esse prazo.

Entretanto, a política deverá ser validada juridicamente antes de ser aplicada, especialmente por envolver contratação e pagamento pela internet.

O PRD registra a regra desejada pelo negócio, mas não confirma sua validade jurídica em todas as situações.

### 15.8. Momento exato de reconhecimento da receita

Foi aprovado que o sistema controlará receitas e que a conclusão do atendimento afetará o financeiro.

Antes do FSD, deve ser esclarecido se a receita será considerada:

- no momento da aprovação do pagamento;
- no dia do atendimento;
- somente quando o atendimento for concluído.

Essa decisão afeta os relatórios de faturamento e lucro.

### 15.9. Tratamento financeiro de faltas e cancelamentos sem reembolso

Deve ser definido como esses valores aparecerão nos relatórios.

Exemplos:

- receita de atendimento;
- receita por falta;
- receita por cancelamento fora do prazo;
- categoria separada.

### 15.10. Processo de reembolso excepcional

O administrador poderá autorizar exceções, mas ainda é necessário definir:

- quais administradores terão essa permissão;
- se será necessária justificativa;
- se haverá valor parcial ou apenas integral;
- se a exceção poderá ser aplicada após o prazo normal.

### 15.11. Funcionamento do pagamento da diferença

Quando um reagendamento gerar valor maior, deverá haver pagamento da diferença.

Ainda deve ser definido:

- por quanto tempo o novo horário ficará reservado;
- o que acontecerá se o pagamento da diferença falhar;
- se o agendamento antigo será mantido até a aprovação.

### 15.12. Encerramento automático após 24 horas

Foi aprovado que um atendimento poderá ser encerrado automaticamente após 24 horas caso ninguém o atualize.

Deve ser definido qual situação automática será aplicada:

- concluído;
- aguardando revisão;
- outra situação específica.

Marcar automaticamente como concluído pode afetar fidelidade, avaliações e receitas.

### 15.13. Confirmação da presença

Foi definido que a confirmação não será obrigatória.

Ainda deve ser esclarecido se o administrador e o profissional visualizarão uma situação específica, como:

- presença confirmada;
- presença não confirmada;
- cliente não respondeu.

### 15.14. Bloqueio por faltas repetidas

Foi aprovado que o administrador poderá bloquear clientes em caso de repetição.

Ainda não foi definida uma quantidade automática de faltas.

Deve-se decidir se:

- o bloqueio será sempre manual;
- haverá sugestão após determinada quantidade;
- haverá bloqueio automático.

### 15.15. Duração dos bloqueios temporários

Foi aprovado o bloqueio temporário de clientes, mas não foi definido:

- prazo padrão;
- possibilidade de prazo personalizado;
- forma de desbloqueio.

### 15.16. Cancelamento de um bloqueio de agenda com atendimento existente

O sistema deverá verificar agendamentos antes de bloquear um período.

Ainda é necessário detalhar se o bloqueio:

- será impedido até resolver os agendamentos;
- poderá ser salvo como pendente;
- poderá cancelar automaticamente os atendimentos após confirmação.

### 15.17. Categorias de despesas

Foi aprovado o registro de categoria, mas não foi definido se:

- haverá categorias iniciais;
- o administrador poderá criar e editar categorias;
- categorias utilizadas poderão ser desativadas.

### 15.18. Arquivo de comprovante de despesa

Foi definido que o comprovante será opcional.

Antes do FSD, será necessário definir quais tipos de arquivo ou documento serão aceitos funcionalmente.

### 15.19. Dados mantidos após exclusão da conta

Foi decidido que dados legalmente necessários poderão ser preservados.

Ainda será necessário definir, com orientação jurídica:

- quais informações serão mantidas;
- por quanto tempo;
- quais informações serão removidas ou anonimizadas.

### 15.20. Avaliação negativa

O administrador será avisado sobre avaliações negativas.

Ainda é necessário definir o que será considerado negativo.

Exemplos:

- notas de 1 ou 2;
- notas de 1 a 3;
- qualquer comentário sinalizado pelo cliente.

### 15.21. Limite total de uma promoção

Foi aprovado que o administrador poderá definir limite total e uma utilização por cliente.

Deve ser definido se o limite total será:

- obrigatório;
- opcional;
- ilimitado quando não informado.

### 15.22. Uso simultâneo de promoção e crédito

O uso conjunto foi aprovado.

Ainda é necessário definir a ordem do cálculo:

- aplicar a promoção e depois o crédito;
- aplicar o crédito e depois a promoção.

Essa ordem pode alterar o valor final.

### 15.23. Validade e encerramento das promoções

Deve ser definido se uma promoção será encerrada automaticamente quando:

- atingir a data final;
- atingir o limite total;
- ocorrer qualquer uma das duas condições.

### 15.24. Canais e funcionamento da notificação no sistema

Foi aprovado o envio por e-mail e pelo próprio sistema.

Antes do FSD, será necessário definir se a notificação interna será exibida:

- apenas quando o usuário estiver conectado;
- em uma central de notificações;
- como aviso do navegador, quando autorizado.

### 15.25. Cancelamento do consentimento promocional

Deve ser definido onde o cliente poderá alterar essa preferência e se a mudança produzirá efeito imediato.

### 15.26. Reembolso por Pix e cartão

As formas de pagamento foram definidas, mas o comportamento funcional poderá variar.

Antes do FSD, será necessário confirmar:

- se o reembolso será sempre automático;
- se poderá exigir ação administrativa;
- como será exibido quando estiver em processamento;
- quais situações de falha deverão ser tratadas.

### 15.27. Perfil autorizado a visualizar o histórico de alterações

Foi confirmado que o histórico existirá.

Ainda deve ser definido se poderá ser consultado por:

- somente o administrador principal;
- administradores com permissão;
- profissionais em relação às próprias ações.

---

## 16. Resumo Final do PRD

Será construído um sistema web de agendamento e pagamento antecipado para uma barbearia ou pequeno estabelecimento de estética.

O sistema será utilizado por clientes, profissionais, um administrador principal e administradores com permissões limitadas.

Os clientes poderão criar conta, consultar serviços e horários, escolher profissionais, agendar, pagar por Pix ou cartão, cancelar, reagendar, receber notificações, consultar comprovantes, utilizar créditos, acompanhar fidelidade e avaliar atendimentos.

Os profissionais poderão acompanhar a própria agenda e registrar a realização dos atendimentos.

Os administradores poderão controlar serviços, preços, profissionais, agendas, bloqueios, clientes, promoções, pagamentos, cancelamentos, despesas, relatórios, permissões e históricos de alterações.

A primeira versão atenderá apenas uma unidade e não incluirá aplicativo mobile nativo, WhatsApp, lista de espera, nota fiscal automática, controle de estoque, comissões, marketplace, inteligência artificial ou contabilidade completa.

O projeto possui uma base funcional ampla e bem definida, mas **ainda não está totalmente pronto para avançar ao FSD**.

Antes da próxima etapa, é recomendável resolver principalmente:

- critérios das insígnias;
- permissões dos administradores limitados;
- apresentação dos serviços e profissionais;
- observações internas sobre clientes;
- reconhecimento financeiro das receitas;
- funcionamento detalhado de exceções e reembolsos;
- tratamento automático de atendimentos não encerrados;
- detalhes das notificações;
- validação jurídica da política de cancelamento.
