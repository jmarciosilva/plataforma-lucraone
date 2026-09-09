# ROADMAP_FASE_02_FEATURES.md
## Implementação de Features de Negócio — Plataforma SaaS de Automação Comercial

**Projeto:** Plataforma SaaS Inteligente de Automação Comercial  
**Fase:** 02 — FEATURES  
**Status:** 🟡 ATIVO — F2.5 Advanced Automation concluída, F2.6 é o próximo
**Prioridade:** Alta  
**Dependência:** FASE 01 ✅ Concluída · FASE 03 ✅ Concluída
**Estimativa:** 6 sprints (~12-16 semanas)  
**Entrega obrigatória por sprint:** API/backend + frontend web no painel administrativo

> ✅ **Retomada liberada em 2026-08-16.**
> A FASE 03 foi concluída, então a FASE 02 pode seguir para F2.2 —
> Inventory Management. A partir deste ponto, cada sprint precisa entregar
> também a interface web correspondente, para validação manual por José e pelos
> sócios no painel administrativo.
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

| Sprint | Backend/API | Frontend Web | Testes |
|--------|-------------|--------------|--------|
| F2.1 — Products Management | ✅ DONE | ✅ DONE via F2.1b | 42 API/domain |
| F2.1b — Products Web UI | ✅ DONE | ✅ DONE | 8 web |
| F2.2 — Inventory Management | ✅ DONE | ✅ DONE | 11 API/web |
| F2.3 — Sales & Orders | ✅ DONE | ✅ DONE | 26 API/web |
| F2.4 — Reporting & Analytics | ✅ DONE | ✅ DONE | 28 API/web |
| F2.5 — Advanced Automation | ✅ DONE | ✅ DONE | 40 testes |
| F2.6 — Integration APIs | 📋 TODO | 📋 TODO | — |

> **Nota sobre numeração:** esta tabela listava anteriormente "F2.4 Payments /
> F2.5 Reports / F2.6 Integrations", divergindo da especificação detalhada da
> seção 5. A especificação detalhada é a fonte de verdade; a tabela foi
> corrigida. Pagamentos não têm sprint própria na FASE 02 — ver nota da F2.3.

---

# 1. Contexto

FASE 02 constrói sobre a fundação técnica sólida entregue por FASE 01.

Agora implementaremos os módulos de negócio que compõem a plataforma de automação comercial:

```text
Produtos → Estoque → Vendas → Pagamentos → Fiscal → Reporting
```

Cada módulo será desenvolvido em uma sprint dedicada com:
- API/backend do domínio
- Interface web no painel administrativo
- Testes automatizados (unit + feature/API + feature/web)
- Validação manual no navegador
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

Todos com suporte a **múltiplos tenants**, **auditoria completa**, **testes automatizados**
e **telas web operáveis** para validação por usuários não técnicos.

---

# 3. Princípios de FASE 02

1. **Consistência Arquitetural** — Manter padrões Modular Monolith de FASE 01
2. **Multi-Tenancy Obrigatória** — Todos os dados isolados por tenant
3. **Testes Primeiro** — Implementar testes antes da lógica (TDD quando possível)
4. **Auditoria Completa** — Rastrear todas operações sensíveis de negócio
5. **Performance** — Manter baselines de FASE 01
6. **Documentação** — ADRs para decisões arquiteturais importantes
7. **Segurança Contínua** — Validar OWASP Top 10 a cada sprint
8. **Frontend Operável** — Nenhuma sprint da FASE 02 pós-F3.6 será considerada
   pronta apenas com API; precisa haver telas no painel para fluxo humano real.

---

# 3.1. Regra de Entrega Full-Stack da FASE 02

A FASE 03 entregou a base administrativa: login, layout, menus, tenants, usuários,
empresas e permissões. Portanto, a partir da F2.2, cada módulo de negócio deve
ser entregue de ponta a ponta:

```text
Banco + Models + Regras
    ↓
API protegida por tenant/RBAC
    ↓
Telas Blade + Tailwind no painel
    ↓
Testes API + testes web
    ↓
Validação manual no navegador
```

Critérios adicionais obrigatórios por sprint:

- Menu/atalho no painel quando o módulo for de uso recorrente.
- Listagem com busca, filtros úteis e paginação.
- Formulários de criação/edição com validação server-side e mensagens claras.
- Tela de detalhe quando houver histórico, auditoria ou dados relacionados.
- Modal de ajuda contextual para orientar usuários leigos e sócios.
- Permissões RBAC aplicadas nas rotas web e API.
- Teste automatizado cobrindo isolamento multi-tenant na API e na tela.
- Validação manual no navegador usando `http://localhost:8000`.

