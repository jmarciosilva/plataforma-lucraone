# Roadmap — LUCRAONE

**Atualizado:** 2026-09-09 · **417 testes passando**

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
| **F2.6 — Integration APIs** | 📋 | 📋 | **próxima** |

---

## Próxima sprint — F2.6, Integration APIs

Integração com sistemas externos: cadastrar integração pelo painel, disparar
webhook de teste e auditar os envios. Modelos `Integration` (com credenciais
cifradas) e `IntegrationLog`. Especificação completa na seção F2.6 do
[ROADMAP_FASE_02](ROADMAP_FASE_02_FEATURES.md).

> **Recomendação: atacar as pendências de segurança abaixo antes da F2.6.**
> A F2.6 acrescenta uma superfície de API nova. Ligá-la antes de a autorização
> existir amplia um buraco que já está aberto.

---

## Pendências abertas

Levantadas em auditoria de 2026-09-09, com os números conferidos contra o
código. Esta seção é o que há de mais útil para auditar o projeto.

### 🔴 Segurança

#### 1. A API v1 não verifica autorização — 12 dos 14 controllers

As 13 Policies existem, estão registradas em `AppServiceProvider` e o painel web
as usa. Nenhum controller de `/api/v1` chama `Gate::authorize`, exceto os de
Automation. Qualquer usuário autenticado com token válido cria e apaga produtos,
lança e cancela pedidos, ajusta estoque e lê relatórios financeiros —
independente do papel que tenha.

O isolamento *entre* estabelecimentos funciona. O controle *dentro* de um, não.

Nenhum teste cobre isso: `SecurityAuditTest` e `RBACTest` verificam isolamento e
o trait `HasRole`, nunca um 403 em endpoint da API. Por isso passou despercebido.

**Correção:** aplicar as Policies existentes nos controllers e escrever os testes
de 403 por endpoint. É mecânico — as peças certas já foram construídas, só não
foram ligadas na API.

#### 2. `POST /api/auth/login` sem rate limiting

Nenhuma rota `/api` tem `throttle`. O login web tem freio correto — 5 tentativas
por e-mail mais IP, em `LoginRequest` — e a API não herdou o cuidado.

Junto disso, `AuthController::login` retorna imediatamente quando o e-mail não
existe e compara o hash quando existe: a diferença de tempo permite enumerar
contas. Os tokens Sanctum também são emitidos sem expiração e sem `abilities`.

#### 3. `TenantResolver` confia no header sem usuário autenticado

Em `TenantResolver.php` a validação de vínculo é condicional — só verifica
`canAccessTenant` se houver usuário. Sem usuário, o `X-Tenant-ID` é aceito sem
checagem.

Hoje não é explorável, porque toda rota usa `auth:sanctum` antes de `tenant`.
Mas a segurança está dependendo de cada arquivo de rotas futuro repetir o par na
ordem certa, e não do resolver.

### 🟡 Correção

#### 4. Índices únicos ignoram `deleted_at`

`products` tem `softDeletes()` e `unique(['tenant_id','sku'])`. Ao arquivar um
produto, o SKU fica ocupado para sempre — recriar dá erro de integridade sem
mensagem útil. Mesmo padrão em `categories.slug`, `users.email` e `tenants`.

É o tipo de defeito que só aparece com o cliente usando.

#### 5. `hasAnyPermission()` multiplica queries

Cada chamada percorre a lista invocando `hasPermission()`, e cada uma refaz
`getPermissions()`, que consulta `rolesForTenant` com eager load. Uma policy que
checa 3 permissões dispara 3 rodadas completas, repetidas a cada
`Gate::authorize` da requisição. Falta memoização por requisição.

#### 6. `create-role` virou superadmin implícito

A permissão aparece como coringa em todas as policies, ao lado das permissões
específicas. Funciona, mas é acoplamento invisível: conceder `create-role` a
alguém entrega o sistema inteiro sem que isso fique óbvio para quem concede.

#### 7. `SendEmailAction` aceita destinatário arbitrário

Uma regra de automação manda e-mail para qualquer endereço, com assunto e corpo
definidos pelo usuário. É a feature funcionando como projetada, mas em SaaS
multi-tenant é vetor de spam com a reputação do domínio. Vale restringir a
usuários do próprio estabelecimento, ou ao menos limitar volume.

### 🔵 Qualidade

| Item | Estado |
|---|---|
| PHPStan (nível 4) | 11 erros — tipos de retorno de View e acesso a `$id` em união de tipos |
| Pint | 22 arquivos fora do padrão — imports não usados, ordenação |
| Automation Rules Guide | Não escrito — único item não entregue da F2.5 |
| `.env.testing` com `APP_KEY` versionada | Chave pública no repositório. Risco baixo — só assina sessões de teste em sqlite em memória — mas é evitável |
| `performance with growing data` | Intermitente sob carga: assertiva sensível a tempo, passa isolada |

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
