# Roadmap Macro de Desenvolvimento
## Plataforma SaaS Inteligente de Automação Comercial

**Versão:** 1.0  
**Objetivo:** organizar a evolução do projeto em grandes fases, deixando cada módulo preparado para possuir posteriormente seu próprio roadmap detalhado, critérios de auditoria, plano de testes e especificações técnicas.

---

# 1. Objetivo do Roadmap

Este roadmap não pretende detalhar todas as regras de negócio de cada módulo.

Ele estabelece a **ordem macro de construção da plataforma**, permitindo que posteriormente cada domínio tenha seu próprio documento específico.

Exemplo:

```text
ROADMAP GERAL
   ↓
Módulo de Produtos
   ↓
ROADMAP_PRODUCT_CORE.md

Módulo Fiscal
   ↓
ROADMAP_FISCAL.md

PDV
   ↓
ROADMAP_PDV.md

Estoque
   ↓
ROADMAP_INVENTORY.md
```

Cada roadmap específico deverá posteriormente detalhar:

- escopo;
- requisitos funcionais;
- requisitos não funcionais;
- regras de negócio;
- arquitetura;
- banco de dados;
- APIs;
- segurança;
- auditoria;
- testes unitários;
- testes de integração;
- testes de regressão;
- testes de carga quando aplicável;
- critérios de aceite;
- documentação;
- checklist de produção.

---

# FASE 0 — Fundação Técnica e Provas de Conceito

**Objetivo:** validar os maiores riscos técnicos antes da construção definitiva da plataforma.

## Principais entregas

### Backend

- projeto Laravel inicial;
- MySQL;
- Redis;
- Docker;
- estrutura de ambientes;
- convenções de código;
- versionamento;
- CI/CD inicial.

### PDV

Criar prova de conceito em:

```text
.NET / C#
+
SQLite
```

Validar:

- execução Windows;
- persistência local;
- leitura de produtos;
- venda local;
- funcionamento offline;
- impressão térmica;
- abertura de gaveta;
- leitura por código de barras;
- comunicação com balança;
- teste inicial de TEF/POS;
- sincronização com Laravel.

### Fiscal

Validar:

- geração de NFC-e;
- certificado digital;
- comunicação com SEFAZ;
- autorização;
- rejeição;
- cancelamento básico;
- impressão DANFCE.

## Critério de saída

A fase somente deverá ser considerada concluída quando ficar tecnicamente comprovado que:

```text
Produto
 ↓
PDV
 ↓
Venda
 ↓
Pagamento
 ↓
Fiscal
 ↓
Sincronização
```

pode funcionar de forma integrada.

---

# FASE 1 — Core SaaS e Multi-Tenancy

**Objetivo:** criar a fundação administrativa da plataforma.

## Módulos

### Tenant

- tenant;
- empresa;
- matriz;
- filiais;
- configurações.

### Usuários

- usuários;
- perfis;
- papéis;
- permissões;
- autenticação;
- recuperação de acesso;
- auditoria de login.

### Configuração

- dados da empresa;
- endereço;
- documentos;
- configurações fiscais;
- parâmetros operacionais.

## Arquitetura

```text
Tenant
  ↓
Empresa
  ↓
Filiais
  ↓
Usuários
  ↓
Módulos
```

## Roadmaps derivados

```text
ROADMAP_TENANCY.md
ROADMAP_IDENTITY_ACCESS.md
ROADMAP_COMPANY_CONFIGURATION.md
```

---

# FASE 2 — Product Core

**Objetivo:** construir o principal domínio cadastral da plataforma.

O Product Core deverá ser genérico o suficiente para suportar varejo, supermercado, moda, calçados, restaurante e padaria.

## Entregas

- produtos;
- categorias;
- subcategorias;
- marcas;
- unidades;
- SKU;
- GTIN/EAN;
- preços;
- custo;
- produto simples;
- produto com variação;
- produto vendido por peso;
- produto composto;
- kit;
- combo;
- serviço.

## Variações

Suporte a:

```text
Produto
  ↓
Cor
Tamanho
Grade
SKU
GTIN
```

## Produtos compostos

Suporte a:

```text
Produto
 ↓
Ficha técnica
 ↓
Componentes
 ↓
Quantidade
 ↓
Custo
```

## Fiscal

Incluir classificação fiscal básica:

- NCM;
- CEST;
- origem;
- unidade tributável;
- GTIN fiscal.

## Roadmap derivado

```text
ROADMAP_PRODUCT_CORE.md
```

Este deverá ser um dos roadmaps mais detalhados do projeto.

---

# FASE 3 — Pricing e Regras Comerciais

**Objetivo:** separar produto de preço e permitir evolução comercial futura.

## Entregas

- preço padrão;
- preço por filial;
- tabela de preços;
- período de vigência;
- promoções;
- descontos;
- preço especial;
- combos;
- regras comerciais.

Estrutura conceitual:

```text
Produto
   ↓
Tabela de Preço
   ↓
Filial
   ↓
Promoção
   ↓
Venda
```

## Roadmaps derivados

```text
ROADMAP_PRICING.md
ROADMAP_PROMOTIONS.md
```

---

# FASE 4 — Estoque

**Objetivo:** criar controle de estoque auditável e baseado em movimentações.

## Entregas

- saldo por filial;
- entrada;
- saída;
- transferência;
- ajuste;
- inventário;
- perdas;
- reservas;
- histórico.

Tipos de movimentação:

```text
PURCHASE
SALE
RETURN
TRANSFER
ADJUSTMENT
PRODUCTION
LOSS
```

## Princípio

Não tratar estoque apenas como:

```text
product.quantity = 10
```

O saldo deverá ser consequência das movimentações.

## Roadmap derivado

```text
ROADMAP_INVENTORY.md
```

---

# FASE 5 — Clientes e CRM Básico

**Objetivo:** criar o núcleo de relacionamento com clientes.

## Entregas

- pessoa física;
- pessoa jurídica;
- contatos;
- endereço;
- documentos;
- histórico de compras;
- preferências;
- observações;
- consentimentos;
- LGPD;
- clientes anônimos;
- identificação opcional.

## Roadmaps derivados

```text
ROADMAP_CUSTOMERS.md
ROADMAP_PRIVACY_LGPD.md
```

---

# FASE 6 — PDV Desktop

**Objetivo:** transformar a prova de conceito em produto operacional.

## Tecnologia

```text
.NET / C#
SQLite
```

## Entregas

- instalação;
- login;
- seleção de filial;
- operador;
- abertura de caixa;
- leitura de código;
- busca de produto;
- carrinho;
- alteração de quantidade;
- desconto permitido;
- cliente;
- formas de pagamento;
- finalização;
- cancelamento;
- impressão;
- fechamento de caixa.

## Offline

O PDV deverá operar com:

```text
Produtos
Preços
Configurações
Clientes básicos
Venda
Pagamento
```

localmente.

## Roadmap derivado

```text
ROADMAP_PDV.md
```

---

# FASE 7 — Sync Engine

**Objetivo:** garantir consistência entre PDV e plataforma.

## Entregas

- Outbox Pattern;
- sincronização incremental;
- idempotência;
- device_id;
- sequência local;
- retries;
- backoff;
- logs;
- resolução de conflitos;
- sincronização de produtos;
- sincronização de preços;
- sincronização de vendas;
- sincronização de clientes.

## Estados

```text
PENDING
PROCESSING
SYNCED
FAILED
RETRY
```

## Roadmap derivado

```text
ROADMAP_SYNC_ENGINE.md
```

Este também deverá possuir uma suíte pesada de testes.

---

# FASE 8 — Pagamentos e Caixa

**Objetivo:** consolidar o domínio financeiro da venda.

## Entregas

- dinheiro;
- PIX;
- débito;
- crédito;
- múltiplos pagamentos;
- troco;
- sangria;
- suprimento;
- abertura;
- fechamento;
- divergência;
- auditoria.