Para F2.1, que foi implementada antes da FASE 03, o backend/API está DONE. A
interface web de produtos/categorias/preços deve ser incluída antes ou junto da
F2.2, porque estoque depende de produtos e precisa ser testável pela tela.

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
├── Products/             # ✅ F2.1 API — ✅ F2.1b Web UI
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
├── Inventory/            # ✅ F2.2 — API + Web UI
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
├── Sales/                # ✅ F2.3 — API + Web UI
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
├── Payments/             # ⏸️ Sem sprint na FASE 02 — ver nota da F2.3
│   ├── Domain/
│   ├── Application/
│   ├── Infrastructure/
│   └── Http/
│
├── Reporting/            # ✅ F2.4 — API + Web UI
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

## Sprint F2.1b — Products Web UI

**Status:** ✅ DONE
**Concluído em:** 2026-08-16
**Testes:** 8 passing (`tests/Feature/Admin/ProductWebManagementTest.php`)
**Objetivo:** Criar interface web para produtos, categorias e preços, permitindo
que José e os sócios testem o módulo sem Postman.
**Como testar:** acessar o menu `produtos`, cadastrar produto, categoria e preços
pelo painel, depois validar listagem, filtros e detalhe.

### Requisitos Frontend

#### Produtos
- [x] Menu `produtos` apontando para rota real
- [x] Listagem de produtos (`/products`) filtrada pelo tenant atual
- [x] Busca por SKU/nome
- [x] Filtro por status
- [x] Formulário de criação de produto
- [x] Formulário de edição de produto
- [x] Página de detalhe do produto
- [x] Soft delete/restaurar produto, se aplicável
- [x] Associação de categorias no formulário

#### Categorias
- [x] Listagem de categorias (`/categories`) com hierarquia
- [x] Criar categoria raiz
- [x] Criar subcategoria
- [x] Editar categoria
- [x] Arquivar categoria com tratamento de filhos

#### Preços
- [x] Listar preços do produto
- [x] Criar/atualizar preço por tipo (`cost`, `sale`, `suggested_retail`)
- [x] Exibir margem calculada
- [x] Exibir histórico de preços

#### Experiência e segurança
- [x] Modal de ajuda contextual para produtos/categorias/preços
- [x] Policies/RBAC nas rotas web
- [x] Mensagens de sucesso/erro no design system
- [x] Testes feature web de listagem, criação, edição e isolamento
- [x] Validação manual no navegador

**Nota:** F2.1b não altera o escopo de domínio já entregue na API; ela torna o
módulo operável por humanos e prepara o terreno para F2.2, que depende de produtos
cadastráveis pela tela.

---

## Sprint F2.2 — Inventory Management

**Status:** ✅ DONE
**Concluído em:** 2026-08-16
**Testes:** 11 passing (`tests/Feature/Inventory` + `tests/Feature/Admin/InventoryWebManagementTest.php`)
**Duração:** ~2 semanas  
**Objetivo:** Controlar estoque com movimentações e níveis de reposição pela API
e pelo painel web.
**Como testar:** acessar o menu `estoque`, consultar posição por produto/empresa,
registrar ajuste de entrada/saída e ver histórico de movimentações.

### Requisitos

#### Modelos
- [x] Inventory (product_id, company_id, quantity_on_hand, reserved, available)
- [x] InventoryMovement (type, quantity, reason, datetime, tenant_id)
- [x] StockLevel (min_qty, max_qty, reorder_point)

#### Banco de Dados
- [x] inventories table
- [x] inventory_movements table
- [x] stock_levels table

#### API Endpoints
- [x] GET /api/v1/inventory (company level)
- [x] POST /api/v1/inventory/{product_id}/adjust (movement)
- [x] GET /api/v1/inventory/{product_id}/movements (history)
- [x] POST /api/v1/stock-levels/{product_id} (set min/max)
- [x] GET /api/v1/inventory/low-stock (alert)
- [x] GET /api/v1/inventory/overstock (alert)

