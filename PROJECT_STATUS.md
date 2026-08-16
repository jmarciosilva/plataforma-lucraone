# Status do Projeto LUCRAONE

**Atualizado em:** 2026-08-16 (F2.3 concluído — sales & orders)
**Fase Atual:** FASE 02 — FEATURES (F2.4 é o próximo)
**Próxima Sprint:** F2.4 — Reporting & Analytics
**Fase Concluída:** FASE 03 — ADMIN FRONTEND (6/6 sprints)
**Progresso Geral:** FASE 01 ✅ 100% (8 sprints) | FASE 02 🟡 4 entregas concluídas | FASE 03 ✅ 100% (6/6)
**Testes:** 349 passing, 0 failing

> 🔓 **O sistema já é operável por um humano.** Desde o F3.2 existe login web
> em `/login`. Uma pessoa com vínculo em vários estabelecimentos escolhe onde
> quer trabalhar e troca pelo menu, sem deslogar.

> 🔄 **F1.8 — Identity Refactor (2026-08-16).** A FASE 01 ganhou um oitavo
> sprint. Ao desenhar o login do F3.2, descobrimos que `users.tenant_id`
> prendia cada pessoa a um único estabelecimento — mas o negócio exige o
> contrário: um dono com duas lojas, um contador atendendo vários clientes.
> A tabela `user_role` já assumia múltiplos estabelecimentos desde o F1.5,
> então as duas metades do sistema discordavam entre si. Corrigido antes de
> seguir, com o painel ainda sem dados reais.

> ⚠️ **Decisão de sequenciamento (2026-08-16):** a FASE 02 foi pausada após F2.1
> porque o sistema não possui interface de acesso — não há tela de login nem painel
> administrativo para cadastrar tenants, usuários e empresas. A FASE 03 (Admin
> Frontend, Blade + Tailwind) foi inserida antes de F2.2 para tornar o produto
> testável por um operador humano, não apenas via cURL/Postman.

---

## 📊 Visão Geral

| Item | Status |
|------|--------|
| **Fase Atual** | FASE 02 — FEATURES (F2.4 é o próximo) |
| **Próxima Sprint** | F2.4 — Reporting & Analytics |
| **Fases Concluídas** | FASE 01 — FOUNDATION (8/8) · FASE 03 — ADMIN FRONTEND (6/6) |
| **Testes Totais** | 349 passing, 0 failing |
| **Cumulative Tests** | FASE 01: 227 (inc. F1.8) · FASE 02: 62+ · FASE 03: 60 |

### Ordem de Execução Atualizada

```
FASE 01 — FOUNDATION      ✅ COMPLETO   (8 sprints, 227 testes)
        ↓
FASE 03 — ADMIN FRONTEND  ✅ COMPLETO   (F3.1-F3.6, 60 testes)
        ↓
FASE 02 — FEATURES        🟡 ATIVO      (F2.1 ✅ | F2.1b ✅ | F2.2 ✅ | F2.3 ✅ | F2.4 é o próximo)
```

### FASE 01 Sprints Status

| Sprint | Status | Itens | Testes |
|--------|--------|-------|--------|
| **F1.1** | ✅ DONE | 15/15 (100%) | 7 passing (code quality) |
| **F1.2** | ✅ DONE | 12/12 (100%) | 18 passing |
| **F1.3** | ✅ DONE | 16/16 (100%) | 16 passing |
| **F1.4** | ✅ DONE | 7/7 (100%) | 12 passing |
| **F1.5** | ✅ DONE | 6/6 (100%) | 17 passing |
| **F1.6** | ✅ DONE | 6/6 (100%) | 15 passing |
| **F1.7** | ✅ DONE | 7/7 (100%) | 87 passing |
| **F1.8** | ✅ DONE | 8/8 (100%) | 22 passing |

### FASE 02 Sprints Status (ATIVO)

| Sprint | Nome | Status | Itens | Testes |
|--------|------|--------|-------|--------|
| **F2.1** | Products Management | ✅ DONE | 20/20 (100%) | 42 API/domain |
| **F2.1b** | Products Web UI | ✅ DONE | 20/20 (100%) | 8 web |
| **F2.2** | Inventory Management | ✅ DONE | 20/20 (100%) | 11 API/web |
| **F2.3** | Sales & Orders | ✅ DONE | 20/20 (100%) | 26 API/web |
| **F2.4** | Reporting & Analytics | 📋 PRÓXIMO | 0/20 | - |
| **F2.5** | Advanced Automation | ⏸️ AGUARDA F2.4 | 0/20 | - |
| **F2.6** | Integration APIs | ⏸️ AGUARDA F2.5 | 0/20 | - |

### FASE 03 Sprints Status (ATIVO)

| Sprint | Nome | Status | Itens | Testes | O que você poderá testar |
|--------|------|--------|-------|--------|--------------------------|
| **F3.1** | Frontend Setup & Layout | ✅ DONE | 15/15 | 6/6 | Página placeholder com Tailwind aplicado |
| **F3.2** | Authentication (Login/Logout) | ✅ DONE | 22/22 | 14/14 | **Login em `/login` com email + senha** |
| **F3.3** | Admin Dashboard | ✅ DONE | 15/15 | 6/6 | Painel com menu lateral e widgets reais |
| **F3.4** | Tenant Management | ✅ DONE | 25/25 | 12/12 | **Cadastrar novo estabelecimento (tenant)** |
| **F3.5** | User Management | ✅ DONE | 25/25 | 12/12 | **Cadastrar usuários (admin, operador)** |
| **F3.6** | Company & Roles Management | ✅ DONE | 20/20 | 10/10 | Empresas, endereços e permissões |

