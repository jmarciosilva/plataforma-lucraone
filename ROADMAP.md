# Roadmap — LUCRAONE

**Atualizado:** 2026-10-10 · **PDV-BE-05 — Endpoints base do PDV concluído ✅ · trilha PDV-BE completa**.
Trilha própria do backend para PDV, concluída; F2.6 liberada e não iniciada.
Backend **apto para a integração inicial do PDV** — os contratos básicos existem;
isso não significa PDV completo nem produção fiscal pronta. **ONB-01A e ONB-01B concluídos** — wizard
ONB-01B publicado em `aee736db22a4f626fea6010d3e67278670606134`; ajuda permanente,
checklist numerado e melhorias de empresa/produto/preço concluídos em 2026-10-04.
**PM-04A concluído ✅ e publicado** em
`d0ae879de8e4c389e6584b9c9f576450d37fcc74` —
`feat(sales): validar quantidade conforme unidade do produto`.
**PM-04B concluído ✅ e publicado** em
`dc23e56e927304c57d57a11863d021bdc02dbf8d` —
`feat(sales): preservar unidade histórica no item do pedido`.
**Cliente Teste concluído ✅** — criado e validação manual realizada.
**PM-04C concluído ✅ e publicado** em
`9cbca4840d53353ef8244e1854508f1bf38938c4` —
`feat(sales): adicionar semântica de apresentação às linhas do pedido`.
**PM-05 concluído ✅ e publicado** em
`d958a50ddf52ee509eb02ea5cb14c881c01cbeaa` —
`feat(products): adicionar classificação fiscal básica`.
**Próxima prioridade operacional:** LucraOne PDV Java — Fase 4, contrato e
conectividade com a API, agora desbloqueada pelo fechamento da trilha PDV-BE.
Não iniciada nesta rodada.
Baseline atual: 1458 testes — 1456 PASS, 0 FAIL, 0 ERROR, 2 RISKY preexistentes,
0 SKIPPED e 6368 assertions. Os RISKY continuam sendo
`test_passwords_not_logged_in_audit` e `test_user_email_properly_protected`; não
foram corrigidos. SEC-01, SEC-02, SEC-03 e SEC-04 resolvidos — os quatro
bloqueadores obrigatórios pré-F2.6 estão encerrados, e o SEC-05 também está
resolvido, assim como SEC-06 e COR-01. **Todos os itens pré-F2.6, obrigatórios
e recomendados, estão encerrados** — resta apenas PERF-01, que nunca bloqueou. A
F2.6 não foi iniciada.
Staging disponível em https://lucraone.jmfsystem.tech; marco Cliente Teste
concluído.