#### Frontend Web
- [x] Menu `estoque` no painel
- [x] Dashboard de estoque com totais, baixo estoque e excesso
- [x] Listagem de estoque por produto/empresa
- [x] Busca por produto/SKU
- [x] Filtros por empresa, status de estoque e categoria
- [x] Tela de detalhe do estoque do produto
- [x] Formulário/modal de ajuste de estoque
- [x] Histórico de movimentações
- [x] Formulário de nível mínimo, máximo e ponto de reposição
- [x] Modal de ajuda contextual do módulo estoque

#### Features
- [x] Real-time inventory updates
- [x] Movement audit trail (quem, quando, por quê)
- [x] Low stock alerts
- [x] Overstock detection
- [ ] Transfer between branches (adiado para multi-filial operacional)
- [ ] Batch/serial number tracking (preparação)
- [x] Reserved quantity for pending orders

#### Testes (18+)
- [x] Inventory movements
- [x] Stock level validation
- [x] Low stock alerts
- [ ] Transfer operations (adiado junto com transferência entre filiais)
- [x] Concurrent updates (locking)
- [x] Audit trail completeness
- [x] Web: listagem renderiza dados do tenant atual
- [x] Web: ajuste de estoque cria movimentação
- [x] Web: usuário sem permissão recebe 403
- [x] Browser: fluxo manual validado

#### Documentação
- [ ] ADR-003: Inventory Strategy
- [x] Inventory Module Guide via documentação da sprint

---

## Sprint F2.3 — Sales & Orders

**Status:** ✅ DONE
**Concluído em:** 2026-08-16
**Testes:** 26 passing (`tests/Feature/Sales` + `tests/Feature/Admin/SalesWebManagementTest.php`)
**Duração:** ~2 semanas
**Objetivo:** Implementar sistema de pedidos e vendas pela API e pelo painel web.
**Como testar:** acessar o menu `vendas`, criar pedido escolhendo cliente
existente ou cadastrando um novo na hora, adicionar itens, avançar o status e
conferir reserva/baixa na tela de `estoque`.

### Requisitos

#### Modelos
- [x] Order (order_number, customer_id, branch_id, status, total, tenant_id)
- [x] OrderItem (order_id, product_id, quantity, unit_price)
- [x] Customer (name, email, phone, company_id)

#### Banco de Dados
- [x] orders table
- [x] order_items table
- [x] customers table

#### API Endpoints
- [x] POST /api/v1/orders (create)
- [x] GET /api/v1/orders (list)
- [x] GET /api/v1/orders/{id} (show)
- [x] PUT /api/v1/orders/{id}/status (update status)
- [x] DELETE /api/v1/orders/{id} (cancel)
- [x] POST /api/v1/customers (create)
- [x] GET /api/v1/customers (list)
- [x] GET /api/v1/customers/{id} (show)

#### Frontend Web
- [x] Menu `vendas` no painel
- [x] Menu `clientes` no painel
- [x] Listagem de pedidos/vendas com busca e filtros
- [x] Criar pedido/venda pela tela
- [x] Adicionar/remover itens do pedido
- [x] Selecionar cliente ou criar cliente inline
- [x] Atualizar status do pedido
- [x] Tela de detalhe com itens, totais e histórico
- [x] CRUD de clientes com busca, filtros e arquivamento
- [x] Modal de ajuda contextual de vendas
- [x] Modal de ajuda contextual de clientes

#### Features
- [x] Order status workflow (draft → pending → confirmed → shipped → completed)
- [x] Inventory reservation na confirmação do pedido (ver ADR-004)
- [x] Baixa definitiva de estoque no envio
- [x] Automatic pricing from price table
- [x] Customer creation inline during order
- [x] Order cancellation with inventory release
- [ ] Payment link generation (adiado; o módulo Payments não existe na FASE 02)

#### Testes (26)
- [x] Order creation
- [x] Inventory reservation
- [x] Status transitions
- [x] Concurrent orders (no overselling)
- [x] Cancellation scenarios
- [x] Pricing accuracy
- [x] Produto sem preço de venda é recusado
- [x] Pedido sem itens não pode ser confirmado
- [x] Endpoints exigem autenticação
- [x] API: isolamento por tenant em pedidos e clientes
- [x] Web: criar pedido pela tela com cliente inline
- [x] Web: adicionar e remover itens recalculando totais
- [x] Web: atualizar status pela tela reserva estoque
- [x] Web: cancelar pedido libera reserva
- [x] Web: CRUD de clientes
- [x] Web: isolamento por tenant
- [x] Web: usuário sem permissão recebe 403
- [x] Browser: fluxo manual validado (criar → item → confirmar → enviar → estoque)