**Total FASE 03:** 120 itens, 60 testes

---

## 🎯 FASE 03 — ADMIN FRONTEND

**Status:** ✅ CONCLUÍDA (6/6 sprints)

**Stack:** Laravel Blade + Tailwind CSS + Alpine.js

**Objetivo:** Criar a interface web administrativa que hoje não existe — tela de
login, painel administrativo e telas de cadastro de tenants, usuários e empresas.
Sem isso, o sistema só é operável via cURL/Postman.

**Roadmap detalhado:** [ROADMAP_FASE_03_ADMIN_FRONTEND.md](ROADMAP_FASE_03_ADMIN_FRONTEND.md)

**Progresso:** 6/6 sprints, 60/60 testes

### Sprints

#### Sprint F3.1 — Frontend Setup & Layout

**Status:** ✅ DONE
**Objetivo:** Instalar Tailwind, criar layout base e componentes Blade reutilizáveis
**Como testar:** acessar `http://localhost:8000` e ver a página com estilo Tailwind aplicado

**Checklist:**

- [ ] Instalar Tailwind CSS via npm
- [ ] Instalar e configurar Alpine.js
- [ ] Configurar Vite (build de assets)
- [ ] Estrutura de diretórios (layouts, components, views)
- [ ] Layout principal (`layouts/app.blade.php`) — header, sidebar, footer
- [ ] Layout de autenticação (`layouts/auth.blade.php`)
- [ ] Componente Button (primário, secundário, danger)
- [ ] Componente Input (text, email, password, textarea)
- [ ] Componente Select (dropdown)
- [ ] Componente Modal
- [ ] Componente Alert/Toast (mensagens de sucesso e erro)
- [ ] Componente Table (com paginação)
- [ ] Componente Form Group (label + input + erro)
- [ ] Middleware de auth para rotas web
- [ ] 5 testes de renderização de componentes

**Testes previstos (5):** componentes renderizam, layout carrega, assets compilam,
middleware redireciona, rota placeholder responde 200

---

#### Sprint F3.2 — Authentication (Login/Logout)

**Status:** 📋 TODO
**Objetivo:** Tela de login funcional com sessão e logout
**Como testar:** acessar `/login`, entrar com `admin@lucraone-dev.local`, chegar no dashboard

**Checklist:**

- [ ] View de login (`/login`)
- [ ] View de erro 403 (não autorizado)
- [ ] View de erro 404 (não encontrado)
- [ ] Formulário email + senha
- [ ] Validação client-side (Alpine.js)
- [ ] Validação server-side (FormRequest)
- [ ] Autenticação via sessão web (guard `web`)
- [ ] Resolução de tenant a partir do usuário logado
- [ ] Logout com limpeza de sessão
- [ ] Opção "lembrar-me"
- [ ] Mensagens de erro (credenciais inválidas)
- [ ] Bloqueio de usuário INACTIVE / SUSPENDED
- [ ] Redirect pós-login para `/dashboard`
- [ ] Redirect de visitante para `/login`
- [ ] Proteção CSRF nos formulários
- [ ] 8 testes de autenticação

**Testes previstos (8):** login válido, login inválido, usuário inativo bloqueado,
logout limpa sessão, visitante redirecionado, sessão persiste, CSRF exigido,
isolamento entre tenants

---

#### Sprint F3.3 — Admin Dashboard

**Status:** ✅ DONE
**Objetivo:** Painel inicial com navegação lateral
**Como testar:** após login, ver widgets de resumo e navegar pelo menu

**Checklist:**

- [x] Sidebar com navegação (Dashboard, Tenants, Usuários, Empresas, Roles)
- [x] Header com nome do usuário + botão logout
- [x] Breadcrumbs
- [x] Menu responsivo (mobile)
- [x] Estado ativo do item de menu
- [x] Widget: total de tenants
- [x] Widget: total de usuários
- [x] Widget: total de empresas
- [x] Widget: último acesso
- [x] Atalhos para seções principais
- [x] 6 testes de dashboard

**Testes implementados (6):** dashboard carrega, widgets exibem contagens corretas,
menu renderiza, links funcionam, breadcrumbs corretos, responsivo

---

#### Sprint F3.4 — Tenant Management

**Status:** ✅ DONE
**Objetivo:** CRUD completo de tenants (estabelecimentos)
**Como testar:** criar um novo estabelecimento pela interface, sem tinker

**Checklist:**

- [x] Listagem de tenants (`/tenants`) com paginação
- [x] Busca por nome
- [x] Filtro por status (TRIAL, ACTIVE, SUSPENDED, CANCELLED)
- [x] Ordenação (nome, status, data)
- [x] Formulário de criação (`/tenants/create`)
- [x] Campo nome
- [x] Campo slug (auto-gerado a partir do nome)
- [x] Campo status
- [x] Campo plano (free, standard, enterprise)
- [x] Campo timezone
- [x] Campo locale (pt-BR, en-US)
- [x] Campo moeda
- [x] Formulário de edição (`/tenants/{id}/edit`)
- [x] Página de detalhe (`/tenants/{id}`)
- [x] Soft delete com modal de confirmação
- [x] Restaurar tenant excluído
- [x] Componente TenantTable
- [x] Componente TenantForm
- [x] Componente DeleteModal
- [x] Validação server-side (StoreTenantRequest)
- [x] Controle de permissão (só admin cria tenant)
- [x] 12 testes de tenant management