> 🟢 **A F2.6 está liberada, e não foi iniciada.** Os quatro bloqueadores
> obrigatórios — SEC-01, SEC-02, SEC-03 e SEC-04 — e os três recomendados —
> SEC-05, SEC-06 e COR-01 — estão resolvidos, e os três itens do
> [critério de liberação](#critério-para-liberar-a-f26) estão satisfeitos.
> Liberar não é começar: iniciar a F2.6 é decisão de quem conduz o produto.
> PERF-01 segue pendente e nunca bloqueou.

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
> e clientes. A preparação do backend para PDV agora tem trilha própria PDV-BE;
> integração real, fiscal e sync continuam futuros.

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
Pendências pré-F2.6              ✅ ENCERRADAS (PERF-01 não bloqueante)
        ↓
F2.6 — Integration APIs          🟢 LIBERADA · NÃO INICIADA
```

As pendências pré-F2.6 não são uma fase nem uma sprint. São correções com IDs
próprios (`SEC-*`, `COR-*`, `PERF-*`), acompanhadas numa seção dedicada abaixo.

Em paralelo corre a trilha
[Cadastro mestre de produtos — preparação para o PDV](#cadastro-mestre-de-produtos--preparação-para-o-pdv)
(`PM-*`), aberta por necessidade de cliente. Ela evolui o cadastro de produtos e
não altera o estado da F2.6 nem a ordem das pendências.

Desde 2026-10-03 corre também, **em paralelo à PM e independente dela**, a
trilha [ONB — Onboarding e Experiência Inicial](#onb--onboarding-e-experiência-inicial)
(`ONB-*`), aberta depois que o ambiente staging entrou no ar e o cadastro do
primeiro cliente real passou a ser necessário.

Desde 2026-10-08 existe também a trilha
[PDV-BE — Backend para integração com PDV](#pdv-be--backend-para-integração-com-pdv).
Ela é separada da F2.6 e prepara os contratos para o Java PDV; concluir sua
fundação não libera a integração real.

### Prioridade operacional atual

A ordem abaixo é **prioridade de execução**, não dependência arquitetural. ONB e
PM são trilhas paralelas: nenhuma etapa de ONB bloqueia tecnicamente uma etapa de
PM, nem o contrário.

```
ONB-01A ✅  →  ONB-01B ✅  →  PM-04A ✅  →  PM-04B ✅  →  Cliente Teste ✅  →  PM-04C ✅  →  PM-05 ✅
```

| Ordem | Etapa | Trilha | Status |
|---|---|---|---|
| 1 | ONB-01A — Fundação administrativa do onboarding | ONB | **Concluído ✅** (`6aa8d62`) |
| 2 | ONB-01B — Experiência guiada de onboarding | ONB | **Concluído ✅** · wizard `aee736d`; ajuda e checklist concluídos |
| 3 | PM-04A — Quantidade coerente com a unidade | PM | **Concluído ✅** · `d0ae879` |
| 4 | PM-04B — Snapshot histórico do item | PM | **Concluído ✅** · `dc23e56` |
| 5 | Criação e teste manual de **Cliente Teste** pelo painel | ONB (validação) | **Concluído ✅** |
| 6 | PM-04C — Semântica de linhas para o futuro PDV | PM | **Concluído ✅** · `9cbca48` |
| 7 | PM-05 — Dados fiscais do produto | PM | **Concluído ✅** · `d958a50` |

> A ordem continua sendo **prioridade operacional, não dependência técnica**.
> ONB-01A destravou o fluxo administrativo; ONB-01B entregou o wizard guiado,
> administrador definido pelo operador, configuração inicial, revisão e próximos
> passos. A ajuda permanente e o checklist numerado facilitam o primeiro uso.
> PM-04A e PM-04B foram concluídos e publicados. O marco separado Cliente
> Teste está concluído: cliente criado e validação manual realizada. PM-04C
> foi concluído e publicado, assim como PM-05. A preparação do backend agora
> seguiu na trilha PDV-BE, agora completa: PDV-BE-01 a PDV-BE-05 concluídos.
> Não há dependência técnica entre ONB e PM. Os bloqueadores obrigatórios da
> F2.6 e os recomendados estão todos encerrados.

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
| **F2.6 — Integration APIs** | 📋 | 📋 | **próxima funcional — 🟢 liberada e não iniciada** |

> Entre a F2.5 e a F2.6 ficam as
> [pendências bloqueadoras pré-F2.6](#pendências-bloqueadoras-pré-f26).

---

## Pendências bloqueadoras pré-F2.6

**A F2.6 continua sendo a próxima sprint funcional, liberada e não iniciada.**
Os bloqueadores obrigatórios e recomendados desta seção foram encerrados;
PERF-01 segue pendente e não bloqueante.

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
| SEC-01 | Segurança | API Authorization | Crítica | Resolvido | **Sim** |
| SEC-02 | Segurança | API Authentication | Crítica | Resolvido | **Sim** |
| SEC-03 | Segurança | TenantResolver | Alta | Resolvido | **Sim** |
| SEC-04 | Segurança | create-role | Crítica | Resolvido | **Sim** |
| SEC-05 | Segurança | SendEmailAction | Média | Resolvido | Recomendado |
| SEC-06 | Segurança | Secrets / .env.testing | Média | Resolvido | Recomendado |
| COR-01 | Correção | Soft delete + unicidade | Alta | Resolvido | Recomendado |
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

**Crítica · Resolvido · Bloqueia F2.6: Sim** — Origem: controllers de negócio da
FASE 02, a partir de 2026-08-16 (depois da F1.7) · Resolvido em `3a14703`,
2026-10-07

**Problema.** As Policies existiam e o painel web as aplicava. Na API, **7 dos
14 controllers** não verificavam permissão em camada nenhuma: nem no controller,
nem no FormRequest (`authorize()` devolvia `true` ou não existia), nem em
middleware — as rotas desses módulos usavam só `['auth:sanctum', 'tenant']`.

| Módulo | Controllers sem autorização | Rotas |
|---|---|---|
| Products | `ProductController`, `CategoryController`, `PriceController` | 21 |
| Inventory | `InventoryController`, `StockLevelController` | 6 |
| Sales | `OrderController`, `CustomerController` | 8 |
| **Total** | **7** | **35** |

**Correção da contagem: 34 → 35.** A tabela anterior dizia 20 rotas em Products
e 34 no total. Estava certa quando foi escrita, em `82a9811` (2026-09-10): o
arquivo de rotas do módulo tinha 10 rotas explícitas mais dois `apiResource`
(5 cada) = 20. Doze dias depois, `c846d8d` (2026-09-22, PM-03) acrescentou
`GET products/resolve-barcode/{barcode}` e a tabela não foi atualizada. O número
correto, conferido com `php artisan route:list --path=api/v1` na VPS em
2026-10-07, é **21 em Products e 35 no total** — de 46 rotas em `api/v1`, as
outras 11 são as de Automation (6) e Reporting (5), que já autorizavam.

Os outros sete controllers já estavam corretos: `AutomationRuleController` e
`AutomationLogController` chamam `Gate::authorize`; `ReportController`,
`DashboardController` e `AnalyticsController` autorizam em
`ReportPeriodRequest::authorize()` (Gate `view-reports`); `HealthController` e
`AuthController` são públicos por definição.

**Consequência.** Um usuário autenticado com vínculo ativo no estabelecimento —
inclusive com papel `viewer` — criava, alterava e apagava produtos, categorias e
preços, ajustava estoque e níveis de reposição, criava e cancelava pedidos e
cadastrava clientes, independentemente das permissões do seu papel. O isolamento
**entre** estabelecimentos nunca foi afetado: `tenant` e `TenantScope` sempre
filtraram. Era escalada horizontal de privilégio **dentro** do estabelecimento.

**Por que passou.** Nenhum teste de API cobria 403 em Products, Inventory ou
Sales. Os únicos testes de 403 na API estavam em `ReportApiTest` e
`AutomationApiTest` — justamente os módulos protegidos.

**Estratégia adotada.** `Gate::authorize()` no controller, em **uma única
camada**, nas 35 rotas. Os FormRequests seguem cuidando só de validação.

A escolha não foi arbitrária: é o padrão que o `AutomationRuleController` — o
controller de API que já autorizava corretamente — vinha usando, e o
`UpdateAutomationRuleRequest` já documentava o motivo em comentário: *"Rota de
API entrega o id como string; a autorização por instância acontece no
controller, depois do findOrFail"*. As rotas de API usam `{id}` em vez de model
binding tipado, então um FormRequest não tem instância para autorizar sem
repetir a busca com escopo de tenant que o controller já faz. Concentrar tudo no
controller evita essa duplicação e deixa a auditoria em um `grep` por arquivo.

O mapeamento reproduz o que o painel web já exigia, sem inventar permissão:

| Controller | Rotas | Policy | Ações |
|---|---|---|---|
| `ProductController` | 8 | `ProductPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `CategoryController` | 7 | `CategoryPolicy` | `viewAny`, `view`, `create`, `update`, `delete` |
| `PriceController` | 6 | `ProductPolicy` | `viewAny` na leitura, `update` do produto dono na escrita |
| `InventoryController` | 5 | `InventoryPolicy` | `viewAny`, `create` no ajuste |
| `StockLevelController` | 1 | `StockLevelPolicy` | `create` |
| `OrderController` | 5 | `OrderPolicy` | `viewAny`, `view`, `create`, `update` no status, `delete` no cancelamento |
| `CustomerController` | 3 | `CustomerPolicy` | `viewAny`, `view`, `create` |

Preço não tem Policy própria e não ganhou uma: o painel web autoriza preço pela
`ProductPolicy` do produto dono (`update` para escrever), e a API passou a usar
a mesma regra. O ajuste de estoque usa `InventoryPolicy::create`, a mesma ação
que `AdjustWebInventoryRequest` já exigia.

Nas rotas de instância a autorização vem **depois** do `findOrFail` com escopo
de tenant, de propósito: um id de outro estabelecimento continua respondendo
`404`, não `403`, para que a resposta não confirme a existência da entidade
alheia. Dentro do próprio estabelecimento, falta de permissão dá `403`.

**Limite conhecido.** Em `store`/`update` com FormRequest, a validação roda
antes do corpo do controller, então um usuário sem permissão que envie payload
inválido recebe `422` antes do `403`. Com payload válido recebe `403`. É
ordenação, não contorno: nenhuma escrita acontece em nenhum dos dois casos.

**Testes adicionados.** 35 testes HTTP reais, 102 assertions, em
`tests/Feature/Security/ApiAuthorization{Products,Inventory,Sales}Test.php`
sobre a base comum `ApiAuthorizationTestCase`. As personas vêm da matriz real do
estabelecimento (`StandardRoleMatrix`, via `ProvisionarEstabelecimento`), não de
papéis inventados: `manager` para o caminho positivo e `viewer` — que tem
`view-*` e nenhuma `manage-*` — para provar que leitura continua liberada e
escrita passa a dar `403`. Antes da correção, 15 desses testes falhavam com
`201`/`200` onde se esperava `403`.

Como o SEC-04 veio antes, as Policies aplicadas aqui já estão sem o coringa
`create-role`, removido em `6c770dc`. Cada módulo tem um teste com um usuário
cujo único direito é `create-role`, cobrando `403` tanto na escrita quanto na
leitura, para garantir que o coringa não volte por tabela.

**Fixtures ajustados.** Nove suítes de API preexistentes autenticavam um usuário
sem papel nenhum, o que bastava quando não havia autorização. Passaram a receber
permissão legítima pelo trait `tests/Concerns/AutorizaUsuarioDeApi.php`, que usa
a matriz de produção em vez de permissões avulsas — assim o fixture não pode
divergir do modelo real. Quem cobre a recusa são os `ApiAuthorization*Test`.

**Fora do escopo, de propósito.** Nada de SEC-02 (token `['*']`, sem expiração,
login sem throttle) nem de SEC-03 (sem cliente de máquina no `TenantResolver`).
Nenhuma Policy foi alterada; nenhuma migration foi criada ou executada.

**Por que bloqueava.** A F2.6 acrescenta uma API que guarda credenciais de
terceiros. Construí-la sobre uma API que não autoriza reproduziria o buraco na
superfície mais sensível.

**Evidência da entrega (2026-10-07).** Suíte completa: 1085 testes — 1083 PASS,
0 FAIL, 0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 4436 assertions, contra
1050/1048/4334 do baseline anterior: +35 testes e +102 assertions, exatamente os
do SEC-01. Os RISKY `test_passwords_not_logged_in_audit` e
`test_user_email_properly_protected` continuam sem correção. Testes focados do
SEC-01: 35 PASS, 102 assertions. Suítes dos módulos afetados
(Products, Inventory, Sales, Security): 462 PASS, 2132 assertions. Auditoria de
rotas conferida por script sobre `route:list --path=api/v1 --json`: 35 rotas nos
sete controllers, **0 sem `Gate::authorize`**. Pint passou nos 20 arquivos do
escopo; as 7 reprovações restantes do `pint --test` do projeto já reprovavam em
`5e5f46a` e ficaram fora do escopo. PHPStan: 17 erros, todos preexistentes em
arquivos não tocados — nenhum dos sete controllers aparece no relatório.
`git diff --check` limpo. Nenhuma migration criada ou executada; nenhum
container reiniciado ou reconstruído.

**Critérios de aceite.** Todos verdes: sete controllers mapeados; 35 rotas
contabilizadas e todas com autorização por Policy; usuário sem permissão recebe
`403`; usuário autorizado continua operando; leitura de `viewer` preservada;
escrita indevida bloqueada; `TenantScope` intacto (testes de cross-tenant
seguem respondendo `404`); nenhum bypass do SEC-04 reintroduzido; cobertura HTTP
real de `403`; suíte com 0 FAIL e 0 ERROR; estilo e análise estática sem
regressão; staging saudável; SEC-02 e SEC-03 fora do escopo.

### SEC-02 — API Authentication

**Crítica · Resolvido · Bloqueia F2.6: Sim** — Origem: login da API da F1.4
(`b38dfb0`, 2026-08-13), anterior à F1.7 e não detectado por ela · Resolvido em
`e306494`, 2026-10-07

**Problema.**

- **Sem rate limiting.** `POST /api/auth/login` não tinha `throttle`, e nenhuma
  rota `/api` tinha. O login web tem dois freios: `throttle:20,1` na rota e o
  limite por e-mail + IP do `LoginRequest` web.
- **Enumeração de contas por tempo.** `AuthController::login` avaliava
  `! $user || ! Hash::check(...)`: quando o e-mail não existia, o hash não era
  calculado e a resposta voltava mais rápido. Mensagem e status já eram iguais
  (`401`); a diferença era só de tempo.
- **Tokens sem expiração.** `config/sanctum.php` tinha `'expiration' => null`.
  Um token vazado valia até que alguém fizesse logout com ele.
- **Tokens sem abilities.** `createToken('auth_token')` era chamado sem
  abilities, o que equivale a `['*']`.

**Consequência.** Força bruta sem freio contra a API, descoberta de quais
e-mails têm conta e tokens com poder total por tempo indeterminado. Somado ao
SEC-01, um token vazado de qualquer papel dava escrita permanente no
estabelecimento.

**Throttle.** Dois freios, como no login web, e com os mesmos números — o freio
não deveria ser mais frouxo porque a porta é a API:

| Camada | Limite | Chave | Onde |
|---|---|---|---|
| Rota | 20/min | IP | limiter nomeado `api-login` |
| Conta | 5/min | e-mail normalizado + IP | `LoginRequest` da API |

O teto por IP é mais alto que o por conta de propósito: um estabelecimento
atrás de uma única saída de rede tem várias pessoas entrando ao mesmo tempo, e
quem trava o ataque a *uma* conta é o limite de 5. A senha nunca entra na
chave. O limiter é nomeado em vez de `throttle:n,m` solto na rota para não criar
um limite que pegue outros endpoints. Excedido, a resposta é `429` em JSON com
`Retry-After` — comprovado em staging: cinco `401` e depois `429`.

O bloqueio vale também para a senha correta. Um freio que liberasse o acerto
seguinte não freia nada: bastaria ao atacante continuar tentando.

**Enumeração por timing.** O caminho do e-mail inexistente passou a comparar a
senha contra um hash bcrypt constante, descartável, que não corresponde a conta
nenhuma e não é a senha de ninguém. Os dois caminhos executam uma verificação
de hash, então o tempo deixa de denunciar quais e-mails existem. O hash é
constante de propósito: gerar um por requisição custaria o mesmo que o ataque
que se quer evitar. Mensagem e status seguem idênticos (`401`,
`{"message":"Credenciais inválidas"}`) — isso já era verdade antes.

A prova é de comportamento, não de cronômetro: o teste espiona a fachada `Hash`
e exige que `check` seja chamado também quando o e-mail não existe. Benchmark de
milissegundos em CI seria frágil e não provaria a causa.

**Abilities.** `business:read` e `business:write`, definidas em
`App\Modules\Identity\Domain\TokenAbility`. O conjunto é pequeno e dividido
por natureza da operação, não por módulo.

Ability não é permissão. Quem decide se *esta pessoa* pode mexer em produto são
as Policies do SEC-01, pelo papel dela no estabelecimento; a ability diz o que
*este token* pode fazer, independentemente de quem o carrega. Duplicar as 26
permissões do RBAC em abilities Sanctum criaria duas verdades para a mesma
regra. O que a remoção do `['*']` elimina não é o alcance atual do token humano
— ele lê e escreve, e segue lendo e escrevendo — é o coringa: com `['*']` o
mesmo token passaria a valer automaticamente para qualquer ability criada
depois, provisionar terminal entre elas, sem ninguém decidir isso.

A conferência fica em um ponto único, o middleware `token.ability`, que deriva
a ability do método HTTP: métodos seguros exigem `business:read`, os demais
`business:write`. Aplicado nos cinco arquivos de rota de módulo como
`['auth:sanctum', 'token.ability', 'tenant']`, sem tocar nenhum dos 35
controllers e sem repetir `tokenCan` por rota. Requisição de sessão de primeira
parte não tem token de acesso e passa direto — o painel web é autorizado pelas
Policies.

**Expiração.** `720` minutos, 12 horas, via
`env('SANCTUM_EXPIRATION_MINUTES', 720)`. Cobre um turno operacional inteiro,
com folga para hora extra, sem obrigar novo login no meio do expediente, e
limita um token vazado a menos de um dia. O default seguro fica no config, então
staging não precisou de alteração de `.env`; a variável está documentada em
`.env.example`. O painel web não é afetado: usa sessão de primeira parte, não
token. O login passou a devolver `expires_at` em ISO-8601 UTC e `abilities`,
para o cliente saber quando pedir outro token em vez de descobrir no meio de uma
operação.

**Revogação.** O logout continua apagando **só** o token da requisição; os
outros dispositivos da pessoa seguem conectados, porque encerrar todos sem ela
pedir seria surpresa. Isso nunca tinha sido testado de verdade: os dois testes
que existiam não verificavam revogação — `SecurityAuditTest::test_logout_revokes_tokens`
terminava em `assertTrue(true)`. Agora há prova no banco de que o token usado no
logout desaparece e o outro permanece.

A novidade é a revogação central por conta desativada. Antes, desativar alguém
não alcançava os tokens já emitidos: o acesso caía apenas porque
`canAccessTenant()` exige conta ativa, o que dependia do middleware `tenant`
estar na rota. Agora o gancho nativo `Sanctum::authenticateAccessTokensUsing()`
recusa o token de quem não está ativo, em toda rota com `auth:sanctum` — sem
middleware de autenticação paralelo e sem espalhar `delete` de tokens por
controllers.

**Decidido ficar de fora.** Refresh token: token expirado leva a novo login, e
um fluxo de renovação próprio é escopo seguinte, não desta correção. Revogação
em massa por administrador: não existe endpoint administrativo de tokens, e
criar um extrapolaria o SEC-02. Troca de senha também não revoga os tokens
anteriores — fica registrado como lacuna, já que o único ponto que altera senha
hoje é o `UserController` do painel, fora do escopo desta rodada.

**Testes adicionados.** 21 testes, 85 assertions, em
`tests/Feature/Security/ApiAuthenticationTest.php`. Antes da correção, 12
falhavam. Cobrem login válido e contrato preservado, `expires_at` e `abilities`,
ausência do coringa, token sem ability de escrita barrado, token sem ability de
leitura barrado, expiração configurada, token válido antes e inválido depois do
prazo (por *time travel*, não relógio real), `429` com `Retry-After`, bloqueio
que também vale para a senha correta, bloqueio que expira, contador zerado no
login válido, bloqueio de uma conta que não atinge outra, `Hash::check`
executado nos dois caminhos, respostas indistinguíveis, resposta de falha sem
vazar hash nem contagem, conta inativa em `403`, logout cirúrgico e token de
conta desativada que para de valer.

Dois achados da auditoria vieram do próprio código e não precisaram de
correção: a mensagem de `401` já era idêntica nos dois casos, e o token de quem
tem o **vínculo** desativado — não a conta — já era barrado pelo middleware
`tenant`.

**Por que bloqueava.** A F2.6 introduz clientes de máquina que vão receber
tokens. Definir expiração e abilities depois obrigaria a reemitir credenciais já
entregues a sistemas externos.

**Evidência da entrega (2026-10-07).** Suíte completa: 1106 testes — 1104 PASS,
0 FAIL, 0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 4521 assertions, contra
1085/1083/4436 do baseline anterior: +21 testes e +85 assertions, exatamente os
do SEC-02. Os RISKY `test_passwords_not_logged_in_audit` e
`test_user_email_properly_protected` continuam sem correção — o SEC-02 não os
toca. Testes focados: 21 PASS, 85 assertions. Regressão do SEC-01: 35 PASS, 102
assertions, nenhuma Policy alterada. Identity + Security + Tenancy + API: 321
PASS, 1142 assertions. Pint passou nos 13 arquivos do escopo; `routes/api.php`
segue com a reprovação `concat_space` que já tinha em `420a08b`, verificada na
versão commitada e deixada fora do escopo. PHPStan: 17 erros, os mesmos 11
arquivos e contagens da rodada anterior — nenhum arquivo do SEC-02 aparece.
`git diff --check` limpo. Nenhuma migration criada ou executada; nenhum
container reiniciado ou reconstruído; `config` não está cacheado, então não foi
preciso limpar nada.

Durante a edição, staging serviu por cerca de um minuto um
`AppServiceProvider` sem os `use` novos, e o healthcheck interno registrou um
`500` em `GET /up` às 18:29:56. O código é bind-mounted, então a recuperação foi
imediata ao salvar os imports, sem restart. Nenhuma requisição externa foi
atingida — staging está ocioso — e não há outro `staging.ERROR` no dia.

**Critérios de aceite.** Todos verdes: login com throttle; `429` em JSON com
`Retry-After`; caminho de hash equivalente para e-mail inexistente e senha
errada; mensagem e status sem permitir enumeração; token sem `['*']`; abilities
mínimas definidas e conferidas; token com expiração; expiração provada por
*time travel*; logout revogando o token atual; estratégia de revogação
documentada, lacunas incluídas; conta desativada sem acesso com token antigo;
SEC-01 verde; `TenantResolver` não alterado; suíte com 0 FAIL e 0 ERROR;
staging saudável; SEC-03 pendente; F2.6 não iniciada.

### SEC-03 — TenantResolver

**Alta · Resolvido · Bloqueia F2.6: Sim** — Origem: F1.8 (`c73b369`,
2026-08-16) · Resolvido em `342f4ad`, 2026-10-07

**Problema.** Em `TenantResolver::resolve()` a checagem de vínculo era
condicional:

```php
if ($usuario && ! $usuario->canAccessTenant($tenantId)) {
    return false;
}
```

Sem usuário autenticado, o `X-Tenant-ID` era aceito e virava o contexto da
requisição.

**Por que não era explorável.** Os cinco arquivos de rotas de módulo aplicam
`['auth:sanctum', 'token.ability', 'tenant']` nessa ordem, e `bootstrap/app.php`
documenta a exigência. A proteção existia, mas dependia de todo arquivo de rotas
futuro repetir essa ordem — não do resolver.

**A fragilidade foi demonstrada antes de corrigir.** Com o resolver chamado
diretamente, um `Request` portando `X-Tenant-ID` e nenhuma identidade devolvia
`true` e preenchia o `TenantContext`. E uma rota registrada só no teste com
`['tenant']` e **sem** autenticação respondia `200`, com o estabelecimento
resolvido a partir do header sozinho. Era o único jeito de provar o item:
exercitar o resolver com `auth:sanctum` na frente apenas mostraria que a
autenticação barra primeiro, o que não é o que o SEC-03 trata.

**Princípio adotado.** `X-Tenant-ID` é um **pedido** de contexto, nunca prova de
autorização. O header diz qual dos vínculos da identidade usar; quem autoriza é
o vínculo de uma identidade autenticada. Sem identidade não há pedido a atender.

**Mudança estrutural.** Uma guarda *fail-closed* no início do `resolve()`, e a
checagem de vínculo da estratégia 1 deixou de ser condicional. Com isso a
segurança passou a morar no resolver, não na ordem dos middlewares.

A guarda testa **tipo**, não apenas presença:

```php
if (! $usuario instanceof User) {
    return $this->recusar();
}
```

Estabelecimento é derivado de vínculo de pessoa, o que só existe para `User`.
Essa é a fronteira para sujeitos não-humanos: um terminal de PDV ou uma
integração futura não será `User`, cai nessa guarda e é recusado — então
precisará de uma estratégia própria e explícita, em vez de pegar carona no
header. **Nenhuma identidade de máquina foi criada nesta rodada**; a fronteira
foi apenas fechada.

A identidade passou a vir de `$request->user()` em vez de `Auth::user()`. É o
sujeito que a autenticação **desta requisição** estabeleceu, em vez do estado
global do guard padrão, e é como o resto do projeto lê o usuário: `Auth::user()`
aparecia em exatamente um arquivo — este resolver — contra 16 arquivos usando
`$request->user()`.

Toda recusa passa por `recusar()`, que limpa o `TenantContext` antes de devolver
`false`. O contexto é o que o `TenantScope` usa para filtrar; uma falha que
deixasse em pé o estabelecimento de uma resolução anterior faria as consultas
seguintes rodarem no estabelecimento errado.

**Estratégias preservadas.** Nenhuma foi removida, e a ordem é a mesma:

| # | Fonte | Exige identidade | Valida vínculo |
|---|---|---|---|
| 1 | header `X-Tenant-ID` | sim | `canAccessTenant` |
| 2 | sessão (`tenant_ativo`) | sim | `canAccessTenant` |
| 3 | vínculo único ativo | sim | pelo próprio vínculo |

A estratégia 2 continua não abortando quando a sessão aponta um
estabelecimento que deixou de valer: pode ser acesso revogado enquanto a pessoa
navegava, e o vínculo único ainda resolve. O painel web segue intacto —
`AutenticarWeb` autentica e confere conta ativa antes de chamar o resolver.

**Defesa em profundidade mantida.** A ordem `auth:sanctum` → `token.ability` →
`tenant` continua nos cinco arquivos de rota. O resolver ficar seguro sozinho
não é motivo para tirar a autenticação da frente.

**Testes adicionados.** 23 testes, 50 assertions, em
`tests/Feature/Tenancy/TenantResolverSecurityTest.php`. Antes da correção, 13
falhavam. Dez provavam a fragilidade — header sem identidade, header e sessão
sem identidade, identidade que não é pessoa, header de estabelecimento sem
vínculo aceito às cegas, vínculo inativo, conta inativa, header malformado,
estabelecimento inexistente, contexto sobrevivendo a uma falha, e a rota com
`tenant` sem autenticação. As outras três falhavam por causa da troca de fonte
de identidade e passaram com a correção.

A suíte combina testes estruturais, que chamam o resolver com um `Request`
montado e o resolvedor de usuário preenchido como o middleware faz, e testes
HTTP sobre as rotas reais de negócio: estabelecimento válido, estabelecimento
alheio em `403`, vínculo único sem header, vínculo inativo em `403` e vários
vínculos sem escolha em `403`. Os contratos de status não mudaram.

**Fora do escopo, de propósito.** Nenhuma migration, nenhuma alteração de
banco, nenhuma Policy do SEC-01, nada do SEC-02 — `TokenAbility`, throttle,
hash descartável, expiração e revogação ficaram intocados. Sem Terminal, sem
pareamento, sem credencial de máquina, sem `/api/v1/pdv/*`, sem webhook.

**Por que bloqueava, embora não fosse explorável.** A F2.6 prevê webhooks e
integração com gateway de pagamento, que recebem chamadas externas sem token
Sanctum. Seriam as primeiras rotas a precisar de contexto de tenant sem usuário
— exatamente o cenário em que o resolver confiava no header.

**Evidência da entrega (2026-10-07).** Suíte completa: 1129 testes — 1127 PASS,
0 FAIL, 0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 4571 assertions, contra
1106/1104/4521 do baseline anterior: +23 testes e +50 assertions, exatamente os
do SEC-03. Os RISKY `test_passwords_not_logged_in_audit` e
`test_user_email_properly_protected` continuam sem correção. Testes focados: 23
PASS, 50 assertions. Regressão do SEC-01: 35 PASS, 102 assertions. Regressão do
SEC-02: 21 PASS, 85 assertions. Tenancy + Identity + Security + Admin: 597 PASS,
2531 assertions. `php -l` sem erro nos dois arquivos. Pint passou nos dois.
PHPStan: 17 erros, com totais **e conjunto de arquivos idênticos** à rodada
anterior — `TenantResolver` não aparece no relatório. `git diff --check` limpo.
Nenhuma migration criada ou executada, nenhum container reiniciado, e nenhum
`500` transitório: o arquivo foi escrito de uma vez e validado com `php -l`
imediatamente, ao contrário do que ocorreu no SEC-02.

**Critérios de aceite.** Todos verdes: header sem identidade não resolve; sessão
sem identidade não resolve; identidade com vínculo válido resolve; vínculo
inválido não resolve; vínculo único automático preservado; vários vínculos não
escolhem nada; vínculo inativo bloqueado; contexto só preenchido após validação
e limpo na recusa; resolver seguro sem depender de `auth:sanctum` antes; ordem
dos middlewares preservada; SEC-01 e SEC-02 verdes; nenhuma Policy e nenhum
`TokenAbility` alterados; nenhuma identidade de máquina criada; nenhuma rota PDV
criada; suíte com 0 FAIL e 0 ERROR; nenhum achado novo de Pint ou PHPStan;
staging saudável; ROADMAP fiel.

**Achado separado, não corrigido aqui: estabelecimento suspenso.**
`canAccessTenant()` exige conta ativa e vínculo ativo, mas **não** consulta o
status do próprio estabelecimento, e `estabelecimentosDisponiveis()` filtra pelo
status do vínculo, não do tenant. Comprovado em sonda descartável: com um tenant
`status = SUSPENDED`, `Tenant::isActive()` devolve `false` e
`canAccessTenant()` devolve `true` — o resolver aceita o estabelecimento.

Não é falha de isolamento nem escalada de privilégio: o alcance é o
estabelecimento ao qual a pessoa já pertence, e nada de outro tenant fica
acessível. É aplicação de ciclo de vida — suspensão por inadimplência ou
bloqueio administrativo não interrompe a operação. Antecede o SEC-03 e não foi
alterado por ele. Fica registrado para priorização própria, em vez de entrar
nesta rodada por conveniência.

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
F2.6**, que permanece não iniciada: com o SEC-03 fechado em `342f4ad`, os
quatro bloqueadores obrigatórios estão resolvidos, e o critério de liberação
está integralmente satisfeito nos dois primeiros itens.

**Nenhum item pré-F2.6 em aberto.** PERF-01 segue pendente e nunca bloqueou.

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

Assim o SEC-01 aplicou as Policies aos 7 controllers e 35 rotas sem propagar o
modelo vulnerável anterior.

**Por que bloqueia.** X1, E3 e E7 são falhas de isolamento entre
estabelecimentos — os três já corrigidos —, da mesma classe que o SEC-01 e o
SEC-03, e E1 e E2 levavam até elas — ambos já corrigidos. O SEC-01 vai ligar essas
Policies à API, e a F2.6 vai criar uma Policy para proteger credenciais de
terceiros, que herdaria o padrão atual.

### SEC-05 — SendEmailAction

**Média · Resolvido · Bloqueia F2.6: Recomendado** — Origem: F2.5 (`3fd8f3b`,
2026-09-09) · Resolvido em `6b271ef`, 2026-10-07

**Problema.** `SendEmailAction` aceitava em `action_config.recipients` até 1.000
caracteres de endereços separados por vírgula, ponto e vírgula ou quebra de
linha, e só filtrava o formato. Assunto e mensagem são escritos pelo usuário e
interpolados com os dados do gatilho. Não havia restrição a usuários ou contatos
do estabelecimento, limite de destinatários, limite de envios por regra ou
período, nem registro voltado a detectar abuso.

Os três números medidos antes de corrigir, para dimensionar o que "1.000
caracteres" significava:

| Medição | Antes |
|---|---|
| Destinatários que cabem em 1.000 caracteres | **143** |
| Destinatários aceitos pela API em um teste | **50** → `201 Created` |
| `A@Casa.test` + `a@casa.test` | **2** destinatários, a mesma caixa duas vezes |
| Execuções consecutivas da mesma regra | **70**, todas executadas, 0 bloqueios |

**Quem pode configurar.** Quem tem `manage-automations`, verificado na API e no
painel. Até `6c770dc`, `create-role` também servia como coringa.

**Agravante histórico, encerrado.** Enquanto o SEC-01 estava aberto, o gatilho
`product_created` podia ser multiplicado por qualquer usuário autenticado, que
criava produto pela API sem passar por permissão. Com o SEC-01 resolvido em
`3a14703`, criar produto exige `manage-products`, e esse vetor deixou de
existir. O registro fica como histórico da avaliação de risco original.

**Risco.** Uso indevido, spam, abuso da infraestrutura de e-mail e deterioração
da reputação do domínio remetente.

**Princípio de produto adotado.** Automação é notificação operacional — avisar o
comprador, o estoquista, o contador —, não campanha de marketing. Os defaults
são, portanto, volume baixo, destinatários controlados e rastro auditável.

**Política de destinatários.**

| Decisão | Valor |
|---|---|
| Máximo por regra | **10** |
| Formato | e-mail válido, e **a lista inteira é recusada** se houver um inválido |
| Separadores | vírgula, ponto e vírgula, quebra de linha |
| Normalização | `trim`, caixa baixa, descarte de entradas vazias |
| Deduplicação | sim, depois da normalização — `A@Casa.test` e `a@casa.test` contam como um |
| Domínio externo | **permitido** |
| Precisa ser `User` do sistema | **não** |

O `max:1000` caracteres continua na validação como freio barato de tamanho, mas
nunca foi um limite semântico — cabiam 143 endereços nele. Quem limita agora é a
quantidade.

Endereço inválido passou a recusar a lista inteira em vez de ser descartado em
silêncio. Antes, um erro de digitação no meio da lista fazia a notificação
simplesmente não chegar àquela pessoa, sem ninguém saber. É mudança de
comportamento deliberada: falhar com motivo registrado é o que dá chance de
corrigir.

**Por que não restringir a `User` do sistema.** Notificação operacional
legítima vai com frequência para quem não tem conta no LucraOne: o contador, o
fornecedor, o financeiro terceirizado, um gestor que só quer receber o aviso.
Exigir cadastro quebraria o uso real sem reduzir o risco de forma proporcional —
quem configura a regra já tem `manage-automations`.

**Por que não usar whitelist de domínio.** Seria rígida demais sem uma tela para
gerenciá-la, e a primeira notificação para um Gmail legítimo viraria um chamado
de suporte. Fica registrada como opção para quando houver interface de
governança; o limite de quantidade mais o orçamento de entregas atacam o mesmo
risco sem esse custo.

**Limites de envio.** Dois limiters nativos, ambos em janela de 3.600 segundos,
aplicados **junto do executor da ação** e não na rota — o risco é a execução da
automação, não a requisição HTTP:

| Limite | Valor | Unidade | Chave |
|---|---|---|---|
| Por regra | **60** | execuções | `automation-email:rule:{tenant_id}:{rule_id}` |
| Por estabelecimento | **500** | **entregas** | `automation-email:tenant:{tenant_id}` |

As chaves incluem o `tenant_id` sempre, então o contador de um estabelecimento
nunca atinge outro — verificado por teste, inclusive para a forma da chave.

O limite por regra conta execuções: 60 por hora é uma por minuto sustentada,
muito acima de qualquer cadência real dos três gatilhos existentes
(`product_created`, `stock_low`, `order_completed`), todos nascidos de ação
humana — nenhum é agendado nem de alta frequência. O limite do estabelecimento
conta **entregas**, porque é a entrega que gasta reputação do remetente, e
porque sem ele somar regras multiplicaria o volume: dez regras sob o limite por
regra entregariam 6.000 e-mails por hora.

**Comportamento ao bloquear.** Nada é enviado — não existe envio parcial. O
orçamento do estabelecimento considera as entregas **desta** execução antes de
liberar, em vez de só o total corrente, justamente para não mandar metade da
lista e cortar o resto. O bloqueio sobe como exceção, que o `RuleEngine` já
captura: a execução é gravada como `failed` com o motivo, as outras regras do
gatilho seguem, e **a fila não repete** — importante, porque o listener roda
enfileirado com `tries = 3` e um bloqueio que escapasse faria a passagem inteira
rodar três vezes.

**Auditoria.** Pelo mecanismo que já existia, sem sistema paralelo: cada
passagem gera uma linha em `automation_logs` com `tenant_id`,
`automation_rule_id`, `trigger`, `action`, `result` e `message`. Sucesso grava
`executed`; bloqueio e configuração inválida gravam `failed` com o motivo em
`message`, cada motivo sendo uma constante — `SendEmailAction::MOTIVO_LIMITE`,
`EmailRecipients::MOTIVO_INVALIDOS`, `MOTIVO_EXCEDE_MAXIMO` e o
`MOTIVO_VAZIO` preexistente. O `Log::warning('automação falhou', …)` do
`RuleEngine` já carrega regra, estabelecimento e gatilho, o que dá a trilha de
abuso sem nada novo.

O `outcome` da execução passou a guardar `recipient_count` em vez da lista de
endereços. A lista é dado de contato de terceiros e o histórico de execuções é
consultável pelo painel; a contagem responde à pergunta operacional sem guardar
os endereços. Nenhum teste dependia do formato anterior.

**O que foi auditado e não precisou de correção.** `recipients` **não** passa
pelo interpolador — só assunto e mensagem passam —, então dado do gatilho não
consegue alterar destinatário. E header injection via assunto não é possível:
testei um assunto com `\r\n` tentando injetar `Bcc:` e o Symfony Mailer
codifica o valor, produzindo `Subject: =?utf-8?Q?Promo?=` sem a linha injetada.
Nenhuma regex própria foi criada para isso.

**Decidido ficar de fora.** Whitelist de domínio e restrição a `User`, pelos
motivos acima. Nenhum `AllowedDomainService`, `RecipientApprovalWorkflow` ou
`DestinationPolicyEngine` — o risco atual não justifica um motor de política. Um
orçamento de entregas por regra, além do de execuções, é a evolução natural se o
volume real pedir. Nada de SMTP de produção, provedor externo, webhook ou SSRF.

**Testes adicionados.** 25 testes, 59 assertions, em
`tests/Feature/Automation/SendEmailAbuseTest.php`. Antes da correção, 21
falhavam. Cobrem a validação pela API (um destinatário, vários, os três
separadores, espaço em excesso, inválido, lista vazia, exatamente no máximo,
acima do máximo, duplicados que não contam para o máximo), a mesma política no
painel web, a normalização no envio, as duas formas de configuração legada
(acima do máximo e com endereço inválido), os limites (abaixo, na última vaga,
acima por regra, acima por entregas do estabelecimento, contagem por
destinatário, expiração da janela), o isolamento (estabelecimento A não atinge
B, regra A não atinge B, forma da chave) e a auditoria (contagem sem endereços,
bloqueio registrado e consultável, bloqueio que não vira retry de fila).

**Mesma política nos dois caminhos.** A validação mora em
`SendEmailAction::regrasDeConfiguracao()`, que é a fonte única consumida pelo
`AutomationRuleRequest` — do qual descendem tanto os requests da API quanto os
do painel. API e web não podem divergir por construção, e há teste para os dois.

**Defesa em profundidade.** A ação relê e revalida a configuração na execução em
vez de confiar no JSON persistido, porque existem regras gravadas antes desta
política e pode haver import futuro. Sem migration e sem backfill: configuração
legada fora da política simplesmente não envia e fica registrada.

**Por que Média, e não Alta.** Exige permissão administrativa de automação, não
há cadastro self-service de estabelecimentos e não há envio real — o ambiente
usa Mailpit. **Sobe para Alta** antes de configurar SMTP de produção ou abrir
cadastro self-service, e é nesse momento que os números acima devem ser
revisados contra volume observado.

**Princípios para a F2.6.** A F2.6 prevê entrega de webhooks para endereços
configurados pelo estabelecimento: o mesmo padrão de destino controlado pelo
cliente, com risco maior, porque permite requisições para a rede interna (SSRF),
como os containers de MySQL e Redis. O que se decidiu aqui e deve ser reusado
conceitualmente, sem que nada de webhook tenha sido implementado:

- **destino é configuração validada**, nunca valor interpolado do evento;
- **limite semântico**, não limite de tamanho de texto — quantidade de destinos,
  não bytes;
- **falhar a configuração cedo**, em vez de descartar destino inválido em
  silêncio na hora de entregar;
- **dois limites, um por regra e um por estabelecimento**, com a chave sempre
  incluindo o `tenant_id`;
- **contar a unidade que custa** — entrega para e-mail, requisição para webhook;
- **sem entrega parcial**, e bloqueio que não vira retry de fila;
- **registro pelo mecanismo existente**, com contagem em vez do destino
  completo.

Para webhook, a esses princípios se soma o que o e-mail não exige: política de
destino de rede, que é o SSRF, deliberadamente fora desta rodada.

**Evidência da entrega (2026-10-07).** Suíte completa: 1154 testes — 1152 PASS,
0 FAIL, 0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 4630 assertions, contra
1129/1127/4571 do baseline anterior: +25 testes e +59 assertions, exatamente os
do SEC-05. Testes focados: 25 PASS, 59 assertions. Módulo Automation inteiro,
incluindo o painel: 65 PASS, 170 assertions. Regressões: SEC-01 35 PASS, SEC-02
21 PASS, SEC-03 23 PASS. `php -l` sem erro nos três arquivos. Pint passou nos
quatro do escopo. PHPStan: 17 erros, com totais **e conjunto de arquivos
idênticos** à rodada anterior — nenhum arquivo do SEC-05 aparece.
`git diff --check` limpo. **Nenhuma migration criada ou executada**, nenhuma
alteração de `.env`, nenhum container reiniciado, nenhum `500` transitório.

**Critérios de aceite.** Todos verdes: destinatários validados, normalizados e
deduplicados; máximo explícito de 10; regra acima do máximo recusada na API e no
painel; configuração legada inválida não envia; rate limiting de execução; limite
isolado por estabelecimento; sem envio parcial; bloqueio sem retry infinito;
bloqueio auditável; sucesso preservado; multi-tenancy intacto; API e painel com a
mesma política; SEC-01, SEC-02 e SEC-03 verdes; suíte com 0 FAIL e 0 ERROR;
nenhum achado novo de Pint ou PHPStan; staging saudável; SEC-06 e COR-01
pendentes; F2.6 não iniciada.

### SEC-06 — Secrets / .env.testing

**Média · Resolvido · Bloqueia F2.6: Recomendado** — Origem: versionado desde
`809442f` (2026-08-13), antes da F1.7; exposto publicamente com a publicação do
repositório no GitHub em 2026-09-09 · Resolvido em `693a8ef`, 2026-10-07

**Problema.** `lucraone-backend/.env.testing` estava versionado com uma
`APP_KEY` de formato real, e o repositório é público — reconferido em
2026-10-07: a API do GitHub responde sem autenticação e devolve
`"private": false`.

Achados reconferidos contra o código de 2026-10-07, não herdados da auditoria
original:

- **A chave era válida.** `base64:` com 32 bytes decodificados, compatível com o
  `AES-256-CBC` configurado. Presente em **1** commit (`809442f`) e alcançável
  em **115** commits do histórico.
- **O limite de 1.000 caracteres não era o problema; a chave era.** O arquivo
  trazia dez variáveis, e só a `APP_KEY` é segredo.
- **Iria para a imagem de produção.** O `.dockerignore` enumerava `.env`,
  `.env.*.local`, `.env.backup` e `.env.production` — uma lista que não cobria
  `.env.testing` —, e o `COPY . .` do alvo `prod` o copiaria.
- **A imagem em uso nunca carregou o arquivo.** O alvo `dev`, que é o publicado
  nesta VPS, não faz `COPY . .`: os arquivos aparecem no container apenas pelo
  bind mount. O risco de imagem era do alvo `prod`, que nunca foi construído
  aqui.
- **Nada é cifrado com a chave.** Zero ocorrências de `Crypt::`, `encryptString`,
  `decryptString`, `Encrypter`, cast `encrypted` ou URL assinada em todo o
  código. Nenhuma coluna guarda dado cifrado reversível: `users.password` e
  `password_reset_tokens.token` são hash, `personal_access_tokens.token` é
  SHA-256 e `users.remember_token` é string aleatória comparada diretamente.
  Logo, **não havia dado a recifrar**.
- **Não há outro segredo versionado.** Ver a auditoria abaixo.

**O achado que mudou a conclusão: staging nunca usou a chave comprometida.**

A auditoria original registrou que o ambiente de desenvolvimento local conferido
usava a mesma `APP_KEY`, e por isso a expectativa era rotacionar. A conferência
de hoje localizou primeiro a **fonte real** da chave de staging, em vez de
presumir, e o resultado foi outro:

- os dois arquivos de compose declaram, de propósito, **nenhuma** variável de
  configuração do Laravel em `environment:`, e não existe `env_file`. A
  configuração vem de `lucraone-backend/.env`, lido em tempo de execução;
- o `docker/entrypoint.sh` cria esse `.env` a partir do `.env.example` — que tem
  `APP_KEY` **vazia** — e então roda `php artisan key:generate --force`;
- portanto staging gerou a própria chave aleatória na primeira subida. A
  comparação por fingerprint confirmou: a chave em uso pelo processo PHP **não é**
  a do `.env.testing`.

Com isso, a condição para rotacionar — staging usar a chave comprometida — é
**falsa**, e **nenhuma rotação foi feita**. Rotacionar por reflexo teria
invalidado todas as sessões web do painel sem ganho de segurança algum, porque a
chave publicada nunca protegeu nada em staging. Se a fonte não tivesse sido
procurada, um `key:generate` às cegas teria feito exatamente isso.

**O que foi mudado.**

| Mudança | Efeito |
|---|---|
| `.env.testing` fora do Git e do disco | nenhum segredo de ambiente versionado |
| `.gitignore`: `.env`, `.env.*`, `!.env.example` | qualquer arquivo de ambiente futuro já nasce ignorado |
| `.dockerignore`: mesma política | nenhum arquivo de ambiente entra no contexto de build |
| `APP_KEY` da suíte no `phpunit.xml` | a suíte deixa de depender de arquivo de ambiente |

A enumeração por nome foi trocada por negar-tudo-menos-o-exemplo nos dois
ignores. Foi justamente a lista incompleta que deixou `.env.testing` passar;
`.env.example` continua versionado e continua entrando na imagem, porque o
entrypoint copia dele quando não há `.env`.

**A chave da suíte é pública de propósito.** Vive no `phpunit.xml`, versionada,
com comentário dizendo que é exclusiva de teste e não pode ser reutilizada em
desenvolvimento, staging ou produção. Uma chave de teste não precisa ser
secreta: ela não protege dado real. O erro original não foi ter uma chave no
repositório — foi **reutilizar** a mesma chave num ambiente real. A nova é
recém-gerada, aleatória e distinta tanto da comprometida quanto da de staging,
verificado por fingerprint.

**Dependência oculta que a remoção revelou.** Com o `.env.testing` fora, a suíte
passou a herdar o `.env` da máquina — porque `.env.<ambiente>` **substitui** o
`.env` no Laravel, não soma, e era esse arquivo que dava à suíte um ambiente
enxuto. Na VPS isso trocou o locale para `pt-BR` e quebrou
`TenantManagementTest::test_paginacao_limita_listagem_em_vinte_por_pagina`, que
espera o rótulo `Next`. O conserto foi fixar o ambiente da suíte no
`phpunit.xml` — `APP_NAME`, `APP_DEBUG`, locales e `REDIS_CACHE_DB`, todos com
`force="true"` e nos valores padrão do framework, que eram os que a suíte já
usava. O teste não foi alterado: a falha apontava um acoplamento real, e era o
acoplamento que precisava sair.

**Auditoria de segredos.** O HEAD e os 118 commits do histórico foram varridos
pelos mesmos padrões (`APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`,
`AWS_SECRET_ACCESS_KEY`, `CLIENT_SECRET`, `PRIVATE_KEY`, `MYSQL_ROOT_PASSWORD`,
blocos `BEGIN PRIVATE KEY`), com valores mascarados. Quatro ocorrências, as
mesmas no HEAD e no histórico:

| Ocorrência | Classificação |
|---|---|
| `.env.testing` → `APP_KEY` | **segredo real** — o item |
| `docker-compose.prod.yml` → `APP_KEY`, `DB_PASSWORD` | falso positivo: comentário de instrução |
| `docker/entrypoint.sh` → `APP_KEY` | falso positivo: o padrão `'^APP_KEY=base64:'` de um `grep` |
| `.env.example` → `DB_PASSWORD` | default de desenvolvimento, não segredo de ambiente real — conferido: staging usa senha própria, de tamanho diferente |

`prepare-testing.sh` escreve `TEST_TOKEN=$TOKEN`, variável gerada em execução, e
o arquivo de saída agora é coberto pelo ignore. Nenhum `.pem`, `.key`, `.p12`,
`.pfx` ou `credentials` jamais existiu no histórico; os únicos arquivos de
ambiente que já existiram são `.env.example` e `.env.testing`. O `.env` nunca
foi commitado.

**A imagem futura está protegida, e isso foi provado.** Um build descartável, com
o contexto real e o `.dockerignore` real, mostrou que **só `.env.example` entra
no contexto** — `.env`, `.env.testing` e `.env.local` ficam fora. A imagem
temporária foi removida em seguida; nada do stack de staging foi reconstruído.

**Histórico Git não foi reescrito, por decisão de escopo.** `git filter-repo`,
BFG e `push --force` reescrevem um histórico público: é operação de alto impacto,
que invalida clones e forks, e precisa ser decidida à parte. A medida de
segurança que importa é outra — a chave publicada **não protege nenhum ambiente
nosso**: staging sempre teve a sua, e a suíte agora usa uma chave pública por
desenho. O valor antigo continua recuperável do histórico para sempre, e é por
isso que ele não pode ser reutilizado em lugar nenhum.

**O que continua pendente, fora do alcance desta VPS.** A auditoria de
2026-09-10 registrou que o ambiente de **desenvolvimento local** conferido usava
a mesma `APP_KEY`. A VPS não tem acesso a essa máquina, então ela **não foi
rotacionada** — e não seria honesto afirmar que foi. Ação necessária lá: apagar o
`.env` local e deixar o entrypoint gerar uma chave nova, ou rodar
`php artisan key:generate` na máquina. Mesma recomendação para qualquer clone ou
ambiente que tenha copiado o `.env.testing`.

**Por que Média.** Não há produção nem dado cifrado hoje, e staging nunca usou a
chave exposta.

**Por que Recomendado.** A F2.6 guarda `credentials_encrypted`, cifrado com a
`APP_KEY`. Com o item fechado antes, a F2.6 nasce sobre uma chave que nunca foi
publicada, e sem nenhum arquivo de ambiente versionado para herdar o problema.

**Evidência da entrega (2026-10-07).** Suíte completa: 1162 testes — 1160 PASS,
0 FAIL, 0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 4644 assertions, contra
1154/1152/4630 do baseline anterior: +8 testes e +14 assertions, exatamente os do
SEC-06. Testes focados: 8 PASS, 14 assertions, nenhum SKIPPED. Regressões:
SEC-01 35 PASS, SEC-02 21 PASS, SEC-03 23 PASS, SEC-05 25 PASS. Os dois RISKY
conhecidos seguem como estavam — o SEC-06 não os toca. `php -l` e Pint sem
achado no escopo. PHPStan: 17 erros, com totais **e conjunto de arquivos
idênticos** à rodada anterior. `git diff --check` limpo. Nenhuma migration,
nenhum container reiniciado ou reconstruído, nenhum `500`, nenhum
`DecryptException`, nenhum `MAC is invalid`, nenhum `No application encryption
key` nos logs. O `.env.testing` foi copiado para
`/var/backups/lucraone/`, fora do repositório e com permissão `600`, antes de
ser apagado — a chave antiga continua comprometida e serve apenas para
investigação.

**Critérios de aceite.** Todos verdes: segredo real fora do versionamento e
protegido contra novo commit; arquivos de ambiente fora do contexto da imagem;
suíte sem dependência de segredo real; chave de teste exclusiva, pública e
distinta da de staging; uso da `APP_KEY` auditado; dado cifrado investigado e
inexistente; fonte real da chave de staging identificada; rotação avaliada e
dispensada com evidência; chave nova nunca impressa; impacto documentado;
ambiente local inacessível **não** declarado como rotacionado; histórico
auditado; nenhum segredo real conhecido remanescente no HEAD; imagem futura
protegida por prova de build; SEC-01, SEC-02, SEC-03 e SEC-05 verdes; suíte com
0 FAIL e 0 ERROR; staging saudável; histórico não reescrito; COR-01 pendente;
F2.6 não iniciada.

### COR-01 — Soft delete + unicidade

**Alta · Resolvido · Bloqueia F2.6: Recomendado** — Origem: F2.1, F3.4 e F3.5,
2026-08-16 · Resolvido em `18eaa10`, 2026-10-07

**Problema.** Tabelas que combinam soft delete com índice único que não
considera `deleted_at`. A auditoria de hoje encontrou **cinco** índices nessa
situação, um a mais que os quatro registrados:

| Tabela | Unicidade | Soft delete desde |
|---|---|---|
| `products` | `(tenant_id, sku)` | F2.1 |
| `products` | `(tenant_id, barcode)` | PM-03 (`c846d8d`) — **não constava** |
| `categories` | `(tenant_id, slug)` | F2.1 |
| `tenants` | `slug` (global) | F3.4 |
| `users` | `email` (global) | F3.5 |

O índice de `barcode` nasceu depois da auditoria original. Já estava coberto: a
regra `BarcodeAvailable` o valida desde o PM-03, e o docblock dela declara a
política — *"produtos arquivados continuam ocupando o código, como no índice
único"*.

**A política já existia no código; o que faltava era aplicá-la por igual.** O
item foi registrado como "definir uma estratégia", mas a estratégia estava
escrita em três lugares, todos dizendo a mesma coisa:

- `BarcodeAvailable` valida contra arquivados, e explica por quê;
- `CriarProdutoComSku::proximo()` gera o próximo SKU com `Product::withTrashed()`,
  pulando os arquivados;
- `UserController::store` e `CriarClienteLucraOne` recusam e-mail de conta
  arquivada com mensagem própria, e o comentário amarra a decisão ao SEC-04 E3:
  *"jamais restaurar ou reativar identidade global como efeito de um novo
  vínculo"*.

Então o COR-01 não escolheu uma política nova: formalizou a que o projeto já
seguia e a estendeu aos pontos que tinham ficado de fora.

**Política adotada.**

| Entidade | Chave única | Valor arquivado reservado? | Create | Restore | Reutilizar |
|---|---|---|---|---|---|
| `products` | `(tenant_id, sku)` | **sim** | `422`, distinguindo ativo de arquivado | sempre possível | não |
| `products` | `(tenant_id, barcode)` | **sim** | `422` (`BarcodeAvailable`, já existia) | sempre possível | não |
| `categories` | `(tenant_id, slug)` | **sim** | `422`, distinguindo | sempre possível | não |
| `tenants` | `slug` (global) | **sim** | erro de validação | sempre possível | não |
| `users` | `email` (global) | **sim** | reusa a identidade se ativa; recusa se arquivada | restore explícito | nunca duas identidades |

**Justificativa por entidade.**

- **Product SKU** — é identificador de negócio, e a F2.6 casa produtos por SKU
  com ERP e marketplace. Reaproveitar um SKU para um produto diferente faria a
  integração externa colar o histórico errado no item errado. O SKU arquivado
  continua pertencendo ao produto histórico, e voltar a usá-lo é restaurar
  aquele produto.
- **Category slug** — é mais apresentação que identidade externa, então a
  reutilização seria defensável. Não foi adotada por dois motivos: a tela de
  categorias tem restore, o que torna o reaproveitamento desnecessário; e uma
  política diferente da do produto, na mesma tela de catálogo, seria uma
  inconsistência que o operador paga para aprender. Vale revisitar se um dia
  houver demanda concreta.
- **Tenant slug** — é global e identifica o estabelecimento em URL, log e
  auditoria. Deixar outro estabelecimento assumir um slug histórico confunde
  exatamente o rastro que se quer preservar. Reativação é por restore.
- **User email** — identidade global desde a F1.8. Duas contas com o mesmo
  e-mail nunca podem existir. Com a conta **ativa**, criar vínculo reaproveita a
  mesma identidade e só acrescenta o vínculo e os papéis. Com a conta
  **arquivada**, a criação é recusada com mensagem, e restaurar é ato próprio de
  quem administra — é a regra do SEC-04 E3, preservada.

**Nenhuma migration foi necessária, e isso é a conclusão, não a economia.** A
política é "arquivado continua reservado", e é exatamente isso que os índices
únicos atuais fazem ao cobrir as linhas arquivadas. Mexer neles — somar
`deleted_at` à chave, ou soltar a constraint — implementaria a política oposta.
De passagem, a decisão evita duas armadilhas: a semântica de `NULL` em
`UNIQUE(..., deleted_at)` no MySQL, que permitiria mais duplicidade do que se
espera, e mutar o identificador histórico no delete, que quebraria auditoria e
restore.

**O que estava realmente quebrado era a aplicação não dizer o que o banco diz.**
Dois defeitos, ambos reproduzidos antes de corrigir:

- **A API não validava.** `StoreProductRequest` tinha a mensagem `sku.unique` e
  **nenhuma regra** `unique`; `UpdateProductRequest` idem; `StoreCategoryRequest`
  não validava `slug`. Um SKU ou slug repetido ia ao banco e voltava como
  **HTTP 500** com `SQLSTATE[23000]: Integrity constraint violation`. Registrado
  no teste vermelho.
- **A mensagem do painel não explicava.** `Rule::unique` conta os arquivados —
  correto para a política — mas responde "já existe" para um registro que
  nenhuma listagem mostra. Quem recebia a mensagem ia procurar e não encontrava.

**A correção.** Uma regra de validação, `IdentificadorDisponivel`, irmã de
`BarcodeAvailable` e com a mesma forma: consulta por `DB::table`, sem global
scope, com o estabelecimento explícito — a mesma pergunta que o índice único
faz. A diferença é que ela sabe responder *por que* o valor está ocupado, porque
lê `deleted_at`:

- ativo: *"já existe um produto ativo com este SKU."*
- arquivado: *"já existe um produto arquivado com este SKU. restaure o produto
  existente ou use outro SKU."*

Aplicada nos três requests de API que não validavam e, em substituição ao
`Rule::unique`, nos requests de produto, categoria e estabelecimento do painel —
para que API e painel digam a mesma frase. Nada no banco mudou.

**Restore nunca colide, por construção.** Como o identificador jamais é
liberado, não existe o cenário "A arquivado, B novo com o mesmo valor, restaurar
A". Há teste provando o encadeamento: arquiva, confirma que a criação com aquele
valor é recusada, restaura, e o registro volta com o identificador intacto.

**Isolamento por estabelecimento preservado.** `products` e `categories` seguem
únicos **por estabelecimento**: o mesmo SKU em dois estabelecimentos continua
permitido, inclusive quando em um deles o produto está arquivado. `tenants.slug`
e `users.email` seguem globais. Há teste para cada caso.

**MySQL e SQLite: uma divergência, documentada e inofensiva.** A suíte roda em
SQLite e o item mexe em unicidade, então o comportamento foi conferido também no
MySQL de staging, em transação revertida — nada foi gravado:

| Pergunta | MySQL 8.4 | SQLite |
|---|---|---|
| Linha arquivada continua ocupando o valor? | sim | sim |
| `COR01-CASE` colide com `cor01-case`? | **sim** | não |

A collation é `utf8mb4_unicode_ci`, insensível a caixa; o SQLite compara byte a
byte. A divergência é preexistente e **não produz erro de banco em nenhum dos
dois**: a consulta da regra usa a mesma coluna e a mesma collation do índice,
então aplicação e banco sempre dão a mesma resposta dentro do mesmo motor —
conferido no MySQL, onde a regra enxerga a linha arquivada inclusive com a caixa
trocada. Nenhum `lower()` foi adicionado: mudar a sensibilidade a caixa é
decisão de produto, não efeito colateral desta correção.

**Nada de dado foi perdido ou alterado.** Nenhum `forceDelete`, nenhum
identificador histórico renomeado, nenhuma entidade ativa sobrescrita, nenhum
dado movido entre estabelecimentos, nenhuma migration, nenhum backfill.

**Testes adicionados.** 23 testes, 61 assertions, em
`tests/Feature/Domain/SoftDeleteUniquenessTest.php`. Antes da correção, 11
falhavam; os 12 que já passavam documentam o que o projeto acertava — tenant
slug reservado, restore, identidade global de usuário, isolamento entre
estabelecimentos e o banco recusando duplicata arquivada nas três tabelas
globais. A suíte cobre API e painel, create, update e restore, e inclui testes
que atacam o banco direto, para provar que validação e constraint dizem a mesma
coisa.

**Por que Recomendado.** A F2.6 prevê sincronização com ERP e marketplace, que
casa produtos por SKU: um SKU reaproveitado quebraria a importação.

**Por que não Sim.** Não era falha de segurança e não corrompia dados — o banco
sempre preservou a integridade.

**Evidência da entrega (2026-10-07).** Suíte completa: 1185 testes — 1183 PASS,
0 FAIL, 0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 4705 assertions, contra
1162/1160/4644 do baseline anterior: +23 testes e +61 assertions, exatamente os
do COR-01. Testes focados: 23 PASS, 61 assertions. Domínio afetado — Products,
Admin, Tenancy, Identity, Companies e Domain: 562 PASS, 2827 assertions.
Regressões: SEC-01 35 PASS, SEC-02 21 PASS, SEC-03 23 PASS, SEC-05 25 PASS,
SEC-06 8 PASS. `php -l` sem erro nos 10 arquivos PHP alterados. Pint passou nos
11 do escopo. PHPStan: 17 erros, com totais **e conjunto de arquivos idênticos**
à rodada anterior. `git diff --check` limpo. As onze entradas
`Integrity constraint` do dia são do canal `testing`, às 20:07, da fase vermelha;
**nenhuma depois da correção**, e zero `5xx` no nginx. Nenhum container
reiniciado, nenhuma migration criada ou executada.

**Critérios de aceite.** Todos verdes: política explícita por entidade; SKU,
slug de categoria, slug de estabelecimento e e-mail com comportamento definido;
create sem `500` por duplicidade previsível; update tratando duplicidade e
ignorando o próprio registro; restore sem colisão possível; identidade global de
`users.email` e de `tenants.slug` preservadas; unicidade por estabelecimento
preservada em products e categories; API e painel com a mesma política e a mesma
mensagem; banco e validação alinhados; divergência MySQL/SQLite conferida e
documentada; nenhuma perda de dados; SEC-01, SEC-02, SEC-03, SEC-05 e SEC-06
verdes; suíte com 0 FAIL e 0 ERROR; nenhum achado novo de qualidade; staging
saudável; F2.6 não iniciada.

### PERF-01 — hasAnyPermission

**Média · Pendente · Bloqueia F2.6: Não** — Origem: laço da F1.5 (`8b34ee4`,
2026-08-13), com custo relevante a partir das Policies da FASE 02 e da FASE 03

**Problema.** `hasAnyPermission()` percorre a lista chamando `hasPermission()`,
e cada chamada executa `getPermissions()` do zero: uma consulta a
`rolesForTenant` mais o eager load de `permissions`, ou seja, duas consultas.
Não há memoização. O laço para na primeira permissão encontrada, então o pior
caso é a **negação**: para um usuário sem permissão, a `ProductPolicy` testa
três permissões e faz seis consultas, fora a de `canAccessTenant`.

**Relação com o SEC-01.** As Policies aplicadas nas 35 rotas colocam esse custo
em cada requisição da API. Com o SEC-01 fechado em `3a14703`, vale medir.

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
SEC-01 — API Authorization        ✅
        ↓
SEC-02 — API Authentication       ✅
        ↓
SEC-03 — TenantResolver           ✅
        ↓
SEC-05 — SendEmailAction          ✅
        ↓
SEC-06 — Secrets / .env.testing   ✅
        ↓
COR-01 — Soft delete + unicidade  ✅
        ↓
PERF-01 — hasAnyPermission        🔴 pendente, nunca bloqueou
        ↓
F2.6 — Integration APIs
```

| Grupo | Itens | Regra |
|---|---|---|
| **Bloqueadores obrigatórios** | SEC-04, SEC-01, SEC-02, SEC-03 | A F2.6 só começa com os quatro `Resolvido` |
| **Recomendados antes da F2.6** | SEC-05, SEC-06, COR-01 | Resolver antes; adiar exige decisão registrada nesta seção |
| **Dívida controlada** | PERF-01 | Pode seguir depois da F2.6; medir logo após o SEC-01 |

**Sobre a ordem.** O SEC-04 veio antes do SEC-01 por dependência arquitetural. O
SEC-01 aplicou as Policies às 35 rotas da API que não as usavam, e o
SEC-04 muda o significado e o alcance dessas Policies: `create-role` era coringa
em várias delas e a gestão de estabelecimentos quebrava o isolamento entre
clientes — ambos já corrigidos —, as escaladas por `update-role` e por
`manage-users` foram contidas em `2f1f6ea` e `2849c10`, e a avaliação da permissão
num estabelecimento diferente do da entidade foi fechada em `7433c5b`.
Espalhar as Policies pela API antes de corrigi-las levaria esses defeitos
para a API e obrigaria a refazer o SEC-01 e os seus testes. A ordem foi
respeitada: o SEC-01 foi fechado em `3a14703`, depois do SEC-04.

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

**Estado em 2026-10-07.** Primeiro item **cumprido**: os quatro bloqueadores
obrigatórios estão `Resolvido`, com testes — SEC-04 em `7433c5b`, SEC-01 em
`3a14703`, SEC-02 em `e306494` e SEC-03 em `342f4ad`. Segundo item **cumprido**:
SEC-05, SEC-06 e COR-01 resolvidos em `6b271ef`, `693a8ef` e `18eaa10`, nenhum
adiado. Terceiro item **cumprido**: tabela de acompanhamento, README e placar da
FASE 02 conferidos nesta data — o README estava defasado, afirmando SEC-01/02/03
pendentes e um baseline de 568 testes, e foi atualizado; o placar da FASE 02 e o
DEVELOPMENT_DASHBOARD não afirmavam status de pendência e seguem corretos.

**Os três critérios estão satisfeitos: a F2.6 está liberada.** Liberar não é
iniciar — começar a F2.6 é decisão de quem conduz o produto, e ela **não foi
iniciada**.

Fechar os bloqueadores obrigatórios também **não** torna o backend apto para o
PDV: esse marco é outro e exige a entidade Terminal, o vínculo
terminal → estabelecimento/empresa/filial, pareamento, credencial de máquina e
as rotas `/api/v1/pdv/*` — nada disso existe.

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

> 🟢 **Liberada e não iniciada.** As
> [pendências pré-F2.6](#pendências-bloqueadoras-pré-f26) obrigatórias e recomendadas
> estão encerradas. A trilha PDV-BE não inicia nem absorve a F2.6.

Integração com sistemas externos: cadastrar integração pelo painel, disparar
webhook de teste e auditar os envios. Modelos `Integration` (com credenciais
cifradas) e `IntegrationLog`. Especificação completa na seção F2.6 do
[ROADMAP_FASE_02](ROADMAP_FASE_02_FEATURES.md).

Sete das oito pendências tocam diretamente o que a F2.6 constrói: API sensível
(SEC-01, SEC-04), clientes de máquina com token (SEC-02), webhooks recebidos sem
usuário (SEC-03), destinos controlados pelo cliente (SEC-05), credenciais
cifradas com a `APP_KEY` (SEC-06) e sincronização por SKU (COR-01).

---

## PDV-BE — Backend para integração com PDV

Trilha própria aberta em **2026-10-08**, após a auditoria de retomada no HEAD
`9bb933e`, para preparar a integração futura do backend com o LucraOne PDV Java.
Usa IDs `PDV-BE-*`, seguindo as trilhas PM e ONB, sem alterar o placar de fases.

**Propósito.** Construir identidade operacional de Terminal, vínculos com
estabelecimento/empresa/filial, pareamento, autenticação de máquina e contratos
reais de API. Branch é o alvo operacional do PDV; Company é a empresa superior;
Tenant é o cliente lógico do SaaS.

- **É separada da F2.6.** F2.6 continua **LIBERADA e NÃO INICIADA**.
- **PDV-BE-01 é apenas fundação.** Concluí-la não torna o backend apto para PDV.
- **Java PDV:** Fases 1–3 concluídas conforme informado pelo responsável pelo
  produto; Fase 4 planejada e aguardando contratos reais do backend. A integração
  real permanece aguardando o marco de aptidão abaixo.
- **Não altera TenantResolver humano nem os findings fora do escopo.** A
  autenticação de máquina terá fronteira própria, sem escolher tenant por header.

### Acompanhamento da trilha PDV-BE

| ID | Etapa | Status | Depende de |
|---|---|---|---|
| PDV-BE-01 | Fundação pré-Terminal | **Concluído ✅** · `2d20395` | Auditoria de retomada ✅ |
| PDV-BE-02 | Entidade Terminal e vínculos | **Concluído ✅** · `1e96b20` | PDV-BE-01 |
| PDV-BE-03 | Pairing e provisionamento | **Concluído ✅** · `6887d60` | PDV-BE-02 |
| PDV-BE-04 | Machine credential e autorização | **Concluído ✅** · `316db63` | PDV-BE-02 e PDV-BE-03 |
| PDV-BE-05 | Endpoints base do PDV | **Concluído ✅** · `be40587` | PDV-BE-03 e PDV-BE-04 |

PDV-BE-03 preparou o fluxo de pairing, o PDV-BE-04 completou a emissão de
credencial e o PDV-BE-05 expôs os três contratos HTTP. A trilha está completa e
a integração inicial do PDV Java está desbloqueada.

### PDV-BE-01 — Fundação pré-Terminal

**Concluído ✅ · 2026-10-08 · `2d203951b2e824234b53b0e675ffe8e5b82bf5c3`**

**Escopo entregue.** BranchPolicy passa a usar o model real, registrada no
AppServiceProvider, sem `User->branches()`. Usa as permissões já existentes
`view-branches`, `view-all-branches` e `manage-branches`; exige conta/vínculo
ativos, tenant/contexto corretos e Company coerente com a filial. Não herda
autoridade de `manage-companies` nem cria permissões novas.

X-Request-ID é ativado uma vez no grupo API, incluindo health, login e APIs de
negócio. Gera ULID quando ausente/inválido; preserva IDs de 1–128 caracteres
alfanuméricos ASCII e `._:-`; compartilha o ID com Request, resposta, logger
Laravel e AuditLog. Respostas 401/403/404 de entidade/422 são cobertas.

**Arquitetura.** Terminal proposto como identidade própria da instalação, com
`tenant_id`, `company_id`, `branch_id` obrigatórios e `installation_id` UUID v4
público. Pairing de uso único e credencial revogável futura não usam login humano
nem X-Tenant-ID para autenticar/resolver contexto. Detalhes, invariantes, status,
contratos conceituais e questões abertas em
[PDV Backend Foundation](lucraone-backend/docs/architecture/pdv-backend-foundation.md).

**Limites.** Sem Terminal/model/table/migration, Branch API, pairing, credencial
de máquina ou rotas `/api/v1/pdv/*`. PERF-01 e demais findings continuam abertos.
Tenant SUSPENDED ainda pode ser resolvido pelo fluxo humano; o bloqueio de
Tenant/Company/Branch para máquinas é decisão pendente de PDV-BE-04.

**Evidência da entrega.** Gate inicial: `main`, HEAD/origin `9bb933e`, ahead/behind
0/0, árvore limpa e 40 migrations executadas, 0 pendentes. Baseline inicial
reproduzido: 1185 testes, 1183 PASS, 2 RISKY conhecidos e 4705 assertions.
Os testes novos foram executados antes da correção: 25 falharam e 2 passaram,
incluindo import incompatível, Policy não encontrada e ausência de correlação.
Após a correção: BranchPolicy 13 PASS/48 assertions; correlação 14 PASS/49
assertions. Regressões SEC-01 35 PASS, SEC-02 21 PASS, SEC-03 23 PASS, SEC-05
25 PASS, SEC-06 8 PASS e COR-01 23 PASS. Suíte relacionada: 414 testes,
412 PASS, 2 RISKY conhecidos e 1359 assertions. Suíte completa: 1212 testes,
1210 PASS, 0 FAIL, 0 ERROR, 2 RISKY conhecidos, 0 SKIPPED e 4802 assertions.

Sintaxe PHP e Pint no escopo aprovados; PHPStan mantém exatamente os 17 achados
preexistentes. A exceção global `class.notFound`, sem correspondência após a
correção do import, foi removida, sem esconder erro novo. `git diff --check`
limpo. Staging: health/login/up 200, raiz 302, ULID gerado e
`pdv-be-01-smoke` preservado; sem novos 5xx ou staging.ERROR nas verificações.
Nenhuma migration criada/executada, container reiniciado ou rebuild realizado.

**Findings preservados.** PERF-01; UUID em category_ids de entidades ULID;
User legado aparentemente órfão; senha default no exemplo; Tenant SUSPENDED
humano. Findings adicionais: construtor antigo de StructuredLoggingService;
FKs individuais não garantem igualdade tenant da Branch/Company; 404 sem rota
não entra no middleware do grupo API. Detalhes no documento de arquitetura.

### PDV-BE-02 — Entidade Terminal e vínculos

**Concluído ✅ · 2026-10-08 · `1e96b207a102d81ed089ab1f7693ef2cc6aadb89`**

**Escopo entregue.** Módulo Terminals com identidade ULID, HasTenant/TenantScope,
Factory coerente, relações Tenant/Company/Branch e inversas `terminals()`.
Schema mínimo: `id`, `tenant_id`, `company_id`, `branch_id`, `installation_id`,
`name`, `status`, timestamps. Nome administrativo obrigatório; sem código,
`paired_at`, `last_seen_at` ou soft delete. Status em strings/enum SQL, seguindo
o padrão existente: PENDING (default), ACTIVE, BLOCKED, REVOKED.

`installation_id` é UUID v4 público, nullable até pairing, único globalmente;
múltiplos NULLs permitidos. UUID é canonicalizado para minúsculas para impedir
que caixa burle unicidade no SQLite. ACTIVE exige instalação informada, mas
não autentica nem comprova pairing. REVOKED não permite reativação pelo model.
Transições operacionais completas e imutabilidade pós-pairing ficam futuras.

`TerminalAssignmentValidator`, chamado pelo evento `saving`, valida criação e
atualização Eloquent: Company e Branch existem e pertencem ao tenant indicado;
Branch pertence à Company indicada. Não confia no contexto para validar esses
IDs. Rejeita inconsistência, nome inválido, UUID inválido e status inválido com
`InvalidTerminalAssignment`. FKs individuais usam RESTRICT, inclusive diante
dos cascades existentes nos pais. SQL direto/bulk updates não disparam eventos;
não são fluxo oficial de escrita. Mudanças nos vínculos dos pais e concorrência
exigem tratamento explícito nos futuros fluxos administrativos; não foi criada
constraint composta nem alterado o schema dos pais.

TerminalPolicy registrada no AppServiceProvider, delegando à autoridade de
Branch: leitura `view-branches`/`view-all-branches`/`manage-branches`, gestão
`manage-branches`, temporariamente. Não há permissão própria de Terminal hoje;
a decisão sobre catálogo específico fica antes de expor UI/API. Exige contexto
igual ao `terminal.tenant_id`, conta/membership ativos, relações coerentes e
permissões no tenant correto. Declara viewAny/view/create/update/revoke;
não autoriza delete físico nem executa revogação de credenciais.

**Validação.** Gate: main e HEAD/origin `7cc1d1a`, 0/0, árvore limpa,
40 migrations executadas; baseline 1212 testes, 1210 PASS, 2 RISKY e 4802
assertions. Tests-first: 33 erros por classes ainda inexistentes. Resultado:
26 testes de domínio PASS/45 assertions e 13 de Policy PASS/43 assertions.
SEC-01 35, SEC-02 21, SEC-03 23, SEC-05 25, SEC-06 8, COR-01 23,
BranchPolicy 13 e correlação 14, todos PASS. Suíte relacionada: 485 testes,
483 PASS, 2 RISKY, 1538 assertions. Suíte completa: **1251 testes, 1249 PASS,
0 FAIL/ERROR, 2 RISKY preexistentes, 0 SKIPPED, 4890 assertions**.
Sintaxe/Pint/diff-check aprovados; PHPStan mantém os 17 achados preexistentes.

**Migration e staging.** `2026_10_08_120000_create_terminals_table` testada em
SQLite e MySQL descartável (FKs, RESTRICT, unicidade/NULL, rollback e reaplicação).
DDL revisado por `--pretend`; única migration pendente aplicada após backup
`/var/backups/lucraone/pdv-be-02-before-terminals-20261008.sql`, 139837 bytes,
root/600, concluído em 2026-10-08 19:44:54 UTC. Agora 41 executadas/0 pendentes;
SHOW CREATE validado. Sem Terminal real cadastrado. Smoke health/login/up 200,
landing 302; ULID gerado e `pdv-be-02-smoke` preservado. Sem 5xx no período
verificado. Um staging.ERROR de diagnóstico (`Missing branch allowed`) foi
produzido pelo script descartável: o caso usava união de arrays em vez de
substituição de branch_id. Caso corrigido e validação final PASS; nenhum erro
operacional novo identificado. Banco descartável e grant removidos.

**Limites.** Sem pairing, credential/abilities/auth de máquina, HasApiTokens no
Terminal, endpoints `/api/v1/pdv/*`, Terminal API/UI ou alteração no Java PDV.
Tenant SUSPENDED humano e demais findings continuam preservados. Backend
continua **NÃO apto para PDV**; F2.6 **LIBERADA / NÃO INICIADA**.

### PDV-BE-03 — Pairing e provisionamento

**Concluído ✅ · 2026-10-08 · `6887d60803fc9989b8623c6c2cc2b12a80b9dfd8`**

**Escopo entregue.** Domínio e serviços de emissão/consumo, sem HTTP nem
credencial. Tabela `terminal_pairing_codes`: id ULID, terminal_id obrigatório
(FK RESTRICT), selector público único, code_hash SHA-256 do segredo, expires_at,
attempts, consumed_at, invalidated_at e timestamps. Histórico preservado; hash
oculto na serialização. Índices somente para lookup por selector e Terminal.

Formato `SSSSSS.XXXXXXXXXXXX`: selector de 6 e segredo de 12 caracteres,
19 caracteres totais. Alfabeto `23456789ABCDEFGHJKMNPQRSTVWXYZ`, 30 símbolos;
random_int criptográfico, aproximadamente 59 bits de entropia no segredo.
Maiúsculas/minúsculas são equivalentes; sem normalização arbitrária de separador.
O aumento sobre 8 caracteres preserva entropia e permite selector independente.
Código/segredo nunca persistidos em claro; retorno somente na emissão. Sem
pepper ou segredo de configuração novo. TTL **10 minutos** e máximo **5
attempts**, centralizados em config/pdv.php.

`IssueTerminalPairingCode` aceita somente PENDING sem installation_id; recusa
ACTIVE/BLOCKED/REVOKED. Regeneração invalida os códigos anteriores não consumidos,
sem apagá-los. `ConsumeTerminalPairingCode` valida código/prazo/attempts/estado,
estrutura e UUID v4; associa instalação e promove PENDING → ACTIVE junto com
consumed_at numa única transação. Retorno tipado com IDs/status/instalação,
sem token ou credential. Replay falha, inclusive com a mesma instalação.

Ambos usam DB::transaction e lockForUpdate; Terminal é bloqueado antes do
pairing, na mesma ordem da emissão, evitando inversão de locks com regeneração.
Pais também são bloqueados e invariantes revalidadas no consumo. Provisionamento
não admite Tenant arquivado, active=false, SUSPENDED/CANCELLED; aceita ACTIVE ou
TRIAL ativos, seguindo isActive existente. Company e Branch devem ser ACTIVE.
Regra isolada no pairing: TenantResolver humano permanece intocado.

Attempts contam submissões bem formadas com selector existente e código ainda
válido, inclusive erro de segredo, UUID/estrutura e sucesso. Rejeições de domínio
persistem o contador antes de lançar PairingFailed; no quinto erro o registro é
invalidado. Código inexistente/malformado, expirado, invalidado ou consumido não
incrementa outra linha. Erro de infraestrutura/constraint concorrente reverte a
transação. Selector conhecido permite esgotamento dirigido: futuro endpoint
**deve** aplicar rate limit por IP e selector/Terminal, sem segredo nas chaves e
com erros públicos genéricos. Não há RateLimiter HTTP nesta etapa.

UUID v4 é canonicalizado, permanece único globalmente e passa a ser imutável
quando o primeiro valor não nulo é persistido. Após pairing, não pode ser trocado
nem apagado pelo model; caixa diferente do mesmo UUID é aceita. Extraída a regra
para InstallationId, reutilizada pelo validador e consumo. SQL/bulk/saveQuietly
continuam fora do fluxo oficial, como no PDV-BE-02; mudanças administrativas de
vínculos e re-pairing exigem fluxo futuro explícito.

Sem logs de código/hash/UUID e sem AuditLog artificial: o helper atual deriva
contexto humano/HTTP e gera request_id quando ausente. Histórico de pairing
registra o ciclo; camada administrativa futura deverá autorizar emissão pela
TerminalPolicy e definir auditoria contextual segura. Consumir não usa User,
X-Tenant-ID ou TenantResolver para escolher vínculos.

**Validação.** Gate main/HEAD/origin `f6315e3`, 0/0 e árvore limpa; 41 migrations.
Baseline inicial reproduzido: 1251 total, 1249 PASS, 2 RISKY, 4890 assertions.
Tests-first: 48 erros por classes ausentes. Resultado final: emissão 9 PASS/33
assertions, consumo 10 PASS/34, segurança/replay 29 PASS/91, transação 3 PASS/12.
Todas as regressões SEC-01 35, SEC-02 21, SEC-03 23, SEC-05 25, SEC-06 8,
COR-01 23, BranchPolicy 13, correlação 14, Terminal domínio 26 e Policy 13 PASS.
Suíte relacionada: 514 total, 512 PASS, 2 RISKY, 1651 assertions. Completa:
**1302 total, 1300 PASS, 0 FAIL/ERROR, 2 RISKY preexistentes, 0 SKIPPED,
5060 assertions**. Sintaxe/Pint/diff-check aprovados; PHPStan idêntico ao baseline
anterior, 17 achados, sem novo finding.

**MySQL e staging.** Banco descartável validou unique, FK/RESTRICT, datas/NULL,
attempts/expiração, rollback e reaplicação. Processos PHP independentes com
barreira de início validaram: mesmo código → um sucesso/um replay; mesmo UUID
entre tenants → um provisionamento; emissão concorrente → um código válido.
SQLite não é prova de concorrência multiprocess; essa evidência é MySQL.
Migration `2026_10_08_130000_create_terminal_pairing_codes_table` aplicada após
DDL --pretend e backup `/var/backups/lucraone/pdv-be-03-before-pairing-20261008.sql`,
141597 bytes, root/600, concluído em 2026-10-08 20:24:29 UTC. Agora 42
executadas/0 pendentes; SHOW CREATE validado. Banco descartável/grant removidos.
Staging health/login/up 200, landing 302; X-Request-ID gerado/preservado.
Sem novos 5xx ou staging.ERROR no período verificado. Nenhum Terminal/pairing real
criado, container reiniciado ou rebuild realizado.

**Limites.** Terminal pode ser pareado pelo domínio, mas não autentica requisições
operacionais. Sem PersonalAccessToken/HasApiTokens/abilities/machine auth,
endpoints `/api/v1/pdv/*`, UI ou Java PDV. Backend **NÃO APTO PARA PDV**;
F2.6 **LIBERADA / NÃO INICIADA**. Próximo: PDV-BE-04, ainda não iniciado.

### PDV-BE-04 — Machine credential e autorização

**Concluído ✅ · 2026-10-10 · `316db631c4f4e9f5c42132cdf9ae92b05268ccc1`**

**A ordem foi de segurança, não de conveniência.** Antes desta etapa o callback
do Sanctum terminava em `: true`: qualquer tokenable que não fosse `User`
autenticava só porque o guard considerou o token válido, sem nenhuma checagem de
estado. O guard não fecha essa porta — `auth.guards.sanctum.provider` é nulo
neste projeto, então o `hasValidProvider()` do Sanctum aceita qualquer tokenable
—, o que faz do callback o único ponto de controle. Por isso o fail-closed foi a
primeira alteração e foi provado por teste **antes** de o Terminal receber
`HasApiTokens`. Em nenhum momento existiu um Terminal tokenable aceito pelo ramo
`: true`: entre uma coisa e outra o Terminal caía em `return false`.

**Política de sujeito.** Decisão por tipo, fail-closed: `User` mantém
`User::isActive()` (SEC-02 intacto), `Terminal` passa por
`TerminalAuthenticationEligibility`, e qualquer outro sujeito — inclusive
tokenable órfão — é recusado. O callback só restringe: `$valido = false` vindo do
guard nunca é revertido.

**Terminal como sujeito.** Implementa `Illuminate\Contracts\Auth\Authenticatable`
e usa `HasApiTokens`. O contrato é exigência técnica verificada no código
instalado, não precaução: o guard devolve o tokenable como `$request->user()` e
`GuardHelpers::setUser()` declara o tipo — é por ele que `Sanctum::actingAs()`
passa. O trait sozinho dá `tokens()`/`createToken()`/`tokenCan()`, não
identidade. Não há password, remember_token, e-mail, login, provider próprio nem
password broker: `getAuthPassword()` e `getAuthPasswordName()` lançam
`LogicException` em vez de devolver string vazia, e `getRememberTokenName()`
devolve null, que é como o framework reconhece a ausência de "lembrar-me".

**Ability própria.** `MachineTokenAbility::TERMINAL_READ` = `pdv:terminal:read`,
em fonte separada do `TokenAbility` humano — cujo docblock já reservava essa
separação. Sem `*`, sem `business:read`/`business:write`. Abilities de venda,
sincronização, estoque ou pagamento não foram antecipadas: entram quando as APIs
correspondentes existirem.

**Expiração: o teto global do Sanctum foi verificado no Guard instalado (4.3.3),
não presumido.** `Guard::isValidAccessToken()` combina as duas expirações com E
lógico — `(! expiration || created_at > now - expiration) && (! expires_at ||
! expires_at->isPast())`. O `expiration` global (`SANCTUM_EXPIRATION_MINUTES`,
720) é medido sobre `created_at` e vale para **qualquer** tokenable, logo é teto
absoluto: um `expires_at` de máquina acima de 720 minutos seria ficção. Por isso
`pdv.machine_credentials.ttl_minutes` vale 720 — o que o projeto promete é igual
ao que o Sanctum cumpre — e há teste provando que um token com `expires_at` de um
ano ainda morre no teto global. **`SANCTUM_EXPIRATION_MINUTES` não foi alterado e
nenhum token humano foi revogado**; a auditoria prévia do banco confirmou 0
`personal_access_tokens` existentes, sem necessidade de migração de política.
Validade de máquina maior exigiria remover o teto global — o que tornaria todo
token humano sem `expires_at` um token sem prazo — ou dar à máquina um guard
próprio. Nenhuma das duas foi feita: é mudança de política de sessão humana,
fora do escopo desta etapa.

**Um token por Terminal, rotação e revogação.** Um Terminal operacional é uma
instalação física, e o `installation_id` é único e imutável desde o PDV-BE-03;
logo há no máximo uma credencial vigente. `IssueTerminalMachineCredential` revoga
antes de criar — nessa ordem, para que uma falha deixe o Terminal sem credencial
em vez de com duas. `RevokeTerminalMachineCredentials` é o ponto único de
deleção, restrito pela morphMany do tokenable (`tokenable_type` + `tokenable_id`),
nunca por nome, ability ou tenant_id; há teste provando que token humano e token
de outro Terminal não são alcançados.

**Mudança de decisão registrada: sair de ACTIVE revoga as credenciais.** A tabela
de status do PDV-BE-02 dizia, para `BLOCKED`, "tokens permanecem sujeitos ao
status". Esta etapa é mais estrita: um gancho Eloquent `updated` revoga
fisicamente as credenciais quando o status deixa de ser `ACTIVE`, tanto em
`BLOCKED` quanto em `REVOKED`. A negação por requisição continua sendo a garantia
principal — um Terminal bloqueado não autentica nem com token íntegro na mão —, e
a remoção resolve o passo seguinte: o desbloqueio não ressuscita uma credencial
que passou tempo fora de controle. Voltar a `ACTIVE` exige nova emissão, e há
teste provando que a antiga não volta. Para `REVOKED` a remoção física é
requisito, e a irreversibilidade do PDV-BE-02 segue valendo. Limite conhecido,
igual ao do validador de vínculos: o gancho é Eloquent — SQL direto, bulk update
e `saveQuietly` mudam status sem passar por ele e não são fluxo oficial.

**Enforcement por requisição.** `TerminalAuthenticationEligibility` exige Terminal
`ACTIVE` com `installation_id`, Tenant operacional (`active=true` e
`ACTIVE`/`TRIAL`, o mesmo `Tenant::isActive()` do fluxo humano, com soft delete
recusando), Company `ACTIVE`, Branch `ACTIVE` e coerência dos três vínculos —
reconferida porque as FKs são individuais e não há FK composta. `TRIAL` continua
aceito, como no pairing. A regra de "estrutura operacional" foi extraída para
`TerminalStructure` e é compartilhada com `PairingEligibility`; só a forma de
carregar difere (o pairing usa `lockForUpdate` por decidir transição, a
autenticação lê sem lock a cada requisição). A coerência de vínculos ficou em
método separado para não trocar o motivo de recusa que o pairing já devolvia.
Testes provam que suspender Tenant, Company ou Branch, ou bloquear o Terminal,
derruba um token já emitido sem que nada o toque — inclusive com o status
alterado fora do Eloquent, caso em que a linha do token permanece no banco e a
negação vem do estado.

**Contexto de máquina.** `TerminalContext` é singleton por requisição, no padrão
do `TenantContext`, e expõe terminal, tenant, company e branch. O middleware
`terminal.context` (`ResolveTerminalContext`) exige sujeito `Terminal`, recusa
`X-Tenant-ID` e preenche `TerminalContext` e `TenantContext` a partir do Terminal
autenticado — o `TenantContext` porque o `TenantScope` dos models de negócio o
consulta. Falha limpa os dois contextos, como o `recusar()` do `TenantResolver`.

**X-Tenant-ID é recusado, inclusive quando correto.** 403, seguindo a convenção
do `ResolveTenantMiddleware`. Aceitá-lo "porque coincide" ensinaria o cliente de
PDV a enviar o cabeçalho, e a divergência entre o que ele manda e o vínculo real
passaria a ser decisão de servidor. Teste prova que, recusada a requisição,
nenhum contexto ficou fixado.

**Fronteira bidirecional, por tipo.** Rota de máquina recusa `User`; rota humana
recusa `Terminal`. O **`TenantResolver` humano não foi alterado** — sua recusa por
tipo (`! $usuario instanceof User`) já era metade da fronteira, e continua sendo.
Como ability não prova tipo de sujeito, os dois casos são testados também com a
ability "certa" adulterada na fixture: `User` com `pdv:terminal:read` segue
recusado em rota de máquina, e Terminal com `business:read`/`business:write`
segue recusado em `/api/v1/products`, onde chega ao `TenantResolver` e é barrado
por tipo. A ability de máquina tem middleware próprio
(`machine.ability:pdv:terminal:read`), e não o `EnsureTokenAbility` humano, que
deriva ability do método HTTP.

**Pipeline documentado**, ainda sem rota registrada:
`auth:sanctum` → `terminal.context` → `machine.ability:<ability>` → controller PDV.

**Pairing passa a emitir a credencial.** `ConsumeTerminalPairingCode` emite
dentro da **mesma transação** da ativação, e `TerminalProvisioningResult` carrega
`credential` e `credentialExpiresAt`. O estado parcial que isso elimina é o pior
possível: Terminal `ACTIVE`, código consumido e nenhuma credencial — um PDV
pareado que não autentica, sem caminho de volta, porque o código é de uso único e
o `installation_id` é imutável. Testes provam que falha na emissão e falha ao
persistir o PAT desfazem ativação, vínculo de instalação e consumo. Texto puro do
token sai uma única vez, nunca é persistido (o banco guarda só o SHA-256 do
Sanctum), nunca é logado nem auditado, e `__debugInfo()` o censura em
`TerminalProvisioningResult` e `IssuedTerminalMachineCredential`.

**Teste do PDV-BE-03 atualizado conscientemente.** `PairingConsumeTest` exigia
`personal_access_tokens = 0` e a ausência do campo `credential` — expectativa
correta para o escopo antigo, em que provar a não emissão era o ponto. A regra
mudou: agora o teste exige exatamente uma credencial, deste Terminal, com o nome
`pdv-machine`, e que o texto puro não esteja no banco. O teste foi renomeado e o
motivo registrado no próprio docblock, em vez de contornado.

**O que não foi feito.** Nenhum endpoint `/api/v1/pdv/*`, controller, FormRequest
ou rota real — pertencem ao PDV-BE-05; as rotas dos testes são registradas dentro
dos próprios testes. **Nenhuma migration**: `personal_access_tokens` já tinha
morph `tokenable`, `tokenable_id` ULID, `abilities`, `expires_at` e
`last_used_at`, e o schema não foi alterado — nada de `terminal_id`, `tenant_id`,
`company_id` ou `branch_id` na tabela de tokens, porque esses dados vêm do
tokenable. **Nenhum refresh token** — auditado e dispensado: a política é
credencial direta com rotação, e um segundo segredo de longa duração só
ampliaria a superfície. Sem device fingerprinting: `installation_id` continua
identificador público, não prova de posse, não é exigido por requisição e não
autentica nada. Recuperação de credencial perdida continua questão operacional
futura (novo processo administrativo ou re-pairing). F2.6, PERF-01 e os findings
fora de escopo seguem intocados.

**Validação.** Gate main/HEAD/origin `f082c3d`, 0/0 e árvore limpa; 7 serviços no
ar; 42 migrations/0 pendentes. Baseline inicial reproduzido: 1302 total, 1300
PASS, 2 RISKY, 5060 assertions. Auditoria do Sanctum 4.3.3 feita no vendor
instalado (Guard, HasApiTokens, PersonalAccessToken) e do banco (0 PATs, 0 com
`expires_at`, 0 sem — nenhum dado sensível exibido). Suítes novas: política de
sujeito 7 PASS, Terminal autenticável/enforcement 20, credencial 11, ciclo de
vida 12, contexto/fronteira 15, pairing+credential 9 — 74 testes novos. Terminals
completo: 125 PASS. Regressões todas verdes nas contagens esperadas: SEC-01 35,
SEC-02 21, SEC-03 23, SEC-05 25, SEC-06 8, COR-01 23, BranchPolicy 13,
correlação 14, Terminal domínio 26, TerminalPolicy 13, pairing PDV-BE-03 51.
Suíte completa: **1376 total, 1374 PASS, 0 FAIL/ERROR, 2 RISKY preexistentes,
0 SKIPPED, 5248 assertions**. `php -l` em cada arquivo editado, Pint PASS em 40
arquivos do escopo, `git diff --check` limpo, PHPStan 17 achados — idêntico ao
baseline, sem novo finding. Um achado novo surgiu durante a rodada
(`booleanNot.alwaysFalse` em `EnsureMachineTokenAbility`) e foi eliminado
removendo a checagem redundante de token nulo, já coberta pelo `tokenCan()` do
Sanctum, com teste cobrindo o caso. Staging: health/login/up 200, landing 302,
X-Request-ID gerado, preservado e presente em 401; nenhum `staging.ERROR` ou 5xx
novo, e nenhum Terminal, token ou pairing real criado. Rotas de API seguem 49,
nenhuma de PDV ou Terminal.

**Limites.** O motor de autenticação de máquina existe e está testado, mas **nada
o expõe por HTTP**: sem os três endpoints, sem rate limiting de pairing HTTP, sem
contrato público de erros e sem validação ponta a ponta em staging. Backend
**NÃO APTO PARA PDV**; F2.6 **LIBERADA / NÃO INICIADA**. Próximo: PDV-BE-05,
ainda não iniciado.

### PDV-BE-05 — Endpoints base do PDV

**Concluído ✅ · 2026-10-10 · `be4058748bba6e77030a4df109e171d1179ae3cf`**

**Escopo entregue.** Os três contratos HTTP que o PDV Java precisa para começar,
e nada além disso. A etapa é camada de borda: HTTP → validação estrutural →
serviço existente → resposta pública. Nenhuma regra de pareamento, credencial ou
elegibilidade foi reimplementada; o domínio das etapas anteriores continua sendo
a autoridade.

| Endpoint | Auth | Middleware | Status | Rate limit |
|---|---|---|---|---|
| `GET /api/v1/pdv/health` | público | — | 200 | — |
| `POST /api/v1/pdv/terminals/pair` | público | `throttle:pdv-pairing` | 200 · 422 · 429 | IP e selector |
| `GET /api/v1/pdv/terminal` | machine credential | `auth:sanctum` → `terminal.context` → `machine.ability:pdv:terminal:read` | 200 · 401 · 403 | — |

Rotas de API: 49 → **52**. Novo módulo `app/Modules/Pdv`, com `Routes/api.php`
exigido por `routes/api.php`, no mesmo padrão modular dos outros cinco módulos.

**Health é contrato de cliente, não diagnóstico.** Devolve apenas
`{"data":{"status":"ok","api":"pdv","version":"v1"}}`. O `/api/health` que já
existia continua intacto, com estado de banco e cache, para quem opera a
plataforma. O do PDV é consumido por aplicativo instalado em loja, fora do nosso
perímetro: ambiente, versões de PHP/Laravel, host, caminho, commit, timestamp e
estado de dependências não têm consumidor ali e cada um ajudaria quem mapeia a
infraestrutura. Teste assevera a ausência de cada um desses termos no corpo.

**IP real atrás do proxy: verificado contra a stack, não presumido.** Antes de
escrever qualquer freio por IP, a cadeia foi medida de ponta a ponta — Internet
→ nginx do host (TLS) → `127.0.0.1:9010` → nginx do container → php-fpm. O que
chega ao PHP como `REMOTE_ADDR` é `172.21.0.1`, o gateway da rede do compose,
dentro de `172.16.0.0/12` e portanto confiado pelo `trustProxies` existente. O
vhost do host define `X-Forwarded-For $proxy_add_x_forwarded_for`, que
**acrescenta à direita** o endereço real. Executando requisições pela stack real
do Laravel:

| Caso | `$request->ip()` |
|---|---|
| cliente público `198.51.100.42` | `198.51.100.42` ✅ |
| cliente injeta `203.0.113.7` à esquerda do XFF | `198.51.100.42` ✅ spoof ignorado |
| sem XFF (acesso direto ao container) | `172.21.0.1` |
| `REMOTE_ADDR` não confiado | o próprio, XFF descartado ✅ |

O Symfony lê a entrada mais à direita que não seja proxy confiado, que é
exatamente a que o nginx do host acrescentou. **Rate limiting por IP é confiável
para cliente da Internet**, e nenhuma alteração em `TrustProxies` foi
necessária. Limite conhecido e registrado: um cliente que *já venha* de faixa
privada confiada poderia injetar a entrada à esquerda e ser lido por ela — é
inerente ao modelo de proxy confiado e não alcançável da Internet, já que o
nginx do container só escuta em `127.0.0.1:9010`.

**Freio do pareamento, em duas dimensões.** Limiter nomeado `pdv-pairing`, sem
reaproveitar o `api-login` humano:

- por IP: 20 tentativas / 10 minutos;
- por selector: 5 tentativas / 10 minutos.

As janelas acompanham o domínio — o código vive 10 minutos, então contar em 10
minutos é contar a vida útil do alvo — e o teto por selector é igual ao
`max_attempts` do PDV-BE-03, para o freio de HTTP não ser mais frouxo que o do
banco. O teto por IP é mais alto porque uma loja instala vários caixas atrás de
uma única saída de rede, mesma lógica do `api-login`. **Os três controles são
complementares:** attempts travam o ataque a um código mas não impedem varrer
muitos; o IP trava volume de uma origem mas não ataque distribuído; o selector
trava insistência contra um alvo vindo de muitos IPs, e trocar de selector
devolve o atacante ao freio por IP. Só a parte **pública** do código entra na
chave (`pdv-pair:selector:<sha256 do selector>`), por `PairingCode::selectorFrom()`,
que extrai o selector e nunca devolve nem registra o segredo; código sem selector
plausível fica apenas sob o freio por IP, em vez de virar chave de cache com
segredo dentro. Teste prova que a tentativa recusada por volume **não chega ao
serviço** — o `attempts` no banco não se move.

**Contrato público de erro, escopado em `/api/v1/pdv/*`.** Forma fixa:

```json
{"error": {"code": "...", "message": "...", "request_id": "..."}}
```

| `code` | HTTP | Quando |
|---|---|---|
| `validation_error` | 422 | requisição malformada; único que detalha campo, em `error.errors` |
| `pairing_failed` | 422 | qualquer falha de pareamento |
| `unauthenticated` | 401 | credencial ausente, inválida, expirada, rotacionada, ou sujeito/estrutura fora de operação |
| `forbidden` | 403 | autenticado mas sem autorização de rota, contexto ou ability |
| `rate_limited` | 429 | freio do pareamento, com `Retry-After` |
| `internal_error` | 500 | erro inesperado, sempre genérico |

`error.request_id` é exatamente o valor do cabeçalho `X-Request-ID`, lido do
próprio Request já normalizado pelo `RequestCorrelationMiddleware` — sem
duplicar aquela implementação. Vai no corpo além do cabeçalho porque quem atende
a loja costuma ter a tela, não o header. As 49 rotas anteriores **não mudaram de
contrato**: cada renderizador devolve null fora do prefixo PDV, e há teste
provando que `/api/v1/products` sem token segue respondendo
`{"message":"Não autenticado"}` e que o login humano mantém o formato antigo de
validação.

**Falha de pareamento não vira oráculo.** O domínio distingue selector
inexistente, segredo errado, expirado, consumido, invalidado, Terminal
inadequado e estrutura fora de operação. Todos saem como o **mesmo** 422
`pairing_failed`, com a mesma mensagem e sem `errors` — saber que o selector
existe mas o segredo está errado diria ao atacante que ele acertou metade de um
código de 18 caracteres. Teste com data provider percorre os seis casos e
compara status, código e mensagem. O motivo real continua disponível no domínio,
para teste e log interno.

**Entrega da credencial.** 200, não 201: o Terminal já existia, pré-cadastrado
pela administração; o que a chamada cria é o vínculo da instalação e a
credencial. A resposta vai com `Cache-Control: no-store, private` — é a única
resposta da API que carrega segredo de longa duração, e sem isso um proxy no
caminho da loja poderia guardar e reentregar a credencial de um Terminal para
outro. O texto puro aparece **só aqui** e não é recuperável depois; o
`GET /api/v1/pdv/terminal` devolve apenas `credential.expires_at`.

**Contrato mapeado campo por campo.** Nenhum model é serializado: `return
$terminal` ou `toArray()` faria qualquer coluna, cast ou relação futura entrar no
contrato sem decisão. O `PdvTerminalPayload` lista explicitamente o que é
público, e só com coluna que existe de verdade — `Company` **não tem** `name`,
tem `trade_name`; `legal_name` e `document` ficaram de fora porque dado
cadastral/fiscal entra junto dos contratos fiscais. Testes fecham a estrutura
com `array_keys`, então acrescentar campo exige editar o arquivo e o teste.
Configuração operacional (timezone, moeda, parâmetros de caixa) **não** foi
inventada: não há consumidor ainda, e campo sem consumidor é contrato que se paga
para manter — será definido junto dos contratos funcionais.

`credential.expires_at` no GET foi incluído com consumidor concreto: o PDV opera
o dia inteiro, a única recuperação hoje é novo pareamento presencial, e saber a
hora da expiração permite avisar o operador antes do turno virar em vez de
descobrir com um 401 no meio de uma venda. Vem de `currentAccessToken()`, sem
token nem hash.

**Validação estrutural sem enfraquecer o domínio.** `PairTerminalRequest` valida
`pairing_code` como string de tamanho exato e `installation_id` como **`uuid:4`**,
com versão explícita — a regra `uuid` sem parâmetro aceita qualquer versão e
seria mais frouxa que o `InstallationId::normalize()` do domínio, que exige v4.
Validação de HTTP nunca pode ser mais permissiva que a do domínio. Mensagens não
ecoam o valor enviado, e teste prova que nem o código nem um payload de 5000
caracteres voltam no corpo do erro.

**Segredos fora do log.** Teste com `Log::spy()` prova que um pareamento
bem-sucedido não registra nada e não cria `audit_logs`. Verificado também no log
real de staging: `Object(SensitiveParameterValue)` aparece 436 vezes — o
`#[SensitiveParameter]` do domínio redige os argumentos nos stack traces —,
`access_token` e `Bearer` aparecem zero vezes, e uma busca pelo formato real do
código (`SELECTOR.SEGREDO`) não encontra nenhuma ocorrência.

**Validação.** Gate main/HEAD/origin `9268eac`, 0/0 e árvore limpa; 7 serviços no
ar; 42 migrations/0 pendentes. Baseline inicial reproduzido: 1376 total, 1374
PASS, 2 RISKY, 5248 assertions. Tests-first: as suítes de health e pareamento
foram escritas e rodadas vermelhas antes da implementação. Suítes novas, todas
pelas rotas reais: health 6 PASS/23 assertions, pareamento 25/340, rate limit
7/114, Terminal 28/425, contrato de erro 12/84, ponta a ponta 4/134 — **82 testes
novos, 1120 assertions**. Regressões todas verdes nas contagens esperadas:
SEC-01 35, SEC-02 21, SEC-03 23, SEC-05 25, SEC-06 8, COR-01 23, BranchPolicy 13,
correlação 14, Terminal domínio 26, TerminalPolicy 13, Terminals completo 125.
Suíte completa: **1458 total, 1456 PASS, 0 FAIL/ERROR, 2 RISKY preexistentes,
0 SKIPPED, 6368 assertions**. `php -l` em cada arquivo editado, Pint PASS em 41
arquivos do escopo, `git diff --check` limpo, PHPStan 17 achados — idêntico ao
baseline, sem novo finding. Nenhuma migration e nenhuma alteração no schema de
`personal_access_tokens`.

**Staging ponta a ponta, com fixture sintética e remoção completa.** Backup
`/var/backups/lucraone/pdv-be-05-before-smoke-20261010-153355.sql`, 143080 bytes,
root/600, 37 tabelas, concluído em 2026-10-10 15:33:55 UTC. Staging tinha dois
tenants com aparência de cliente real (`Mercadinho da Dona Cida`,
`Maxi Massas Vila Maria`) e nenhuma Branch — nenhum deles foi tocado. Foi criada
estrutura **inteiramente sintética** marcada `PDV-BE-05-SMOKE-<ULID>`, depois de
confirmar que `TenantCreated` e `CompanyCreated` não têm listener algum, que não
há observer, Mail ou Notification nesses módulos e que existem 0 regras de
automação — ou seja, nada externo é disparado. Fluxo por HTTPS real: pareamento
200 com credencial e `Cache-Control: no-store, private`; `GET terminal` 200 com
os quatro IDs coerentes e sem token no corpo; `X-Tenant-ID` 403 `forbidden`;
replay 422 `pairing_failed`; `X-Request-ID` enviado preservado nos três
endpoints. Limpeza em ordem segura de FK, cada `DELETE` com o id exato — PAT 1,
pairing 1, Terminal 1, Branch 1, Company 1, Tenant 1 (forceDelete, por causa do
soft delete). Conferido depois: 0 registros com a marca, incluindo soft-deleted,
e os totais de volta ao pré-smoke (3 tenants, 1 company, 0 branches, 0 terminals,
0 pairing codes, 0 PATs, 0 audit_logs). Nenhuma credencial temporária ficou
ativa; os arquivos com código e token foram apagados com `shred`. Nenhum número
de código ou token foi impresso em nenhum momento.

**Limites.** Infraestrutura de integração, não operação comercial. Não existe
endpoint de produto, preço, estoque, venda, pagamento, caixa, sincronização ou
fiscal; a ability de máquina segue sendo apenas `pdv:terminal:read`. Credencial
perdida ainda exige novo pareamento presencial — não há recuperação nem rotação
por HTTP. `installation_id` continua identificador público, sem fingerprinting
nem detecção de clonagem. F2.6 **LIBERADA / NÃO INICIADA**; Java PDV não foi
alterado.

### Marco — Backend apto para integração inicial do PDV

**ATINGIDO ✅ · 2026-10-10 · PDV-BE-05.** Os dez requisitos mínimos estão
cumpridos:

- ✅ Terminal implementado com identidade própria — PDV-BE-02, autenticável no PDV-BE-04;
- ✅ vínculos Terminal → Tenant/Company/Branch e invariantes validadas — PDV-BE-02;
- ✅ pairing de curta duração, uso único e proteção de replay — PDV-BE-03;
- ✅ machine credential, expiração e revogação — PDV-BE-04;
- ✅ autenticação/autorização de máquina isolada de User e abilities humanas — PDV-BE-04;
- ✅ status operacionais de Tenant, Company e Branch aplicados à máquina, por requisição — PDV-BE-04;
- ✅ `GET /api/v1/pdv/health`, `POST /api/v1/pdv/terminals/pair` e
  `GET /api/v1/pdv/terminal` implementados — PDV-BE-05;
- ✅ X-Request-ID ativo, inclusive nos erros dos contratos PDV — PDV-BE-05, testado em 401, 403, 422, 429 e falha de domínio;
- ✅ testes automatizados de contrato, isolamento e segurança — PDV-BE-05, 82 testes pelas rotas reais;
- ✅ staging validado com os contratos reais — PDV-BE-05, ponta a ponta por HTTPS com fixture sintética removida.

**O que este marco significa, exatamente:** o backend possui os contratos
básicos necessários para o aplicativo PDV **iniciar** sua integração. É o que
desbloqueia a Fase 4 do Java.

**O que NÃO significa:** não é "PDV completo" e não é "produção fiscal pronta".
Não existe nenhum endpoint comercial — produto, preço, estoque, venda,
pagamento, caixa, sincronização, fiscal —, a ability de máquina é apenas
`pdv:terminal:read`, e recuperação de credencial perdida continua exigindo novo
pareamento presencial. Operação real de loja depende dos contratos funcionais,
que ainda não existem.

**Próximo passo:** LucraOne PDV Java — Fase 4, contrato e conectividade.
PDV-BE-05 não inicia o Java nem a F2.6.

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
  não iniciada. Os itens das
  [pendências pré-F2.6](#pendências-bloqueadoras-pré-f26) estão encerrados.
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
| PM-04A | Quantidade coerente com a unidade | **Concluído ✅** · `d0ae879` | PM-01 |
| PM-04B | Snapshot histórico do item | **Concluído ✅** · `dc23e56` | PM-04A ✅ |
| PM-04C | Semântica de linhas para o futuro PDV | **Concluído ✅** · `9cbca48` | PM-04B ✅ |
| PM-05 | Dados fiscais do produto | **Concluído ✅** · `d958a50` | — |

Como nas pendências, uma etapa só passa a `Concluído` com testes que provem a
entrega.

```
PM-01 — barcode + unit                ✅ CONCLUÍDO
        ↓
PM-02A — product_packages             ✅ CONCLUÍDO
        ├─ PM-02B — entrada por embalagem      ✅ CONCLUÍDO
        └─ PM-03 — resolução exata de barcode  ✅ CONCLUÍDO
        ↓
PM-04A — quantidade coerente com a unidade   ✅ CONCLUÍDO · d0ae879
        ↓
PM-04B — snapshot histórico do item          ✅ CONCLUÍDO · dc23e56
        ↓
PM-04C — semântica de linhas para o PDV      ✅ CONCLUÍDO · 9cbca48
        ↓
PM-05 — dados fiscais do produto      ✅ CONCLUÍDO · d958a50
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

**Estado encontrado na auditoria de 2026-09-23 (histórico, antes do PM-04A).**

- `order_items.quantity` é `decimal(14,3)`; `unit_price` e `total` são
  `decimal(14,2)`;
- `OrderItem` guarda snapshot só de `sku` e `name`. Não há `unit` nem `barcode`;
- API (`StoreOrderRequest`) e painel (`StoreWebOrderItemRequest`) validam
  `quantity` só como `numeric|min:0.001`: Product `UN` aceitava 1,5 à época;
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

**Concluído ✅ e publicado em 2026-10-05** — Depende de: PM-01 ✅

**Commit funcional:** `d0ae879de8e4c389e6584b9c9f576450d37fcc74` —
`feat(sales): validar quantidade conforme unidade do produto`.

**Entrega.** Quantidade coerente com `Product.unit` em pedidos e estoque:

| `Product.unit` | Regra entregue | `min` / `step` dos inputs existentes |
|---|---|---|
| `UN` | apenas inteira positiva, mínimo 1; frações reais rejeitadas | `1` / `1` |
| `KG` | positiva, mínimo `0.001`, até 3 casas decimais | `0.001` / `0.001` |

Para `UN`, `2`, `2.0`, `2.00` e `2.000` são equivalentes e persistem como
`2.000`. Para `KG`, `1`, `1.0`, `1.00` e `1.000` são aceitos. O contrato HTTP
continua usando ponto decimal, sem nova normalização de vírgula.

**Validação e precisão.** A regra central `ProductQuantity` usa `BigDecimal`
para validar e normalizar quantidades; float não é autoridade para quantidade.
Rejeita zero, negativos, notação científica, NaN/INF quando aplicável, frações
reais em `UN`, mais de 3 casas em `KG` e overflow antes do banco. O limite é
`99999999999.999`, compatível com os 11 dígitos inteiros e 3 decimais do schema.
A proteção também cobre somas de itens e entradas de estoque. Literais numéricos
JSON são preservados para não perder notação ou escala na decodificação, sem
alterar as normalizações dos demais campos HTTP.

**Integração.** Web, API, `OrderService` e Inventory reutilizam a mesma regra.
O backend permanece autoritativo, inclusive em chamadas diretas que não passam
por FormRequest. Erros têm feedback controlado, com HTTP 422 na API e erro de
validação no Web; as transações existentes evitam persistência parcial.
O frontend apenas acompanha a unidade selecionada com os atributos acima.

**Estoque.** Operações críticas, reservas, liberações, saldo disponível e
comparações relevantes passaram a usar precisão decimal. Exemplos validados
com assertions em strings:

| Operação | Resultado |
|---|---|
| `0.100 + 0.200` | `0.300` |
| `0.300 - 0.100` | `0.200` |
| `0.200 - 0.200` | `0.000` |
| `0.300 - 0.301` | rejeitada por saldo insuficiente |

**Schema.** Nenhuma migration necessária: `order_items.quantity`, saldos,
reservas e quantidades das movimentações já usavam `DECIMAL(14,3)`.

**Evidência da entrega (2026-10-05).** Suíte completa: 949 testes — 947 PASS,
0 FAIL, 0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 3627 assertions. Os dois
RISKY conhecidos não foram corrigidos. Testes focados: 152 PASS, 0 FAIL,
583 assertions. Frontend, build, Pint dos arquivos PHP alterados/criados e
`git diff --check` passaram.

**Fora do PM-04A:** snapshot de unidade no `OrderItem` / PM-04B, snapshot de
`barcode`, código de barras lido, `product_package_id`, ProductPackage adicional,
remoção da unicidade `(order_id, product_id)`, linhas repetidas e semântica de
linhas do PDV / PM-04C, scanner, PDV, fiscal, preço, margem, relatórios e SEC.

#### PM-04B — Snapshot histórico do item

**Concluído ✅ e publicado em 2026-10-06** — Depende de: PM-04A ✅

**Commit funcional:** `dc23e56e927304c57d57a11863d021bdc02dbf8d` —
`feat(sales): preservar unidade histórica no item do pedido`.

**Objetivo entregue.** Preservar no `OrderItem` o significado histórico de
`quantity`, com snapshot da unidade no momento da criação da linha.

- `order_items.unit` guarda o código técnico `UN` ou `KG`;
- migration aditiva: coluna `VARCHAR(6)`, nullable e sem default, sem alteração
  das colunas, índices ou FKs existentes; rollback remove somente `unit`;
- novas linhas capturam `Product.unit` no servidor, sem usar a unidade enviada
  pelo cliente como autoridade;
- UN e KG permanecem históricos: alterar `Product.unit` posteriormente não
  reinterpreta o pedido antigo;
- `sku`, `name`, `product_id` e `unit` são preservados como identidade
  histórica; acumulação com a mesma unidade mantém esses campos;
- acumulação com unidade divergente ou desconhecida é bloqueada;
- movimentações de estoque são bloqueadas quando a unidade histórica é
  desconhecida ou diverge do Product atual, sem conversão automática;
- API expõe `unit`, inclusive NULL; painel exibe a unidade histórica e identifica
  legado como "unidade histórica desconhecida", sem fallback para Product;
- proteção de identidade no Model usa eventos Eloquent; updates em massa e SQL
  direto podem ignorar esses eventos;
- nenhuma mudança em Reporting nesta etapa.

**Legado e backfill.** Itens antigos sem snapshot permanecem com `unit = NULL`.
Não existe trilha confiável para reconstruir a unidade histórica; portanto não
há backfill presumido com `Product.unit` atual nem informação histórica inventada.

**Evidência da entrega (2026-10-06).** Suíte completa: 973 testes — 971 PASS,
0 FAIL, 0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 3730 assertions. Os RISKY
`test_passwords_not_logged_in_audit` e `test_user_email_properly_protected`
continuam sem correção. Testes focados: 101 testes, 556 assertions, 0 FAIL e
0 ERROR; 24 testes de snapshot incluem migration, API, Web e estoque. Security:
185 testes, 183 PASS, 0 FAIL, 0 ERROR e 2 RISKY preexistentes. Frontend: 5 arquivos
de testes passaram; Pint dos seis arquivos PHP alterados/criados e
`git diff --check` passaram. Nenhum asset alterado, sem necessidade de build.

**Reporting — dívida conhecida.** Relatórios ainda podem misturar UN e KG,
o ranking ainda não separa por unidade e alguns rótulos fixos "un" precisam
revisão futura. Essa dívida não bloqueia o encerramento do PM-04B.

**Fora desta entrega.** PM-04C, PDV, fiscal, Reporting, SEC, ONB e staging.
Na conclusão do PM-04B, Cliente Teste passou a ser a próxima prioridade
operacional. Cliente Teste, PM-04C e PM-05 estão agora concluídos; a próxima
prioridade operacional será reavaliada após o fechamento documental do PM-05.

**Código de barras — decisão adiada.** `barcode` não entrou no PM-04B.
São dois conceitos diferentes, não necessariamente iguais:

- `Product.barcode` — o código do Product base;
- código de barras efetivamente lido.

| Registro | Código |
|---|---|
| `Product.barcode` | 789AAA |
| `ProductPackage.barcode` | 789BOX |
| lido pelo scanner | 789BOX |

O PM-03 resolve os dois códigos para o mesmo Product base. A estratégia de
snapshot de código de barras foi adiada nessa entrega. No PM-04C,
`presentation_barcode` passou a guardar o código comercial da apresentação,
sem representar o código efetivamente lido nem a origem operacional.

#### PM-04C — Semântica de linhas para o futuro PDV

**Concluído ✅ e publicado em 2026-10-06** — PM-04B ✅; Cliente Teste concluído.

**Commit funcional:** `9cbca4840d53353ef8244e1854508f1bf38938c4` —
`feat(sales): adicionar semântica de apresentação às linhas do pedido`.

**Entrega.** Semântica de apresentação comercial nas linhas do pedido:

- venda base e por embalagem; o mesmo Product pode ter múltiplas linhas,
  permitindo avulso + package e duas embalagens diferentes no mesmo pedido;
- preço diferente cria linha distinta; nova adição não reprecifica a quantidade
  anterior. Mesmo package compatível acumula; fator ou snapshots diferentes
  criam nova linha;
- `quantity` continua quantidade base e `unit_price` continua preço efetivo por
  unidade base. Inventory não foi remodelado e permanece exclusivamente base-only:
  3 UN avulsas + 1 caixa de 12 movimentam 15 UN;
- snapshots de apresentação preservados no OrderItem, sem reconstrução pelo
  cadastro atual; remoção por identidade da linha (`OrderItem.id`);
- API expõe os campos aditivos e o painel mostra apresentação histórica;
- nenhuma interface operacional de PDV nem pricing específico por package.

**Campos.** `sale_presentation_type` (`base` ou `package`),
`product_package_id`, `package_name`, `package_factor` e
`presentation_barcode`: todos nullable, sem default de negócio.
`product_package_id` é referência opcional com FK `ON DELETE SET NULL`;
nome, fator e barcode históricos permanecem após remoção de ProductPackage,
sem destruir a interpretação da venda. Os snapshots são protegidos por eventos
Eloquent; updates em massa e SQL direto podem ignorar essa proteção.

**Identidade e índice.** Removida `UNIQUE(order_id, product_id)` para permitir
apresentações ou preços distintos; mantido índice não-único adequado.
Seleção manual e barcode da mesma apresentação comercial podem acumular.
`presentation_barcode` guarda o barcode da apresentação no momento da venda,
não a origem operacional scanner/manual. Essa origem não integra a identidade
comercial. Alteração posterior de fator ou barcode preserva a linha antiga.

**ProductPackage.** Continua apresentação comercial/conversão, sem virar
Product separado, sem estoque por embalagem e sem preço próprio nesta etapa.
Venda package valida tenant, Product, fator e unidade UN no servidor; Product
que passou a KG não aceita nova venda por embalagem. O estoque histórico usa a
quantidade base persistida, sem consultar o fator atual.

**Legado.** Linhas antigas permanecem com os campos de apresentação NULL.
Não há backfill presumido nem inferência de base/package pela quantidade.
As garantias de quantidade do PM-04A e de unidade histórica do PM-04B permanecem.

**Concorrência.** OrderService serializa alterações do mesmo pedido com
transação e lock da Order. A validação automatizada usa SQLite e caracteriza a
transação/solicitação de lock; não prova concorrência real entre processos.

**Evidência da entrega.** Suíte completa: 1007 testes — 1005 PASS, 0 FAIL,
0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 3880 assertions. Os RISKY
`test_passwords_not_logged_in_audit` e `test_user_email_properly_protected`
continuam sem correção. PM-04C: 30 testes de apresentação e 4 de migration
aprovados. Regressão focada: 239 testes, 970 assertions, sem FAIL/ERROR.
Frontend: 5 arquivos de testes aprovados. Pint dos arquivos PHP da rodada e
`git diff --check` passaram.

**Limites e dívidas preservados.** Não foi implementado pricing por
apresentação/package: preços comerciais não divisíveis exatamente pela unidade
base no contrato monetário atual ficam para evolução futura, sem arredondamento
silencioso. Reporting permanece com mistura UN/KG, ranking por Product sem
separação por unidade/apresentação e labels fixos. Essas dívidas não bloqueiam
o encerramento do PM-04C. PDV operacional, fiscal, SEC, ONB e staging ficaram
fora desta entrega. Após essa publicação, PM-05 foi concluído em 2026-10-06
(`d958a50`). A próxima prioridade operacional será reavaliada após seu fechamento
documental.

#### PM-04 — Dívidas e riscos preservados

**Relatórios.** A dívida já registrada no PM-01 continua: `SalesReportService`
soma `UN` e `KG` em `itens_vendidos`, e a tela e o e-mail exibem a quantidade
com 0 casas decimais e rótulo "un". Ela passa a ser funcionalmente relevante
com a venda `KG` validada pelo PM-04A. Não foi corrigida nessa entrega.

Situação histórica dos demais achados da auditoria após a entrega do PM-04A
(antes do PM-04C):

| Achado | Observação |
|---|---|
| `quantity` com mais de 3 casas | Corrigido no PM-04A: `KG` acima de 3 casas e frações reais em `UN` são rejeitados antes da persistência |
| `quantity` sem `max` | Corrigido no PM-04A: limite de `DECIMAL(14,3)` validado antes do banco, inclusive nas somas |
| Preço com mais de 2 casas | O total é calculado com o valor informado e pode divergir do `unit_price` persistido |
| `addItem` sem lock | Duas adições simultâneas do mesmo Product podem colidir no índice único ou perder uma soma |
| Product arquivado | `moveStock` depende do Product atual; arquivar um Product pode travar envio ou cancelamento de pedido confirmado |
| FK `order_items.product_id` com `CASCADE` | Um `forceDelete` futuro de Product apagaria linhas de pedidos históricos |
| `OrderItemFactory` | Gera quantidade fracionada para Product `UN` |

### PM-05 — Dados fiscais do produto

**Concluído ✅ e publicado em 2026-10-06**

**Commit funcional:** `d958a50ddf52ee509eb02ea5cb14c881c01cbeaa` —
`feat(products): adicionar classificação fiscal básica`.

**Entrega.** Classificação cadastral mínima no Product, com regras tributárias
separadas para a fase fiscal futura:

| Campo | Tipo | Validação estrutural |
|---|---|---|
| `ncm_code` | `VARCHAR(8) NULL` | string com exatamente 8 dígitos ASCII |
| `cest_code` | `VARCHAR(7) NULL` | string com exatamente 7 dígitos ASCII, opcional quando aplicável |
| `default_origin_code` | `VARCHAR(1) NULL` | string de 1 caractere no domínio controlado aprovado |

- os três campos são opcionais, sem default de negócio, backfill, índice,
  unique ou FK novos; produtos existentes permanecem NULL e a criação comercial
  continua possível sem dados fiscais;
- zeros à esquerda são preservados; origem `"0"` permanece valor válido,
  diferente de NULL;
- Store aceita ausência; update parcial preserva campos omitidos e NULL explícito
  limpa somente o campo enviado;
- API expõe os três campos de forma aditiva como strings ou NULL; Web ganhou
  seção simples **"Dados fiscais"**, com ajuda contextual e ausência explícita;
- validação compartilhada entre Web/API; TrimStrings exclui somente os três
  nomes de campos para não corrigir espaços inválidos silenciosamente;
- rollback remove somente as três colunas. Schema validado no SQLite de testes
  e SQL compilado para MySQL 8.4; migration não aplicada ao banco operacional
  nem ao staging nesta rodada.

**Origem referencial.** `default_origin_code` representa a origem padrão do
cadastro, não a verdade fiscal definitiva da operação. O futuro motor fiscal
precisará resolver a origem efetiva conforme o contexto. Labels atuais são
somente técnicos ("Código 0" a "Código 8"), sem descrições legais presumidas.

**Validação normativa.** PM-05 valida estrutura, sem confirmação oficial de
existência, vigência, enquadramento ou regras tributárias. Os produtos não foram
considerados "fiscalmente validados". CEST não é derivado por NCM e sua presença
não determina automaticamente ST.

**Fronteiras preservadas.** ProductPackage permanece apresentação
comercial/logística, sem duplicação fiscal. OrderItem continua linha comercial,
sem novos dados fiscais; a futura verdade fiscal histórica deverá ficar em
entidade fiscal própria. Nenhuma alteração em Inventory, Pricing, Reporting,
Company, Branch ou regime. Nenhum Fiscal Engine criado e nenhuma emissão
NF-e/NFC-e implementada. Regime/CRT continua para configuração futura do emitente.

**Evidência da entrega (2026-10-06).** Suíte completa: 1050 testes — 1048 PASS,
0 FAIL, 0 ERROR, 2 RISKY preexistentes, 0 SKIPPED e 4334 assertions. Os RISKY
`test_passwords_not_logged_in_audit` e `test_user_email_properly_protected`
continuam sem correção. Regressão focada: 491 testes, 491 PASS, 0 FAIL, 0 ERROR
e 2218 assertions. Testes focados de Product/migration/Web/API: 85 PASS,
651 assertions; 43 testes novos no PM-05. Frontend: 5 arquivos aprovados.
Pint dos 15 arquivos PHP alterados/criados e `git diff --check` passaram.

**Evolução fiscal adiada.** CFOP, CST, CSOSN, ICMS, PIS, COFINS, IPI, FCP,
MVA, bases, alíquotas, benefícios, regime/CRT, unidade e GTIN tributáveis,
catálogos oficiais NCM/CEST, Fiscal Engine, FiscalDocument/FiscalDocumentItem,
NF-e/NFC-e, integração SEFAZ e validação normativa oficial permanecem futuros.
O escopo originalmente previsto incluía unidade e GTIN tributáveis; ambos foram
adiados para a fase fiscal. CFOP e demais decisões tributárias não ficam fixos
no Product: dependem da operação, emitente, regime e contexto fiscal.

**Dívidas preservadas.** Reporting ainda mistura UN/KG, mantém ranking por
Product e apresentações/labels sem revisão. Pricing por package não foi
implementado. SQLite não comprova concorrência real entre processos. Validação
normativa, catálogos fiscais e regime/CRT permanecem futuros. F2.6 continua
liberada e não iniciada: SEC-01/02/03/04 e recomendados encerrados.

**Próximo passo.** A próxima prioridade operacional será reavaliada após o
fechamento documental do PM-05. Nenhuma nova implementação iniciada nesta rodada.

---

## ONB — Onboarding e Experiência Inicial

Trilha aberta em 2026-10-03, depois que a infraestrutura VPS/HTTPS foi concluída
(`be44a0e`) e o ambiente staging entrou no ar em https://lucraone.jmfsystem.tech.
Com o ambiente de pé, o cadastro do primeiro cliente real passou a ser a próxima
necessidade concreta — e a auditoria do fluxo administrativo mostrou que ele não
existe de ponta a ponta pelo painel.

**Objetivo da trilha.** Transformar a criação, a ativação e os primeiros passos
de um cliente do LucraOne em uma experiência:

- intuitiva;
- didática;
- segura;
- previsível;
- adequada a usuários leigos;
- sem exigir conhecimento de conceitos técnicos como *tenant*, *role* ou
  *permission*.

O que esta trilha é, e o que não é:

- **É paralela à trilha PM.** Não substitui nem absorve PM-04A, PM-04B, PM-04C
  ou PM-05, que mantêm escopo, status e ordem próprios.
- **Não libera a F2.6 por si.** Quem a libera são as
  [pendências pré-F2.6](#pendências-bloqueadoras-pré-f26), hoje encerradas.
- **Não é uma fase nem uma sprint.** Usa IDs próprios (`ONB-*`), como `PM-*`,
  `SEC-*`, `COR-*` e `PERF-*`. O placar de fases não muda.
- **Não antecipa PDV nem fiscal.** Trata do cadastro e da ativação do cliente,
  não da operação de venda.

### Acompanhamento da trilha ONB

| ID | Etapa | Status | Depende de |
|---|---|---|---|
| ONB-01A | Fundação administrativa do onboarding | **Concluído ✅** · `6aa8d625cbb0a41269bc6eeb9b3ca377929fc816` | — |
| ONB-01B | Experiência guiada de onboarding | **Concluído ✅** · `aee736d`; ajuda concluída nesta rodada | ONB-01A ✅ satisfeita |

Como nas demais trilhas, uma etapa só passa a `Concluído` com testes que provem
a entrega. O ONB-01A passou com 60 testes novos; os detalhes estão em
[Implementação concluída](#implementação-concluída).

```
ONB:                              PM:

ONB-01A — fundação                PM-01 — barcode + unit                ✅
✅ CONCLUÍDO · 6aa8d62                    ↓
        ↓                         PM-02A — product_packages             ✅
ONB-01B — experiência guiada              ├─ PM-02B                     ✅
✅ CONCLUÍDO · aee736d               └─ PM-03                      ✅
                                          ↓
                                  PM-04A — quantidade coerente    ✅ CONCLUÍDO · d0ae879
                                          ↓
                                  PM-04B — snapshot do item       ✅ CONCLUÍDO · dc23e56
                                          ↓
                                  PM-04C — semântica de linhas    ✅ CONCLUÍDO · 9cbca48
                                          ↓
                                  PM-05 — dados fiscais     ✅ CONCLUÍDO · d958a50
```

As duas colunas não se cruzam: **não há dependência técnica entre ONB e PM.** A
ordem de execução combinada está em
[Prioridade operacional atual](#prioridade-operacional-atual).

### Vocabulário do onboarding

Três conceitos distintos do código que a interface atual não distingue com
clareza, e que esta trilha precisa separar:

| Termo do negócio | Entidade no código | O que é |
|---|---|---|
| **Cliente do LucraOne** | `Tenant` | O estabelecimento que assina e usa o SaaS |
| **Cliente do estabelecimento** | `Customer` | O comprador final, cliente comercial daquele tenant |
| **Empresa** | `Company` | Entidade fiscal/jurídica (CNPJ) pertencente ao tenant |

**Decisão de interface.** O termo técnico *tenant* não deve ser exposto a usuário
leigo. Na UX futura, o cliente do LucraOne é apresentado como **"Clientes do
LucraOne"** ou **"Estabelecimentos"**. "Clientes" no menu do estabelecimento
continua significando `Customer`, e "Empresas" continua significando `Company` —
a separação visual entre nível de plataforma e nível de estabelecimento faz parte
do escopo de ONB-01A.

### ONB-01 — Onboarding guiado de clientes

**Planejado**

Permitir que o operador da plataforma LucraOne crie e ative um novo
cliente/estabelecimento com clareza, segurança e consistência, e que o
administrador desse estabelecimento saiba exatamente quais são os próximos passos
para começar a operar.

Dividida em duas etapas: `ONB-01A` destrava e torna consistente o fluxo
administrativo; `ONB-01B` transforma esse fluxo em experiência guiada.

#### ONB-01A — Fundação administrativa do onboarding

**Concluído ✅ · 2026-10-03 · `6aa8d625cbb0a41269bc6eeb9b3ca377929fc816`**

**Objetivo.** Destravar e tornar consistente o fluxo administrativo necessário
para criar clientes do LucraOne pelo painel.

**Escopo.**

1. **Mecanismo controlado de promoção de Platform Admin.** Preferência
   arquitetural registrada: um comando Artisan

   ```
   plataforma:promover {email}
   ```

   O objetivo é eliminar a dependência de Tinker ou SQL para o bootstrap do
   Platform Admin, e deixar a promoção com rastro na aplicação em vez de ocorrer
   apenas por acesso de infraestrutura.

2. **Promoção da conta operacional da plataforma** para
   `is_platform_admin = true`. A role `admin` de um estabelecimento **não** deve
   ser transformada em Platform Admin.

   > **Platform Admin ≠ Tenant Admin.** São autoridades de níveis diferentes, e
   > permanecem separadas conforme a
   > [decisão arquitetural do SEC-04](#decisão-arquitetural--platform-admin).

3. **Correção dos atalhos quebrados do dashboard.** O bloco de atalhos referencia
   nomes de rota que não existem:

   | Referência atual | Rota real |
   |---|---|
   | `tenants` | `tenants.index` |
   | `usuarios` | `users.index` |
   | `empresas` | `companies.index` |

   Como `Route::has()` falha, os três atalhos são renderizados desabilitados e
   exibem textos obsoletos — *"cadastro chega no F3.4"*, *"cadastro chega no
   F3.5"* e *"gestão chega no F3.6"* — para funcionalidades **já implementadas**
   na F3.4 (`b8c19ae`), F3.5 (`e1548e8`) e F3.6 (`a13fe95`). Os textos devem ser
   removidos e os atalhos passar a apontar para as rotas reais.

4. **Nomenclatura administrativa.** Preferir "Clientes do LucraOne" ou
   "Estabelecimentos" a expor apenas "Tenants", conforme
   [Vocabulário do onboarding](#vocabulário-do-onboarding).

5. **Unificação do provisionamento de autorização de um novo tenant.** Hoje
   existem dois comportamentos divergentes para a mesma intenção:

   | Caminho | Roles | Permissions |
   |---|---|---|
   | `AuthorizationSeeder` | 4 (`admin`, `manager`, `user`, `viewer`) | 28 |
   | `TenantController::provisionarAutorizacaoPadrao()` | 1 (`admin`) | 26 |

   ONB-01A deve eliminar essa divergência.

6. **Fonte única de provisionamento.** Nome conceitual sugerido:
   `ProvisionarEstabelecimento`, ou equivalente coerente com a arquitetura
   modular do projeto. Esse serviço deve ser consumido por:

   - `TenantController`;
   - `AuthorizationSeeder`;
   - o wizard de `ONB-01B`.

7. **Estado esperado de um tenant novo após o provisionamento:**

   - roles: `admin`, `manager`, `user`, `viewer`;
   - permissions: 28 registradas, conforme o modelo atual;
   - `admin`: as 26 permissões do `AdminPermissionMatrix`.

   As duas permissões órfãs atuais (`manage-assigned-branches` e
   `view-assigned-branches`, criadas e não concedidas a nenhuma role) devem ser
   **preservadas como estão**, sem correção oportunista, a menos que uma etapa
   futura trate explicitamente do assunto.

8. **Critério de pronto.** Ser possível criar manualmente um **Cliente Teste**
   pelo painel, sem Tinker nem SQL para a criação do tenant.

**Fora do escopo de ONB-01A.** Registrado explicitamente para não haver
ampliação silenciosa:

- wizard completo;
- criação automática do primeiro usuário do cliente;
- convite por e-mail;
- token de definição de senha;
- checklist de onboarding;
- tour guiado;
- modal de primeiro acesso (*first-run*);
- tabela `onboarding_progress`;
- qualquer migration;
- CRUD completo de Platform Admin;
- RBAC de plataforma;
- PDV;
- fiscal;
- PM-04A, PM-04B e PM-04C.

##### Implementação concluída

**Data:** 2026-10-03
**Commit funcional:** `6aa8d625cbb0a41269bc6eeb9b3ca377929fc816`
**Título:** *feat(onboarding): implementar fundação administrativa ONB-01A*

19 arquivos, 1644 inserções e 209 remoções. Nenhuma migration.

**Critério de pronto atendido.** Já é possível criar um estabelecimento pelo
painel, sem Tinker e sem SQL. Na entrega do ONB-01A, o marco separado
**Cliente Teste** ainda estava planejado para depois do PM-04A e do PM-04B.

**Estado atual do marco Cliente Teste: Concluído ✅.** Cliente Teste criado e
validação manual realizada. PM-04C e PM-05 também estão concluídos; a próxima
prioridade será reavaliada após o fechamento documental do PM-05, conforme a
[prioridade operacional atual](#prioridade-operacional-atual).

**1 · Comando controlado de Platform Admin.**

```
plataforma:promover {email} [--force]
```

Registrado em `bootstrap/app.php`, porque a descoberta automática só varre
`app/Console/Commands` e os comandos do projeto vivem nos módulos. Características
entregues:

- confirmação interativa por padrão, exibindo nome e e-mail da identidade e o
  aviso de que a autoridade é global e nenhuma tela do painel a desfaz;
- confirmação com **padrão negativo** — um Enter distraído não concede nada;
- `--force` para uso explicitamente não interativo, em provisionamento;
- recusar a confirmação não grava, não altera `updated_at` e não gera log;
- idempotente: já sendo Platform Admin, informa e não toca o banco;
- identidade inexistente **não é criada** — o comando recusa e explica que o
  cadastro é operação do painel;
- identidade arquivada é recusada, com mensagem própria;
- log mínimo (`user_id`, `origem`, `comando`), sem e-mail, nome, senha, hash,
  token ou sessão;
- não aceita `--tenant`, `--role`, `--password` nem `--create-user`.

**2 · Conta operacional da plataforma promovida.** A conta operacional recebeu
`is_platform_admin = true` pelo comando oficial, sem Tinker e sem SQL. A
promoção não alterou senha, status, e-mail, vínculo nem papel de
estabelecimento.

**3 · Dashboard corrigido.** Os atalhos passaram a citar nomes de rota que
existem — `users.index`, `companies.index`, `catalog.products.index` e, para
Platform Admin, `tenants.index`. O atalho de plataforma só aparece para quem a
`TenantPolicy` autoriza, em vez de levar a um 403. Os textos que anunciavam como
futuras as telas de cadastro já entregues pela F3.4, F3.5 e F3.6 foram removidos,
assim como a guarda `Route::has()`/`href="#"` que os produzia.

**4 · UX administrativa.** O rótulo da área de plataforma passou a ser
**"clientes do LucraOne"** na sidebar, no atalho do dashboard e nas telas de
`/tenants`; **"estabelecimento"** é usado onde descreve a entidade de dentro do
painel, incluindo as ações de arquivar e restaurar — "cliente" ali colidiria com
o `Customer`, que já usa esse rótulo na própria tela. O indicador do dashboard
deixou de se chamar "tenants". `Tenant` e `tenant_id` seguem como termos
técnicos no código, e o texto didático da ajuda continua ensinando o conceito:
ali o termo é conteúdo, não rótulo.

**5 · Fonte única de autorização padrão.** `StandardRoleMatrix`, em
`app/Modules/Authorization/Domain/`, com o catálogo de permissões e os quatro
papéis. O `admin` não é redeclarado: continua vindo de `AdminPermissionMatrix`.

**6 · Service `ProvisionarEstabelecimento`**, em
`app/Modules/Tenancy/Application/`, com duas responsabilidades separadas —
`provisionarMatriz(Tenant)` e `atribuirAdministrador(Tenant, User)`. A separação
é necessária: o seeder provisiona vários estabelecimentos e não tem um usuário a
quem dar admin. Opera com `tenant_id` explícito e `withoutGlobalScopes()`, **sem
depender do `TenantContext`**, porque o provisionamento acontece fora de um
contexto resolvido.

**7 · `TenantController` delegando ao Service.** O método privado
`provisionarAutorizacaoPadrao()` foi removido. Criação de tenant, provisionamento
e atribuição do administrador seguem na mesma transação; redirects, flashes,
ULID, `active` e autorização pela `TenantPolicy` preservados.

**8 · `AuthorizationSeeder` consumindo a mesma fonte**, reduzido de 155 para 31
linhas. Como o concern de testes `MontaCenariosDeAutorizacao` já delegava ao
seeder, a fonte única se propagou para toda a suíte de segurança sem tocar
naqueles arquivos.

**9 · Divergência eliminada.** Criação pelo painel e `AuthorizationSeeder`
produzem agora a **mesma** matriz, verificado por teste que compara as duas.

###### Matriz final

| | quantidade |
|---|---|
| permissions no catálogo | **28** |
| `admin` | **26** (idêntica a `AdminPermissionMatrix::NAMES`) |
| `manager` | **15** |
| `user` | **8** |
| `viewer` | **8** |

`manage-assigned-branches` e `view-assigned-branches` continuam **presentes no
catálogo e não atribuídas a nenhum papel padrão**. Não é defeito novo: é a
decisão preservada do SEC-04, implementada pela migration
`2026_09_11_000000_remove_obsolete_assigned_branch_permissions_from_standard_roles`.
A contenção do SEC-04 · E2 segue valendo — user ⊆ manager ⊆ admin e
viewer ⊆ manager.

###### Testes

**60 testes novos:**

| arquivo | casos |
|---|---|
| `tests/Feature/Platform/PromoverPlatformAdminCommandTest.php` | 27 |
| `tests/Feature/Tenancy/ProvisionamentoDeAutorizacaoTest.php` | 21 |
| `tests/Feature/Admin/DashboardAtalhosTest.php` | 12 |

**Baseline após o ONB-01A:** 702 testes no total — 700 PASS, 0 FAIL, 0 ERROR,
2 risky preexistentes, 0 skipped e 2449 assertions. A baseline anterior era de
642 testes, 640 PASS e 2132 assertions.

As matrizes esperadas são escritas à mão nos testes, de propósito: se lessem a
mesma constante que a implementação usa, provariam apenas que a classe é igual a
si mesma. Nenhum teste antigo foi removido. Cinco assertivas de três testes
existentes foram ajustadas e três delas ficaram **mais fortes** — passaram a
afirmar que quem não administra a plataforma não recebe o caminho dela, onde
antes afirmavam, por acidente, um sintoma do defeito dos atalhos.

###### Segurança confirmada

- Platform Admin continua separado de Tenant Admin;
- Tenant Admin continua **sem** criar estabelecimentos (403 mantido);
- Platform Admin administra os clientes do LucraOne;
- a `TenantPolicy` **não** foi alterada;
- `is_platform_admin` **não** virou mass assignable;
- `TenantScope` e o isolamento multi-tenant **não** foram alterados;
- o ONB-01A **não** implementou RBAC global de plataforma;
- o ONB-01A **não** criou o grupo `/plataforma/*` nem middleware próprio.

O ONB-01A **operacionalizou o bootstrap** do Platform Admin e não alterou o
modelo de segurança: a [decisão arquitetural do SEC-04](#decisão-arquitetural--platform-admin)
segue valendo integralmente, e a role `admin` de um estabelecimento não foi
transformada em autoridade de plataforma.

###### Dívidas e observações preservadas

- quem cria um estabelecimento pelo fluxo atual continua recebendo vínculo nele,
  comportamento anterior ao ONB-01A e caracterizado em teste;
- com múltiplos vínculos ativos, o Platform Admin passa a usar o seletor de
  estabelecimento a cada login, e `HasRole::tenantDeReferencia()` deixa de
  resolver sozinho fora de requisição;
- a decisão de manter ou não esse acesso será tomada no passo 3 do wizard do
  ONB-01B;
- a sidebar ainda não filtra por permissão todos os seus itens — só o de
  plataforma;
- convite por e-mail continua futuro;
- CRUD de papéis continua fora de escopo: um estabelecimento novo nasce com os
  quatro papéis, mas o cliente ainda não cria papéis próprios;
- os 2 risky preexistentes do `SecurityAuditTest` continuam;
- as 22 pendências de Pint continuam — nenhuma delas em arquivo do ONB-01A.

###### Commits da trilha

| Commit | O que é |
|---|---|
| `2ff1ada695960a40104a43c102646b39ec42f412` | *docs(roadmap): registrar trilha de onboarding ONB-01* — abertura documental da trilha |
| `6aa8d625cbb0a41269bc6eeb9b3ca377929fc816` | *feat(onboarding): implementar fundação administrativa ONB-01A* — entrega funcional |

#### ONB-01B — Experiência guiada de onboarding

**Concluído ✅** — entrega funcional publicada em
`aee736db22a4f626fea6010d3e67278670606134`, com 47 testes ONB-01B aprovados.

Wizard de quatro passos com confirmação transacional única, administrador novo
ou existente sem alteração silenciosa da identidade global, Company opcional,
escolha explícita de acesso do operador (default Não), revisão sem senha e tela
de sucesso com próximos passos. Checklist derivado do banco, isolado por
estabelecimento; o administrador inicial sozinho e o suporte da plataforma não
concluem equipe. Nesta rodada, ajuda permanente exclusiva de Platform Admin em
`/tenants/ajuda/cadastro`, CTAs no dashboard/lista/wizard e etapas fixas 1–7.

Observações futuras preservadas: sucesso depende de flash de sessão,
concorrência do mesmo e-mail, situação do estabelecimento no resolver,
teste específico de credenciais em logs de exception e browser E2E completo.

**Objetivo.** Transformar o processo administrativo de ONB-01A em uma experiência
guiada para usuários leigos.

**Wizard de criação de cliente — implementado.**

| Passo | Conteúdo |
|---|---|
| **1 — Estabelecimento** | nome; slug; plano; status; timezone; locale; moeda |
| **2 — Administrador do cliente** | nome; e-mail; senha inicial (ou, no futuro, convite); reaproveitamento de identidade global já existente |
| **3 — Configuração** | `Company` opcional, com dados fiscais básicos; decisão explícita sobre manter ou não o acesso do Platform Admin ao tenant |
| **4 — Revisão** | resumo; validação; confirmação |

Após a conclusão, tela **"Cliente criado com sucesso"**, com atalhos para:

- entrar no estabelecimento;
- configurar empresa;
- cadastrar produtos;
- cadastrar equipe;
- voltar para clientes do LucraOne.

**Checklist do cliente no dashboard.**

```
✅ 1. Estabelecimento criado
⬜ 2. Cadastrar empresa
⬜ 3. Criar primeira categoria
⬜ 4. Cadastrar primeiro produto
⬜ 5. Definir preço
⬜ 6. Informar estoque inicial
⬜ 7. Cadastrar equipe
```

O progresso é **derivado do banco**, sem nova tabela de progresso nem flag de
primeiro acesso — estado derivado não diverge da realidade.
Exemplos de derivação: `Company::exists()`, `Category::exists()`,
`Product::exists()`, preço de venda (`Price` do tipo `SALE`) existente,
`Inventory` existente e `activeUsers() > 1`.

**Ajuda contextual.** O padrão `x-help-modal` foi preservado. A orientação
permanente de cadastro de clientes complementa a ajuda existente e pode ser
reaberta pelo dashboard, lista de clientes e wizard.

**Evolução de rotas planejada.** Grupo `/plataforma/*`, servido com
`auth.web:sem-tenant` mais um middleware específico de Platform Admin, para que a
administração da plataforma não dependa de um estabelecimento resolvido na
sessão. **Não implementar agora.**

#### ONB — Invariantes de segurança

Valem para toda a trilha e não podem ser relaxados por conveniência de UX:

- **Tenant Admin não cria novos tenants.**
- Somente **Platform Admin** administra clientes do LucraOne.
- `is_platform_admin` continua separado das roles de tenant, não é mass
  assignable e não é concedido por `manage-users`.
- A `TenantPolicy` continua sendo a proteção da entidade `Tenant`.
- `ONB-01B` deverá operar **fora do `TenantContext`** quando estiver
  administrando a plataforma, passando `tenant_id` explicitamente em tudo que
  cria.
- Nenhum `tenant_id` vindo do navegador deve ser confiado sem validação de
  vínculo — o padrão correto já existe em `EstabelecimentoController::definir`.
- O isolamento entre estabelecimentos deve permanecer intacto.

#### ONB — Company antes do primeiro produto

Observação de ordem que condiciona o desenho do onboarding: **`Company` é
obrigatória antes do primeiro `Product`**, porque o cadastro de produto exige
`company_id` existente no estabelecimento. Sem nenhuma empresa cadastrada não há
como cadastrar produto, e sem produto não há estoque nem venda.

Por isso: a `Company` **pode ser opcional durante a criação do cliente** — o CNPJ
pode não estar à mão no momento da contratação —, mas deve ser o **primeiro passo
obrigatório do onboarding do estabelecimento** caso ainda não exista.

#### ONB — Etapas futuras, fora do ONB-01A/B mínimo

Registradas para não se perderem, sem ID próprio e sem previsão:

- convite por e-mail;
- token temporário de convite;
- definição da própria senha pelo usuário convidado;
- aceite do vínculo;
- expiração de convite;
- reenvio de convite.

**Motivação.** Evitar que administradores precisem conhecer e transmitir a senha
dos usuários que cadastram. Hoje a senha é digitada pelo administrador no
formulário e comunicada por fora do sistema. **Não implementar.**

---

## Melhorias de cadastro e preços — concluídas em 2026-10-04

- Company: máscara brasileira de celular compartilhada entre create/edit, sem
  dependência nova; formato persistido e validação existentes preservados.
- Produto: SKU interno assistido no Web, geração autoritativa com unicidade por
  estabelecimento e tratamento de colisões; API mantém SKU obrigatório. SKU não
  é EAN/GTIN. Edição não regenera silenciosamente o código.
- Nome do produto normalizado em maiúsculas no model, inclusive UTF-8, Web/API;
  sem backfill de nomes antigos.
- Preços: entrada brasileira com vírgula, seletor de moeda e margem desejada
  opcional apenas em CUSTO. Sugestão usa custo × (1 + margem/100), com duas casas;
  usar sugestão apenas preenche VENDA, sem gravação automática. O custo precisa
  estar salvo para compor a margem efetiva; venda sem custo continua permitida.
- Margem efetiva: fórmula oficial sobre custo, `((venda - custo) / custo) × 100`,
  decimal assinado com quatro casas. `RegistrarPreco` centraliza Web/API/automação,
  com custo de referência da mesma moeda/produto/estabelecimento e transação.
- Histórico: eventos `initial`, `amount_changed`, `reference_cost_changed` e
  `price_removed`, snapshots old/new e identidade por tenant/product/tipo/moeda.
  Remoção de Price mantém eventos (`price_id` SET NULL); tenant/product RESTRICT.
  Margem desconhecida é NULL; valores antigos não são inventados nem recalculados.
- Migration `2026_10_04_100000_add_historical_margin_snapshots` aplicada somente
  no staging, com backup prévio. Teste manual de custo 2,19 + 30% → sugestão 2,85
  e salvamento de CUSTO/VENDA sem 500 concluído pelo operador.

Relatórios de lucratividade e meta histórica de margem desejada continuam
futuros. Na rodada de 2026-10-04, PM-04A/B/C, fiscal e PDV não faziam parte
daquela entrega. O PM-04A foi concluído posteriormente em 2026-10-05, no commit
`d0ae879`; PM-04B foi concluído em 2026-10-06 (`dc23e56`). PM-04C foi concluído e
publicado em 2026-10-06 (`9cbca48`), após Cliente Teste. PM-05 foi concluído e
publicado em 2026-10-06 (`d958a50`). A próxima prioridade operacional será
reavaliada após seu fechamento documental.

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
atual da suíte está registrado no cabeçalho e na evidência da entrega do PM-05.

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