## Integrações futuras

- TEF;
- PinPad;
- adquirentes;
- SmartPOS;
- conciliação.

## Roadmaps derivados

```text
ROADMAP_PAYMENTS.md
ROADMAP_CASH_REGISTER.md
ROADMAP_TEF.md
```

---

# FASE 9 — Fiscal

**Objetivo:** tornar a operação comercialmente viável no ambiente fiscal brasileiro.

## Entregas

### NFC-e

- emissão;
- assinatura;
- autorização;
- rejeição;
- cancelamento;
- contingência;
- retransmissão;
- XML;
- DANFCE.

### NF-e

Posteriormente:

- emissão;
- entrada;
- saída;
- devolução;
- cancelamento;
- carta de correção quando aplicável.

### Certificados

- upload;
- armazenamento seguro;
- validação;
- expiração;
- alertas.

## Auditoria

Todo documento deverá possuir histórico completo.

```text
Documento
 ↓
Tentativas
 ↓
SEFAZ
 ↓
Resposta
 ↓
Eventos
```

## Roadmap derivado

```text
ROADMAP_FISCAL.md
```

Esse deverá ser tratado como módulo crítico.

---

# FASE 10 — Compras e Fornecedores

**Objetivo:** fechar o ciclo comercial do estoque.

## Entregas

- fornecedores;
- produtos por fornecedor;
- pedido de compra;
- recebimento;
- custo;
- entrada de estoque;
- contas a pagar;
- documentos de entrada.

## Evolução

Importação de XML NF-e para apoiar:

- recebimento;
- cadastro;
- conferência;
- atualização de custo.

## Roadmaps derivados

```text
ROADMAP_SUPPLIERS.md
ROADMAP_PURCHASING.md
```

---

# FASE 11 — Financeiro

**Objetivo:** oferecer visão financeira operacional.

## Entregas

- contas a receber;
- contas a pagar;
- fluxo de caixa;
- categorias financeiras;
- centro de custo;
- receitas;
- despesas;
- conciliação;
- relatórios.

## Roadmap derivado

```text
ROADMAP_FINANCIAL.md
```

---

# FASE 12 — Especialização para Restaurante

**Objetivo:** adicionar regras específicas de food service.

## Entregas

- mesas;
- comandas;
- garçons;
- pedidos;
- adicionais;
- observações;
- divisão de conta;
- taxa de serviço;
- cozinha;
- KDS;
- impressão de produção;
- retirada;
- delivery.

## Roadmap derivado

```text
ROADMAP_RESTAURANT.md
```

---

# FASE 13 — Ficha Técnica e Produção

**Objetivo:** controlar produtos produzidos internamente.

## Entregas

- receitas;
- ingredientes;
- rendimento;
- custo;
- perdas;
- produção;
- consumo;
- lotes.

Exemplo:

```text
Produção
 ↓
100 pães
 ↓
Consumo
 ↓
Farinha
Fermento
Sal
Água
```

## Roadmaps derivados

```text
ROADMAP_RECIPES.md
ROADMAP_PRODUCTION.md
```

---

# FASE 14 — Especialização para Padaria

**Objetivo:** adicionar recursos específicos para padarias e cafeterias.

## Entregas

- balanças;
- produtos por peso;
- produção;
- lotes;
- validade;
- desperdício;
- previsão de produção;
- etiquetas;
- integração com receita/ficha técnica.

## Roadmap derivado

```text
ROADMAP_BAKERY.md
```

---

# FASE 15 — Moda, Calçados e Grades

**Objetivo:** especializar o Product Core para varejo de moda.

## Entregas

- cor;
- tamanho;
- grade;
- coleção;
- variações;
- estoque por SKU;
- GTIN por SKU;
- preço por variação quando necessário.

## Roadmap derivado

```text
ROADMAP_FASHION_RETAIL.md
```

---

# FASE 16 — Supermercado

**Objetivo:** adicionar funcionalidades de grande volume e operação rápida.

## Entregas

