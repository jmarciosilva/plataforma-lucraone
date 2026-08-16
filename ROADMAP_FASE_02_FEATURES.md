# ROADMAP_FASE_02_FEATURES.md
## Implementação de Features de Negócio — Plataforma SaaS de Automação Comercial

**Projeto:** Plataforma SaaS Inteligente de Automação Comercial  
**Fase:** 02 — FEATURES  
**Status:** 🟡 ATIVO — F2.1 concluído, F2.2 é o próximo
**Prioridade:** Alta  
**Dependência:** FASE 01 ✅ Concluída · FASE 03 ✅ Concluída
**Estimativa:** 6 sprints (~12-16 semanas)  

> ✅ **Retomada liberada em 2026-08-16.**
> A FASE 03 foi concluída, então a FASE 02 pode seguir para F2.2 —
> Inventory Management.
>
> ⏸️ **Pausa anterior em 2026-08-16 — após F2.1.**
> A FASE 02 entrega APIs de negócio, mas o sistema ainda não possui interface de
> acesso: não há tela de login nem painel administrativo para cadastrar tenants,
> usuários e empresas. Continuar acumulando endpoints sem UI tornaria o produto
> não testável por um operador humano.
>
> **Decisão:** inserir a [FASE 03 — ADMIN FRONTEND](ROADMAP_FASE_03_ADMIN_FRONTEND.md)
> (Blade + Tailwind) antes de F2.2. A FASE 02 retoma no Sprint F2.2 — Inventory
> Management após o F3.6.

### Progresso Atual

| Sprint | Status | Testes |
|--------|--------|--------|
| F2.1 — Products Management | ✅ DONE | 42 passing |
| F2.2 — Inventory Management | 📋 TODO | — |
| F2.3 — Orders / Vendas | ⏸️ Aguarda F2.2 | — |
| F2.4 — Payments | ⏸️ Aguarda F2.3 | — |
| F2.5 — Reports | ⏸️ Aguarda F2.4 | — |
| F2.6 — Integrations | ⏸️ Aguarda F2.5 | — |

---

# 1. Contexto

FASE 02 constrói sobre a fundação técnica sólida entregue por FASE 01.

Agora implementaremos os módulos de negócio que compõem a plataforma de automação comercial:

```text
Produtos → Estoque → Vendas → Pagamentos → Fiscal → Reporting
```

Cada módulo será desenvolvido em uma sprint dedicada com:
- Testes automatizados (unit + integration)
- Documentação arquitetural
- Validação de isolamento multi-tenant
- Auditoria de operações críticas

---

# 2. Objetivo Principal

Ao término de FASE 02, a plataforma deverá ser capaz de:

```text
Definir Produtos
    ↓
Categorizar e Precificar
    ↓
Registrar em Estoque
    ↓
Realizar Vendas
    ↓
Processar Pagamentos
    ↓
Gerar Documentos Fiscais
    ↓
Gerar Relatórios e Analytics
```

Todos com suporte a **múltiplos tenants**, **auditoria completa** e **testes automatizados**.

---

# 3. Princípios de FASE 02

1. **Consistência Arquitetural** — Manter padrões Modular Monolith de FASE 01
2. **Multi-Tenancy Obrigatória** — Todos os dados isolados por tenant
3. **Testes Primeiro** — Implementar testes antes da lógica (TDD quando possível)
4. **Auditoria Completa** — Rastrear todas operações sensíveis de negócio
5. **Performance** — Manter baselines de FASE 01
6. **Documentação** — ADRs para decisões arquiteturais importantes
7. **Segurança Contínua** — Validar OWASP Top 10 a cada sprint

---

# 4. Estrutura de Módulos FASE 02

```
app/Modules/
├── Core/                 # ✅ Já existe
├── Tenancy/              # ✅ Já existe
├── Companies/            # ✅ Já existe
├── Branches/             # ✅ Já existe
├── Identity/             # ✅ Já existe
├── Authorization/        # ✅ Já existe
├── Audit/                # ✅ Já existe
│
├── Products/             # ✅ F2.1 — Implementado
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
├── Inventory/            # 📋 F2.2 — Próximo
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
├── Sales/                # 🟡 F2.3 — Novo
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
├── Payments/             # 🟡 F2.4 — Futuro
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
├── Reporting/            # 🟡 F2.5 — Futuro
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
└── Integration/          # 🟡 F2.6 — Futuro
    ├── Domain/
    ├── Application/
    ├── Infrastructure/
    └── Http/
```

---

# 5. Especificação de Sprints

