# ROADMAP_FASE_02_FEATURES.md
## Implementação de Features de Negócio — Plataforma SaaS de Automação Comercial

**Projeto:** Plataforma SaaS Inteligente de Automação Comercial  
**Fase:** 02 — FEATURES  
**Status:** Planejado (FASE 01 ✅ COMPLETE)  
**Prioridade:** Alta  
**Dependência:** FASE 01 — FOUNDATION (✅ Concluída)  
**Estimativa:** 6 sprints (~12-16 semanas)  

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
├── Products/             # 🟡 F2.1 — Novo
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
├── Inventory/            # 🟡 F2.2 — Novo
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

**Duração:** ~2 semanas  
**Objetivo:** Permitir criação, categorização e precificação de produtos  

### Requisitos

#### Modelos
- [ ] Product (SKU, name, description, tenant_id, company_id)
- [ ] Category (name, parent_id, tenant_id) — hierarchical
- [ ] ProductCategory (many-to-many)
- [ ] Price (product_id, currency, amount, type, tenant_id)
- [ ] PriceHistory (auditoria de mudanças de preço)

#### Banco de Dados
- [ ] products table
- [ ] categories table (with parent_id for hierarchy)
- [ ] product_category pivot
- [ ] prices table
- [ ] price_history table (audit trail)

#### API Endpoints
- [ ] POST /api/v1/products (create)
- [ ] GET /api/v1/products (list with filters)
- [ ] GET /api/v1/products/{id} (show)
- [ ] PUT /api/v1/products/{id} (update)
- [ ] DELETE /api/v1/products/{id} (soft delete)
- [ ] POST /api/v1/categories (create)
- [ ] GET /api/v1/categories (hierarchical list)
- [ ] PUT /api/v1/categories/{id} (update)
- [ ] POST /api/v1/prices (create/update)
- [ ] GET /api/v1/prices/history/{product_id} (audit trail)

#### Features
- [ ] Category hierarchy (parent-child relationships)
- [ ] Product image upload (abstração para storage)
- [ ] Bulk import de produtos (CSV)
- [ ] SKU uniqueness por tenant
- [ ] Price versioning com histórico completo
- [ ] Multi-currency support ready
- [ ] Soft deletes para produtos e categorias

#### Testes (20+)
- [ ] Product CRUD operations
- [ ] Category hierarchy validation
- [ ] Category isolation by tenant
- [ ] Price history tracking
- [ ] Bulk import validation
- [ ] Concurrent price updates
- [ ] SKU uniqueness enforcement
- [ ] Cascading operations (delete category, orphan products)

#### Documentação
- [ ] ADR-002: Product Domain Structure
- [ ] Product Module Architecture
- [ ] API Specification (OpenAPI)

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

# 12. Checklist de Inicialização F2.1

Antes de começar F2.1:

- [ ] Revisar ROADMAP_FASE_02_FEATURES.md
- [ ] Criar branches para cada sprint
- [ ] Atualize PROJECT_STATUS.md com F2.1 status
- [ ] Configure environment para F2.1 (if needed)
- [ ] Crie ADR-002 (Product Domain)
- [ ] Implemente primeiro Product model
- [ ] Escreva testes para Product CRUD
- [ ] Crie primeira migration para products table
- [ ] Faça commit com `feat(F2.1): Initialize Products module`

---

**Autor:** Claude Code  
**Data de Criação:** 2026-08-16  
**Status:** Planejado  
**Próxima Atualização:** Quando F2.1 iniciado