#### Documentação
- [x] ADR-004: Order Workflow (`lucraone-backend/docs/adr/ADR-004-order-workflow.md`)
- [x] Sales Module Guide via ADR-004 e modais de ajuda contextual

**Notas de escopo:**

- **Reserva na confirmação, não na criação.** O roadmap dizia "inventory
  reservation on order creation", mas rascunho abandonado seguraria estoque e
  impediria outra venda. Decisão registrada no ADR-004.
- **Payment link generation adiado.** Não existe módulo Payments na FASE 02 —
  a seção 5 vai de F2.3 (Sales) direto para F2.4 (Reporting). Gerar link de
  pagamento sem gateway seria um campo inerte na tela.
- **`branch_id` nasce nulo.** A coluna existe em `orders`, mas filiais ainda não
  têm tela no painel, então o campo não é exposto.

---

## Sprint F2.4 — Reporting & Analytics

**Status:** ✅ DONE
**Concluído em:** 2026-08-16
**Testes:** 28 passing (`tests/Feature/Reporting` + `tests/Feature/Admin/ReportWebManagementTest.php`)
**Duração:** ~2 semanas
**Objetivo:** Gerar relatórios executivos e dashboards pela API e pelo painel web.
**Como testar:** acessar o menu `relatórios`, trocar intervalo e agrupamento,
conferir os KPIs contra os menus `vendas`, `estoque` e `clientes`, e exportar CSV.

### Requisitos

#### Modelos
- [ ] Report (query definition, schedule, recipients) — adiado, ver nota
- [ ] Dashboard (widget definitions) — adiado, ver nota

#### API Endpoints
- [x] GET /api/v1/reports/sales (daily/weekly/monthly)
- [x] GET /api/v1/reports/inventory (stock status)
- [x] GET /api/v1/reports/customers (top customers)
- [x] GET /api/v1/dashboard/summary (KPIs)
- [x] GET /api/v1/analytics/trends (sales trends)

#### Frontend Web
- [x] Menu `relatórios` no painel
- [x] Dashboard de KPIs comerciais (em `/reports` e faixa no `/dashboard`)
- [x] Filtros por período e empresa, com atalhos de 7/30/90 dias
- [ ] Filtro por filial — serviços aceitam, sem UI (filiais não têm tela)
- [x] Relatório de vendas
- [x] Relatório de estoque
- [x] Relatório de clientes
- [x] Gráficos/tabelas responsivos
- [x] Exportação CSV dos três relatórios
- [x] Modal de ajuda contextual de relatórios

#### Features
- [x] Real-time KPI dashboard (calculado sob demanda, sem cache)
- [x] Sales by period (day/week/month/year)
- [x] Inventory value analysis (valor a custo, a venda e margem potencial)
- [x] Top selling products
- [x] Customer segmentation (vip / recorrente / novo / sem compra)
- [x] Revenue forecasting (basic) — regressão linear, rotulada como estimativa
- [ ] Scheduled report generation (emails) — adiado para F2.5, ver nota

#### Testes (28)
- [x] Report accuracy (faturamento conta só enviado e concluído)
- [x] Carteira em aberto não soma pedido já faturado
- [x] Date filtering
- [x] Agrupamento por dia, semana e mês
- [x] Baldes vazios preenchidos com zero
- [x] Top produtos ordenados por receita
- [x] Valor de estoque a custo e a venda
- [x] Alerta de baixo estoque
- [x] Segmentação de clientes
- [x] Comparação com período anterior
- [x] Variação nula sem base anterior
- [x] Projeção de tendência e piso em zero
- [x] Tenant isolation in reports
- [x] Granularidade inválida recusada
- [x] Endpoints exigem autenticação e permissão
- [x] Web: relatórios renderizam KPIs reais
- [x] Web: filtros alteram resultado
- [x] Web: exportação CSV de vendas e estoque
- [x] Web: dashboard mostra faixa comercial
- [x] Web: usuário sem permissão recebe 403
- [x] Browser: fluxo manual validado
- [ ] Aggregation performance — não medida; ver nota

#### Documentação
- [x] Reporting Architecture (`lucraone-backend/docs/architecture/REPORTING.md`)
- [x] Analytics Guide — coberto pelo mesmo documento e pelo modal de ajuda

**Notas de escopo:**