## Sprint F2.1 — Core Features: Products, Categories & Pricing

**Status:** ✅ DONE
**Concluído em:** 2026-08-16
**Testes:** 42 passing (`tests/Feature/Products`)
**Objetivo:** Permitir criação, categorização e precificação de produtos  

**Validação real em 2026-08-16:** módulo `app/Modules/Products` presente,
20 rotas API em `/api/v1`, migrations/factories criadas e suíte de Products
passando com 42 testes.

### Requisitos

#### Modelos
- [x] Product (SKU, name, description, tenant_id, company_id)
- [x] Category (name, parent_id, tenant_id) — hierarchical
- [x] ProductCategory (many-to-many)
- [x] Price (product_id, currency, amount, type, tenant_id)
- [x] PriceHistory (auditoria de mudanças de preço)

#### Banco de Dados
- [x] products table
- [x] categories table (with parent_id for hierarchy)
- [x] product_category pivot
- [x] prices table
- [x] price_history table (audit trail)

#### API Endpoints
- [x] POST /api/v1/products (create)
- [x] GET /api/v1/products (list with filters)
- [x] GET /api/v1/products/{id} (show)
- [x] PUT /api/v1/products/{id} (update)
- [x] DELETE /api/v1/products/{id} (soft delete)
- [x] POST /api/v1/categories (create)
- [x] GET /api/v1/categories (hierarchical list)
- [x] GET /api/v1/categories/roots
- [x] GET /api/v1/categories/{id}/children
- [x] PUT /api/v1/categories/{id} (update)
- [x] DELETE /api/v1/categories/{id} (soft delete)
- [x] POST /api/v1/prices (create/update)
- [x] GET /api/v1/prices/product/{product_id}
- [x] GET /api/v1/prices/sale/{product_id}
- [x] GET /api/v1/prices/cost/{product_id}
- [x] GET /api/v1/prices/history/{product_id} (audit trail)
- [x] DELETE /api/v1/prices/{price_id}

#### Features
- [x] Category hierarchy (parent-child relationships)
- [x] SKU uniqueness por tenant
- [x] Price versioning com histórico completo
- [x] Multi-currency support ready
- [x] Soft deletes para produtos e categorias
- [x] Tenant isolation em products, categories e prices
- [x] Busca de produtos por SKU/nome
- [x] Filtro de produtos por status
- [x] Cálculo de margem entre preço de custo e venda
- [ ] Product image upload (adiado; não entrou no F2.1 entregue)
- [ ] Bulk import de produtos (adiado; não entrou no F2.1 entregue)

#### Testes (42)
- [x] Product CRUD operations
- [x] Category hierarchy validation
- [x] Category isolation by tenant
- [x] Price history tracking
- [x] SKU uniqueness enforcement
- [x] Slug de categoria único por tenant
- [x] Soft delete de produto
- [x] Associação produto-categoria
- [x] Price scopes: sale/cost
- [x] Multi-currency pricing
- [x] Endpoints exigem autenticação
- [x] Tenant isolation na API
- [ ] Bulk import validation (adiado junto com importação CSV)
- [ ] Concurrent price updates (não implementado no F2.1 entregue)

#### Documentação
- [x] `TESTING_GUIDE.md` com testes manuais da API F2.1
- [x] `PROJECT_STATUS.md` e `DEVELOPMENT_DASHBOARD.md` registram F2.1 como DONE
- [ ] ADR-002: Product Domain Structure (pendente)
- [ ] API Specification (OpenAPI) (pendente)

**Nota:** a F2.1 foi considerada concluída como API operacional de produtos,
categorias e preços. Upload de imagens, importação CSV e concorrência avançada
de preços ficam para sprints futuras, pois não bloqueiam o Inventory Management.

---

## Sprint F2.2 — Inventory Management

**Duração:** ~2 semanas  
**Objetivo:** Controlar estoque com movimentações e níveis de reposição  

### Requisitos

#### Modelos
- [ ] Inventory (product_id, company_id, quantity_on_hand, reserved, available)
- [ ] InventoryMovement (type, quantity, reason, datetime, tenant_id)
- [ ] StockLevel (min_qty, max_qty, reorder_point)

#### Banco de Dados
- [ ] inventories table
- [ ] inventory_movements table
- [ ] stock_levels table

#### API Endpoints
- [ ] GET /api/v1/inventory (company level)
- [ ] POST /api/v1/inventory/{product_id}/adjust (movement)
- [ ] GET /api/v1/inventory/{product_id}/movements (history)
- [ ] POST /api/v1/stock-levels/{product_id} (set min/max)
- [ ] GET /api/v1/inventory/low-stock (alert)
- [ ] GET /api/v1/inventory/overstock (alert)

