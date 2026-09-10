# Roadmap — LUCRAONE

**Atualizado:** 2026-09-10 · **SEC-04 em andamento** — 489 testes no total: 440
PASS, 47 EXPECTED FAIL do SEC-04 e 2 risky preexistentes

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
| SEC-04 | Segurança | create-role | Crítica | Em andamento | **Sim** |
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

**Crítica · Em andamento · Bloqueia F2.6: Sim** — Origem: identidade global da F1.8
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

**Evidência atual.** O SEC-04 tem 72 testes: 25 PASS, 47 EXPECTED FAIL e 0
ERROR. X1 está corrigido com 21 PASS e 0 FAIL — 15 da baseline e 6 positivos
de Platform Admin. A suíte completa tem 489 testes: 440 PASS, 47 falhas SEC-04
esperadas, 2 risky preexistentes, 0 SKIPPED e 1353 assertions. O SEC-04 continua
em andamento: E1, E2, E3, E7 e os bypasses de `create-role` nos módulos seguem
pendentes; por isso a F2.6 permanece bloqueada e não iniciada.

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
ou o impedisse. O baseline executável passou a caracterizá-los; após a primeira
correção, X1 tem 21 testes verdes e os demais vetores continuam sem correção.

**Coringa `create-role`.** A permissão aparece ao lado das permissões
específicas em 10 Policies (`Product`, `Category`, `Inventory`, `StockLevel`,
`Order`, `Customer`, `Company`, `AutomationRule`, `Role`, `Permission`), no Gate
`view-reports` e no filtro de destinatários do comando `relatorios:enviar-resumo`. A
capacidade que o nome descreve — criar papel — não tem rota: a permissão
funciona só como marcador de "é admin". Estabelecimentos criados pelo painel dão
ao papel `admin` apenas 19 permissões, sem `manage-sales`, `view-sales`,
`manage-customers`, `view-customers`, `view-reports`, `manage-automations` e
`view-automations`; nesses estabelecimentos o admin só acessa vendas, clientes,
relatórios e automações pelo coringa.

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
`RolePolicy::update` exige só `update-role`, `SyncRolePermissionsRequest` valida
apenas que as permissões existem no estabelecimento ativo, e o controller
substitui o conjunto sem compará-lo às permissões de quem executa. Não há
proteção do próprio papel, de papéis de sistema (existe só em `delete`, que não
tem rota) nem do último administrador. Quem tem `update-role` concede
`create-role` a qualquer papel, inclusive ao próprio. No seed só `admin` tem
`update-role`; o vetor se abre com qualquer papel personalizado que a receba.

**E2 — Escalada por `manage-users`.** *Vulnerabilidade crítica.* O papel padrão
`manager` recebe `manage-users` no seed. `UserPolicy::create` e `update` exigem
apenas essa permissão; `StoreUserRequest` e `UpdateUserRequest` aceitam qualquer
papel existente no estabelecimento ativo; e `UserController::update` só impede
que a pessoa desative o próprio acesso — não que altere os próprios papéis. Nada
verifica se quem executa possui as permissões do papel atribuído.

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

Também é possível criar uma segunda conta já com o papel `admin`.

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
nada em B, mas a autorização passa. A confirmação inicial veio da leitura do
código e da ordem de middleware do framework. O baseline executável do SEC-04
posteriormente reproduziu o vetor por testes HTTP reais, que permanecem vermelhos
até a correção do E7.

É obrigatório corrigir antes do SEC-01, porque o SEC-01 aplicará essas mesmas
Policies à API.

**Consequência.** Antes da correção de X1, X1, E3 e E7 atravessavam a fronteira
entre estabelecimentos — pela gestão de estabelecimentos, pela identidade
compartilhada e pelas Policies. X1 está corrigido; E3 e E7 permanecem abertos.
E1 e E2 ainda levam a poder administrativo indevido, mas não concedem mais
administração da plataforma pelo X1.

#### Causa raiz

1. **Antes de X1, sem separação entre autoridade de plataforma e de tenant.** O RBAC da F1.5
   existe só dentro de um estabelecimento. A F3.4 criou uma operação de
   plataforma — gerenciar estabelecimentos — sem criar esse nível.
2. **`create-role` como marcador improvisado de administrador.** Antes de X1, a `TenantPolicy`
   registra a decisão como provisória ("até F3.6"), e as Policies seguintes
   copiaram o padrão.
3. **Delegação de permissões e papéis sem contenção.** Sincronizar permissões e
   atribuir papéis só validam "pertence ao estabelecimento ativo", nunca "quem
   executa pode delegar isso".
4. **Identidade global administrada por permissão local.** A F1.8 tornou a pessoa
   global; a F3.5 manteve `manage-users` com poder sobre credenciais e status
   globais.
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

O SEC-04 deve remover `create-role` como bypass administrativo das Policies de
domínio, do Gate `view-reports` e do comando `relatorios:enviar-resumo`. A
primeira correção removeu apenas seu uso como autoridade de plataforma na
`TenantPolicy`; os bypasses nos módulos permanecem pendentes:

- `ProductPolicy`, `CategoryPolicy`, `InventoryPolicy`, `StockLevelPolicy`,
  `OrderPolicy`, `CustomerPolicy`, `CompanyPolicy` e `AutomationRulePolicy`;
- Gate `view-reports` e resumo de vendas.

O uso de `create-role` no próprio domínio de roles e permissions continua sendo
tratado separadamente. Depois da remoção dos bypasses pendentes:

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
precisam receber explicitamente as permissões de que dependiam dele.

#### Contenção da delegação

O SEC-04 deve impedir que uma pessoa conceda poder superior ao que possui:

- `update-role` não pode conceder permissões que o ator não esteja autorizado a
  delegar;
- `manage-users` não pode ser usado para autoelevação;
- `manage-users` não pode atribuir papel cujo poder exceda a autoridade delegável
  do ator;
- nenhum caminho de delegação pode levar a Platform Admin;
- papéis administrativos relevantes devem ser protegidos;
- considerar guarda contra lockout e contra a remoção do último administrador;
- mudanças de autorização continuam tenant-aware.

Não é necessário implementar hierarquia complexa de papéis.

#### Proteção da identidade global

Uma permissão local como `manage-users` não deve permitir que um administrador do
Tenant A comprometa os acessos de uma identidade no Tenant B. A implementação
deverá revisar:

- redefinição de senha;
- alteração de e-mail;
- `account_status`;
- associação de papéis;
- associação a estabelecimento;
- reutilização de e-mail existente.

A regra definitiva deve preservar o modelo de identidade global da F1.8.

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

**Platform Admin**

- admin de tenant recebe 403 nas operações de `/tenants`;
- Platform Admin executa as operações autorizadas de `/tenants`;
- possuir apenas `create-role` não concede acesso de plataforma.

**`create-role`**

- usuário somente com `create-role` não usa como bypass as Policies de Products,
  Categories, Inventory, Sales, Customers, Companies, Automation e Reports;
- `relatorios:enviar-resumo` não aceita `create-role` como substituto de `view-reports`.

**E1**

- `update-role` não permite adquirir permissões não delegáveis;
- possuir `update-role` não basta para adquirir `create-role`.

**E2**

- `manage-users` não permite autoatribuição do papel admin;
- `manage-users` não permite atribuir poder acima da autoridade delegável.

**E3**

- administração local não compromete credenciais nem status global de identidade
  vinculada a outros tenants.

**E7** — cenário obrigatório, cobrindo os módulos afetados:

```
Usuário:  admin no Tenant A, viewer no Tenant B, Tenant A ativo
Entidade: pertence ao Tenant B
```

O usuário não pode visualizar nem alterar a entidade de B usando permissões que
possui somente em A.

**Regressão**

- admins legítimos mantêm as funcionalidades de negócio que devem possuir;
- admins provisionados pelo painel recebem explicitamente as permissões
  necessárias;
- o isolamento multi-tenant existente continua funcionando;
- fixtures que dependiam de `create-role` como sinônimo de admin são corrigidas,
  não contornadas.

#### Relação com o SEC-01

O SEC-04 vem primeiro porque, quando concluído, o SEC-01 poderá assumir:

- Policies sem bypass por `create-role`;
- contexto de tenant consistente entre entidade e permissão;
- delegação administrativa protegida;
- Platform Admin separado de admin de tenant;
- testes de API com 403 representando o modelo correto.

Assim o SEC-01 aplica as Policies aos 7 controllers e 34 rotas sem propagar o
modelo vulnerável atual.

**Por que bloqueia.** X1, E3 e E7 são falhas de isolamento entre
estabelecimentos, da mesma classe que o SEC-01 e o SEC-03, e E1 e E2 levam até
elas. O SEC-01 vai ligar essas Policies à API, e a F2.6 vai criar uma Policy para
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
`hasPermission()` aceita `$tenantId` explícito, e `relatorios:enviar-resumo` verifica
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
clientes, `update-role` e `manage-users` permitem escalada de privilégio, e a
permissão pode ser avaliada num estabelecimento diferente do da entidade.
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
  vendas, clientes, relatórios e automações;
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

### Achados da auditoria do SEC-04 fora do escopo

Registrados em 2026-09-10 para tratamento posterior. Não são necessários para
provar nem para corrigir o SEC-04, e não receberam ID.

| Achado | Observação |
|---|---|
| Status `SUSPENDED`/`CANCELLED` do estabelecimento | Aparentemente sem efeito funcional completo: `Tenant::isActive()` não é chamado, e o resolver e o login não consultam o status |
| Arquivamento de estabelecimento | Pode derrubar o acesso dos usuários sem aviso adequado: o estabelecimento some do seletor e do login, e sessões abertas passam a receber 404 |
| `BranchPolicy` | Aparenta estar desconectada: não é registrada, usa permissões que não existem no seed e não há rotas de filiais |
| Testes de integração vazios | Em `ApiIntegrationTest`, `test_rbac_admin_can_manage_roles`, `test_user_without_permission_gets_403` e `test_admin_workflow_manage_users_and_roles` só chamam `/up` e `assertTrue(true)` |

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