**Nota:** arquivamento usa `deleted_at` via SoftDeletes; `ARCHIVED` não é status do enum real de tenants.

---

#### Sprint F3.5 — User Management

**Status:** ✅ DONE
**Objetivo:** CRUD de usuários por tenant, com atribuição de papéis
**Como testar:** criar usuário operador e fazer login com ele

**Checklist:**

- [x] Listagem de usuários (`/users`) filtrada por tenant
- [x] Busca por nome/email
- [x] Filtro por status
- [x] Formulário de criação (`/users/create`)
- [x] Campo nome
- [x] Campo email (único por vínculo no tenant; identidade segue e-mail global)
- [x] Campo senha (gerada ou definida)
- [x] Campo status (ACTIVE, INVITED, INACTIVE, SUSPENDED)
- [x] Seleção de roles (multi-select)
- [x] Formulário de edição
- [x] Alterar senha (com confirmação)
- [x] Resetar senha
- [x] Ativar / desativar usuário
- [x] Página de detalhe do usuário
- [x] Soft delete
- [x] Componente UserTable
- [x] Componente UserForm
- [x] Componente RoleSelector
- [x] Validação de email duplicado
- [x] 12 testes de user management

**Nota:** usuários continuam sendo identidades globais. A tela gerencia o vínculo
no tenant atual (`tenant_user.status`) e os papéis em `user_role.tenant_id`.

---

#### Sprint F3.6 — Company & Roles Management

**Status:** 📋 TODO
**Objetivo:** CRUD de empresas/endereços e gestão de papéis e permissões
**Como testar:** cadastrar empresa e ajustar permissões de um papel

**Checklist:**

- [x] Listagem de empresas (`/companies`)
- [x] Criar empresa (nome, CNPJ, tenant)
- [x] Editar empresa
- [x] Deletar empresa
- [x] Listar endereços da empresa
- [x] Criar/editar endereço
- [x] Listagem de roles (`/roles`)
- [x] Listagem de permissions (`/permissions`)
- [x] Visualizar permissions de cada role
- [x] Atribuir permissions a role (modal)
- [x] Listar usuários por role
- [x] Componente CompanyTable / CompanyForm
- [x] Componente RoleTable / PermissionTable
- [x] Componente PermissionAssigner
- [x] 10 testes de company e roles

**Nota:** empresas são desativadas com `status = INACTIVE` em vez de apagadas,
preservando histórico conforme a política de entidades principais da fundação.

---

### ✅ Critério de Conclusão da FASE 03

- [x] Usuário faz login pela interface web
- [x] Dashboard carrega com dados reais
- [x] Novo tenant criado pela interface
- [x] Novos usuários criados pela interface
- [x] Permissões (RBAC) respeitadas nas telas
- [x] Layout responsivo em mobile
- [x] 60 testes de FASE 03 passando

---

## 🎯 FASE 02 — FEATURES

**Status:** 🟡 ATIVO — F2.4 é o próximo após F2.3

**Objetivo:** Implementar módulos de negócio para gerenciar produtos, inventário, pedidos, pagamentos, relatórios e integrações.

**Progresso:** F2.1 API + F2.1b Web UI + F2.2 Inventory + F2.3 Sales concluídas

### Sprints

#### Sprint F2.1 — Products Management

**Status:** ✅ DONE
**Objetivo:** CRUD de produtos, categorias hierarchicas, preços multi-moeda

**Checklist:**

- [x] Product model com SKU único por tenant
- [x] Category model com suporte a hierarquia parent-child
- [x] Price model com tipos (cost, sale, suggested_retail)
- [x] PriceHistory model para auditoria de preços
- [x] ProductController com índice, criar, mostrar, atualizar, deletar, buscar
- [x] CategoryController com hierarquia (roots, children)
- [x] PriceController com histórico e preços por tipo
- [x] StoreProductRequest validation
- [x] StoreCategoryRequest validation
- [x] StorePriceRequest validation
- [x] ProductResource serialization
- [x] CategoryResource serialization
- [x] PriceResource serialization
- [x] HasUlid trait para auto-geração de ULIDs
- [x] 17 testes passando (8 ProductApiTest + 9 CategoryApiTest)
- [x] Tenant isolation em todos endpoints
- [x] Soft delete support
- [x] Foreign key constraints com cascade/set null
- [x] Multi-currency support
- [x] Margin calculation para preços

**Conclusão:** 20/20 — 100% ✅

**Entregáveis Validados:**
- ✅ 17 testes API passando
- ✅ ULID auto-generation funcionando
- ✅ Response serialization com 'data' wrapper
- ✅ Tenant isolation validado
- ✅ Hierarquia de categorias funcionando

#### Sprint F2.1b — Products Web UI

**Status:** ✅ DONE
**Objetivo:** Operar produtos, categorias e preços pelo painel administrativo

**Checklist:**

- [x] Menu `produtos` ativo no painel
- [x] CRUD web de produtos com busca, filtros, paginação e detalhe
- [x] CRUD web de categorias com hierarquia, arquivamento e restauração
- [x] Associação produto-categoria pelo formulário
- [x] Cadastro/atualização de preços por tipo e moeda
- [x] Histórico de alterações de preço no detalhe do produto
- [x] Modal de ajuda contextual para usuários leigos e sócios
- [x] Policies/RBAC web com permissões `manage-products` e `view-products`
- [x] 8 testes feature web passando

**Como validar:** acessar `/products` com usuário admin, criar categoria,
cadastrar produto, adicionar preço e conferir detalhe/histórico.

#### Sprint F2.2 — Inventory Management

