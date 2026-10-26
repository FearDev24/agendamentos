# Agendamentos

Sistema web de agendamento para serviços, com áreas separadas para clientes, profissionais e administração.

## Funcionalidades

- cadastro e autenticação de usuários;
- catálogo de serviços, profissionais, promoções e horários;
- criação, revisão, reagendamento e acompanhamento de agendamentos;
- checkout e integração de pagamento com Mercado Pago e sandbox;
- gestão financeira, relatórios, auditoria e exportações;
- avaliações, notificações, webhooks e suporte a PWA.

## Tecnologias

PHP, HTML/CSS, JavaScript e banco de dados relacional. A aplicação usa uma arquitetura MVC própria, com roteador, controllers, models, helpers, views e gateways de pagamento.

## Estrutura

```text
app/
├── controllers/   fluxos da aplicação
├── models/        acesso e regras de dados
├── gateways/      pagamentos
├── helpers/       autenticação, banco, e-mail e utilitários
└── views/         interfaces por perfil
```

## Execução

Configure o servidor PHP para apontar para a raiz do projeto, habilite reescrita de URLs e preencha as credenciais de banco e integrações no ambiente. O arquivo `prd.md` reúne os requisitos funcionais do produto.

> Projeto em evolução: valide as configurações de produção, chaves de pagamento e políticas de segurança antes de publicar.