- milhares de SKUs;
- balanças;
- etiquetas;
- preço por peso;
- promoções;
- códigos de balança;
- alto volume de vendas;
- performance;
- importação em massa;
- inventário.

## Roadmap derivado

```text
ROADMAP_SUPERMARKET.md
```

---

# FASE 17 — Cardápio Digital / Catálogo Digital

**Objetivo:** criar a camada de interação direta do consumidor.

Mesmo sem possuir o cardápio originalmente criado pelos idealizadores, a plataforma deverá possuir seu próprio módulo.

## Entregas

- cardápio web;
- QR Code;
- NFC;
- categorias;
- imagens;
- adicionais;
- disponibilidade;
- preço;
- pedido;
- carrinho.

Fluxo:

```text
NFC / QR
   ↓
Cardápio
   ↓
Produto
   ↓
Pedido
```

## Roadmap derivado

```text
ROADMAP_DIGITAL_MENU.md
```

---

# FASE 18 — Event Tracking

**Objetivo:** começar a construir o ativo de dados da plataforma.

Embora apareça aqui como módulo formal, eventos essenciais deverão começar a ser coletados desde as primeiras fases.

## Eventos

```text
menu_viewed
product_viewed
product_searched
product_added
product_removed
order_started
order_abandoned
order_completed
sale_completed
promotion_applied
```

## Requisitos

- append-only;
- anonimização;
- session_id;
- tenant_id;
- branch_id;
- device_id;
- canal;
- timestamp;
- metadata.

## Roadmap derivado

```text
ROADMAP_EVENT_TRACKING.md
```

---

# FASE 19 — Analytics

**Objetivo:** transformar dados em informação operacional.

## Entregas

- faturamento;
- ticket médio;
- produtos vendidos;
- horários de pico;
- margem;
- estoque;
- curva ABC;
- clientes recorrentes;
- taxa de conversão;
- abandono;
- desempenho por filial.

## Roadmap derivado

```text
ROADMAP_ANALYTICS.md
```

---

# FASE 20 — Customer Intelligence

**Objetivo:** transformar dados comerciais em inteligência.

## Entregas

- cesta de produtos;
- produtos relacionados;
- comportamento;
- recorrência;
- segmentação;
- propensão;
- oportunidades;
- retenção;
- produtos em crescimento;
- produtos em queda.

Exemplo:

```text
X-Burger
   ↓

Clientes também compram:

Batata        72%
Refrigerante  61%
Sobremesa     18%
```

## Roadmap derivado

```text
ROADMAP_CUSTOMER_INTELLIGENCE.md
```

---

# FASE 21 — Demand Intelligence

**Objetivo:** começar a prever o futuro operacional.

## Entregas

- previsão de vendas;
- previsão de demanda;
- previsão de ruptura;
- recomendação de compra;
- previsão de produção;
- sazonalidade;
- tendências.

## Roadmap derivado

```text
ROADMAP_DEMAND_INTELLIGENCE.md
```

---

# FASE 22 — Recommendation Engine

**Objetivo:** transformar inteligência em ação.

## Casos

### PDV

```text
Cliente compra X
 ↓
Sugestão Y
```

### Cardápio

```text
Visualizou Hambúrguer
 ↓
Sugere Batata
```

### Gestor

```text
Produto crescendo
 ↓
Sugere aumentar estoque
```

## Roadmap derivado

```text
ROADMAP_RECOMMENDATION_ENGINE.md
```

---

# FASE 23 — Inteligência Artificial

**Objetivo:** criar uma camada de IA sobre dados já confiáveis.

A IA não deverá substituir regras determinísticas do ERP.

Ela deverá atuar principalmente em:

- interpretação;
- recomendação;
- previsão;
- explicação;
- priorização.

Exemplo:

> Quais produtos devo comprar esta semana?

> Quais itens correm risco de ruptura?

> Onde minha margem caiu?

> Qual promoção tem maior chance de funcionar amanhã?

## Roadmap derivado

```text
ROADMAP_AI_ASSISTANT.md
```

---

# FASE 24 — Observabilidade