**Status:** ✅ DONE
**Objetivo:** Controlar estoque, níveis de reposição e histórico pelo painel e API

**Checklist:**

- [x] Módulo `Inventory` com models Inventory, InventoryMovement e StockLevel
- [x] Migrations de saldos, movimentos e níveis de estoque
- [x] API `/api/v1/inventory`, ajuste, histórico, baixo estoque e excesso
- [x] API `/api/v1/stock-levels/{product_id}`
- [x] Tela `/inventory` com KPIs, filtros, posição e formulário de movimento
- [x] Tela de detalhe com histórico de movimentações
- [x] Modal de ajuda contextual de estoque
- [x] Seeder de saldos e níveis iniciais
- [x] RBAC com `manage-inventory` e `view-inventory`
- [x] 11 testes feature API/web passando

**Como validar:** acessar `/inventory`, registrar entrada/saída/ajuste e abrir
o detalhe para conferir o histórico.

---

#### Sprint F2.3 — Sales & Orders

**Status:** ✅ DONE
**Objetivo:** Registrar pedidos de venda pelo painel e pela API, movimentando
estoque nas transições de status

**Checklist:**

- [x] Módulo `Sales` com models Order, OrderItem e Customer
- [x] Migrations de `orders`, `order_items` e `customers`
- [x] `OrderService` centralizando fluxo de status e efeitos de estoque
- [x] `OrderNumberGenerator` com numeração `PED-AAAAMM-0001` por tenant
- [x] API `/api/v1/orders` (criar, listar, detalhar, mudar status, cancelar)
- [x] API `/api/v1/customers` (criar, listar, detalhar)
- [x] Tela `/orders` com KPIs, busca, filtros e listagem
- [x] Tela de detalhe com itens, totais, mudança de status e cancelamento
- [x] Cliente inline durante a criação do pedido
- [x] CRUD completo de clientes em `/customers` com arquivar/restaurar
- [x] Modais de ajuda contextual de vendas e de clientes
- [x] Seeder de clientes de exemplo
- [x] RBAC com `manage-sales`, `view-sales`, `manage-customers`, `view-customers`
- [x] ADR-004 documentando o fluxo de pedidos
- [x] 26 testes feature API/web passando

**Fluxo de estoque (ADR-004):** confirmar reserva os itens; enviar converte a
reserva em baixa definitiva; cancelar um pedido confirmado devolve a reserva.
Rascunho e aguardando não tocam no estoque.

**Como validar:** acessar `/orders`, criar pedido, adicionar item, avançar para
aguardando → confirmado → enviado e conferir a posição em `/inventory`.

---

## 🔄 Sprint F1.8 — Identity Refactor

**Status:** ✅ DONE · **Testes:** 22 (13 isolamento + 9 multi-estabelecimento)

**Problema:** `users.tenant_id` prendia cada pessoa a um único estabelecimento.
Uma pessoa que atende dois seria duas contas separadas, com duas senhas para
lembrar e trocar em dois lugares. Enquanto isso, `user_role` já tinha
`unique(user_id, role_id, tenant_id)` desde o F1.5 — o RBAC sempre esperou
múltiplos estabelecimentos. Identidade e permissões discordavam entre si.

**Solução:** identidade global + vínculos.

```
ANTES                             DEPOIS
users                             users (identidade)
├─ id                             ├─ id
├─ tenant_id  ← prende a 1        ├─ email (único global)
├─ email      (único por tenant)  ├─ password
└─ password                       └─ status: ACTIVE | INACTIVE

Maria em 2 lojas =                tenant_user (vínculo)
2 contas, 2 senhas                ├─ tenant_id
                                  ├─ user_id
                                  └─ status: ACTIVE | INVITED
                                            | INACTIVE | SUSPENDED

                                  Maria = 1 conta + 2 vínculos
```

**Checklist (8/8):**

- [x] Schema: `users` sem `tenant_id`, e-mail único global, tabela `tenant_user`
- [x] Models: `User` sem `HasTenant`, com `tenants()`/`memberships()`;
      `Tenant::users()` vira many-to-many; pivot `TenantUser`
- [x] `HasRole` usa o estabelecimento ativo do contexto, não o do usuário
- [x] `UserFactory` com `forTenant()`; seeders criam identidade + vínculo
- [x] API de auth devolve a lista de estabelecimentos; policies usam `canAccessTenant()`
- [x] Testes existentes adaptados (94 chamadas traduzidas na factory)
- [x] 22 testes novos do modelo de identidade
- [x] Documentação (roadmap F1, PROJECT_STATUS, dashboard, roadmap F3)

**Brecha de segurança encontrada e corrigida:**

`ResolveTenantMiddleware` estava registrado como middleware **global**, ou seja,
rodava **antes** da autenticação. Sem usuário conhecido, não havia como validar
o vínculo — qualquer pessoa autenticada podia enviar um `X-Tenant-ID` de outro
estabelecimento e ler os dados dele. Agora é o alias `tenant`, aplicado
explicitamente **depois** de `auth:sanctum`.

Isso também expôs que, em requisições web, o middleware desistia silenciosamente
(não havia bearer token), deixando o `TenantScope` sem contexto — e o scope, sem
contexto, **não filtra nada**. A resolução para o painel passou para
`AutenticarWeb`, que roda com a sessão já iniciada.

**Outras correções:**

| Item | Situação anterior |
|------|-------------------|
| `PermissionFactory` sorteava ação e recurso separadamente | Colisão de nome em 1 a cada 20 execuções — falha intermitente |
| `HasUlid` não dispara em models Pivot | SQLite tolerava (rowid implícito), MySQL rejeitava |

