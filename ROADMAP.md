# Roadmap — LUCRAONE

**Atualizado:** 2026-09-23 · **PM-04 dividido em PM-04A/PM-04B/PM-04C — PM-04A
planejado** — 642 testes no total: 640 PASS, 0 FAIL e 2 risky preexistentes ·
SEC-04 resolvido em 2026-09-12

> 🔴 **A F2.6 continua bloqueada.** Ela segue sendo a próxima sprint funcional,
> mas só começa depois que os bloqueadores obrigatórios de
> [Pendências bloqueadoras pré-F2.6](#pendências-bloqueadoras-pré-f26) forem
> resolvidos. O SEC-04 está resolvido; **SEC-01, SEC-02 e SEC-03 continuam
> pendentes e bloqueiam.**

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

Em paralelo corre a trilha
[Cadastro mestre de produtos — preparação para o PDV](#cadastro-mestre-de-produtos--preparação-para-o-pdv)
(`PM-*`), aberta por necessidade de cliente. Ela evolui o cadastro de produtos e
não altera o bloqueio da F2.6 nem a ordem das pendências.

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
| SEC-04 | Segurança | create-role | Crítica | Resolvido | **Sim** |
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

Como o SEC-04 vem antes, as Policies aplicadas aqui já estão sem o coringa
`create-role`, removido em `6c770dc`. Os testes de 403 devem usar um usuário sem **nenhuma** das
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

**Crítica · Resolvido · Bloqueia F2.6: Sim** — Origem: identidade global da F1.8
(`c73b369`) e Policies e telas da F2.1b (`8fbc4c3`), F3.4 (`b8c19ae`), F3.5
(`e1548e8`) e F3.6 (`a13fe95`), todas de 2026-08-16

**Escopo.** O item leva o nome `create-role` porque foi por essa permissão que o
problema apareceu, mas o que ele corrige é o modelo de autorização: a separação
entre a autoridade da plataforma e a autoridade de um estabelecimento, a
contenção da delegação de poder e o contexto de estabelecimento usado pelas
Policies. Continua sendo **um único bloqueador**: os vetores abaixo não viram
itens separados.

**Histórico.** A auditoria de 2026-09-09 registrou o coringa, a gestão de todos
os estabelecimentos (X1) e a escalada por `update-role` (E1). A auditoria
pré-implementação de 2026-09-10, feita só por leitura de código, testes e
histórico, confirmou os três e encontrou mais três vetores: E2, E3 e E7. Nenhum
deles era conhecido na F1.7, concluída em 2026-08-14 — os fluxos envolvidos
surgiram depois dela. Os identificadores seguem o relatório da auditoria; E4 a
E6 foram variantes descartadas ou absorvidas pelos demais.

#### Estado atual

| Frente | Status | Evidência |
|---|---|---|
| Platform Admin / X1 | ✅ Concluído | `faf3c4e` |
| Remoção de `create-role` como coringa dos módulos de negócio | ✅ Concluído | `6c770dc` |
| Matriz explícita do admin de estabelecimento (26 permissões) | ✅ Concluído | `6c770dc` |
| Provisionamento de novos estabelecimentos | ✅ Concluído | `6c770dc` |
| Data fix dos admins existentes | ✅ Concluído | `6c770dc` |
| E1 — Escalada por `update-role` | ✅ Concluído | `2f1f6ea` |
| Matriz dos papéis padrão aninhada (pré-requisito do E2) | ✅ Concluído | `cbba727` |
| Data fix da matriz nos estabelecimentos existentes | ✅ Concluído | `cbba727` |
| Autoridade efetiva por estabelecimento (`TenantAuthority`) | ✅ Concluído | `4909137` |
| E2 — Escalada por `manage-users` | ✅ Concluído | `2849c10` |
| E3 — Administração local de identidade global | ✅ Concluído | `d6fdaac` |
| E7 — Entidade e permissão avaliadas em estabelecimentos diferentes | ✅ Concluído | `7433c5b` |

O SEC-04 está **resolvido**: todos os vetores estão corrigidos e os
[critérios de aceite](#critérios-de-aceite) estão verdes. Isso **não libera a
F2.6**, que permanece bloqueada e não iniciada — SEC-01, SEC-02 e SEC-03 seguem
pendentes e são bloqueadores obrigatórios.

**Próximo bloqueador técnico:** SEC-01 — API Authorization, ainda não iniciado.

**Evidência atual.** Após `7433c5b`, o SEC-04 tem 151 testes: 151 PASS, 0 FAIL,
0 ERROR e 583 assertions.

| Grupo | PASS | FAIL | Situação |
|---|---|---|---|
| X1 | 21 | 0 | Corrigido |
| `create-role` e admin explícito | 33 | 0 | Corrigido |
| E1 | 8 | 0 | Corrigido |
| Matriz padrão | 6 | 0 | Corrigido |
| Data fix da matriz | 9 | 0 | Corrigido |
| Autoridade efetiva (`TenantAuthority`) | 5 | 0 | Corrigido |
| E2 | 29 | 0 | Corrigido |
| E3 | 23 | 0 | Corrigido |
| E7 | 17 | 0 | Corrigido |
| **Total** | **151** | **0** | |

Não restam falhas esperadas: as 14 que pertenciam ao E7 ficaram verdes com
`7433c5b`, sem alteração de testes. A evidência de cada etapa anterior fica
registrada na implementação correspondente, abaixo, com as baselines históricas
preservadas.

A suíte completa tem 568 testes: 566 PASS, 0 FAIL, 2 risky preexistentes de
`SecurityAuditTest`, 0 ERROR, 0 SKIPPED e 1820 assertions. Não houve regressão
funcional.
A instabilidade histórica de `test_performance_with_growing_data` não se reproduziu
nesta execução, o que não indica que tenha sido corrigida (ver
[Outras pendências de qualidade](#outras-pendências-de-qualidade)).

**Commits de evidência.**

| Commit | Título |
|---|---|
| `bb64d86950c77670454d4f7c97f52e6d5f345ba0` | test(security): registrar baseline executável do SEC-04 |
| `faf3c4ed8769a612d3eec19d8a248175c300efcf` | fix(security): separar Platform Admin da autorização de tenant |
| `6f932c841c6c0ef7e2a436a70ad48253fcf85549` | docs(security): sincronizar progresso do SEC-04 após Platform Admin e X1 |
| `6c770dcd2ef90c3624d30d9b6950041e73bfb3ac` | fix(security): remover bypass create-role dos módulos de negócio |
| `dd1100f82bf7156dd9c69b04dc2c7fe6cc94d64a` | docs(security): sincronizar SEC-04 após remoção do bypass create-role |
| `df287dec95cab9f3aa111d85fbae0e68a9083c01` | test(security): ampliar caracterização do E3 no SEC-04 |
| `d6fdaac3ed97df8d293d42b79a5991fdd4a4f0e9` | fix(security): proteger identidade global contra autoridade local |
| `f80d45768487715f59b1b1d846f6486caa188b59` | docs(security): sincronizar SEC-04 após correção do E3 |
| `00aa07ed82fc3929203d5868b7944b982c36b917` | test(security): ampliar caracterização do E1 no SEC-04 |
| `2f1f6ead57a0baa34b306136e6deb43970a4bdf2` | fix(security): conter delegação de permissões em update-role |
| `1ffa02caa9ea11a3a65f2f666df90ca3bbf0d473` | docs(security): sincronizar SEC-04 após correção do E1 |
| `badf1328ea249a5d09dcf67f299984c71523af66` | test(security): caracterizar matriz padrão necessária ao E2 |
| `cbba7274e6fec4fc074e4f998086c15bf8916775` | fix(security): normalizar matriz padrão para contenção do E2 |
| `540ea59a84d1ac79fbfe605c60392897bee6a49a` | test(security): ampliar caracterização do E2 no SEC-04 |
| `4909137f6fb39cd914f392d3893ea621e4bb263f` | refactor(security): extrair autoridade efetiva por tenant |
| `2849c1001b1293133e2d59f9ae77a35ddb85548a` | fix(security): conter administração de usuários por autoridade |
| `7ace0c46e9dfc9829e8636560365a5561b1d36b3` | docs(security): sincronizar SEC-04 após correção do E2 |
| `7433c5bd9bc8d686d1322a59e00f5e570bcfac15` | fix(security): resolver estabelecimento antes do route binding no painel |

#### Implementação 1 — Platform Admin + X1

O baseline executável do SEC-04 foi publicado em
`bb64d86950c77670454d4f7c97f52e6d5f345ba0`: 66 testes, 4 PASS e 62 EXPECTED
FAIL. A primeira correção de produção foi publicada em
`faf3c4ed8769a612d3eec19d8a248175c300efcf`.

Ela implementou o marcador global e protegido `is_platform_admin`, consultado
semanticamente por `User::isPlatformAdmin()`. Platform Admin não depende de
`create-role` nem de papel administrativo tenant-scoped; roles e permissions de
negócio continuam tenant-scoped, com `roles.tenant_id` e `permissions.tenant_id`
obrigatórios. Não foi criado RBAC global nesta etapa.

Na `TenantPolicy`, `create-role` deixou de representar autoridade de plataforma:
somente Platform Admin administra tenants. A proteção continua no backend pela
Policy; a sidebar foi sincronizada apenas como UX. Platform Admin também funciona
sem `create-role`.

**Evidência na publicação.** Com essa correção, o SEC-04 passou a ter 72 testes:
25 PASS, 47 EXPECTED FAIL e 0 ERROR. X1 ficou corrigido com 21 PASS e 0 FAIL — 15
da baseline e 6 positivos de Platform Admin. A suíte completa tinha 489 testes:
440 PASS, 47 falhas SEC-04 esperadas, 2 risky preexistentes, 0 SKIPPED e 1353
assertions. Esse progresso foi sincronizado na documentação em
`6f932c841c6c0ef7e2a436a70ad48253fcf85549`.

#### Implementação 2 — remoção do coringa `create-role`

A segunda correção de produção foi publicada em
`6c770dcd2ef90c3624d30d9b6950041e73bfb3ac`.

Ela removeu `create-role` como autorização coringa dos módulos de negócio — das
Policies `Product`, `Category`, `Inventory`, `StockLevel`, `Order`, `Customer`,
`Company` e `AutomationRule`, do Gate `view-reports` e do filtro de destinatários
de `relatorios:enviar-resumo`. `create-role` permanece somente no domínio legítimo
de papéis e permissões (`RolePolicy` e `PermissionPolicy`).

Para que os admins legítimos deixassem de depender do coringa:

- `AdminPermissionMatrix` define as 26 permissões explícitas do papel `admin` de
  estabelecimento, usadas pelo `AuthorizationSeeder` e pelo `TenantController`;
- estabelecimentos criados pelo painel passaram a receber as 26 permissões; antes
  o `TenantController` provisionava só 19;
- a migration `2026_09_10_000001_grant_explicit_business_permissions_to_admin_roles`
  complementa os papéis `admin` já existentes de forma aditiva, idempotente e
  tenant-aware, com um snapshot histórico próprio das 26 permissões, desacoplado
  da matriz da aplicação. Ela não altera papéis personalizados nem Platform Admin.

A cobertura positiva está em `TenantAdminExplicitPermissionsSecurityTest`, que
prova o acesso do admin pelas permissões explícitas e o data fix, e em
`TenantManagementTest`, que confere as 26 permissões no estabelecimento novo. Os
testes de `CreateRoleBypassSecurityTest`, que caracterizavam o coringa, passaram a
verde.

**Evidência na publicação.** Com essa correção, o SEC-04 passou a ter 83 testes:
58 PASS, 25 EXPECTED FAIL, 0 ERROR e 231 assertions — X1 com 21 PASS, `create-role`
e admin explícito com 33 PASS, E1 com 3 FAIL, E2 com 3 FAIL, E3 com 1 PASS e 5 FAIL
e E7 com 3 PASS e 14 FAIL. A suíte completa tinha 500 testes: 472 PASS, 25 falhas
SEC-04 esperadas, 2 risky preexistentes, 1 falha preexistente de
`PerformanceBaselineTest`, 0 ERROR, 0 SKIPPED e 1468 assertions. Não houve
regressão funcional atribuível à correção: a falha de
`test_performance_with_growing_data` foi reproduzida também no commit-base
`6f932c8`, sem as alterações do `create-role`. Esse progresso foi sincronizado na
documentação em `dd1100f82bf7156dd9c69b04dc2c7fe6cc94d64a`.

#### Implementação 3 — identidade global (E3)

Antes da correção, a caracterização do E3 foi ampliada em
`df287dec95cab9f3aa111d85fbae0e68a9083c01`: `GlobalIdentitySecurityTest` passou de
6 para 23 testes, com 7 PASS e 16 EXPECTED FAIL. Os casos novos cobrem o Platform
Admin como alvo, senha e nome alterados pelo update comum, vínculo `INVITED`,
`INACTIVE` e `SUSPENDED` em outro estabelecimento, cadastro com identidade inativa
e arquivada, a proteção já existente de `is_platform_admin` contra payload e
controles positivos para identidade exclusiva, vínculo e papéis locais. O SEC-04
passou a ter 100 testes: 64 PASS, 36 EXPECTED FAIL, 0 ERROR e 268 assertions.

A correção foi publicada em `d6fdaac3ed97df8d293d42b79a5991fdd4a4f0e9`, sem mudança
de schema, migration, endpoint novo ou alteração de testes. A regra está descrita em
[Proteção da identidade global](#proteção-da-identidade-global).

**Evidência na publicação.** Os 23 testes de `GlobalIdentitySecurityTest` ficaram
verdes, e `UserManagementTest` continuou verde. O SEC-04 passou a ter 100 testes:
80 PASS, 20 EXPECTED FAIL, 0 ERROR e 298 assertions; E1, E2 e E7 não foram
alterados. A suíte completa passou a ter 517 testes: 495 PASS, 20 falhas SEC-04
esperadas, 2 risky preexistentes, 0 ERROR e 1535 assertions. Esse progresso foi
sincronizado na documentação em `f80d45768487715f59b1b1d846f6486caa188b59`.

#### Implementação 4 — contenção da delegação (E1)

Antes da correção, a caracterização do E1 foi ampliada em
`00aa07ed82fc3929203d5868b7944b982c36b917`: `RoleDelegationSecurityTest` passou de
3 para 8 testes, com 2 PASS e 6 EXPECTED FAIL. Os casos novos cobrem a retirada
de permissão que o ator não possui, a remoção de `update-role` do próprio papel
(lockout), um papel privilegiado personalizado e dois controles positivos — a
delegação de uma permissão que o ator possui e o admin editando o `manager` sem
alterar `manage-assigned-branches`, que ele não possui. O SEC-04 passou a ter 105
testes: 82 PASS, 23 EXPECTED FAIL, 0 ERROR e 313 assertions.

A correção foi publicada em `2f1f6ead57a0baa34b306136e6deb43970a4bdf2`, sem mudança
de schema, migration ou endpoint novo e sem alterar testes de segurança. A regra
está descrita em [Contenção da delegação](#contenção-da-delegação). O único teste
ajustado foi `CompanyAndRolesManagementTest::test_atribuir_permissions_a_role_salva_pivot_do_tenant`:
a fixture fazia o admin delegar duas permissões que não possuía e passou a
concedê-las a ele, sem mudar a expectativa do teste.

**Evidência na publicação.** Os 8 testes de `RoleDelegationSecurityTest` ficaram
verdes, com 30 assertions, e `CompanyAndRolesManagementTest` continuou verde. O
SEC-04 passou a ter 105 testes: 88 PASS, 17 EXPECTED FAIL, 0 ERROR e 326
assertions; E2 e E7 não foram alterados. A suíte completa passou a ter 522 testes:
503 PASS, 17 falhas SEC-04 esperadas, 2 risky preexistentes, 0 ERROR e 1563
assertions. Esse progresso foi sincronizado na documentação em
`1ffa02caa9ea11a3a65f2f666df90ca3bbf0d473`.

#### Implementação 5 — administração de usuários (E2)

O E2 foi fechado em cinco commits: caracterização e correção da matriz dos papéis
padrão, caracterização ampliada, extração da autoridade efetiva e contenção.

**Pré-requisito — matriz dos papéis padrão.** A contenção por autoridade exige que
quem atribui um papel domine as permissões dele, o que só permite os fluxos
legítimos se os conjuntos dos papéis padrão forem aninhados. A propriedade foi
caracterizada em `badf1328ea249a5d09dcf67f299984c71523af66`, em
`StandardRoleMatrixSecurityTest`: `manager ⊆ admin`, `user ⊆ manager` e
`user ⊆ admin` falhavam por `manage-assigned-branches` e `view-assigned-branches`;
`viewer ⊆ manager`, `viewer ⊆ admin` e o admin igual às 26 permissões de
`AdminPermissionMatrix` passavam. O SEC-04 passou a ter 111 testes: 91 PASS e 20
EXPECTED FAIL.

A correção da matriz foi publicada em `cbba7274e6fec4fc074e4f998086c15bf8916775`. O
seed deixou de atribuir `manage-assigned-branches` ao `manager` e
`view-assigned-branches` ao `user` — permissões consultadas só pela `BranchPolicy`,
que não está registrada —, e as duas continuam no catálogo. A matriz ficou com
admin 26, manager 15, user 8 e viewer 8, sem ranking por nome: a relação vem da
contenção entre os conjuntos (`manager ⊆ admin`, `user ⊆ manager`, `user ⊆ admin`,
`viewer ⊆ manager`, `viewer ⊆ admin`). A migration
`2026_09_11_000000_remove_obsolete_assigned_branch_permissions_from_standard_roles`
corrige os estabelecimentos existentes: remove só essas duas atribuições dos papéis
padrão quando papel, permissão e linha de `role_permission` são do mesmo
estabelecimento; é idempotente; não apaga permissões do catálogo; não toca `admin`,
`viewer` nem papéis personalizados; e o `down()` não recria a matriz insegura. O
controle positivo do E1 que dependia do `manager` passou a usar um papel
personalizado, sem mudar a regra protegida. Evidência: matriz com 6 PASS e data fix
com 9 PASS e 49 assertions; o SEC-04 passou a ter 120 testes, 103 PASS e 17
EXPECTED FAIL.

**Caracterização ampliada.** Em `540ea59a84d1ac79fbfe605c60392897bee6a49a`,
`UserRoleEscalationSecurityTest` passou de 3 para 29 testes — 19 vetores negativos e
10 controles positivos —, com 10 PASS e 19 EXPECTED FAIL. A auditoria que a
precedeu encontrou um caminho de escalada ainda não registrado: como o E3 permite
administrar identidade exclusiva do estabelecimento, `manage-users` redefinia a
senha — exibida a quem executou — e trocava e-mail e senha de um admin exclusivo,
tomando a conta dele. O SEC-04 passou a ter 146 testes: 113 PASS e 33 EXPECTED
FAIL.

| Frente | Vetores negativos |
|---|---|
| Tomada de conta | reset de senha de admin exclusivo, senha pelo update, troca de e-mail |
| Retirada e sabotagem | retirada do papel admin, arquivamento, vínculo `SUSPENDED`, `INACTIVE` e `INVITED` |
| Delegação indevida | papel admin, papel personalizado privilegiado |
| Autoedição | autoelevação, remoção do próprio papel, adição de papel dominado |
| Atomicidade | `viewer` + `admin` sem gravação parcial, cadastro recusado, identidade existente sem vínculo parcial, edição recusada sem gravação parcial |

**Autoridade efetiva por estabelecimento.** Em
`4909137f6fb39cd914f392d3893ea621e4bb263f`, o cálculo de autoridade foi extraído para
`TenantAuthority`, com teste próprio (5 PASS, 12 assertions), e o
`PermissionDelegationService` do E1 passou a reutilizá-lo, sem mudança de semântica
(8 PASS). O SEC-04 passou a ter 151 testes: 118 PASS e 33 EXPECTED FAIL.

**Contenção.** A correção foi publicada em
`2849c1001b1293133e2d59f9ae77a35ddb85548a`, sem mudança de schema, migration,
Policy, Request ou teste. A regra está descrita em
[Administração de usuários por autoridade](#administração-de-usuários-por-autoridade).

**Evidência na publicação.** Os 29 testes de `UserRoleEscalationSecurityTest` ficaram
verdes, com 180 assertions e sem alteração dos testes; `UserManagementTest` (12
PASS), E1 (8 PASS) e E3 (23 PASS) continuaram verdes. O SEC-04 passou a ter 151
testes: 137 PASS, 14 EXPECTED FAIL — todas do E7 —, 0 ERROR e 577 assertions. A
suíte completa passou a ter 568 testes: 552 PASS, 14 falhas SEC-04 esperadas, 2
risky preexistentes, 0 ERROR e 1814 assertions.

#### Implementação 6 — contexto do estabelecimento no route binding (E7)

A correção foi publicada em `7433c5bd9bc8d686d1322a59e00f5e570bcfac15`, em um único
arquivo — `bootstrap/app.php`, +8 linhas —, sem mudança de Policy, Controller,
Request, Model, migration, seeder ou teste.

- **Causa raiz:** o route binding ocorria antes da resolução do `TenantContext`.
- **Correção:** `AutenticarWeb` passou a ter prioridade antes de `SubstituteBindings`.
- **Efeito:** o `TenantScope` filtra entidades de outro estabelecimento durante o
  binding.
- Entidade de outro estabelecimento retorna 404.
- Os 14 vetores restantes foram fechados sem alteração de testes.

**Evidência na publicação.** `CrossTenantPolicyContextSecurityTest` ficou verde: 17
PASS, 0 FAIL e 75 assertions. O SEC-04 passou a ter 151 testes: 151 PASS, 0 FAIL, 0
ERROR e 583 assertions. A suíte completa passou a ter 568 testes: 566 PASS, 0 FAIL,
2 risky preexistentes, 0 ERROR e 1820 assertions.

#### Vetores confirmados

| ID | Vetor | Registrado em | Evidência |
|---|---|---|---|
| — | `create-role` como coringa administrativo | 2026-09-09 | Código |
| X1 | Administração indevida entre estabelecimentos | 2026-09-09 | Código e testes |
| E1 | Escalada por `update-role` | 2026-09-09 | Código |
| **E2** | **Escalada por `manage-users`** — vulnerabilidade crítica | 2026-09-10 | Código |
| **E3** | **Administração local de identidade global** — vulnerabilidade crítica | 2026-09-10 | Código |
| **E7** | **Policy avalia entidade e permissão em estabelecimentos diferentes** | 2026-09-10 | Código e ordem de middleware do framework |

Na auditoria pré-implementação, nenhum dos vetores tinha teste que o demonstrasse
ou o impedisse. O baseline executável passou a caracterizá-los. Após as correções,
todos os vetores — o coringa `create-role`, X1, E1, E2, E3 e E7 — estão corrigidos,
com testes verdes.

**Coringa `create-role` — achado histórico.** A permissão aparecia ao lado das
permissões específicas em 10 Policies (`Product`, `Category`, `Inventory`,
`StockLevel`, `Order`, `Customer`, `Company`, `AutomationRule`, `Role`,
`Permission`), no Gate `view-reports` e no filtro de destinatários do comando
`relatorios:enviar-resumo`. A capacidade que o nome descreve — criar papel — não
tem rota: a permissão funcionava só como marcador de "é admin". Estabelecimentos
criados pelo painel davam ao papel `admin` apenas 19 permissões, sem
`manage-sales`, `view-sales`, `manage-customers`, `view-customers`,
`view-reports`, `manage-automations` e `view-automations`; nesses
estabelecimentos o admin só acessava vendas, clientes, relatórios e automações
pelo coringa.

**Status atual do coringa.** Corrigido em `6c770dc`: `create-role` saiu das oito
Policies de negócio, do Gate `view-reports` e do resumo de vendas, e permanece
apenas em `RolePolicy` e `PermissionPolicy`, no domínio de papéis e permissões. O
papel `admin` de todo estabelecimento — novo ou existente — opera os módulos
pelas 26 permissões explícitas.

**X1 — Administração indevida entre estabelecimentos.** Antes da correção, a
`TenantPolicy` usava `create-role` como único critério, verificado no estabelecimento ativo, e o
`TenantController` consulta `Tenant::query()` sem filtro. O admin de qualquer
estabelecimento lista, abre, edita — inclusive o status —, arquiva e restaura os
estabelecimentos de todos os outros, e cria novos, tornando-se admin deles. Não
existia administrador da plataforma, e todo admin recebe `create-role`, tanto pelo
`AuthorizationSeeder` quanto pelo provisionamento de novo estabelecimento. O teste
`test_listagem_de_tenants_renderiza_corretamente` afirma esse comportamento: o
admin vê um estabelecimento que não é o seu. Após
`faf3c4ed8769a612d3eec19d8a248175c300efcf`, somente Platform Admin pode executar
essas operações; tenant admin e usuário apenas com `create-role` recebem 403.

**E1 — Escalada por `update-role`.** Em `POST /roles/{role}/permissions`,
`RolePolicy::update` exige só `update-role`, e `SyncRolePermissionsRequest` valida
apenas que as permissões existem no estabelecimento ativo. Antes da correção, o
controller substituía o conjunto sem compará-lo às permissões de quem executa, e
não havia proteção do próprio papel, de papéis de sistema (existe só em `delete`,
que não tem rota) nem do último administrador. Quem tinha `update-role` concedia
`create-role` a qualquer papel, inclusive ao próprio. No seed só `admin` tem
`update-role`; o vetor se abria com qualquer papel personalizado que a recebesse.

**Status atual do E1.** Corrigido em `2f1f6ea`: quem usa `update-role` não
sincroniza as permissões do próprio papel e não adiciona nem retira de outro papel
permissão que não possui no estabelecimento daquele papel. A proteção vem da
autoridade, não do nome: vale para o `admin` e para papéis personalizados. Não há
regra de último administrador. Detalhe em
[Contenção da delegação](#contenção-da-delegação).

**E2 — Escalada por `manage-users`.** *Vulnerabilidade crítica.* O papel padrão
`manager` recebe `manage-users` no seed. `UserPolicy::create` e `update` exigem
apenas essa permissão, e `StoreUserRequest` e `UpdateUserRequest` aceitam qualquer
papel existente no estabelecimento ativo. Antes da correção, `UserController::update`
só impedia que a pessoa desativasse o próprio acesso — não que alterasse os
próprios papéis —, e nada verificava se quem executava possuía as permissões do
papel atribuído.

```
manager
↓
manage-users
↓
atribui o papel admin a si mesmo
↓
passa a possuir permissões administrativas — inclusive create-role; após a
correção de X1, isso não concede administração de plataforma
```

Também era possível criar uma segunda conta já com o papel `admin`.

**Status atual do E2.** Corrigido em `2849c10`: quem usa `manage-users` só administra
uma pessoa se dominar a autoridade dela no estabelecimento — a atual e, ao atribuir
papéis, a desejada — e não altera o próprio conjunto de papéis. Isso fecha também a
tomada de conta de admin exclusivo pela senha ou pelo e-mail, encontrada na
caracterização ampliada; a retirada, o arquivamento e a suspensão de quem tem mais
autoridade; e a atribuição de papéis personalizados privilegiados. As regras do E3
continuam valendo e se somam às do E2. Detalhe em
[Administração de usuários por autoridade](#administração-de-usuários-por-autoridade).

**E3 — Administração local de identidade global.** *Vulnerabilidade crítica.*
Desde a F1.8, `User` é uma identidade global, com vínculos em vários
estabelecimentos. Mas `manage-users`, permissão local de um estabelecimento,
altera atributos globais de qualquer pessoa vinculada ao estabelecimento ativo:

- **senha:** a redefinição exibe a senha temporária a quem executou;
- **e-mail:** vale para o login em todos os estabelecimentos;
- **`account_status`:** conta inativa fica bloqueada em todos os estabelecimentos;
- **identidade existente:** cadastrar um e-mail que já existe reativa e renomeia
  a pessoa globalmente.

Assim, quem tem `manage-users` no Tenant A assume ou bloqueia a conta de uma
pessoa que também tem vínculo no Tenant B, e herda os papéis dela em B. O seed
torna o cenário concreto: o administrador de teste fixo é `admin` em todos os
estabelecimentos. A UX definitiva de administração de identidade fica para a
implementação; o requisito de segurança é:

> Autoridade local de um tenant não pode, por consequência indireta, conceder
> acesso ou comprometer os vínculos de uma identidade em outros tenants.

**Status atual do E3.** Corrigido em `d6fdaac`: `manage-users` só altera os dados
da identidade global — e só redefine a senha, arquiva ou restaura a identidade —
quando a pessoa pertence exclusivamente ao estabelecimento e não é Platform Admin.
Vínculo e papéis continuam locais, e o cadastro com e-mail existente não renomeia,
não reativa nem restaura a identidade. Detalhe em
[Proteção da identidade global](#proteção-da-identidade-global).

**E7 — Policy avalia entidade e permissão em estabelecimentos diferentes.**

- O vínculo é validado contra o estabelecimento **da entidade**
  (`canAccessTenant($entidade->tenant_id)`), que pode ser o Tenant B;
- a permissão é consultada no estabelecimento **ativo**, que pode ser o Tenant A;
- no painel web, o `SubstituteBindings` do grupo `web` carrega o model da rota
  antes de `auth.web` resolver o `TenantContext`, então o `TenantScope` ainda não
  filtra;
- nenhum controller web confere o `tenant_id` da entidade carregada.

Com vínculo em A e B, uma pessoa privilegiada em A pode obter autorização para
operar uma entidade de B usando as permissões de A. Afeta potencialmente os
fluxos com route binding de Product, Category, Customer, Order, Automation,
Company e Role. Em Role, a gravação usa o estabelecimento ativo e não concede
nada em B, mas a autorização passa. Desde `2f1f6ea`, a contenção do E1 avalia a
autoridade no estabelecimento do papel, e não no ativo, o que estreita esse
caminho; ainda assim a Policy continua autorizando e o controller continua
gravando as linhas com o estabelecimento ativo — essa divergência pertence ao E7.
A confirmação inicial veio da leitura do código e da ordem de middleware do
framework. O baseline executável do SEC-04 posteriormente reproduziu o vetor por
testes HTTP reais, que permaneceram vermelhos até a correção do E7.

Era obrigatório corrigir antes do SEC-01, porque o SEC-01 aplicará essas mesmas
Policies à API.

**Status atual do E7.** Corrigido em `7433c5b`: `AutenticarWeb` passou a ter
prioridade antes de `SubstituteBindings`, então o `TenantContext` já está resolvido
quando o route binding carrega a entidade, e o `TenantScope` filtra pelo
estabelecimento ativo. Entidade de outro estabelecimento retorna 404. Detalhe na
Implementação 6, acima.

**Consequência.** Antes das correções, X1, E3 e E7 atravessavam a fronteira entre
estabelecimentos — pela gestão de estabelecimentos, pela identidade compartilhada e
pelas Policies. X1, E3 e E7 estão corrigidos — o E7 em `7433c5b`. E1 e E2 levavam a
poder administrativo indevido dentro do estabelecimento e foram corrigidos em
`2f1f6ea` e `2849c10`.

#### Causa raiz

1. **Antes de X1, sem separação entre autoridade de plataforma e de tenant.** O RBAC da F1.5
   existe só dentro de um estabelecimento. A F3.4 criou uma operação de
   plataforma — gerenciar estabelecimentos — sem criar esse nível.
2. **`create-role` como marcador improvisado de administrador.** Antes de X1, a `TenantPolicy`
   registra a decisão como provisória ("até F3.6"), e as Policies seguintes
   copiaram o padrão. O padrão saiu da `TenantPolicy` em `faf3c4e` e das Policies
   de negócio em `6c770dc`.
3. **Delegação de permissões e papéis sem contenção.** Sincronizar permissões e
   atribuir papéis só validavam "pertence ao estabelecimento ativo", nunca "quem
   executa pode delegar isso". A sincronização de permissões ganhou contenção em
   `2f1f6ea`, e a administração de usuários e a atribuição de papéis, em
   `2849c10`.
4. **Identidade global administrada por permissão local.** A F1.8 tornou a pessoa
   global; a F3.5 manteve `manage-users` com poder sobre credenciais e status
   globais. Corrigido em `d6fdaac`.
5. **Vínculo e permissão avaliados em contextos de tenant diferentes.** As
   Policies presumem que a entidade pertence ao estabelecimento ativo, garantia
   que o `TenantScope` não oferece durante o route binding do painel.
6. **Testes que cristalizavam parte do comportamento incorreto na baseline.** As fixtures de
   `TenantManagementTest` e `CompanyAndRolesManagementTest` tratam `create-role`
   como sinônimo de admin, e nove testes de `TenantManagementTest` afirmam como
   correta a gestão de estabelecimentos alheios.

#### Decisão arquitetural — Platform Admin

**Implementado no SEC-04: marcador explícito de Platform Admin na identidade global.**

```
User
├── identidade global
├── status
└── marcador protegido de Platform Admin
```

O atributo técnico implementado é `is_platform_admin`; `User::isPlatformAdmin()`
é o único ponto semântico de consulta. Ele atende aos requisitos:

- não depende de tenant;
- não usa papel de tenant nem `create-role`;
- não é mass assignable;
- não é alterável pelas telas normais de usuários;
- não é concedido por meio de `manage-users`;
- tem um único ponto de consulta no domínio/model;
- é testado explicitamente;
- somente Platform Admin executa operações de administração da plataforma, como
  `/tenants`.

É a solução atual, e foi escolhida por ser evolutiva.

**Alternativas avaliadas.** Uma camada própria de RBAC de plataforma foi adiada
(ver evolução futura). Tornar globais os papéis e permissões atuais foi
descartado: quebraria o invariante abaixo e permitiria que uma permissão global
satisfizesse checagens de estabelecimento — a mesma classe de defeito que o
SEC-04 corrige.

#### Evolução futura

O marcador de Platform Admin não é necessariamente a arquitetura final. Quando a
LucraOne precisar de perfis distintos de operação da plataforma — suporte,
onboarding, financeiro, operações, administração de integrações, suporte fiscal,
administração global —, o projeto deverá avaliar uma camada própria de RBAC de
plataforma. Essa camada não faz parte do SEC-04, e os papéis e permissões atuais
não serão transformados em globais. O invariante se mantém:

> Roles e Permissions de negócio continuam pertencendo obrigatoriamente a um
> tenant.

#### Remoção do coringa `create-role`

O SEC-04 removeu `create-role` como bypass administrativo das Policies de
domínio, do Gate `view-reports` e do comando `relatorios:enviar-resumo`, em duas
etapas. A primeira correção (`faf3c4e`) removeu seu uso como autoridade de
plataforma na `TenantPolicy`; a segunda (`6c770dc`) removeu os bypasses nos
módulos:

- `ProductPolicy`, `CategoryPolicy`, `InventoryPolicy`, `StockLevelPolicy`,
  `OrderPolicy`, `CustomerPolicy`, `CompanyPolicy` e `AutomationRulePolicy`;
- Gate `view-reports` e resumo de vendas.

O uso de `create-role` no próprio domínio de roles e permissions continua sendo
tratado separadamente. Com a remoção dos bypasses:

```
manage-products     → gerencia produtos
manage-inventory    → gerencia estoque
manage-sales        → gerencia vendas
manage-customers    → gerencia clientes
manage-companies    → gerencia empresas
manage-automations  → gerencia automações
view-reports        → visualiza relatórios
```

`create-role` passa a significar somente a capacidade de criar papéis, caso essa
funcionalidade venha a existir, e continua no catálogo de permissões. Antes de
remover o coringa, os admins de estabelecimentos provisionados pelo painel
precisavam receber explicitamente as permissões de que dependiam dele; isso foi
feito no mesmo commit, pela matriz explícita de 26 permissões, pelo
provisionamento corrigido e pelo data fix dos admins existentes.

#### Contenção da delegação

O SEC-04 deve impedir que uma pessoa conceda poder superior ao que possui:

- `update-role` não pode conceder permissões que o ator não esteja autorizado a
  delegar — ✅ atendido em `2f1f6ea`;
- `manage-users` não pode ser usado para autoelevação — ✅ atendido em `2849c10`;
- `manage-users` não pode atribuir papel cujo poder exceda a autoridade delegável
  do ator — ✅ atendido em `2849c10`;
- nenhum caminho de delegação pode levar a Platform Admin;
- papéis administrativos relevantes devem ser protegidos;
- considerar guarda contra lockout e contra a remoção do último administrador;
- mudanças de autorização continuam tenant-aware.

Não é necessário implementar hierarquia complexa de papéis.

**Implementado para `update-role` em `2f1f6ea`.** Sincronizar as permissões de um
papel é delegar autoridade — ao conceder e ao retirar. A contenção, isolada em
`PermissionDelegationService`, compara o estado atual do papel com o desejado e
avalia só o que muda:

| Permissão | Exigência |
|---|---|
| adicionada — desejada e ausente hoje | o ator precisa possuí-la |
| removida — presente hoje e não desejada | o ator precisa possuí-la |
| preservada — presente e reenviada | nenhuma: não é nova delegação |

- **próprio papel:** quem usa `update-role` não sincroniza as permissões de um papel
  que tenha no estabelecimento, qualquer que seja a mudança. Isso protege contra
  autoelevação, lockout e alteração ambígua da própria autoridade. Não é proteção
  do último administrador, que não foi implementada;
- **estabelecimento do papel:** as permissões do ator são as que ele efetivamente
  possui no estabelecimento do papel, informado explicitamente, e não no
  estabelecimento ativo;
- **antes da gravação:** a mudança inteira é validada antes de alterar
  `role_permission`; uma recusa não deixa gravação parcial;
- **autoridade, não nome:** a proteção não depende do nome `admin` e vale também
  para papéis personalizados privilegiados. Não foi criada hierarquia
  `admin > manager > user` nem ranking de papéis.

Continuam permitidos delegar a outro papel uma permissão que o ator possui e o
admin editar um papel quando a mudança está dentro da sua autoridade. Uma permissão
reenviada sem alteração não bloqueia a operação, mesmo fora da autoridade do ator —
é o que permite ao admin editar um papel que tenha, por exemplo,
`manage-assigned-branches`, que ele não possui. Até `cbba727` esse era o caso do
`manager` padrão; desde então o controle positivo do E1 usa um papel personalizado.

**Limites conhecidos.** A validação ocorre antes da transação que grava
`role_permission`, então edições concorrentes do mesmo papel podem se intercalar —
registrado como dívida em [Outras pendências de qualidade](#outras-pendências-de-qualidade).
O serviço avalia a autoridade no estabelecimento do papel, mas o controller ainda
grava pelo estabelecimento ativo; essa divergência pertence ao E7.

**Relação com o E2.** Desde `4909137`, a autoridade do ator no E1 vem de
`TenantAuthority`, o mesmo cálculo usado pelo E2, sem mudança de semântica. As
regras são diferentes: o E1 avalia o delta de permissões de um papel; o E2 avalia a
dominância sobre a pessoa inteira.

**Pré-requisito do E2 — matriz dos papéis padrão.** A auditoria de 2026-09-10
constatou que a matriz não era hierárquica: o `admin`, com 26 permissões, não
tinha `manage-assigned-branches` nem `view-assigned-branches`, presentes em
`manager` e `user`, e o `manager` não tinha `view-assigned-branches`, presente em
`user`. Essas permissões só são consultadas pela `BranchPolicy`, que não está
registrada. Uma contenção por subconjunto de permissões impediria o admin de gerir
managers e users, então a matriz precisava ser decidida antes do E2. Decidido e
corrigido em `cbba727`: `manager` e `user` deixaram de receber essas duas
permissões, que continuam no catálogo, e o admin manteve as 26. Detalhe na
Implementação 5.

#### Administração de usuários por autoridade

**Implementado em `2849c10`.** A `UserPolicy` continua decidindo quem entra nos
fluxos de `manage-users`. O `UserAdministrationGuard` decide se o ator pode exercer
a operação sobre aquela pessoa, naquele estabelecimento. Dominar é conter: as
permissões dos papéis envolvidos precisam estar todas entre as do ator, e autoridade
vazia é sempre dominada.

| Operação | Exigência |
|---|---|
| cadastrar | o ator domina a autoridade dos papéis pedidos |
| editar outra pessoa | o ator domina a autoridade atual dela e a dos papéis pedidos |
| editar a si mesmo | o conjunto de papéis só pode ser reenviado igual |
| arquivar | o ator domina a autoridade atual da pessoa |
| redefinir senha | o ator domina a autoridade atual da pessoa |

- **autoridade atual inteira:** o mesmo endpoint altera credenciais, vínculo e
  papéis, então um papel preservado que o ator não domina não é exceção — diferente
  do E1, em que a permissão preservada não conta como delegação. Mudar só o status
  do vínculo também exige dominar a pessoa;
- **autoridade latente:** a autoridade do alvo considera os papéis em qualquer
  status de vínculo; a do ator exige conta e vínculo ativos, e é a união das
  permissões de todos os seus papéis no estabelecimento;
- **indivisível:** um papel que não existe no estabelecimento, ou que o ator não
  domina, recusa a operação inteira. A recusa acontece antes da primeira gravação —
  no cadastro, dentro da transação e depois das regras do E3 —, sem identidade,
  vínculo ou papel parciais;
- **autoridade, não nome:** vale para o `admin` e para papéis personalizados, sem
  ranking de papéis;
- **E3 continua separado:** o E3 protege a identidade global e é avaliado antes; o
  E2 protege a autoridade do alvo no estabelecimento. As duas verificações se somam.

A recusa volta para a tela com uma mensagem genérica, que não revela a autoridade
de ninguém. A autoridade vem de `TenantAuthority`, que calcula no estabelecimento
informado explicitamente, sem `TenantContext`.

**Fora do E2.** O `restore` não passou pela contenção: o arquivamento já retira os
papéis, então a identidade restaurada pelo painel volta sem autoridade. Também
ficaram fora o último administrador, o vínculo de Platform Admin sem papéis, o
formulário que lista todos os papéis, a concorrência entre validação e gravação, o
payload com papel repetido e cache (ver PERF-01). As dívidas estão em
[Outras pendências de qualidade](#outras-pendências-de-qualidade) e nos achados fora
do escopo.

#### Proteção da identidade global

**Implementado em `d6fdaac`.** Uma permissão local como `manage-users` não concede
mais autoridade irrestrita sobre a identidade global. A correção separa o que
pertence a cada lado:

| Identidade global | Do estabelecimento |
|---|---|
| nome, e-mail, senha, status da conta, arquivamento e restauração da identidade | vínculo, status do vínculo e papéis |

A autoridade local continua administrando o vínculo, o status do vínculo e os papéis
do seu estabelecimento. Os dados da identidade global só são alterados localmente
quando a pessoa pertence **exclusivamente** àquele estabelecimento e não é Platform
Admin:

- **identidade compartilhada:** qualquer vínculo com outro estabelecimento protege a
  identidade, seja qual for o status dele — `ACTIVE`, `INVITED`, `INACTIVE` ou
  `SUSPENDED`. Alterar nome, e-mail, senha ou status da conta é recusado, e não
  ignorado; reenviar o valor atual não conta como alteração, então editar só o
  vínculo ou os papéis continua possível. A redefinição de senha é recusada, e
  arquivar desativa apenas o vínculo atual;
- **Platform Admin como alvo:** a autoridade local não altera nome, e-mail, senha ou
  status da conta do Platform Admin, não redefine sua senha e não arquiva nem
  restaura sua identidade — mesmo que ele tenha um único vínculo e nenhum papel no
  estabelecimento;
- **cadastro com e-mail:**
  - identidade nova: criação normal;
  - identidade ativa existente: é vinculada ao estabelecimento, e os dados globais
    existentes são preservados;
  - identidade inativa: a autoridade local não a reativa, e o cadastro é recusado
    sem vínculo parcial;
  - identidade arquivada: a autoridade local não a restaura, e o cadastro é recusado
    sem vínculo parcial.

A identidade exclusiva de um estabelecimento continua integralmente administrável
por ele, inclusive a redefinição de senha. O modelo da F1.8 foi preservado, e
`is_platform_admin` continua global, fora do mass assignment e fora do fluxo de
`manage-users`. Quais papéis podem ser atribuídos é assunto do E2, corrigido em
`2849c10` — ver [Administração de usuários por autoridade](#administração-de-usuários-por-autoridade).

**Fora do E3.** O self-service de identidade global ("Minha Conta" / "Meu Perfil"),
com autoridade diferente de `manage-users`, fica como evolução futura. Até lá,
`users.update` continua sendo um fluxo administrativo do estabelecimento, sem
exceção para a própria pessoa. Não é bloqueador do SEC-04.

#### Contexto de estabelecimento nas Policies

Invariante esperado depois do SEC-04:

```
entidade autorizada
+
tenant da entidade
+
tenant usado para consultar permissões
```

devem representar o mesmo contexto de autorização, exceto em operações
explicitamente classificadas como administração da plataforma. Uma pessoa com
vínculo em A e B não pode usar

```
permissão privilegiada em A
+
entidade pertencente a B
```

para autorizar operação em B.

#### Critérios de aceite

O SEC-04 só é `Resolvido` quando testes demonstrarem, no mínimo:

**Platform Admin** — ✅ atendido em `faf3c4e`

- admin de tenant recebe 403 nas operações de `/tenants`;
- Platform Admin executa as operações autorizadas de `/tenants`;
- possuir apenas `create-role` não concede acesso de plataforma.

**`create-role`** — ✅ atendido em `6c770dc`

- usuário somente com `create-role` não usa como bypass as Policies de Products,
  Categories, Inventory, Sales, Customers, Companies, Automation e Reports;
- `relatorios:enviar-resumo` não aceita `create-role` como substituto de `view-reports`.

**E1** — ✅ atendido em `2f1f6ea`

- `update-role` não permite adquirir permissões não delegáveis;
- possuir `update-role` não basta para adquirir `create-role`;
- `update-role` não permite retirar de outro papel permissões não delegáveis;
- quem usa `update-role` não sincroniza as permissões do próprio papel.

**E2** — ✅ atendido em `2849c10`

- `manage-users` não permite autoatribuição do papel admin;
- `manage-users` não permite atribuir poder acima da autoridade delegável;
- `manage-users` não retira, arquiva, suspende nem redefine a senha de quem tem
  autoridade acima da do ator;
- ninguém altera o próprio conjunto de papéis;
- uma recusa não deixa identidade, vínculo ou papel parciais.

**E3** — ✅ atendido em `d6fdaac`

- administração local não compromete credenciais nem status global de identidade
  vinculada a outros tenants.

**E7** — ✅ atendido em `7433c5b` — cenário obrigatório, cobrindo os módulos afetados:

```
Usuário:  admin no Tenant A, viewer no Tenant B, Tenant A ativo
Entidade: pertence ao Tenant B
```

O usuário não pode visualizar nem alterar a entidade de B usando permissões que
possui somente em A. Coberto por `CrossTenantPolicyContextSecurityTest`: 17 PASS e
75 assertions, em Product, Category, Customer, Order, Automation, Company e Role.

**Regressão**

- admins legítimos mantêm as funcionalidades de negócio que devem possuir;
- admins provisionados pelo painel recebem explicitamente as permissões
  necessárias;
- o isolamento multi-tenant existente continua funcionando;
- fixtures que dependiam de `create-role` como sinônimo de admin são corrigidas,
  não contornadas.

#### Relação com o SEC-01

O SEC-04 vem primeiro e, agora concluído, o SEC-01 pode assumir:

- Policies sem bypass por `create-role`;
- contexto de tenant consistente entre entidade e permissão;
- delegação administrativa protegida;
- Platform Admin separado de admin de tenant;
- testes de API com 403 representando o modelo correto.

Assim o SEC-01 aplica as Policies aos 7 controllers e 34 rotas sem propagar o
modelo vulnerável atual.

**Por que bloqueia.** X1, E3 e E7 são falhas de isolamento entre
estabelecimentos — os três já corrigidos —, da mesma classe que o SEC-01 e o
SEC-03, e E1 e E2 levavam até elas — ambos já corrigidos. O SEC-01 vai ligar essas
Policies à API, e a F2.6 vai criar uma Policy para proteger credenciais de
terceiros, que herdaria o padrão atual.

### SEC-05 — SendEmailAction

**Média · Pendente · Bloqueia F2.6: Recomendado** — Origem: F2.5 (`3fd8f3b`,
2026-09-09)

**Problema.** `SendEmailAction` aceita em `action_config.recipients` até 1.000
caracteres de endereços separados por vírgula, ponto e vírgula ou quebra de
linha, e só filtra o formato. Assunto e mensagem são escritos pelo usuário e
interpolados com os dados do gatilho. Não há restrição a usuários ou contatos do
estabelecimento, limite de destinatários, limite de envios por regra ou período,
nem registro voltado a detectar abuso.

**Quem pode configurar.** Quem tem `manage-automations`, verificado na API e no
painel. Até `6c770dc`, `create-role` também servia como coringa.

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
`hasPermission()` aceita `$tenantId` explícito, e `relatorios:enviar-resumo` verifica
permissões estabelecimento por estabelecimento: um cache indexado só por usuário
devolveria, para quem tem vínculo em mais de um estabelecimento, as permissões
do estabelecimento errado.

**Por que Não.** É desempenho, sem efeito em segurança, e com o volume atual não
há medição de impacto que justifique segurar a F2.6.

---

### Ordem recomendada

```
SEC-04 — create-role              ✅ RESOLVIDO
  ├─ Platform Admin / X1          ✅
  ├─ bypass create-role           ✅
  ├─ E3                           ✅
  ├─ E1                           ✅
  ├─ E2                           ✅
  └─ E7                           ✅
        ↓
SEC-01 — API Authorization        🔴 próximo bloqueador
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
SEC-04 muda o significado e o alcance dessas Policies: `create-role` era coringa
em várias delas e a gestão de estabelecimentos quebrava o isolamento entre
clientes — ambos já corrigidos —, as escaladas por `update-role` e por
`manage-users` foram contidas em `2f1f6ea` e `2849c10`, e a avaliação da permissão
num estabelecimento diferente do da entidade foi fechada em `7433c5b`.
Espalhar as Policies atuais pela API antes de corrigi-las levaria esses defeitos
para a API e obrigaria a refazer o SEC-01 e os seus testes.

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

**Auditoria pré-implementação do SEC-04 (2026-09-10).** Feita antes de iniciar a
correção, só por leitura de código, testes e histórico. Ampliou o SEC-04 sem
criar novo ID:

- confirmou o coringa `create-role`, o X1 e o E1;
- encontrou três vetores novos: E2 (escalada por `manage-users`), E3
  (administração local de identidade global) e E7 (entidade e permissão avaliadas
  em estabelecimentos diferentes);
- corrigiu a afirmação anterior de que a gestão de estabelecimentos era "o único
  ponto conhecido em que um cliente alcança dados e operações de outro": E3 e E7
  também atravessam estabelecimentos;
- constatou que estabelecimentos criados pelo painel dependem do coringa para
  vendas, clientes, relatórios e automações — corrigido em `6c770dc`;
- registrou a decisão por um marcador explícito de Platform Admin;
- confirmou que os fluxos de E2, E3 e E7 surgiram depois da F1.7, com a F1.8 e as
  telas de 2026-08-16. A F1.7 permanece como está.

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

## Cadastro mestre de produtos — preparação para o PDV

Trilha funcional aberta em 2026-09-22 a partir da necessidade de um cliente real
de varejo alimentício: massa de pastel, massa de macarrão, massas frescas
vendidas a peso, refrigerantes e outros produtos embalados, vendidos por unidade
ou por peso e comprados em caixa ou fardo. O cliente precisa começar a cadastrar
agora, e o objetivo é que esse cadastro seja reaproveitado — sem recadastro —
por estoque, vendas, PDV, scanner, embalagens e fiscal.

O que esta trilha é, e o que não é:

- **Não libera a F2.6.** É uma evolução funcional paralela. A F2.6 continua
  bloqueada, e SEC-01, SEC-02 e SEC-03 mantêm o status e a prioridade das
  [pendências pré-F2.6](#pendências-bloqueadoras-pré-f26).
- **Não é uma fase nem uma sprint.** Usa IDs próprios (`PM-*`), como as
  pendências usam `SEC-*`, e o placar de fases não muda.
- **Não antecipa PDV, fiscal nem scanner.** Prepara apenas o cadastro mestre
  para eles; PDV, fiscal e sync continuam no [roadmap macro](<Roadmap Macro de Desenvolvimento — Plataforma SaaS Inteligente de Automação Comercial.md>).

### Acompanhamento da trilha

| ID | Etapa | Status | Depende de |
|---|---|---|---|
| PM-01 | Código de barras e unidade base | Concluído | — |
| PM-02A | Embalagens comerciais / Product Packages | Concluído | PM-01 |
| PM-02B | Entrada de estoque por embalagem | Concluído | PM-02A |
| PM-03 | Resolução exata por código de barras | Concluído | PM-02A |
| PM-04A | Quantidade coerente com a unidade | Planejado | PM-01 |
| PM-04B | Snapshot histórico do item | Planejado | PM-04A |
| PM-04C | Semântica de linhas para o futuro PDV | Planejado | PM-04B + desenho do PDV |
| PM-05 | Dados fiscais do produto | Futuro · antes da NFC-e/NF-e | — |

Como nas pendências, uma etapa só passa a `Concluído` com testes que provem a
entrega.

```
PM-01 — barcode + unit                ✅ CONCLUÍDO
        ↓
PM-02A — product_packages             ✅ CONCLUÍDO
        ├─ PM-02B — entrada por embalagem      ✅ CONCLUÍDO
        └─ PM-03 — resolução exata de barcode  ✅ CONCLUÍDO
        ↓
PM-04A — quantidade coerente com a unidade   📋 PLANEJADO
        ↓
PM-04B — snapshot histórico do item          📋 PLANEJADO
        ↓
PM-04C — semântica de linhas para o PDV      📋 PLANEJADO · + desenho do PDV
        ↓
PM-05 — dados fiscais do produto      📋 FUTURO · antes da NFC-e/NF-e
```

### Decisão arquitetural — Produto comercial x embalagem

**Cada apresentação efetivamente vendida é um Product distinto.** Refrigerante
350 ml, 600 ml e 2 L são três Products, cada um com SKU, código de barras,
preço e estoque próprios.

**Caixa, fardo e multipack não são variantes.** São embalagens
(`ProductPackage`) associadas ao Product base, que continua sendo a unidade de
estoque e venda — a menor unidade efetivamente vendida.

**Motivação.** Inventory, Prices, Orders e Reports já são estruturados em torno
de `product_id`. Essa abordagem:

- preserva a arquitetura atual;
- evita refatorar Inventory e Orders;
- evita mover preço e estoque para um `variant_id`;
- permite evolução aditiva;
- mantém o estoque na menor unidade vendida.

**Alternativas avaliadas.** Product pai com variantes foi descartado: exigiria
mover preço, estoque e item de pedido para a variante, redesenhando os módulos
já entregues.

Na primeira versão, `ProductPackage` só é permitido para Product com
`unit = UN`.

### PM-01 — Código de barras e unidade base

**Concluído** — `588c6e4d3dca2e35822ee05980af50bb012d02ff` (2026-09-22)

Entregue, de forma aditiva:

- `products.barcode` — até 14 caracteres (GTIN), opcional, único por tenant;
- `products.unit` — unidade base de venda e estoque, `UN` ou `KG`, default `UN`;
- busca por código de barras no painel e na API, junto de SKU e nome;
- campos no formulário, no detalhe e na listagem (unidade na listagem, código de
  barras no detalhe);
- compatibilidade retroativa da API: `unit` é opcional na API e assume `UN`; no
  painel é obrigatória;
- dados existentes preservados: os produtos já cadastrados ficaram com `unit =
  UN` e código de barras nulo, sem backfill manual.

Decisões:

- o SKU continua sendo o código interno, e o código de barras não o substitui;
- o código de barras é opcional — produto sem EAN usa só o SKU;
- cada apresentação comercial vendida é um Product distinto;
- produto embalado é `UN` mesmo quando o nome traz peso ou volume. `unit`
  responde "o que significa quantidade = 1", e não o conteúdo da embalagem.

| Apresentação | Cadastro | `unit` |
|---|---|---|
| Massa de Pastel 500 g | Product | `UN` |
| Massa de Pastel 1 kg | outro Product | `UN` |
| Refrigerante 350 ml | Product | `UN` |
| Refrigerante 600 ml | outro Product | `UN` |
| Refrigerante 2 L | outro Product | `UN` |
| Massa fresca vendida por peso | Product | `KG` |

**Evidência na publicação.** 23 testes novos (15 de API e 8 de painel). A suíte
completa passou a ter 591 testes: 589 PASS, 0 FAIL, 2 risky preexistentes de
`SecurityAuditTest` e 1912 assertions. No banco local, os 60 produtos existentes
foram preservados, todos com código de barras nulo e `unit = UN`.

**Dívidas observadas e preservadas.** Encontradas na auditoria que precedeu o
PM-01 e deixadas fora da entrega:

| Dívida | Observação |
|---|---|
| `StorePriceRequest` valida `product_id` como UUID | Os ids são ULID, então `POST /api/v1/prices` recusa todo produto |
| SKU duplicado na API de produtos | Já registrado no [COR-01](#cor-01--soft-delete--unicidade) |
| Ajuste absoluto de estoque não aceita zero | A validação exige quantidade mínima de 0,001 |
| Relatório `itens_vendidos` soma `UN` e `KG` | Passa a importar quando houver venda a peso |
| Pint em `Product::company()` e `inventory()` | Nomes de classe completos, anteriores ao PM-01 |

### PM-02A — Embalagens comerciais / Product Packages

**Concluído** — `c4fafea0b7212bd10d28675becc3982e53fbff08` (2026-09-22) —
Depende de: PM-01

**Objetivo.** Representar caixas, fardos e multipacks associados ao Product
base, que continua sendo a unidade de estoque, venda, preço e pedido.

Entregue, de forma aditiva:

- tabela `product_packages`:

  | Campo | Observação |
  |---|---|
  | `id` | ULID |
  | `tenant_id` | explícito, como nas demais tabelas filhas do projeto |
  | `product_id` | Product base, com cascade na exclusão definitiva |
  | `name` | "Caixa 24", "Fardo 6" |
  | `barcode` | opcional, até 14 caracteres |
  | `factor` | inteiro: unidades base contidas na embalagem |
  | `created_at`, `updated_at` | — |

  com índice único `(tenant_id, barcode)` e índice `(tenant_id, product_id)`;
- model `ProductPackage` (`HasTenant`, `HasUlid`), `ProductPackageFactory` e a
  relação `Product::packages()`;
- bloco "embalagens" no detalhe do Product, com cadastro (nome, código de barras
  e fator) e remoção física. Sem edição nesta primeira versão: um erro se
  corrige removendo e cadastrando de novo;
- autorização pela `ProductPolicy::update`, sem permissão nova. Produto ou
  embalagem de outro tenant, ou embalagem de outro produto, retorna 404.

**Fator.** Inteiro, mínimo 2. Fator 1 ficou de fora porque representa outro
conceito — código de barras alternativo da mesma unidade —, fora do PM-02A.

**Unidade do produto.** Embalagem só pode ser cadastrada para Product com
`unit = UN`. Produto `KG` continua sem embalagem comercial nesta etapa, e a
unidade do produto nunca é alterada automaticamente.

**Código de barras — `BarcodeAvailable`.** Regra de validação reutilizável, usada
no cadastro de embalagem e nos quatro requests de Product (painel e API, criação
e edição). O código de barras passa a ser único dentro do tenant considerando
`products.barcode` **e** `product_packages.barcode`:

| Novo código em | Já usado em | Resultado |
|---|---|---|
| Product | Product | recusado (na edição, o próprio produto é ignorado) |
| Embalagem | Embalagem | recusado |
| Embalagem | Product — inclusive o produto base | recusado |
| Product | Embalagem | recusado |
| qualquer um | registro de outro tenant | permitido |

- o código de barras continua opcional, e vários registros sem código são
  permitidos;
- Product arquivado continua reservando o código, como no índice único;
- cada tabela continua protegida pelo próprio índice único `(tenant_id,
  barcode)`. Não existe constraint SQL única abrangendo as duas tabelas: essa
  parte é garantida pela aplicação.

**Limitação conhecida.** Como a unicidade entre `products` e `product_packages`
é garantida pela aplicação, existe uma janela teórica de concorrência em dois
cadastros simultâneos com o mesmo código. Risco aceito para o cadastro
administrativo atual; não é bloqueador.

| Product base | `unit` | Embalagem | `factor` |
|---|---|---|---|
| Coca-Cola 350 ml | `UN` | Caixa 24 | 24 |
| Refrigerante 2 L | `UN` | Fardo 6 | 6 |
| Massa de Pastel 500 g | `UN` | Caixa 10 | 10 |

**Escopo preservado.** O PM-02A não alterou Inventory, Orders, Price, fiscal, PDV
nem busca exata para scanner. Não houve API de embalagens, preço ou custo por
embalagem, status, soft delete nem `company_id`.

**Evidência na publicação.** 21 testes novos — 19 no painel e 2 de colisão de
código de barras na API de produtos —, cobrindo criação, remoção, isolamento
entre tenants, fator, Product `UN`/`KG`, colisão de código de barras entre as
duas tabelas, vários nulos, índice único, cascade e autorização. Os testes
específicos somam 94 testes: 94 PASS, 0 FAIL e 333 assertions. A suíte completa
passou a ter 612 testes: 610 PASS, 0 FAIL, 2 risky preexistentes de
`SecurityAuditTest` e 1991 assertions. No banco local, os 60 produtos foram
preservados e `product_packages` começou vazia.

**Dívidas preservadas.** As do PM-01 continuam como registradas acima, e a busca
por código de barras segue por `LIKE` até o PM-03. A única observação nova é a
janela de concorrência descrita em "Limitação conhecida".

### PM-02B — Entrada de estoque por embalagem

**Concluído** — `da291b12adb77d2e9b2ca50c71315ab8a5e8e725` (2026-09-22) —
Depende de: PM-02A

**Objetivo.** Permitir informar a quantidade em embalagens e convertê-la
automaticamente para a unidade base do Product.

Entregue no painel web:

- movimentação de estoque com `package_id` opcional. A `ProductPackage` é usada
  só como entrada para a conversão;
- a conversão acontece em `InventoryWebController::adjust`, **antes** da chamada
  ao `InventoryAdjustmentService`, que continua recebendo quantidade na unidade
  base. Inventory não conhece `ProductPackage`, e nem o serviço nem o schema de
  Inventory foram alterados.

```
quantidade informada × ProductPackage.factor = quantidade base enviada ao serviço
2 × Caixa 24                                 = 48 UN
```

**Tipos.** Embalagem vale para `in` e `out`. Não vale para `adjustment`, que
representa saldo absoluto, nem para `reservation` e `release`.

**Quantidade.** Com embalagem, `quantity` é o número de embalagens: inteiro e
maior ou igual a 1. Sem embalagem, o comportamento anterior permanece:

| Product | Quantidade informada | Estoque |
|---|---|---|
| `UN`, sem embalagem | 5 | 5 UN |
| `KG`, sem embalagem | 0,350 | 0,350 KG |
| `UN`, 2 × Caixa 24 | 2 | 48 UN |

**Produto KG.** Embalagem não pode ser usada em Product com `unit = KG`,
preservando a regra do PM-02A — inclusive quando a embalagem foi criada antes de
a unidade do produto mudar. Produto `KG` continua aceitando quantidade decimal
sem embalagem.

**Movimentação e saldo.** `InventoryMovement.quantity` e o saldo continuam
gravados na unidade base:

| Operação | Movimento | Saldo |
|---|---|---|
| saldo inicial | — | 10 |
| entrada de 2 × Caixa 24 | 48 | 58 |
| saída de 1 × Caixa 24 | 24 | 34 |

**Rastreabilidade no motivo.** Quando há embalagem, a conversão é incluída no
`reason` e o motivo informado pelo usuário é preservado:

```
entrada por embalagem: 2 × Caixa 24 = 48 UN · compra fornecedor
```

Sem embalagem, o `reason` mantém o comportamento anterior. O texto composto
respeita o limite de 255 caracteres da coluna.

**Painel.** Seletor de embalagens no formulário de movimentação, com as
embalagens apenas do Product escolhido, apenas para Product `UN` e apenas em
entrada ou saída; opção "unidade base — sem conversão"; texto de ajuda; e resumo
visual da conversão. O frontend só auxilia a UX: a conversão real é sempre
refeita no backend. Sem JavaScript, o seletor não aparece e o fluxo tradicional,
sem embalagem, continua funcionando.

**API.** A movimentação de estoque por embalagem **não** foi implementada na API.
Fica para o futuro, antes de integrações externas.

**Escopo preservado.** O PM-02B não alterou o schema de Inventory, o
`InventoryAdjustmentService`, a API, Orders, Price, Products, fiscal, PDV nem
scanner.

**Evidência na publicação.** 15 testes novos em `InventoryPackageEntryWebTest`,
cobrindo entrada e saída sem embalagem, `KG` decimal sem embalagem,
`adjustment` sem embalagem, entrada e saída por embalagem, fator, embalagem de
outro tenant, embalagem de outro produto, Product `KG`, `adjustment`,
`reservation` e `release` com embalagem, quantidade inválida, embalagem
removida, formulário, `InventoryMovement` e saldo final. As recusas conferem a
mensagem esperada. Testes específicos: 15 PASS, 0 FAIL e 63 assertions. A suíte
completa passou a ter 627 testes: 625 PASS, 0 FAIL, 2 risky preexistentes de
`SecurityAuditTest` e 2054 assertions.

**Dívidas preservadas.** As do PM-01 e do PM-02A continuam como registradas
acima — `StorePriceRequest` com UUID, SKU duplicado na API, ajuste absoluto sem
zero, relatório somando `UN` e `KG`, Pint preexistente, busca por `LIKE` e a
janela de concorrência na unicidade de código de barras entre as duas tabelas.
Continuam futuras a API de `ProductPackage` e a API de estoque por embalagem.

### PM-03 — Resolução exata por código de barras

**Concluído** — `c846d8d2e84fc5770a3f99a1794bd81e1a65cd2c` (2026-09-22) —
Depende de: PM-02A

**Objetivo.** Preparar o futuro scanner: receber um código de barras e resolver
o Product base e a quantidade na unidade base que aquele código representa.

Entregue, de forma aditiva:

- `ProductBarcodeResolver` (`Products/Application`) — resolução exata por
  código de barras, com o tenant recebido explicitamente e sem acoplamento a
  HTTP;
- `ResolvedProductBarcode` (`Products/Domain`) — resultado com o código lido, o
  Product base, a quantidade, a origem (`product` ou `package`) e a embalagem,
  quando houver;
- `BarcodeConflictException` (`Products/Domain/Exceptions`) — colisão entre
  produto e embalagem;
- endpoint autenticado `GET /api/v1/products/resolve-barcode/{barcode}`,
  protegido por `auth:sanctum` e pelo middleware `tenant`, como as demais rotas
  de Products. Não há permissão nova, e o endpoint não resolve nem antecipa o
  SEC-01.

**Fluxo de resolução.** O código recebido é procurado, por igualdade exata e no
tenant da requisição, em `Product.barcode` e em `ProductPackage.barcode`:

| Encontrado em | `source` | `quantity` | Product retornado |
|---|---|---|---|
| `Product.barcode` | `product` | 1 | o próprio produto |
| `ProductPackage.barcode` | `package` | `factor` da embalagem | o Product base |

O resultado é sempre `product_id` + quantidade na unidade base + origem +
unidade; a embalagem nunca é devolvida como produto separado.

**Disponibilidade.** Resolve apenas produto operacional, o mesmo padrão dos
seletores de estoque e de pedido do painel:

| Product | Resolve |
|---|---|
| `active` | sim |
| `inactive` | não |
| `discontinued` | não |
| arquivado (soft delete) | não |

`ProductPackage` não tem status e herda a disponibilidade do Product base.

**Produto KG.** Product `unit = KG` com código de barras comum resolve
normalmente, com quantidade 1 e unidade `KG`. O PM-03 não interpreta código de
balança, peso embutido nem GTIN de quantidade variável; esses cenários ficam
para etapa futura.

**Busca manual.** `/products/search/{query}` continua usando `LIKE` para busca
manual e não foi substituída. O PM-03 é uma capacidade separada, específica para
resolução de código de barras.

**Respostas.**

| Caso | HTTP |
|---|---|
| código resolvido | 200 |
| código inexistente | 404 |
| código de outro tenant | 404 |
| produto indisponível | 404 |
| código com mais de 14 caracteres | 422 |
| colisão entre produto e embalagem | 409 |

Os três casos de 404 usam a mesma mensagem — "código de barras não encontrado."
—, sem revelar a existência de produto indisponível ou de outro tenant. Não foi
introduzida validação de apenas dígitos, preservando a decisão do PM-01.

**Colisão histórica.** Se o mesmo código existir em `products` e em
`product_packages` do mesmo tenant, o resolver não escolhe um dos dois em
silêncio: lança `BarcodeConflictException`, e a API responde 409. Isso torna
visível uma inconsistência histórica ou de concorrência. A proteção detecta o
problema na leitura, mas não elimina a janela teórica de concorrência
documentada no PM-02A.

**Desempenho.** Igualdade exata (`where barcode = ?`) com filtro de tenant, sem
`LIKE`, sobre colunas que já têm índice único por tenant. Não foi criado cache.

**Escopo preservado.** O PM-03 não alterou Inventory, Orders, Price, PDV,
fiscal, migrations nem scanner físico. Não há movimentação de estoque, criação
de pedido, alteração de preço nem interpretação de balança.

**Evidência na publicação.** 15 testes novos em
`ProductBarcodeResolutionApiTest`, cobrindo código de Product e de
ProductPackage, quantidade 1 e quantidade igual ao fator, isolamento entre
tenants, código parcial que não resolve, rota sem conflito com `show` e
`search`, Product `inactive`, `discontinued` e arquivado, embalagem de Product
indisponível, código inválido, Product `KG`, colisão com 409, autenticação e o
resolver chamado diretamente, sem HTTP. Todo 404 esperado confere a mensagem do
resolver. Testes específicos: 15 PASS, 0 FAIL e 78 assertions. A suíte completa
passou a ter 642 testes: 640 PASS, 0 FAIL, 2 risky preexistentes de
`SecurityAuditTest` e 2132 assertions.

**Dívidas preservadas.** Continuam como registradas no PM-01, PM-02A e PM-02B:
`StorePriceRequest` com UUID, SKU duplicado na API, ajuste absoluto sem zero,
relatório somando `UN` e `KG`, Pint preexistente, janela de concorrência na
unicidade de código de barras entre as duas tabelas, API de `ProductPackage` e
API de estoque por embalagem. Observadas no PM-03 e também preservadas: o pedido
pela API não confere o status do Product; EAN alternativo da mesma unidade
(fator 1) e código de balança continuam futuros.

### PM-04 — Regras de quantidade para PDV (dividido)

Planejado originalmente como etapa única, dependente do PM-01. A auditoria
pré-implementação (2026-09-23, sobre `9f955ba`) encontrou três
responsabilidades diferentes:

1. regra de quantidade conforme `Product.unit`;
2. snapshot histórico do `OrderItem`;
3. semântica de linhas para o futuro PDV.

Por isso o PM-04 foi dividido em PM-04A, PM-04B e PM-04C, na mesma trilha. Não
é uma fase nova, e a trilha F2.x não muda. O PM-05 continua independente desta
divisão.

**Estado atual encontrado na auditoria.**

- `order_items.quantity` é `decimal(14,3)`; `unit_price` e `total` são
  `decimal(14,2)`;
- `OrderItem` guarda snapshot só de `sku` e `name`. Não há `unit` nem `barcode`;
- API (`StoreOrderRequest`) e painel (`StoreWebOrderItemRequest`) validam
  `quantity` só como `numeric|min:0.001`: Product `UN` aceita 1,5 hoje;
- `OrderService::addItem` é o caminho comum de API e painel. Ele converte para
  `float`, soma com a linha existente do mesmo Product e grava por
  `updateOrCreate`, apoiado no índice único `(order_id, product_id)`;
- Sales passa `quantity` direto para `InventoryAdjustmentService`, em
  `reservation`, `release` e `out`, sem conversão nem arredondamento;
- o total da linha é `round(quantity × unit_price, 2)` em `float`. 0,350 ×
  R$ 20,00 resulta corretamente em R$ 7,00;
- não havia teste de quantidade decimal, Product `KG` em pedido, duas linhas do
  mesmo Product nem snapshot histórico.

#### PM-04A — Quantidade coerente com a unidade

**Planejado** — Depende de: PM-01

**Objetivo.** Garantir que a quantidade usada em pedidos seja coerente com
`products.unit`.

| `Product.unit` | Regra |
|---|---|
| `UN` | inteira, mínimo 1; quantidade fracionada recusada |
| `KG` | decimal, maior que zero, no máximo 3 casas decimais |

**Onde a regra mora.**

- API e painel compartilham a mesma regra;
- `OrderService` continua como guarda autoritativo, porque é o caminho comum de
  API, painel e futuro PDV;
- a validação HTTP reutiliza uma regra compartilhada, para a mensagem sair no
  campo certo;
- o frontend só melhora a UX (por exemplo, `step` conforme a unidade);
- a regra não fica apenas no Model.

**Precisão.** A auditoria confirmou um risco real em Inventory: a comparação de
reservas usa `float`, e em PHP `0.1 + 0.2 > 0.3` resulta verdadeiro. Isso pode
recusar uma reserva válida de Product `KG` — por exemplo, saldo 0,300, reserva
existente 0,100 e novo pedido de 0,200. **O PM-04A só pode ser considerado
concluído se a reserva de estoque for compatível com a precisão decimal usada
por `quantity`.**

O PM-04A também deve:

- limitar `quantity` à escala de `decimal(14,3)`;
- recusar mais de 3 casas decimais;
- evitar overflow de `decimal(14,3)`;
- recusar notação científica (`1e3` passa em `numeric` hoje);
- preservar o comportamento correto de 0,350 `KG`.

**Fora do PM-04A:** migration de `order_items`, snapshot de `unit`, snapshot de
`barcode`, código de barras lido, `product_package_id`, remoção da unicidade
`(order_id, product_id)`, linhas repetidas, scanner, PDV e fiscal.

#### PM-04B — Snapshot histórico do item

**Planejado** — Depende de: PM-04A

**Objetivo.** Preservar no `OrderItem` o significado histórico de `quantity`.

**Decisão.** `order_items.unit` deverá ser avaliado e implementado como snapshot
da unidade vigente no momento da venda. `quantity` sozinha não diz se 0,350 é
`KG` ou se 2 é `UN`.

- `OrderItem` já guarda snapshot de `sku` e `name`;
- `unit` ainda não existe;
- ler `Product.unit` depois da venda tornaria o histórico mutável, porque a
  unidade do Product pode ser editada;
- relatórios e o futuro fiscal precisam da unidade histórica.

**Migration.** Provavelmente exigirá `order_items.unit`, com backfill dos
registros existentes a partir do `Product.unit` atual. Limitação conhecida: para
pedidos antigos, o backfill assume que a unidade do Product não mudou desde a
venda.

**Código de barras — decisão adiada.** Não está decidido que `barcode` entra no
PM-04B. São dois conceitos diferentes, não necessariamente iguais:

- `Product.barcode` — o código do Product base;
- código de barras efetivamente lido.

| Registro | Código |
|---|---|
| `Product.barcode` | 789AAA |
| `ProductPackage.barcode` | 789BOX |
| lido pelo scanner | 789BOX |

O PM-03 resolve os dois códigos para o mesmo Product base. A estratégia de
snapshot de código de barras será decidida junto com a semântica do futuro PDV,
no PM-04C ou em etapa equivalente.

#### PM-04C — Semântica de linhas para o futuro PDV

**Planejado** — Depende de: PM-04B e do desenho funcional do PDV

**Objetivo.** Definir como cada leitura ou adição aparece como linha de venda.

**Problemas atuais.**

- índice único `(order_id, product_id)`;
- `OrderService` consolida itens por `product_id`;
- `updateOrCreate` soma a quantidade;
- adicionar de novo o mesmo Product pode reprecificar a linha inteira com o novo
  preço unitário;
- a origem por embalagem é perdida.

**Exemplo.**

```
1 Coca-Cola 350 ml avulsa
+ 1 Caixa 24 Coca-Cola 350 ml

hoje        → 25 UN em uma única linha
futuro PDV  → pode exigir linhas distintas para preservar origem e apresentação
```

Esse não é necessariamente o desenho final: é uma questão a responder no
PM-04C.

**A avaliar:**

- remover a unicidade `(order_id, product_id)`;
- permitir linhas repetidas do mesmo Product;
- preservar o código de barras efetivamente lido;
- armazenar `product_package_id` quando fizer sentido;
- snapshot de `package_name`;
- snapshot de `factor`;
- cancelamento de uma linha específica;
- linhas separadas no cupom;
- comportamento da entrada manual versus scanner.

**ProductPackage.** Não é necessária no `OrderItem` para estoque, porque
Inventory trabalha na unidade base. Pode ser útil para cupom, auditoria, UX,
rastreabilidade e preço por embalagem. A decisão fica no PM-04C.

**PM-03 → Orders.** O PM-03 já fornece o Product base, `source`, `quantity`,
`unit` e a embalagem opcional, e o `OrderService` atual já consegue receber a
quantidade base. Hoje, porém, `source`, embalagem e código lido se perdem, e o
item é consolidado por `product_id`. Essas limitações pertencem ao PM-04C.

#### PM-04 — Dívidas e riscos preservados

**Relatórios.** A dívida já registrada no PM-01 continua: `SalesReportService`
soma `UN` e `KG` em `itens_vendidos`, e a tela e o e-mail exibem a quantidade
com 0 casas decimais e rótulo "un". Ela passa a ser funcionalmente relevante
quando o PM-04A permitir venda `KG` oficialmente. Não será corrigida agora.

Outros achados da auditoria, preservados sem correção:

| Achado | Observação |
|---|---|
| `quantity` com mais de 3 casas | Validação aceita; o MySQL arredonda ao gravar, e o total é calculado com o valor sem arredondar |
| `quantity` sem `max` | Overflow de `decimal(14,3)` pode gerar 500 em vez de 422 |
| Preço com mais de 2 casas | O total é calculado com o valor informado e pode divergir do `unit_price` persistido |
| `addItem` sem lock | Duas adições simultâneas do mesmo Product podem colidir no índice único ou perder uma soma |
| Product arquivado | `moveStock` depende do Product atual; arquivar um Product pode travar envio ou cancelamento de pedido confirmado |
| FK `order_items.product_id` com `CASCADE` | Um `forceDelete` futuro de Product apagaria linhas de pedidos históricos |
| `OrderItemFactory` | Gera quantidade fracionada para Product `UN` |

### PM-05 — Dados fiscais do produto

**Futuro · antes da NFC-e/NF-e**

Itens previstos no cadastro do produto:

- NCM;
- CEST, quando aplicável;
- origem;
- unidade tributável;
- GTIN tributável.

**Decisão.** CFOP não deve ficar fixo no Product: depende da operação — venda
dentro do estado, venda interestadual, entrada, devolução, transferência. CST,
CSOSN e alíquotas também não devem ser atributos fixos simples do Product:
dependem da regra da operação, do regime tributário e do contexto fiscal.

---

## Outras pendências de qualidade

Não bloqueiam a F2.6 e não receberam ID.

| Item | Estado |
|---|---|
| PHPStan (nível 4) | 11 erros — tipos de retorno de View e acesso a `$id` em união de tipos |
| Pint | 22 arquivos fora do padrão — imports não usados, ordenação |
| Automation Rules Guide | Não escrito — único item não entregue da F2.5 |
| `performance with growing data` | Intermitente sob carga: assertiva sensível a tempo, passa isolada. Reproduzida também no commit-base `6f932c8`, antes da remoção do coringa `create-role` — não é regressão do SEC-04. Não se reproduziu na suíte completa após `d6fdaac`, `2f1f6ea`, `2849c10` nem `7433c5b`, o que não indica correção |
| Concorrência na contenção do E1 e do E2 | A validação da contenção de `update-role` e de `manage-users` ocorre antes da gravação, sem bloqueio: edições concorrentes podem se intercalar. Registrada nas publicações do E1 (`2f1f6ea`) e do E2 (`2849c10`); não bloqueia nenhum dos dois e não foi corrigida |
| Último administrador | Um admin ainda pode retirar o papel ou arquivar outro admin, inclusive o último do estabelecimento. Fora do E2 |
| Restauração de identidade arquivada fora do painel | O `restore` não passa pela contenção do E2. Pelo painel, o arquivamento já retira os papéis; uma identidade arquivada por outro caminho que ainda tenha papéis voltaria com essa autoridade |
| Formulário de papéis | A tela de usuários lista todos os papéis do estabelecimento; o backend recusa os que o ator não domina. Melhoria de UX |
| Papel repetido no payload | Um payload HTTP com o mesmo id de papel repetido chega ao `syncRoles` sem normalização de gravação e pode violar a unicidade de `user_role`. Comportamento anterior ao E2; a UI não gera repetição, e o guard só normaliza para decidir |

> O item "`.env.testing` com `APP_KEY` versionada", que ficava nesta tabela, virou
> o SEC-06.

### Achados da auditoria do SEC-04 fora do escopo

Registrados em 2026-09-10 para tratamento posterior. Não são necessários para
provar nem para corrigir o SEC-04, e não receberam ID.

| Achado | Observação |
|---|---|
| Status `SUSPENDED`/`CANCELLED` do estabelecimento | Aparentemente sem efeito funcional completo: `Tenant::isActive()` não é chamado, e o resolver e o login não consultam o status |
| Arquivamento de estabelecimento | Pode derrubar o acesso dos usuários sem aviso adequado: o estabelecimento some do seletor e do login, e sessões abertas passam a receber 404 |
| `BranchPolicy` | Aparenta estar desconectada: não é registrada, usa permissões que não existem no seed e não há rotas de filiais. As permissões de filiais atribuídas que ela consulta saíram dos papéis padrão em `cbba727` e continuam no catálogo |
| Testes de integração vazios | Em `ApiIntegrationTest`, `test_rbac_admin_can_manage_roles`, `test_user_without_permission_gets_403` e `test_admin_workflow_manage_users_and_roles` só chamam `/up` e `assertTrue(true)` |
| Platform Admin depende de vínculo com estabelecimento | O login exige vínculo ativo, e `/tenants` passa por `auth.web`, que exige estabelecimento resolvido. O E3 protege a identidade global do Platform Admin, mas desativar o seu único vínculo ainda o tira do painel. O E2 também não impede: sem papéis no estabelecimento, a autoridade dele é vazia e qualquer um com `manage-users` a domina |

---

## Cobertura de testes por área — fotografia da auditoria

417 testes, contados por diretório de `tests/` na auditoria anterior. O estado
atual da suíte está registrado na evidência do SEC-04 acima.

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