**Objetivo:** permitir operação em escala.

## Backend

- logs;
- métricas;
- tracing;
- health checks;
- erros;
- filas;
- banco.

## PDV

Painel:

```text
PDV 001  ONLINE
PDV 002  ONLINE
PDV 003  OFFLINE
PDV 004  SYNC ERROR
PDV 005  FISCAL ERROR
```

## Roadmap derivado

```text
ROADMAP_OBSERVABILITY.md
```

---

# FASE 25 — Atualização Automática do PDV

**Objetivo:** eliminar manutenção manual das instalações.

Fluxo:

```text
PDV
 ↓
Version Check
 ↓
Update
 ↓
Download
 ↓
Validação
 ↓
Instalação
 ↓
Restart
 ↓
Health Check
```

Requisitos:

- assinatura;
- rollback;
- versões obrigatórias;
- versões graduais;
- telemetria.

## Roadmap derivado

```text
ROADMAP_PDV_UPDATER.md
```

---

# FASE 26 — Segurança e LGPD

**Objetivo:** consolidar governança e proteção de dados.

Embora segurança deva existir desde a primeira linha de código, esta fase representa uma auditoria ampla da plataforma.

## Escopo

- autenticação;
- autorização;
- tenant isolation;
- criptografia;
- certificados;
- logs;
- secrets;
- rate limits;
- LGPD;
- consentimentos;
- retenção;
- anonimização;
- exclusão;
- auditoria.

## Roadmap derivado

```text
ROADMAP_SECURITY_LGPD.md
```

---

# FASE 27 — Performance e Escalabilidade

**Objetivo:** preparar a plataforma para centenas ou milhares de estabelecimentos.

## Testes

- carga;
- stress;
- concorrência;
- grandes catálogos;
- milhares de PDVs;
- filas;
- sync;
- emissão fiscal;
- horário de pico.

## Avaliar

```text
MySQL
Redis
Workers
Queues
Storage
API
Event Pipeline
```

## Roadmap derivado

```text
ROADMAP_SCALABILITY.md
```

---

# FASE 28 — APIs e Integrações

**Objetivo:** transformar a plataforma em ecossistema.

## Possíveis integrações

- e-commerce;
- delivery;
- marketplaces;
- contabilidade;
- ERP externo;
- gateways;
- fintechs;
- BI;
- CRM;
- apps móveis.

## Roadmap derivado

```text
ROADMAP_INTEGRATIONS.md
```

---

# FASE 29 — Mobile

**Objetivo:** oferecer aplicações complementares.

Exemplos:

```text
App Garçom
App Gestor
App Estoque
App Cliente
```

O mobile deverá consumir a mesma API central.

## Roadmap derivado

```text
ROADMAP_MOBILE.md
```

---

# FASE 30 — Maturidade SaaS

**Objetivo:** consolidar a plataforma como produto comercial escalável.

## Entregas

- planos;
- assinaturas;
- billing;
- trial;
- onboarding;
- provisionamento;
- limites por plano;
- feature flags;
- suporte;
- SLA;
- central de ajuda;
- métricas SaaS.

## Roadmap derivado

```text
ROADMAP_SAAS_PLATFORM.md
```

---

# Organização Recomendada dos Roadmaps

A documentação do projeto poderá evoluir para:

```text
/docs
│
├── ROADMAP.md
│
├── architecture/
│
│   ├── ARCHITECTURE.md
│   ├── MULTI_TENANCY.md
│   └── ADR/
│
├── roadmap/
│
│   ├── ROADMAP_PRODUCT_CORE.md
│   ├── ROADMAP_PRICING.md
│   ├── ROADMAP_INVENTORY.md
│   ├── ROADMAP_CUSTOMERS.md
│   ├── ROADMAP_PDV.md
│   ├── ROADMAP_SYNC_ENGINE.md
│   ├── ROADMAP_PAYMENTS.md
│   ├── ROADMAP_FISCAL.md
│   ├── ROADMAP_PURCHASING.md
│   ├── ROADMAP_FINANCIAL.md
│   ├── ROADMAP_RESTAURANT.md
│   ├── ROADMAP_BAKERY.md
│   ├── ROADMAP_SUPERMARKET.md
│   ├── ROADMAP_DIGITAL_MENU.md
│   ├── ROADMAP_EVENT_TRACKING.md
│   ├── ROADMAP_ANALYTICS.md
│   ├── ROADMAP_CUSTOMER_INTELLIGENCE.md
│   ├── ROADMAP_DEMAND_INTELLIGENCE.md
│   ├── ROADMAP_RECOMMENDATION_ENGINE.md
│   └── ROADMAP_AI_ASSISTANT.md
│
├── testing/
│
│   ├── TEST_STRATEGY.md
│   ├── SECURITY_TESTS.md
│   ├── PERFORMANCE_TESTS.md
│   └── ACCEPTANCE_TESTS.md
│
└── security/
    ├── SECURITY.md
    ├── LGPD.md
    └── THREAT_MODEL.md
```

---

# Estrutura Recomendada para Cada Roadmap de Módulo

Cada módulo deverá seguir aproximadamente o mesmo padrão:

```text
1. Contexto
2. Objetivo
3. Escopo
4. Fora de escopo
5. Requisitos funcionais
6. Requisitos não funcionais
7. Modelo de domínio
8. Banco de dados
9. APIs
10. Permissões
11. Auditoria
12. Segurança
13. Integrações
14. Edge cases
15. Testes unitários
16. Testes de integração
17. Testes E2E
18. Performance
19. Migrações
20. Critérios de aceite
21. Checklist
22. Documentação
23. Observabilidade
24. Deploy
25. Critérios de conclusão
```

Esse padrão permitirá que cada fase seja realmente **auditável**.

---

# Estratégia de Qualidade

Uma fase não deverá ser considerada concluída apenas porque:

> "a funcionalidade apareceu na tela."

Para cada fase deverá existir:

```text
Implementação
      ↓
Code Review
      ↓
Unit Tests
      ↓
Integration Tests
      ↓
E2E
      ↓
Security Review
      ↓
Regression
      ↓
Documentation
      ↓
Acceptance
      ↓
DONE
```

---

# Definição de Pronto

Uma funcionalidade somente poderá ser marcada como concluída quando:

- regra implementada;
- migrations criadas;
- validações implementadas;
- permissões verificadas;
- logs implementados;
- auditoria implementada quando necessária;
- testes unitários passando;
- testes de integração passando;
- testes E2E passando quando aplicáveis;
- erros tratados;
- documentação atualizada;
- critérios de aceite atendidos;
- nenhuma regressão conhecida crítica;
- deploy validado.

---

# Visão Macro

```text
FUNDAÇÃO
  ↓
PRODUCT CORE
  ↓
ESTOQUE / PREÇO / CLIENTES
  ↓
PDV
  ↓
SYNC
  ↓
PAGAMENTO
  ↓
FISCAL
  ↓
COMPRAS / FINANCEIRO
  ↓
ESPECIALIZAÇÕES
  ↓
CARDÁPIO
  ↓
EVENTOS
  ↓
ANALYTICS
  ↓
CUSTOMER INTELLIGENCE
  ↓
FORECASTING
  ↓
RECOMENDAÇÕES
  ↓
IA
  ↓
ESCALA
```

---

# Princípio de Desenvolvimento

O projeto deverá avançar de forma incremental:

> **primeiro construir corretamente o dado e a operação; depois transformar o dado em inteligência.**

O foco inicial deverá estar em criar um núcleo extremamente confiável para:

```text
Produto
+
Preço
+
Estoque
+
Venda
+
Pagamento
+
Fiscal
```

Somente depois a plataforma deverá aumentar progressivamente a complexidade.

Isso permitirá desenvolver o projeto módulo a módulo, criando roadmaps específicos e detalhados para cada domínio, com maior capacidade de **planejamento, desenvolvimento, auditoria, testes e manutenção**.