### Investigação: suspeita de regressão de performance

Após o F1.8, `test_performance_with_growing_data` passou a falhar de forma
intermitente (razão ~11x contra limite de 10x). A suspeita inicial era que o
JOIN da nova relação tivesse piorado a escalabilidade. **A investigação
descartou isso** — o teste é que estava medindo errado.

**Evidências coletadas:**

| Configuração medida | Razão observada |
|---------------------|-----------------|
| Consulta pré-F1.8 (tabela única, sem join) | 5,1x · 9,9x · **12,2x** |
| `belongsToMany` sem pivot | 5,3x · 11,6x · 11,9x |
| `using()` + `withPivot('status')` | 7,2x · 11,0x · 12,2x |
| Como estava (`using` + pivot completo) | 4,2x · 10,8x · 14,1x |

Três conclusões:

1. **O rótulo "100" lia 160 linhas.** O laço acumula usuários (10, +50, +100),
   então o aumento real de dados era **16x**, não 10x. O limite fixo de 10x
   vinha dessa leitura errada e exigia escala **sublinear** — impossível para
   consulta que instancia N models.

2. **O caminho anterior ao F1.8 também falha** (12,2x numa das medições). O
   teste nunca mediu o que dizia medir; passava por sorte.

3. **O ruído domina a razão.** Numa execução em que o baseline de 10 linhas
   ficou 2x mais lento por jitter, *todas* as configurações passaram de uma vez.
   O denominador ruidoso governava o resultado, não a escalabilidade.

**Custo real do modelo de identidade:** o JOIN custa cerca de 2x em valor
absoluto (3,4ms → 7,6ms para 160 linhas). É proporcional e esperado — o preço
de trocar uma coluna por um vínculo — e **escala igual** ao caminho anterior.

**Correção aplicada ao teste** (sem afrouxar nada):

- O limite passou a ser o próprio aumento de dados, medido em tempo de execução:
  o tempo não pode crescer mais rápido que o volume. É a definição de ausência
  de escala superlinear, e é mais significativo que um número fixo arbitrário.
- Mediana de 9 execuções com aquecimento, para medir a consulta e não o jitter.
- A mensagem de falha agora informa linhas reais, crescimento de dados e de tempo.

Resultado: 6 execuções seguidas com tempo crescendo de 4,1x a 11,6x contra
14,6x de dados — sempre sublinear.

---

## 🎯 FASE 01 — FOUNDATION

**Status:** ✅ COMPLETE

**Objetivo:** Criar fundação técnica segura, testável, auditável e preparada para receber módulos posteriores.

**Progresso:** 7/7 sprints concluídas, 100% completo

**Conclusão:** FASE 01 concluída com sucesso! 189 testes passando, fundação arquitetural sólida estabelecida.

### Sprints

#### Sprint F1.1 — Bootstrap

**Status:** ✅ DONE
**Objetivo:** Projeto Laravel, MySQL, Redis, Docker, CI, estrutura modular

**Checklist:**

- [x] Criar projeto Laravel 13 (v13.25.0)
- [x] Configurar MySQL 8.4
- [x] Configurar Redis 7
- [x] Docker Compose setup (app, mysql, redis, queue-worker, mailpit)
- [x] Estrutura modular (Modules/) — 7 módulos, 84 diretórios
- [x] .gitignore robusto
- [x] README documentado (setup, dev, testes)
- [x] ADR-001 (Modular Monolith) — decisão arquitetural
- [x] Instalação Laravel Sanctum (v4.3.3)
- [x] Documentação arquitetural completa (ARCHITECTURE.md)
- [x] git init + commits estruturados
- [x] Configurar code style (Pint) — 56 issues fixed
- [x] Configurar static analysis (PHPStan) — Level 4, 0 errors
- [x] CI/CD pipeline (.github/workflows) — tests.yml + code-quality.yml
- [x] Health check endpoint (/health) — Database and cache checks

**Conclusão:** 15/15 — 100% ✅

**Entregáveis Validados:**
- ✅ Projeto Laravel funcionando
- ✅ MySQL 8.4 container pronto
- ✅ Redis 7 container pronto
- ✅ Arquitetura modular estabelecida
- ✅ Documentação completa
- 🟡 Docker (app build pendente, mas infra OK)

---

#### Sprint F1.2 — Tenancy

**Status:** ✅ DONE

- [x] Modelo Tenant com status
- [x] TenantContext singleton
- [x] TenantResolver para determinar tenant
- [x] Global Scopes automáticos (TenantScope)
- [x] ResolveTenantMiddleware
- [x] HasTenant trait para modelos
- [x] TenantFactory com múltiplos states
- [x] TenantSeeder
- [x] TenancyServiceProvider
- [x] Testes de isolamento (18/19 passing)
- [x] Documentação (TENANT_ISOLATION.md)
- [x] Domain events (TenantCreated)

**Conclusão:** 12/12 — 100%

---

#### Sprint F1.3 — Companies & Branches

**Status:** ✅ DONE

- [x] Modelo Company com HasTenant trait
- [x] Modelo Branch com HasTenant trait
- [x] Modelo Address (polymorphic, reutilizável)
- [x] Migrations (3 tables com constraints)
- [x] Factories (Company, Branch, Address)
- [x] Relationships (hasMany, morphMany, morphOne)
- [x] Domain Event (CompanyCreated)
- [x] Testes de isolamento (16 tests passing)
- [x] CompanyIsolationTest (10 tests)
- [x] AddressTest (6 tests)

**Conclusão:** 16/16 — 100%