#### Features
- [ ] Real-time inventory updates
- [ ] Movement audit trail (quem, quando, por quê)
- [ ] Low stock alerts
- [ ] Overstock detection
- [ ] Transfer between branches
- [ ] Batch/serial number tracking (preparação)
- [ ] Reserved quantity for pending orders

#### Testes (18+)
- [ ] Inventory movements
- [ ] Stock level validation
- [ ] Low stock alerts
- [ ] Transfer operations
- [ ] Concurrent updates (locking)
- [ ] Audit trail completeness

#### Documentação
- [ ] ADR-003: Inventory Strategy
- [ ] Inventory Module Guide

---

## Sprint F2.3 — Sales & Orders

**Duração:** ~2 semanas  
**Objetivo:** Implementar sistema de pedidos e vendas  

### Requisitos

#### Modelos
- [ ] Order (order_number, customer_id, branch_id, status, total, tenant_id)
- [ ] OrderItem (order_id, product_id, quantity, unit_price)
- [ ] Customer (name, email, phone, company_id)

#### Banco de Dados
- [ ] orders table
- [ ] order_items table
- [ ] customers table

#### API Endpoints
- [ ] POST /api/v1/orders (create)
- [ ] GET /api/v1/orders (list)
- [ ] GET /api/v1/orders/{id} (show)
- [ ] PUT /api/v1/orders/{id}/status (update status)
- [ ] DELETE /api/v1/orders/{id} (cancel)
- [ ] POST /api/v1/customers (create)
- [ ] GET /api/v1/customers (list)

#### Features
- [ ] Order status workflow (draft → pending → confirmed → shipped → completed)
- [ ] Inventory reservation on order creation
- [ ] Automatic pricing from price table
- [ ] Customer creation inline during order
- [ ] Order cancellation with inventory release
- [ ] Payment link generation

#### Testes (22+)
- [ ] Order creation
- [ ] Inventory reservation
- [ ] Status transitions
- [ ] Concurrent orders (no overselling)
- [ ] Cancellation scenarios
- [ ] Pricing accuracy

#### Documentação
- [ ] ADR-004: Order Workflow
- [ ] Sales Module Guide

---

## Sprint F2.4 — Reporting & Analytics

**Duração:** ~2 semanas  
**Objetivo:** Gerar relatórios executivos e dashboards  

### Requisitos

#### Modelos
- [ ] Report (query definition, schedule, recipients)
- [ ] Dashboard (widget definitions)

#### API Endpoints
- [ ] GET /api/v1/reports/sales (daily/weekly/monthly)
- [ ] GET /api/v1/reports/inventory (stock status)
- [ ] GET /api/v1/reports/customers (top customers)
- [ ] GET /api/v1/dashboard/summary (KPIs)
- [ ] GET /api/v1/analytics/trends (sales trends)

#### Features
- [ ] Real-time KPI dashboard
- [ ] Sales by period (day/week/month/year)
- [ ] Inventory value analysis
- [ ] Top selling products
- [ ] Customer segmentation
- [ ] Revenue forecasting (basic)
- [ ] Scheduled report generation (emails)

#### Testes (15+)
- [ ] Report accuracy
- [ ] Aggregation performance
- [ ] Date filtering
- [ ] Tenant isolation in reports

#### Documentação
- [ ] Reporting Architecture
- [ ] Analytics Guide

---

## Sprint F2.5 — Advanced Automation

**Duração:** ~2 semanas  
**Objetivo:** Automações de negócio baseadas em regras  

### Requisitos

#### Modelos
- [ ] AutomationRule (trigger, condition, action, tenant_id)
- [ ] AutomationLog (execution history)

#### Features
- [ ] Rules engine: when X then do Y
- [ ] Triggers: product created, stock low, order completed
- [ ] Actions: send email, create task, update price
- [ ] Scheduled tasks (cron)
- [ ] Audit log de execuções

#### Testes (12+)
- [ ] Rule evaluation
- [ ] Action execution
- [ ] Error handling

#### Documentação
- [ ] Automation Rules Guide

---

## Sprint F2.6 — Integration APIs

**Duração:** ~2 semanas  
**Objetivo:** Integração com sistemas externos  

### Requisitos

#### Modelos
- [ ] Integration (type, endpoint, credentials_encrypted)
- [ ] IntegrationLog (request/response)

