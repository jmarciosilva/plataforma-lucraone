# Roadmap — LUCRAONE

**Atualizado:** 2026-09-10 · **417 testes passando** (última execução da suíte: 2026-09-09)

> 🔴 **A F2.6 está bloqueada.** Ela continua sendo a próxima sprint funcional,
> mas só começa depois que os bloqueadores obrigatórios de
> [Pendências bloqueadoras pré-F2.6](#pendências-bloqueadoras-pré-f26) forem
> resolvidos.

---

## Como ler este roadmap

O projeto tem **duas camadas de planejamento**, e elas usam numeração parecida
por acidente histórico. Confundi-las é o erro mais fácil de cometer aqui:

| Camada | O quê | Onde |
|---|---|---|
| **Macro** | Visão de produto em 30 fases, incluindo PDV desktop (.NET), fiscal, sync offline, IA. Horizonte de anos. | [Roadmap Macro](<Roadmap Macro de Desenvolvimento — Plataforma SaaS Inteligente de Automação Comercial.md>) |
| **Execução** | O que está sendo construído agora, em sprints `F1.x`, `F2.x`, `F3.x`. | `ROADMAP_FASE_01/02/03` |

> ⚠️ **A FASE 2 do macro não é a FASE 02 da execução.** A execução cobre hoje o
> que o macro chama de FASE 0 a 5 — fundação, tenancy, produtos, preços, estoque
> e clientes. PDV, fiscal e sync, que são o grosso do macro, ainda não começaram.

Este arquivo é o índice e o placar. O detalhe por sprint fica nos arquivos de
fase; o tracking histórico, em [PROJECT_STATUS.md](PROJECT_STATUS.md).

---

## Onde estamos

```
FASE 01 — FOUNDATION       ✅ 8/8 sprints
        ↓
FASE 03 — ADMIN FRONTEND   ✅ 6/6 sprints
        ↓
FASE 02 — FEATURES         🟡 6/7 sprints   ← AQUI
        ↓
FASE 04+ — a definir       PDV, fiscal, sync — ver roadmap macro
```

Dentro da FASE 02, a sequência oficial é:

```
F2.5 — Automation                 ✅
        ↓
Pendências pré-F2.6              🔴 BLOQUEADOR
        ↓
F2.6 — Integration APIs          📋 NÃO INICIADA
```

As pendências pré-F2.6 não são uma fase nem uma sprint. São correções com IDs
próprios (`SEC-*`, `COR-*`, `PERF-*`), acompanhadas numa seção dedicada abaixo.

A FASE 03 entrou na frente da F2.2 de propósito: depois da F2.1 o backend já
expunha APIs completas, mas **não havia como um humano entrar no sistema**.
Continuar acumulando endpoint sem interface tornaria o produto testável apenas
por Postman.

---

## Placar por fase

### FASE 01 — Foundation ✅

Detalhe: [ROADMAP_FASE_01](<ROADMAP_FASE_01_FOUNDATION.md — Fundação Técnica da Plataforma.md>)

| Sprint | Entrega |
|---|---|
| F1.1 | Bootstrap — Docker, estrutura modular, Pint, PHPStan, health check |
| F1.2 | Tenancy — multi-tenant com isolamento por global scope |
| F1.3 | Companies & Branches |
| F1.4 | Identity — autenticação com Sanctum |
| F1.5 | Authorization — RBAC por estabelecimento |
| F1.6 | Audit & Observability |
| F1.7 | Hardening — security audit e performance baseline |
| F1.8 | Identity Refactor — uma pessoa, vários estabelecimentos |

> **Por que a F1.8 existiu.** Ao desenhar o login da F3.2 descobrimos que
> `users.tenant_id` prendia cada pessoa a um único estabelecimento — mas o
> negócio exige o contrário: um dono com duas lojas, um contador atendendo
> vários clientes. A tabela `user_role` já assumia múltiplos estabelecimentos
> desde a F1.5, então as duas metades do sistema discordavam entre si.

### FASE 03 — Admin Frontend ✅

Detalhe: [ROADMAP_FASE_03](ROADMAP_FASE_03_ADMIN_FRONTEND.md) ·
Referência visual: [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)

| Sprint | Entrega |
|---|---|
| F3.1 | Setup do frontend e layout do painel |
| F3.2 | Login web com sessão e escolha de estabelecimento |
| F3.3 | Dashboard administrativo |
| F3.4 | Gerenciar tenants |
| F3.5 | Gerenciar usuários |
| F3.6 | Gerenciar empresas e permissões |

### FASE 02 — Features 🟡

Detalhe: [ROADMAP_FASE_02](ROADMAP_FASE_02_FEATURES.md)

| Sprint | API | Painel | Entrega |
|---|---|---|---|
| F2.1 — Products | ✅ | ✅ | Produtos, categorias, preços e histórico |
| F2.1b — Products Web UI | — | ✅ | Catálogo pelo painel |
| F2.2 — Inventory | ✅ | ✅ | Saldos, movimentações, níveis de reposição |
| F2.3 — Sales & Orders | ✅ | ✅ | Clientes, pedidos, reserva de estoque |
| F2.4 — Reporting | ✅ | ✅ | Relatórios, dashboard, tendências, CSV |
| F2.5 — Automation | ✅ | ✅ | Regras, gatilhos, notificações, e-mail |
| **F2.6 — Integration APIs** | 📋 | 📋 | **próxima funcional — 🔴 bloqueada** |

> Entre a F2.5 e a F2.6 ficam as
> [pendências bloqueadoras pré-F2.6](#pendências-bloqueadoras-pré-f26).

---

## Pendências bloqueadoras pré-F2.6

**A F2.6 continua sendo a próxima sprint funcional, mas não pode começar** até
que os bloqueadores obrigatórios desta seção estejam resolvidos.

O que estas pendências são, e o que não são:

- **Não são uma fase nova.** Não existem sprints `F2.H*` nem equivalentes, e o
  placar de fases não muda.
- **Não reabrem a F1.7.** Ela foi concluída em 2026-08-14, validando o escopo
  que existia na época: a fundação, sem nenhum módulo de negócio na API. Continua ✅.
- **São correções identificadas depois que a aplicação cresceu.** A maior parte
  nasceu com os módulos da FASE 02 e as telas da FASE 03, entregues a partir de
  2026-08-16. Duas têm origem anterior à F1.7 e não foram detectadas por ela —
  ver [registro para auditoria](#registro-para-auditoria).

Levantadas na auditoria de 2026-09-09 e conferidas contra o código em
2026-09-10, somente por leitura, sem alteração de código.

### Acompanhamento

| ID | Categoria | Pendência | Prioridade | Status | Bloqueia F2.6 |
|---|---|---|---|---|---|
| SEC-01 | Segurança | API Authorization | Crítica | Pendente | **Sim** |
| SEC-02 | Segurança | API Authentication | Crítica | Pendente | **Sim** |
| SEC-03 | Segurança | TenantResolver | Alta | Pendente | **Sim** |
| SEC-04 | Segurança | create-role | Crítica | Pendente | **Sim** |
| SEC-05 | Segurança | SendEmailAction | Média | Pendente | Recomendado |
| SEC-06 | Segurança | Secrets / .env.testing | Média | Pendente | Recomendado |
| COR-01 | Correção | Soft delete + unicidade | Alta | Pendente | Recomendado |
| PERF-01 | Performance | hasAnyPermission | Média | Pendente | Não |

**Bloqueia F2.6** — **Sim**: a F2.6 não começa com o item aberto.
**Recomendado**: resolver antes, por motivo ligado à F2.6, mas sem impedir o
início; adiar exige decisão registrada nesta seção. **Não**: dívida controlada,
pode seguir depois.

**Status** — `Pendente` → `Em andamento` → `Resolvido`. Um item só passa a
`Resolvido` com testes que provem a correção.

### Por que a classificação difere da proposta inicial

A proposta de 2026-09-10 trazia SEC-01 a SEC-03 como bloqueadores e deixava
SEC-04 e SEC-05 para avaliação. A conferência contra o código mudou três itens:

| ID | Proposta | Final | Motivo |
|---|---|---|---|
| SEC-04 | Alta · Avaliar | **Crítica · Sim** | A `TenantPolicy` usa `create-role` como único critério, então o admin de **qualquer** estabelecimento gerencia **todos** os estabelecimentos pelo painel. É quebra de isolamento entre clientes, não só acoplamento. |
| SEC-05 | Alta · Avaliar | Média · Recomendado | Exige permissão administrativa de automação, e não há SMTP de produção nem cadastro self-service. A decisão sobre destinos controlados pelo cliente, porém, precisa existir antes dos webhooks da F2.6. |
| SEC-06 | Média · Não | Média · Recomendado | O repositório é público e a mesma chave está em uso no ambiente local conferido. A F2.6 vai cifrar credenciais com a `APP_KEY`: trocá-la agora é de graça, depois exige recifrar dados. |

SEC-01, SEC-02, SEC-03, COR-01 e PERF-01 mantiveram a classificação proposta. A
justificativa de cada item está no detalhamento.

---

### SEC-01 — API Authorization

**Crítica · Pendente · Bloqueia F2.6: Sim** — Origem: controllers de negócio da
FASE 02, a partir de 2026-08-16 (depois da F1.7)

**Problema.** As Policies existem e o painel web as aplica. Na API, **7 dos 14
controllers** não verificam permissão em camada nenhuma: nem no controller, nem
no FormRequest (`authorize()` devolve `true` ou não existe), nem em middleware —
as rotas desses módulos usam só `['auth:sanctum', 'tenant']`.

| Módulo | Controllers sem autorização | Rotas |
|---|---|---|
| Products | `ProductController`, `CategoryController`, `PriceController` | 20 |
| Inventory | `InventoryController`, `StockLevelController` | 6 |
| Sales | `OrderController`, `CustomerController` | 8 |
| **Total** | **7** | **34** |

Os outros sete estão corretos: `AutomationRuleController` e
`AutomationLogController` chamam `Gate::authorize`; `ReportController`,
`DashboardController` e `AnalyticsController` autorizam em
`ReportPeriodRequest::authorize()` (Gate `view-reports`); `HealthController` e
`AuthController` são públicos por definição.

**Consequência.** Um usuário autenticado com vínculo ativo no estabelecimento —
inclusive com papel `viewer` — cria, altera e apaga produtos, categorias e
preços, ajusta estoque e níveis de reposição, cria e cancela pedidos e cadastra
clientes, independentemente das permissões do seu papel. O isolamento **entre**
estabelecimentos não é afetado: `tenant` e `TenantScope` continuam filtrando.

**Por que passou.** Nenhum teste de API cobre 403 em Products, Inventory ou
Sales. Os únicos testes de 403 na API estão em `ReportApiTest` e
`AutomationApiTest` — justamente os módulos protegidos.

**Objetivo futuro.**

- aplicar as Policies existentes nos sete controllers;
- adicionar testes HTTP reais de `403` por rota;
- preservar o isolamento multi-tenant (`auth:sanctum` → `tenant` → Policy);
- não criar um segundo sistema de autorização.

Como o SEC-04 vem antes, as Policies aplicadas aqui já estarão sem o coringa
`create-role`. Os testes de 403 devem usar um usuário sem **nenhuma** das
permissões aceitas pela Policy e incluir um caso com `create-role`, para
garantir que o coringa não volte.

**Por que bloqueia.** A F2.6 acrescenta uma API que guarda credenciais de
terceiros. Construí-la sobre uma API que não autoriza reproduz o buraco na
superfície mais sensível.

### SEC-02 — API Authentication

**Crítica · Pendente · Bloqueia F2.6: Sim** — Origem: login da API da F1.4
(`b38dfb0`, 2026-08-13), anterior à F1.7 e não detectado por ela

**Problema.**

- **Sem rate limiting.** `POST /api/auth/login` não tem `throttle`, e nenhuma
  rota `/api` tem. O login web tem dois freios: `throttle:20,1` na rota e o
  limite por e-mail + IP do `LoginRequest` web.
- **Enumeração de contas por tempo.** `AuthController::login` avalia
  `! $user || ! Hash::check(...)`: quando o e-mail não existe, o hash não é
  calculado e a resposta volta mais rápido. Mensagem e status são iguais
  (`401`); a diferença é só de tempo.
- **Tokens sem expiração.** `config/sanctum.php` tem `'expiration' => null`. Um
  token vazado vale até que alguém faça logout com ele.
- **Tokens sem abilities.** `createToken('auth_token')` é chamado sem
  abilities, o que equivale a `['*']`.

**Consequência.** Força bruta sem freio contra a API, descoberta de quais
e-mails têm conta e tokens com poder total por tempo indeterminado. Somado ao
SEC-01, um token vazado de qualquer papel dá escrita permanente no
estabelecimento.

**Objetivo futuro.**

- limitar tentativas de login na API;
- reduzir a diferença observável entre usuário inexistente e senha inválida;
- definir estratégia de expiração de tokens;
- definir as abilities mínimas necessárias.

**Por que bloqueia.** A F2.6 introduz clientes de máquina que vão receber
tokens. Definir expiração e abilities depois obriga a reemitir credenciais já
entregues a sistemas externos.

### SEC-03 — TenantResolver

**Alta · Pendente · Bloqueia F2.6: Sim** — Origem: F1.8 (`c73b369`, 2026-08-16)

**Problema.** Em `TenantResolver::resolve()` a checagem de vínculo é condicional:

```php
if ($usuario && ! $usuario->canAccessTenant($tenantId)) {
    return false;
}
```

Sem usuário autenticado, o `X-Tenant-ID` é aceito e vira o contexto da
requisição.

**Por que hoje não é explorável.** Os cinco arquivos de rotas de módulo aplicam
`['auth:sanctum', 'tenant']` nessa ordem, e `bootstrap/app.php` documenta a
exigência. A proteção existe, mas depende de todo arquivo de rotas futuro
repetir essa ordem — não do resolver.

**Objetivo futuro.** Fazer o próprio resolver rejeitar contexto de tenant quando
não houver identidade autenticada adequada.

**Por que bloqueia, embora hoje não seja explorável.** A F2.6 prevê webhooks e
integração com gateway de pagamento, que recebem chamadas externas sem token
Sanctum. Serão as primeiras rotas a precisar de contexto de tenant sem usuário —
exatamente o cenário em que o resolver confia no header.

### SEC-04 — create-role

**Crítica · Pendente · Bloqueia F2.6: Sim** — Origem: Policies da F2.1b
(`8fbc4c3`) e da F3.4 (`b8c19ae`), 2026-08-16

**Problema.** A permissão `create-role` produz três efeitos que quem a concede
não enxerga:

1. **Superadmin implícito.** Aparece como coringa, ao lado das permissões
   específicas, em 10 Policies (`Product`, `Category`, `Inventory`,
   `StockLevel`, `Order`, `Customer`, `Company`, `AutomationRule`, `Role`,
   `Permission`), no Gate `view-reports` e no filtro de destinatários do
   comando `sales:summary`.
2. **Gestão de todos os estabelecimentos.** A `TenantPolicy` usa `create-role`
   como único critério, verificado no estabelecimento atual, e o
   `TenantController` consulta `Tenant::query()` sem filtro. O admin de
   qualquer estabelecimento lista, abre, edita — inclusive o status, podendo
   suspender ou cancelar —, arquiva e restaura os estabelecimentos de todos os
   outros, e cria novos. Não existe papel de administrador da plataforma, e todo
   admin recebe `create-role`, tanto pelo `AuthorizationSeeder` quanto pelo
   provisionamento de novo estabelecimento. O teste
   `test_listagem_de_tenants_renderiza_corretamente` afirma esse comportamento:
   o admin vê um estabelecimento que não é o seu.
3. **Escalada por `update-role`.** `RolePolicy::update` exige só `update-role`,
   e a sincronização de permissões do papel aceita qualquer permissão do
   estabelecimento. Quem tem `update-role` pode conceder `create-role` a
   qualquer papel, inclusive ao próprio.

**Consequência.** O item 2 é uma quebra de isolamento entre estabelecimentos no
painel web — o único ponto conhecido em que um cliente alcança dados e operações
de outro.

**Objetivo futuro.**

- revisar semanticamente `create-role`, restringindo-a ao gerenciamento de papéis;
- remover as dependências implícitas das demais Policies;
- separar a administração da plataforma, que gerencia estabelecimentos, da
  administração de um estabelecimento — decisão que merece ADR;
- impedir que um usuário conceda permissões que ele mesmo não possui.

**Por que bloqueia.** Pelo item 2, é falha de isolamento, da mesma classe que o
SEC-01 e o SEC-03. Além disso, o SEC-01 vai ligar essas Policies à API: sem esta
revisão, o coringa passa a valer lá também. E a F2.6 vai criar uma Policy para
proteger credenciais de terceiros, que herdaria o padrão atual.

### SEC-05 — SendEmailAction

**Média · Pendente · Bloqueia F2.6: Recomendado** — Origem: F2.5 (`3fd8f3b`,
2026-09-09)

**Problema.** `SendEmailAction` aceita em `action_config.recipients` até 1.000
caracteres de endereços separados por vírgula, ponto e vírgula ou quebra de
linha, e só filtra o formato. Assunto e mensagem são escritos pelo usuário e
interpolados com os dados do gatilho. Não há restrição a usuários ou contatos do
estabelecimento, limite de destinatários, limite de envios por regra ou período,
nem registro voltado a detectar abuso.

**Quem pode configurar.** Quem tem `manage-automations` ou `create-role`,
verificado na API e no painel.

**Agravante enquanto o SEC-01 estiver aberto.** O gatilho `product_created`
dispara na criação de produto, que hoje qualquer usuário autenticado faz pela
API, sem limite de volume. Um usuário sem privilégio consegue multiplicar os
envios de uma regra já existente.

**Risco.** Uso indevido, spam, abuso da infraestrutura de e-mail e deterioração
da reputação do domínio remetente.

**Por que Média, e não Alta.** Exige permissão administrativa de automação, não
há cadastro self-service de estabelecimentos e não há envio real — o ambiente
usa Mailpit. **Sobe para Alta** antes de configurar SMTP de produção ou abrir
cadastro self-service.

**Por que Recomendado.** A F2.6 prevê entrega de webhooks para endereços
configurados pelo estabelecimento: o mesmo padrão de destino controlado pelo
cliente, com risco maior, porque permite requisições para a rede interna (SSRF),
como os containers de MySQL e Redis. A política decidida aqui deve orientar o
desenho da F2.6.

**Objetivo futuro.** Avaliar:

- restrição a usuários e contatos pertencentes ao tenant;
- whitelist de domínios ou destinatários;
- limites de envio;
- rate limiting;
- auditoria de abuso.

### SEC-06 — Secrets / .env.testing

**Média · Pendente · Bloqueia F2.6: Recomendado** — Origem: versionado desde
`809442f` (2026-08-13), antes da F1.7; exposto publicamente com a publicação do
repositório no GitHub em 2026-09-09

**Problema.** `lucraone-backend/.env.testing` está versionado com uma `APP_KEY`
real, e o repositório é público — conferido em 2026-09-10: a API do GitHub
responde sem autenticação.

Achados da conferência:

- **A chave não é só de teste.** O ambiente de desenvolvimento local conferido
  usa a mesma `APP_KEY` (comparação por hash, sem exibir o valor).
- **Vai para a imagem de produção.** O `.dockerignore` não exclui
  `.env.testing`, e o `COPY . .` do alvo `prod` o copia.
- **Não há outro segredo aparente.** Nenhum arquivo `.pem`, `.key`, `.p12`,
  `.pfx`, `credentials` ou `secret` versionado, e o `.env` nunca foi commitado.
- **Nada é cifrado com a chave hoje:** não há cast `encrypted` nem uso de `Crypt`.

**Consequência.** Qualquer dado cifrado ou assinado com essa chave deixa de ser
confiável nos ambientes que a usam.

**Por que Média.** Não há produção nem dado cifrado hoje.

**Por que Recomendado.** A F2.6 guarda `credentials_encrypted`, cifrado com a
`APP_KEY`. Trocar a chave agora não custa nada; depois exige recifrar as
credenciais.

**Objetivo futuro.**

- remover o segredo versionado. Remover do repositório não basta: a chave está
  no histórico público e deve ser tratada como comprometida — gerar chave nova
  em todo ambiente que a use;
- definir geração segura da chave para o ambiente de testes;
- revisar outros possíveis segredos commitados por acidente, inclusive no
  histórico.

### COR-01 — Soft delete + unicidade

**Alta · Pendente · Bloqueia F2.6: Recomendado** — Origem: F2.1, F3.4 e F3.5,
2026-08-16

**Problema.** Quatro tabelas combinam soft delete com índice único que não
considera `deleted_at`:

| Tabela | Unicidade | Soft delete desde |
|---|---|---|
| `products` | `(tenant_id, sku)` | F2.1 |
| `categories` | `(tenant_id, slug)` | F2.1 |
| `tenants` | `slug` (global) | F3.4 |
| `users` | `email` (global) | F3.5 |

O que isso causa, pela leitura do código:

- **No painel**, as validações `Rule::unique` contam registros arquivados e
  respondem "já existe" para um valor que não aparece na listagem padrão.
  Restaurar, disponível nas quatro telas, é a única saída.
- **Na API de produtos**, `StoreProductRequest` não valida a unicidade do SKU —
  a mensagem `sku.unique` existe, a regra não — e não há tratamento de violação
  de constraint. Um SKU repetido, arquivado ou ativo, chega ao banco como
  exceção não tratada.
- **Em `users.email`**, que é identidade global desde a F1.8, o efeito depende
  do fluxo de reingresso de uma pessoa arquivada. Ainda não foi mapeado.

O banco preserva a integridade: nenhuma duplicata é gravada.

**Consequência.** Um valor que pertence a um registro arquivado fica
indisponível indefinidamente.

**Objetivo futuro.** Definir uma estratégia coerente entre soft delete,
restauração, reutilização, constraints e validação de domínio. É a política que
a Fundação pediu na seção 51 ("a política deverá ser definida explicitamente") e
que não chegou a ser escrita.

**Por que Recomendado.** A F2.6 prevê sincronização com ERP e marketplace, que
casa produtos por SKU: um SKU arquivado quebraria a importação.

**Por que não Sim.** Não é falha de segurança e não corrompe dados.

### PERF-01 — hasAnyPermission

**Média · Pendente · Bloqueia F2.6: Não** — Origem: laço da F1.5 (`8b34ee4`,
2026-08-13), com custo relevante a partir das Policies da FASE 02 e da FASE 03

**Problema.** `hasAnyPermission()` percorre a lista chamando `hasPermission()`,
e cada chamada executa `getPermissions()` do zero: uma consulta a
`rolesForTenant` mais o eager load de `permissions`, ou seja, duas consultas.
Não há memoização. O laço para na primeira permissão encontrada, então o pior
caso é a **negação**: para um usuário sem permissão, a `ProductPolicy` testa
três permissões e faz seis consultas, fora a de `canAccessTenant`.

**Relação com o SEC-01.** Aplicar as Policies nas 34 rotas coloca esse custo em
cada requisição da API. Vale medir logo depois do SEC-01.

**Objetivo futuro.** Reduzir as idas ao banco com memoização ou cache por
requisição, ou carregando uma única vez as permissões do usuário no tenant
corrente.

**Restrição.** O cache precisa ser indexado por usuário **e** estabelecimento.
`hasPermission()` aceita `$tenantId` explícito, e `sales:summary` verifica
permissões estabelecimento por estabelecimento: um cache indexado só por usuário
devolveria, para quem tem vínculo em mais de um estabelecimento, as permissões
do estabelecimento errado.

**Por que Não.** É desempenho, sem efeito em segurança, e com o volume atual não
há medição de impacto que justifique segurar a F2.6.

---

### Ordem recomendada

```
SEC-04 — create-role
        ↓
SEC-01 — API Authorization
        ↓
SEC-02 — API Authentication
        ↓
SEC-03 — TenantResolver
        ↓
SEC-05 — SendEmailAction
        ↓
SEC-06 — Secrets / .env.testing
        ↓
COR-01 — Soft delete + unicidade
        ↓
PERF-01 — hasAnyPermission
        ↓
F2.6 — Integration APIs
```

| Grupo | Itens | Regra |
|---|---|---|
| **Bloqueadores obrigatórios** | SEC-04, SEC-01, SEC-02, SEC-03 | A F2.6 só começa com os quatro `Resolvido` |
| **Recomendados antes da F2.6** | SEC-05, SEC-06, COR-01 | Resolver antes; adiar exige decisão registrada nesta seção |
| **Dívida controlada** | PERF-01 | Pode seguir depois da F2.6; medir logo após o SEC-01 |

**Sobre a ordem.** O SEC-04 vem antes do SEC-01 por dependência arquitetural. O
SEC-01 vai aplicar as Policies às 34 rotas da API que hoje não as usam, e o
SEC-04 muda o significado e o alcance dessas Policies: hoje `create-role` é
coringa em várias delas, a gestão de estabelecimentos quebra o isolamento entre
clientes e `update-role` permite escalada de privilégio. Espalhar as Policies
atuais pela API antes de corrigi-las levaria esses defeitos para a API e
obrigaria a refazer o SEC-01 e os seus testes.

```
SEC-04  corrige o modelo de privilégios e o isolamento administrativo
        ↓
        as Policies passam a representar corretamente as permissões
        ↓
SEC-01  aplica as Policies corrigidas na API
```

### Critério para liberar a F2.6

- SEC-01, SEC-02, SEC-03 e SEC-04 com status `Resolvido`, cada um com os testes
  que provam a correção;
- SEC-05, SEC-06 e COR-01 resolvidos, ou com decisão de adiamento registrada
  nesta seção;
- tabela de acompanhamento, README e placar da FASE 02 atualizados.

### Registro para auditoria

**Correções em relação à auditoria de 2026-09-09.** A versão anterior deste
roadmap (commit `01424ca`) afirmava:

| Afirmação anterior | Conferência de 2026-09-10 |
|---|---|
| "12 dos 14 controllers" da API não chamam `Gate::authorize` | São **7 de 14**. Reporting autoriza via `ReportPeriodRequest`, como a F2.4 registrou, e Health e Auth são públicos |
| Qualquer token "lê relatórios financeiros" | Incorreto: os relatórios exigem `view-reports` na API, e `ReportApiTest` testa o 403 |
| "O isolamento entre estabelecimentos funciona" | Vale para os dados de negócio. A gestão de estabelecimentos no painel é exceção (SEC-04) |
| `.env.testing` como item de qualidade | Reclassificado como SEC-06, depois de confirmado que o repositório é público e a chave está em uso fora dos testes |

**Lacunas não detectadas pela F1.7 e pelos registros da FASE 01.** A F1.7
permanece concluída, e os seus documentos não foram alterados. A conferência
encontrou três pontos em que o registro não corresponde ao código:

- `F1.7_HARDENING_PLAN.md` marca como validado "Tokens com expiração (Sanctum
  default)". O padrão do Sanctum é não expirar: `config/sanctum.php` tem
  `'expiration' => null` desde a F1.4, e `test_expired_token_rejected` contém
  apenas `assertTrue(true)`. → SEC-02
- O mesmo plano anotou "Rate limiting (não implementado, notar)". A ausência foi
  percebida, mas nunca virou tarefa. → SEC-02
- `PROJECT_STATUS.md` marca "Secrets não versionados" como verificado, e o
  `DEVELOPMENT_DASHBOARD.md`, "No credentials in code". O `.env.testing` com
  `APP_KEY` já era versionado desde 2026-08-13; na época o repositório ainda não
  tinha remoto. → SEC-06

---

## Próxima sprint funcional — F2.6, Integration APIs

> 🔴 **Bloqueada** até que os bloqueadores obrigatórios das
> [pendências pré-F2.6](#pendências-bloqueadoras-pré-f26) estejam resolvidos.
> Status: 📋 não iniciada.

Integração com sistemas externos: cadastrar integração pelo painel, disparar
webhook de teste e auditar os envios. Modelos `Integration` (com credenciais
cifradas) e `IntegrationLog`. Especificação completa na seção F2.6 do
[ROADMAP_FASE_02](ROADMAP_FASE_02_FEATURES.md).

Sete das oito pendências tocam diretamente o que a F2.6 constrói: API sensível
(SEC-01, SEC-04), clientes de máquina com token (SEC-02), webhooks recebidos sem
usuário (SEC-03), destinos controlados pelo cliente (SEC-05), credenciais
cifradas com a `APP_KEY` (SEC-06) e sincronização por SKU (COR-01).

---

## Outras pendências de qualidade

Não bloqueiam a F2.6 e não receberam ID.

| Item | Estado |
|---|---|
| PHPStan (nível 4) | 11 erros — tipos de retorno de View e acesso a `$id` em união de tipos |
| Pint | 22 arquivos fora do padrão — imports não usados, ordenação |
| Automation Rules Guide | Não escrito — único item não entregue da F2.5 |
| `performance with growing data` | Intermitente sob carga: assertiva sensível a tempo, passa isolada |

> O item "`.env.testing` com `APP_KEY` versionada", que ficava nesta tabela, virou
> o SEC-06.

---

## Cobertura de testes por área

417 testes, contados por diretório de `tests/`:

| Área | Testes | Área | Testes |
|---|---|---|---|
| Admin (painel) | 106 | Sales | 16 |
| Quality | 45 | Companies | 16 |
| Products | 42 | API (integração) | 16 |
| Identity | 35 | Performance | 15 |
| Automation | 29 | Audit | 13 |
| Security | 26 | Tenancy | 10 |
| Authorization | 17 | Unit | 8 |
| Reporting | 17 | Inventory | 5 |

> `Inventory` com 5 testes destoa do peso do módulo. A maior parte da cobertura
> de estoque vive em `Admin/InventoryWebManagementTest` — vale conferir se o
> caminho de API está tão coberto quanto o do painel.

---

## Documentos

| Arquivo | Para quê |
|---|---|
| [README.md](README.md) | Porta de entrada: o que é e como subir |
| [DOCKER.md](DOCKER.md) | Ambiente: containers, comandos, produção, desempenho |
| [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) | Referência visual do painel |
| [PROJECT_STATUS.md](PROJECT_STATUS.md) | Tracking detalhado, sprint a sprint |
| [DEVELOPMENT_DASHBOARD.md](DEVELOPMENT_DASHBOARD.md) | Visão de progresso |
| [TESTING_GUIDE.md](TESTING_GUIDE.md) | Como testar |
| [Visão do produto](<Projeto — Plataforma SaaS Inteligente de Automação Comercial.md>) | O que a plataforma pretende ser |
| [Roadmap macro](<Roadmap Macro de Desenvolvimento — Plataforma SaaS Inteligente de Automação Comercial.md>) | As 30 fases de longo prazo |
| `lucraone-backend/README.md` | Detalhe técnico do backend |
| `lucraone-backend/docs/architecture/` | Arquitetura e multi-tenancy |
| `lucraone-backend/docs/adr/` | Decisões arquiteturais registradas |