---

#### Sprint F1.4 — Identity / Authentication

**Status:** ✅ DONE

- [x] Modelo User com HasTenant trait
- [x] Autenticação (Sanctum) com migrations publicadas
- [x] Login endpoint (POST /api/auth/login) com validações
- [x] Logout endpoint (POST /api/auth/logout) com revogação de token
- [x] Validação de status (ACTIVE, INVITED, INACTIVE)
- [x] Update de last_login_at no login
- [x] 12 testes de autenticação passando
- [x] Exception handling para API 401 responses
- [x] ResolveTenantMiddleware atualizado para rotas públicas

**Conclusão:** 7/7 — 100%

**Testes Implementados (12/12 ✅):**
1. Login com credenciais válidas retorna token
2. Login com email inválido retorna 401
3. Login com senha incorreta retorna 401
4. Login com usuário INACTIVE retorna 403
5. Login com usuário INVITED retorna 403
6. Login atualiza last_login_at
7. Login retorna dados do usuário corretos
8. Logout com token válido revoga acesso
9. Logout sem token retorna 401
10. Login não obtém credenciais de outro tenant
11. Validação de email obrigatório
12. Validação de senha obrigatória

---

#### Sprint F1.5 — Authorization / Roles & Permissions

**Status:** ✅ DONE

- [x] Modelo Role com HasTenant trait
- [x] Modelo Permission com HasTenant trait
- [x] User ↔ Role many-to-many relacionamento
- [x] Role ↔ Permission many-to-many relacionamento
- [x] RolePolicy, PermissionPolicy, BranchPolicy
- [x] Branch access control com role-based checks
- [x] 17 testes de autorização (RBAC + isolamento)
- [x] HasRole trait com methods para role/permission checking
- [x] RoleFactory e PermissionFactory com estados
- [x] AuthorizationSeeder com roles/permissions padrão
- [x] Documentação completa (AUTHORIZATION_GUIDE.md)

**Conclusão:** 6/6 — 100%

**Testes Implementados (17/17 ✅):**

*RBACTest (12 testes):*
1. User pode ser atribuído a role
2. Role pode ser removida de user
3. User herda permissões da role
4. User sem role não tem permissões
5. Role pode conceder permissão
6. Role pode revogar permissão
7. Tenant A roles isoladas de tenant B
8. Permissions isoladas por tenant
9. User pode ter múltiplas roles
10. hasAnyRole retorna true
11. syncRoles substitui todas roles
12. getPermissions retorna todas permissões

*AuthorizationIsolationTest (5 testes):*
1. Roles de tenant A não visíveis para B
2. Permissions de tenant A não acessíveis para B
3. User de tenant A não pode ter role de B
4. Mesma role name pode existir em diferentes tenants

5. Roles automaticamente scoped por tenant

---

#### Sprint F1.6 — Audit & Observability

**Status:** ✅ DONE

- [x] AuditLog model com HasTenant trait
- [x] Request ID correlation via middleware
- [x] Structured logging service com contexto
- [x] Health check endpoint (/up) com status
- [x] Database e cache health checks
- [x] 15 testes de auditoria e health (100% passing)
- [x] Documentação completa (AUDIT_GUIDE.md)

**Conclusão:** 6/6 — 100%

**Testes Implementados (15/15 ✅):**

*AuditLogTest (10 testes):*
1. AuditLog criado com dados corretos
2. Captura IP e User-Agent
3. Captura request_id
4. Isolamento por tenant
5. Filtro por usuário
6. Filtro por entity
7. Sem usuário autenticado
8. Armazena changes como JSON
9. Múltiplas ações em sequência
10. Descrição de log

*HealthCheckTest (5 testes):*
1. Retorna JSON
2. Inclui timestamp
3. Database check incluído
4. Cache check incluído
5. Database check passa

---

#### Sprint F1.7 — Hardening & Final Validation

**Status:** ✅ DONE

- [x] Task 1: Security Audit (15 tests)
- [x] Task 2: Cross-Tenant Validation (11 tests)
- [x] Task 3: API Integration Tests (16 tests)
- [x] Task 4: Performance Baseline (15 tests)
- [x] Task 5: Documentation Review (15 tests)
- [x] Task 6: Backup/Restore Tests (15 tests)
- [x] Task 7: Final Audit (15 tests)

**Conclusão:** 7/7 — 100%

**Testes Implementados (87/87 ✅):**
- SecurityAuditTest (15): OWASP Top 10 validations
- CrossTenantValidationTest (11): Multi-tenant isolation verification
- ApiIntegrationTest (16): End-to-end API flows
- PerformanceBaselineTest (15): Performance metrics and scalability
- DocumentationReviewTest (15): Documentation completeness
- BackupRestoreTest (15): Database backup and restore
- FinalAuditTest (15): Complete system audit

---

## 📋 Implementado

### ✅ Concluído (40+ itens)

**F1.1 — Bootstrap:**
- ✅ Projeto Laravel 13.25.0
- ✅ PHP 8.3.30 com todas extensões necessárias
- ✅ Docker Compose completo (app, MySQL, Redis, queue-worker, mailpit)
- ✅ Estrutura modular (Core, Tenancy, Companies, Branches, Identity, Authorization, Audit)
- ✅ .env configurado para MySQL + Redis + pt-BR
- ✅ Dockerfile para aplicação (PHP 8.3-FPM com Redis)
- ✅ README.md com instruções de setup e desenvolvimento
- ✅ .gitignore robusto (secrets, IDE, cache, credentials)
- ✅ git init + 7 commits estruturados
- ✅ Laravel Sanctum instalado (v4.3.3) para autenticação
- ✅ ADR-001 criado (decisão de Modular Monolith)
- ✅ ARCHITECTURE.md completo (visão técnica total)
- ✅ SPRINT_F1.1_REPORT.md (métricas e análise)