#### Suportes Iniciais
- [ ] Webhook delivery
- [ ] ERP sync (genérico)
- [ ] Marketplace sync (preparação para Amazon, B2B)
- [ ] Payment gateway integration (Stripe ready)

#### Testes (10+)
- [ ] Webhook handling
- [ ] Retry logic
- [ ] Error recovery

#### Documentação
- [ ] Integration Guide
- [ ] Webhook Specification

---

# 6. Critérios de Aceite por Sprint

Uma sprint é considerada **DONE** quando:

✅ Todos os requisitos implementados  
✅ Testes escrevendo e passando (≥90% coverage)  
✅ Zero security vulnerabilities  
✅ Documentação atualizada (ADR, API, guides)  
✅ Isolamento multi-tenant validado  
✅ Auditoria funcionando  
✅ Performance dentro baselines  
✅ Code review aprovado  
✅ Commit estruturado no git  

---

# 7. Padrões de Implementação

### Validação
```php
// Form Requests para entrada
app/Modules/{Module}/Http/Requests/

// Domain Validation para regra de negócio
app/Modules/{Module}/Domain/Rules/
```

### Autenticação & Autorização
```php
// Sempre validar tenant_id
// Sempre usar policies para autorização
// Sempre usar HasTenant trait
```

### Auditoria
```php
// Registrar criações, atualizações, deleções
// Incluir IP, User-Agent, request_id
// Armazenar old/new values para mudanças
```

### Testes
```php
// 1 teste por comportamento crítico
// Sempre testar isolamento tenant
// Sempre testar error cases
// Usar factories para dados
```

---

# 8. Dependências & Integração

### Ordem Recomendada
```
F2.1 — Products ✓
  ↓
F2.2 — Inventory (depende de produtos)
  ↓
F2.3 — Sales (depende de produtos + inventory)
  ↓
F2.4 — Reporting (depende de sales)
  ↓
F2.5 — Automation (independente)
  ↓
F2.6 — Integration (independente)
```

### Compatibilidade com FASE 01
- ✅ Todos os módulos usam TenantContext
- ✅ Todos os modelos têm tenant_id
- ✅ Todos os endpoints protegidos com autenticação
- ✅ Todos os acessos auditados

---

# 9. Métricas de Sucesso

| Métrica | Target FASE 02 |
|---------|----------------|
| Testes Automatizados | ≥150 tests |
| Code Coverage | ≥85% |
| Security Audit | 0 críticos, 0 altos |
| Performance P95 | <500ms (GET), <2s (POST) |
| Uptime | 99.9% |
| Test Success Rate | 100% |

---

# 10. Timeline Estimada

```
FASE 02 Timeline:

Semana 1-2:    F2.1 — Products ✓
Semana 3-4:    F2.2 — Inventory ✓
Semana 5-6:    F2.3 — Sales ✓
Semana 7-8:    F2.4 — Reporting ✓
Semana 9-10:   F2.5 — Automation ✓
Semana 11-12:  F2.6 — Integration ✓
Semana 13:     UAT & Bug Fixes
Semana 14:     Release Ready

Target: Início: 2026-08-19 | Fim: 2026-11-30
```

---

# 11. Próximas Fases

### FASE 03 — PDV & Sync
- PDV .NET com sync para Laravel
- Offline mode
- Impressão térmica

### FASE 04 — Fiscal
- NFC-e generation
- SEFAZ integration
- Certificado digital

### FASE 05 — Advanced Features
- Customer Intelligence
- AI-powered recommendations
- Multi-channel sync

---

# 12. Checklist de Inicialização F2.2

Antes de começar F2.2:

- [x] Revisar ROADMAP_FASE_02_FEATURES.md
- [x] Confirmar F2.1 como concluído no checklist detalhado
- [ ] Criar branches para cada sprint
- [ ] Atualizar PROJECT_STATUS.md com início de F2.2
- [ ] Configurar environment para F2.2, se necessário
- [ ] Criar ADR-003 (Inventory Strategy), se a modelagem exigir decisão arquitetural
- [ ] Implementar Inventory model
- [ ] Escrever testes para movimentos de estoque
- [ ] Criar migrations de inventories, inventory_movements e stock_levels
- [ ] Fazer commit com `feat(F2.2): iniciar inventory management`

---

**Autor:** Claude Code  
**Data de Criação:** 2026-08-16  
**Status:** F2.1 concluído · F2.2 pronto para iniciar
**Próxima Atualização:** Quando F2.2 iniciar