- **Relatórios fixos em vez de motor genérico.** Os modelos `Report`/`Dashboard`
  do roadmap descrevem um construtor de relatórios: query definida pelo usuário e
  guardada no banco. Os cinco endpoints pedidos são todos determinados, e
  executar query definida por usuário exigiria interpretador próprio e whitelist
  de colunas — sprint inteira gasta numa demanda que ainda não existe. Decisão e
  caminho de evolução registrados em `docs/architecture/REPORTING.md`.
- **Envio agendado por e-mail adiado para F2.5.** Exigiria criar Mailable, Job e
  scheduler do zero — o projeto não tem nenhum, e `MAIL_MAILER=log`. A F2.5 já
  prevê "scheduled tasks (cron)" e "actions: send email"; montar a infraestrutura
  lá evita construir duas vezes.
- **Performance de agregação não foi medida.** As consultas agregam no banco
  (`GROUP BY`), exceto o valor de estoque, que soma em PHP porque cruza preço de
  custo e de venda por produto. Com o volume atual não há baseline que valha; a
  medição pertence a um teste de carga, não a esta sprint.
- **Fuso do estabelecimento aplicado no agrupamento.** Sem isso, venda às 22h em
  Brasília cairia no dia seguinte. Offset fixo — regiões com horário de verão
  terão erro de uma hora nas viradas.

---

## Sprint F2.5 — Advanced Automation ✅

**Status:** ✅ DONE — 40 testes  
**Objetivo:** Automações de negócio baseadas em regras, com API e painel web
para operadores configurarem regras sem tinker.
**Como testar:** criar uma regra no painel, simular gatilho e consultar histórico
de execução.

### Requisitos

#### Modelos
- [x] AutomationRule (trigger, condition, action, tenant_id)
- [x] AutomationLog (execution history)
- [x] Notification — avisos no painel, alvo da ação `create_notification`

#### API Endpoints
- [x] GET /api/v1/automation-rules
- [x] POST /api/v1/automation-rules
- [x] GET /api/v1/automation-rules/{id}
- [x] PUT /api/v1/automation-rules/{id}
- [x] DELETE /api/v1/automation-rules/{id}
- [x] GET /api/v1/automation-logs

#### Frontend Web
- [x] Menu `automações` no painel
- [x] Listagem de regras
- [x] Criar/editar regra
- [x] Ativar/desativar regra
- [x] Listar execuções e erros
- [x] Tela de detalhe da regra
- [x] Modal de ajuda contextual de automações
- [x] Tela de notificações (`/notificacoes`) com marcar como lido

#### Features
- [x] Rules engine: when X then do Y (`RuleEngine` + `ConditionEvaluator`)
- [x] Triggers: `product_created`, `stock_low`, `order_completed`
- [x] Actions: `send_email`, `create_notification`, `update_price`
- [x] Scheduled tasks (cron) — `automations:prune-logs` e `sales:summary`
- [x] Audit log de execuções — toda passagem grava `executado`, `ignorado` ou
      `falhou`, porque "por que essa regra não rodou?" é a pergunta mais comum

#### Testes (40)
- [x] Rule evaluation (`RuleEngineTest`)
- [x] Action execution (`RuleEngineTest`)
- [x] Error handling — regra que falha não derruba as demais
- [x] Web: criar regra pela tela (`AutomationWebManagementTest`)
- [x] Web: consultar histórico de execução
- [x] API (`AutomationApiTest`), agendamento (`ScheduledTasksTest`)

#### Documentação
- [ ] Automation Rules Guide — **pendente**, único item não entregue da sprint

### Decisões de projeto

- **Condições só com E lógico.** Sem OU nem parênteses: a regra que precisaria
  disso são duas regras. É o que permite a tela ser um formulário em vez de um
  editor de expressão.
- **Campos vêm de um catálogo (`TriggerCatalog`).** Uma condição não alcança
  dado que o gatilho não declarou expor.
- **O gancho fica no model, não no controller.** Produto nasce por três
  caminhos — API, painel e seeder — e o gatilho precisa valer nos três.
- **A avaliação roda na fila.** Enviar e-mail ou reprecificar não pode segurar
  a resposta de quem só cadastrou um produto.

---

## Sprint F2.6 — Integration APIs

**Duração:** ~2 semanas  
**Objetivo:** Integração com sistemas externos, com APIs e painel web para
configurar, acompanhar e auditar integrações.
**Como testar:** cadastrar uma integração no painel, disparar webhook/teste e
consultar logs de envio.

### Requisitos

#### Modelos
- [ ] Integration (type, endpoint, credentials_encrypted)
- [ ] IntegrationLog (request/response)