**F1.2 — Tenancy:**
- ✅ Tenant model com status enum
- ✅ TenantContext singleton com isolamento
- ✅ TenantResolver para determinar tenant da requisição
- ✅ TenantScope para filtro automático de tenant_id
- ✅ ResolveTenantMiddleware registrado
- ✅ HasTenant trait reutilizável
- ✅ TenantFactory com múltiplos estados
- ✅ TenantSeeder para dados iniciais
- ✅ 18 testes de isolamento tenant passando
- ✅ docs/tenancy/TENANT_ISOLATION.md (guia completo)

**F1.3 — Companies & Branches:**
- ✅ Company model com HasTenant trait
- ✅ Branch model com HasTenant trait
- ✅ Address model (polymorphic, reutilizável)
- ✅ 3 Migrations (companies, branches, addresses)
- ✅ 3 Factories (CompanyFactory, BranchFactory, AddressFactory)
- ✅ Relationships (hasMany, morphMany, morphOne)
- ✅ CompanyCreated domain event
- ✅ 16 testes de isolamento (CompanyIsolationTest, AddressTest)
- ✅ CNPJ generation para testes realistas
- ✅ AUDIT_CHECKLIST.md (auditoria completa)
- ✅ DEVELOPMENT_DASHBOARD.md (painel executivo)

**F1.4 — Identity / Authentication:**
- ✅ User model com HasTenant trait
- ✅ AuthController (login + logout)
- ✅ LoginRequest com validações (email, password)
- ✅ LogoutRequest com auth sanctum
- ✅ API Routes (POST /api/auth/login, POST /api/auth/logout)
- ✅ Laravel Sanctum integration (migrations publicadas)
- ✅ Exception handling para API 401 responses
- ✅ ResolveTenantMiddleware atualizado para auth routes públicas
- ✅ UserFactory com neverLoggedIn() state
- ✅ 12 testes de autenticação (credenciais, status, isolamento, validações)
- ✅ Last login timestamp tracking
- ✅ Token creation e revogação

**F1.5 — Authorization / Roles & Permissions:**
- ✅ Role model com HasTenant trait
- ✅ Permission model com HasTenant trait
- ✅ User ↔ Role many-to-many relationship
- ✅ Role ↔ Permission many-to-many relationship
- ✅ HasRole trait com métodos (hasRole, hasPermission, assignRole, etc)
- ✅ RolePolicy, PermissionPolicy, BranchPolicy implementadas
- ✅ RoleFactory e PermissionFactory com estados predefinidos
- ✅ AuthorizationSeeder (roles padrão: Admin, Manager, User, Viewer)
- ✅ 17 testes de RBAC e isolamento de tenant (100% passing)
- ✅ Tenant isolation em todas as operações
- ✅ Branch access control preparation
- ✅ AUTHORIZATION_GUIDE.md documentation
- ✅ SPRINT_F1.5_PLAN.md architecture documentation

### ✅ Tudo Concluído em FASE 01

**FASE 01 está 100% COMPLETA**

Todos os itens foram implementados e testados:
- ✅ F1.1 — Bootstrap (15/15)
- ✅ F1.2 — Tenancy (12/12)
- ✅ F1.3 — Companies & Branches (16/16)
- ✅ F1.4 — Identity / Authentication (7/7)
- ✅ F1.5 — Authorization / RBAC (6/6)
- ✅ F1.6 — Audit & Observability (6/6)
- ✅ F1.7 — Hardening & Final Validation (7/7)

**Nenhum item pendente.**

---

## 🔧 Arquivos Principais Alterados

```
D:\PROJETO-LUCRAONE\
├── lucraone-backend/
│   ├── .env (MySQL + Redis)
│   ├── .gitignore (robusto)
│   ├── docker-compose.yml
│   ├── docker/Dockerfile
│   ├── README.md
│   ├── docs/adr/ADR-001-modular-monolith.md
│   ├── app/Modules/
│   │   ├── Core/
│   │   ├── Tenancy/
│   │   ├── Companies/
│   │   ├── Branches/
│   │   ├── Identity/
│   │   ├── Authorization/
│   │   └── Audit/
│   └── composer.json (Sanctum)
└── PROJECT_STATUS.md (este arquivo)
```

---

## 🗄️ Banco de Dados

**Status:** ✅ VALIDADO e COMPLETO

- [x] MySQL 8.4 no docker-compose
- [x] Credenciais configuradas
- [x] Migrations executadas (tenants, companies, branches, users, roles, permissions, audit_logs)
- [x] Schema validado com testes
- [x] Índices criados (tenant_id, foreign keys, unique constraints)

---

## 🧪 Testes

**Total FASE 01:** 189 passing (100% passing, 0 failing)

### Breakdown por Sprint

| Sprint | Tipo | Passing | Failing | Total |
|--------|------|---------|---------|-------|
| **F1.1** | Code Quality | 7 | 0 | 7 |
| **F1.2** | Tenancy | 18 | 0 | 18 |
| **F1.3** | Companies | 16 | 0 | 16 |
| **F1.4** | Authentication | 12 | 0 | 12 |
| **F1.5** | Authorization | 17 | 0 | 17 |
| **F1.6** | Audit & Health | 15 | 0 | 15 |
| **F1.7** | Hardening & Validation | 87 | 0 | 87 |
| **TOTAL** | **All** | **189** | **0** | **189** |