#### API Endpoints
- [ ] GET /api/v1/integrations
- [ ] POST /api/v1/integrations
- [ ] GET /api/v1/integrations/{id}
- [ ] PUT /api/v1/integrations/{id}
- [ ] DELETE /api/v1/integrations/{id}
- [ ] POST /api/v1/integrations/{id}/test
- [ ] GET /api/v1/integration-logs

#### Frontend Web
- [ ] Menu `integrações` no painel
- [ ] Listagem de integrações
- [ ] Criar/editar integração
- [ ] Testar integração
- [ ] Exibir status e últimos erros
- [ ] Listar logs de requisição/resposta
- [ ] Modal de ajuda contextual de integrações

#### Suportes Iniciais
- [ ] Webhook delivery
- [ ] ERP sync (genérico)
- [ ] Marketplace sync (preparação para Amazon, B2B)
- [ ] Payment gateway integration (Stripe ready)

#### Testes (10+)
- [ ] Webhook handling
- [ ] Retry logic
- [ ] Error recovery
- [ ] Web: cadastrar integração pela tela
- [ ] Web: consultar logs

#### Documentação
- [ ] Integration Guide
- [ ] Webhook Specification

---

# 6. Critérios de Aceite por Sprint

Uma sprint é considerada **DONE** quando:

✅ Todos os requisitos implementados  
✅ Testes escrevendo e passando (≥90% coverage)  
✅ Interface web do módulo entregue no painel administrativo
✅ Fluxo validado manualmente no navegador por um operador humano
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

### Frontend Web
```text
// Views Blade seguem o design system da FASE 03
resources/views/{module}/

// Controllers web finos, usando TenantContext e policies
app/Http/Controllers/Web/{Module}Controller.php

// Componentes existentes devem ser reutilizados
components: button, input, select, form-group, card, kpi, badge, alert, modal, table, section-label
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
// Cobrir API e web quando houver tela
```

### Validação Manual
```text
// Toda sprint deve terminar com teste real no navegador:
1. Login com usuário admin
2. Acesso ao menu do módulo
3. Criar registro principal
4. Editar registro principal
5. Conferir listagem/filtros/detalhe
6. Conferir modal de ajuda
7. Conferir que usuário sem permissão recebe 403
```

---

# 8. Dependências & Integração

### Ordem Recomendada
```
F2.1 — Products ✓
  ↓
F2.1b — Products Web UI (torna produtos testáveis pelo painel)
  ↓
F2.2 — Inventory (depende de produtos cadastráveis pela tela) ✓
  ↓
F2.3 — Sales (depende de produtos + inventory) ✓
  ↓
F2.4 — Reporting (depende de sales) ✓
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
Semana 3:      F2.1b — Products Web UI ✓
Semana 4-5:    F2.2 — Inventory ✓
Semana 6-7:    F2.3 — Sales ✓
Semana 8-9:    F2.4 — Reporting ✓
Semana 10-11:  F2.5 — Automation ✓
Semana 12-13:  F2.6 — Integration ✓
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

# 12. Checklist de Inicialização F2.1b

Antes de começar F2.2, implementar F2.1b para que produtos estejam operáveis no painel:

- [x] Revisar ROADMAP_FASE_02_FEATURES.md
- [x] Confirmar F2.1 como concluído no checklist detalhado
- [x] Definir que FASE 02 entrega API/backend + frontend web por sprint
- [ ] Criar branches para cada sprint
- [x] Atualizar PROJECT_STATUS.md com início/conclusão de F2.1b
- [x] Configurar environment para F2.1b, se necessário
- [x] Implementar telas web de produtos/categorias/preços
- [x] Validar produtos no navegador com usuário admin
- [ ] Fazer commit com `feat(F2.1b): gerenciar produtos pelo painel`
- [x] Depois iniciar F2.2 — Inventory Management
- [ ] Criar ADR-003 (Inventory Strategy), se a modelagem exigir decisão arquitetural
- [ ] Implementar Inventory model
- [ ] Escrever testes para movimentos de estoque
- [ ] Criar migrations de inventories, inventory_movements e stock_levels
- [ ] Fazer commit com `feat(F2.2): iniciar inventory management`

---

**Autor:** Claude Code  
**Data de Criação:** 2026-08-16  
**Status:** F2.4 Reporting & Analytics concluída · F2.5 Advanced Automation é o próximo passo
**Próxima Atualização:** Quando F2.5 iniciar