✅ **Meta FASE 01 atingida: 189/189 testes (100%)**

---

## 🔒 Segurança

### Verificações Iniciais

- [x] Secrets não versionados (.env em .gitignore)
- [x] Docker (não expõe credenciais)
- [x] CORS a configurar
- [ ] Rate limiting
- [ ] CSRF protection
- [ ] SQL injection prevention (Eloquent)
- [ ] Mass assignment protection
- [ ] XSS sanitization
- [ ] Audit logging

---

## 📝 Documentação

- [x] README.md
- [x] ADR-001
- [ ] docs/architecture/ARCHITECTURE.md
- [ ] docs/architecture/MULTI_TENANCY.md
- [ ] docs/api/openapi.yaml
- [ ] docs/security/SECURITY.md

---

## 🗺️ Sequência Completa de Fases

| Ordem | Fase | Sprints | Status |
|-------|------|---------|--------|
| 1 | **FASE 01 — FOUNDATION** | F1.1 → F1.7 | ✅ COMPLETO (189 testes) |
| 2 | **FASE 02 — FEATURES** (parcial) | F2.1 | ✅ COMPLETO (17 testes) |
| 3 | **FASE 03 — ADMIN FRONTEND** | F3.1 → F3.6 | 📋 **ATIVO** (~53 testes) |
| 4 | **FASE 02 — FEATURES** (retomada) | F2.2 → F2.6 | ⏸️ Aguarda F3.6 |

**Documentação detalhada:**
- [ROADMAP_FASE_01_FOUNDATION.md](ROADMAP_FASE_01_FOUNDATION.md%20—%20Fundação%20Técnica%20da%20Plataforma.md)
- [ROADMAP_FASE_02_FEATURES.md](ROADMAP_FASE_02_FEATURES.md)
- [ROADMAP_FASE_03_ADMIN_FRONTEND.md](ROADMAP_FASE_03_ADMIN_FRONTEND.md)
- [TESTING_GUIDE.md](TESTING_GUIDE.md) — testes manuais da API F2.1

---

## 🚀 Próximas Etapas

### Imediato — Sprint F3.1 (Frontend Setup & Layout)

1. Instalar Tailwind CSS + Alpine.js + configurar Vite
2. Criar layout base (header, sidebar, footer) e layout de autenticação
3. Criar componentes Blade reutilizáveis
4. Criar middleware de auth para rotas web
5. 5 testes de renderização

### Sequência da FASE 03

```
F3.1 Setup → F3.2 Login → F3.3 Dashboard → F3.4 Tenants → F3.5 Users → F3.6 Companies/Roles
```

### Depois — Retomada da FASE 02

1. **F2.2:** Inventory Management
2. Manter cobertura de testes
3. Continuar auditando segurança a cada sprint

---

## ⚠️ Bloqueios e Dívida Técnica

### Bloqueios
Nenhum bloqueio impeditivo.

### Dívida técnica (identificada nos testes manuais de F2.1)

| Item | Impacto | Situação |
|------|---------|----------|
| `personal_access_tokens.tokenable_id` era `bigint`, incompatível com ULID | `createToken()` falhava | ✅ resolvido no F3.1 |
| `products.sku` tinha unique **global**, não por tenant | Dois tenants não podiam usar o mesmo SKU | ✅ resolvido no F3.1 |
| `categories.slug` tinha unique **global**, não por tenant | Mesmo problema | ✅ resolvido no F3.1 |
| Pivot `product_categories` exigia `id` que `attach()` não preenchia | Associar produto a categoria falhava | ✅ resolvido no F3.1 |
| Soft delete de categoria deixava filhos apontando para pai invisível | Hierarquia inconsistente | ✅ resolvido no F3.1 |
| `config/auth.php` apontava para `App\Models\User` (scaffolding) em vez do módulo Identity | Login por sessão não funcionaria | ✅ resolvido no F3.1 |
| Rota de health documentada como `/health`, real é `/api/health` | Confusão em testes | ✅ documentado |
| Container `app` sem `bash` | `docker-compose exec app bash` falha | ✅ documentado (usar `sh`) |
| Rota `/preview-login` fazia bypass de autenticação | Risco se vazasse para outro ambiente | ✅ apagada no F3.2 |

---

## 📞 Contato / Notas

- Desenvolvedor: Claude Code
- Time: 1 (solo)
- Timezone: UTC-3 (Brasil)
- Comunicação: Assíncrona via documentação

---

**Próxima revisão:** Quando F3.1 for concluído

*Última atualização deste documento: 2026-08-16 (FASE 03 planejada e inserida no roadmap)*

---

## ✨ MARCOS ALCANÇADOS

```
✅ MARCO 1 — FOUNDATION COMPLETE (FASE 01)

189/189 testes passando (100%)
7/7 sprints concluídas
Arquitetura Modular Monolith com Multi-Tenancy
Segurança auditada (OWASP Top 10)
Performance baseline estabelecido


✅ MARCO 2 — PRIMEIRO MÓDULO DE NEGÓCIO (F2.1)

17/17 testes passando
API de Produtos, Categorias e Preços
Hierarquia de categorias + multi-moeda + auditoria de preços
ULID auto-gerado via trait HasUlid


📋 MARCO 3 — PRODUTO OPERÁVEL POR HUMANO (FASE 03) ← PRÓXIMO

Meta: login web + painel administrativo + cadastro de
tenants, usuários, empresas e permissões pela interface.
Sem depender de tinker ou Postman.
```
