# Roadmap Multissegmento — LUCRAONE

**Criado:** 2026-11-01  
**Revisado:** 2026-11-01 (revisão 2 — três ressalvas; fases MS-02 e REST-05 expandidas)  
**Escopo:** plataforma SaaS multissegmento, começando pelo segmento restaurantes  
**Documentos complementares:**
- [ARQUITETURA_MULTISSEGMENTO.md](ARQUITETURA_MULTISSEGMENTO.md) — decisões arquiteturais e análises técnicas
- [RESTAURANTES.md](RESTAURANTES.md) — catálogo oficial de funcionalidades com IDs únicos

> Este roadmap não substitui o ROADMAP.md principal — coexiste com ele.
> A F2.6 (Integration APIs) permanece na trilha original.

---

## Visão executiva

```
┌────────────────────────────────────────────────────────────────────┐
│  FASES MULTISSEGMENTO                         STATUS               │
│                                                                     │
│  MS-01  Diagnóstico e arquitetura             ✅ CONCLUÍDO          │
│  MS-02  Segmento, BranchContext e perfil      ○ NÃO INICIADO        │
│  MS-03  Templates de dashboard e navegação    ○ NÃO INICIADO        │
│  REST-02 Cardápio                             ○ NÃO INICIADO        │
│  REST-03 Mesas e comandas                     ○ NÃO INICIADO        │
│  REST-04 Pedidos e produção (KDS)             ○ NÃO INICIADO        │
│  REST-05 Tipos de produto + fichas técnicas   ○ NÃO INICIADO        │
│  REST-01 Dashboard de restaurante             ○ NÃO INICIADO        │
│  REST-06 Integração PDV Java                  ○ NÃO INICIADO        │
│  REST-07 Testes integrados e homologação      ○ NÃO INICIADO        │
│                                                                     │
│  Fases planejadas:    10                                            │
│  Concluídas:           1  (MS-01)                                   │
│  Em andamento:         0                                            │
│  Bloqueadas:           0                                            │
│  Progresso:           10%                                           │
│  Próxima fase:        MS-02 (aguarda aprovação arquitetural)        │
│  Última atualização:  2026-11-01                                    │
└────────────────────────────────────────────────────────────────────┘
```

### Dependências entre fases

```
MS-01 ──→ MS-02 ──→ MS-03 ──→ REST-03 ──→ REST-04
                └──→ REST-02 ──→ REST-03
                               REST-04 ──→ REST-01 (parcial)
                REST-02 ──→ REST-05 ──→ REST-01 (completo)
                               REST-03 ──→ REST-06
                               REST-04 ──→ REST-06
MS-01 a REST-06 ──→ REST-07
```

**Mudança em relação à revisão 1:** REST-05 (fichas técnicas) passou a ser
pré-requisito de REST-01 completo, e MS-02 foi expandido para incluir
BranchContext além do campo de segmento.

---

## MS-01 — Diagnóstico e arquitetura multissegmento

**Status:** CONCLUÍDO  
**Início:** 2026-11-01 · **Conclusão:** 2026-11-01 · **Commits:** `e83afab`

### Objetivo

Levantamento do estado real do código e produção dos documentos de planejamento.

### Critérios de aceite

- [x] Inventário de módulos existentes documentado
- [x] Decisão arquitetural proposta e justificada
- [x] Roadmap elaborado com fases e dependências
- [x] Documentos criados e versionados
- [ ] Aprovação das decisões arquiteturais pelo responsável de produto ← **PENDENTE**

### Pendências

- Aprovação das decisões A, B, C, D, E, F, G, H, I da ARQUITETURA_MULTISSEGMENTO.md.

---

## MS-02 — Segmento, BranchContext e perfil operacional

**Status:** NÃO INICIADO  
**Bloqueio:** aprovação das decisões A e B (granularidade do segmento + BranchContext)  
**Estimativa:** 4–6 dias

### Objetivo

Adicionar ao modelo de dados o segmento da filial e implementar o mecanismo de
contexto de filial no painel web, preservando retrocompatibilidade integral.

> Esta fase foi expandida na revisão 2 para incluir o BranchContext, identificado
> como pré-requisito arquitetural de toda a plataforma multissegmento.

### Escopo

**Parte 1 — Banco de dados (não-destrutiva):**
- Migration: `segment VARCHAR(30) NOT NULL DEFAULT 'SUPERMARKET'` em `branches`.
- Migration: `operational_profile JSON NULL` em `branches`.
- Todas as branches existentes recebem automaticamente `SUPERMARKET`.

**Parte 2 — BranchContext:**
- `BranchContext` (service singleton, análogo a `TenantContext`).
- `BranchResolver` (lê `'filial_ativa'` da sessão, valida pertencimento ao tenant).
- Regras de resolução:
  1. Sessão `'filial_ativa'` se válida.
  2. Filial única do tenant — auto-selecionada sem interação.
  3. Sem resolução — visão consolidada (comportamento atual preservado).
- Limpeza do BranchContext se filial desativada ou acesso revogado.

**Parte 3 — Seleção de filial:**
- Rota e tela de seleção de filial (similar a `estabelecimentos.escolher`).
- Ativada apenas quando tenant tem filiais de segmentos diferentes.

**Parte 4 — Formulário de filial:**
- Select de segmento no `BranchController` (create + edit).
- Validação do valor do segmento.

### Módulos existentes reutilizados

- `TenantContext` / `TenantResolver` — modelo de implementação do `BranchContext`.
- `AutenticarWeb` — estendido para resolver branch após tenant.
- `BranchController` — estendido com novos campos.

### Componentes novos

- `BranchContext` (Application service).
- `BranchResolver` (Application service).
- `BranchSegment` (constante com valores e labels).
- Migration `add_segment_and_profile_to_branches_table`.
- Rota e view de seleção de filial (quando houver múltiplas de segmentos diferentes).

### Alterações no banco

```sql
-- Não-destrutiva: DEFAULT garante retrocompatibilidade total
ALTER TABLE branches
  ADD COLUMN segment VARCHAR(30) NOT NULL DEFAULT 'SUPERMARKET',
  ADD COLUMN operational_profile JSON NULL;
```

### Alterações em APIs

- `GET /branches/{id}` → retorna `segment` e `operational_profile`.
- `POST/PUT /branches` → aceita `segment` e `operational_profile`.
- Nenhuma rota existente quebra.

### Impactos no PDV Java

Nenhum impacto imediato. O terminal já conhece sua branch.

### Critérios de aceite

- [ ] Migration executada em staging sem erros
- [ ] Todas as branches existentes com `segment = SUPERMARKET`
- [ ] `BranchContext::resolved()` retorna false para tenant com filial única de supermercado (auto-resolve corretamente)
- [ ] `BranchContext` limpo quando filial fica inativa durante sessão
- [ ] Formulário de filial exibe e persiste o segmento
- [ ] Sidebar exibe segmento correto para cada tipo de filial
- [ ] Suite de testes existente passa sem regressões (baseline 1458 testes)

---

## MS-03 — Templates de dashboard e navegação por segmento

**Status:** NÃO INICIADO  
**Bloqueio:** MS-02 concluído  
**Estimativa:** 3–5 dias

### Objetivo

Seleção automática do template de dashboard e menus de navegação com base no
segmento da filial ativa, sem alterar o comportamento do segmento supermercado.

### Escopo

- `DashboardController` despacha por segmento usando `BranchContext`.
- Extração do nav atual para `nav/supermarket.blade.php` (sem mudança de comportamento).
- Criação de `nav/restaurante.blade.php` (stub com estrutura, sem links funcionais ainda).
- Criação de `dashboard/restaurante.blade.php` (stub com KPIs zerados).
- `DashboardRestauranteService` (stub — sem queries; preenchido em REST-01).
- Atualização do `sidebar.blade.php` para inclusão condicional.

### Comportamento quando branch não está resolvida

Se `BranchContext::resolved()` for false (tenant com uma filial, ou visão consolidada),
o dashboard atual (`dashboard/index.blade.php`) é renderizado — comportamento atual
preservado.

### Critérios de aceite

- [ ] Filial SUPERMARKET: dashboard e menus idênticos ao estado anterior
- [ ] Filial RESTAURANT: dashboard de restaurante exibido (KPIs zerados é aceito)
- [ ] Tenant sem filial selecionada: dashboard atual preservado
- [ ] Nenhuma regressão nos 1458+ testes existentes

---

## REST-02 — Cardápio

**Status:** NÃO INICIADO  
**Bloqueio:** MS-02 concluído  
**Estimativa:** 5–7 dias

### Objetivo

Gestão do cardápio do restaurante: pratos, bebidas, modificadores e disponibilidade.

### Funcionalidades do MVP

Ver [RESTAURANTES.md — REST-02](RESTAURANTES.md): CAR-01 a CAR-07.

### Componentes novos

```sql
-- Campos opcionais em products (retrocompatíveis)
ALTER TABLE products
  ADD COLUMN preparation_time_minutes SMALLINT UNSIGNED NULL,
  ADD COLUMN is_available BOOLEAN NOT NULL DEFAULT TRUE;

CREATE TABLE menu_modifier_groups (
  id CHAR(26) PRIMARY KEY,
  tenant_id CHAR(26) NOT NULL,
  name VARCHAR(120) NOT NULL,
  required BOOLEAN NOT NULL DEFAULT FALSE,
  min_choices TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_choices TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP, updated_at TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE menu_modifiers (
  id CHAR(26) PRIMARY KEY,
  group_id CHAR(26) NOT NULL,
  tenant_id CHAR(26) NOT NULL,
  name VARCHAR(120) NOT NULL,
  price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_available BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (group_id) REFERENCES menu_modifier_groups(id) ON DELETE CASCADE
);

CREATE TABLE product_modifier_groups (
  product_id CHAR(26) NOT NULL,
  group_id CHAR(26) NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, group_id)
);
```

### Alterações em APIs

- Catálogo PDV (`GET /api/v1/pdv/catalog`) estendido com modificadores quando
  `branch.segment = RESTAURANT`. Retrocompatível (campo adicional no response).

### Critérios de aceite

- [ ] Prato cadastrado com grupo de modificadores e opções
- [ ] Disponibilidade de item alterada via painel
- [ ] Catálogo PDV inclui modificadores para branch RESTAURANT
- [ ] Produtos de supermercado não afetados (campos novos são NULL/TRUE por default)
- [ ] Testes de regressão do módulo Products passam

---

## REST-03 — Mesas e comandas

**Status:** NÃO INICIADO  
**Bloqueio:** MS-02 concluído, REST-02 concluído  
**Estimativa:** 10–15 dias

### Objetivo

Gestão completa de mesas e comandas com ciclo de vida próprio, auditoria e
múltiplas formas de pagamento.

### Funcionalidades do MVP

Ver [RESTAURANTES.md — REST-03](RESTAURANTES.md): MES-01 a MES-03, COM-01 a COM-06,
COM-08, COM-11, COM-12, COM-14, COM-15.

### Tabelas novas

```sql
CREATE TABLE restaurant_tables (
  id CHAR(26) PRIMARY KEY,
  tenant_id CHAR(26) NOT NULL,
  branch_id CHAR(26) NOT NULL,
  number SMALLINT UNSIGNED NOT NULL,
  sector VARCHAR(60) NULL,
  capacity TINYINT UNSIGNED NOT NULL DEFAULT 4,
  status ENUM('AVAILABLE','OCCUPIED','RESERVED','MAINTENANCE') DEFAULT 'AVAILABLE',
  waiter_user_id CHAR(26) NULL,
  created_at TIMESTAMP, updated_at TIMESTAMP
);

CREATE TABLE restaurant_tabs (
  id CHAR(26) PRIMARY KEY,
  tenant_id CHAR(26) NOT NULL,
  branch_id CHAR(26) NOT NULL,
  table_id CHAR(26) NULL,          -- NULL = balcão
  status ENUM('OPEN','CLOSING','CLOSED','CANCELLED') DEFAULT 'OPEN',
  opened_at TIMESTAMP NOT NULL,
  closed_at TIMESTAMP NULL,
  cancelled_at TIMESTAMP NULL,
  split_parts TINYINT UNSIGNED NULL,
  ingredients_applied_at TIMESTAMP NULL,  -- idempotência do consumo
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at TIMESTAMP, updated_at TIMESTAMP
);

CREATE TABLE restaurant_tab_items (
  id CHAR(26) PRIMARY KEY,
  tab_id CHAR(26) NOT NULL,
  product_id CHAR(26) NOT NULL,
  product_name VARCHAR(200) NOT NULL,   -- snapshot do nome
  quantity DECIMAL(10,3) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL,
  modifiers_snapshot JSON NULL,          -- snapshot dos modificadores no momento
  status ENUM('ACTIVE','CANCELLED') DEFAULT 'ACTIVE',
  cancelled_at TIMESTAMP NULL,
  cancellation_reason TEXT NULL,
  total_amount DECIMAL(12,2) NOT NULL
);

CREATE TABLE restaurant_tab_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tab_id CHAR(26) NOT NULL,
  seq TINYINT UNSIGNED NOT NULL,
  method VARCHAR(20) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  part_number TINYINT UNSIGNED NULL    -- para divisão em partes
);

CREATE TABLE restaurant_tab_audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tab_id CHAR(26) NOT NULL,
  user_id CHAR(26) NULL,
  action VARCHAR(60) NOT NULL,
  payload JSON NULL,
  occurred_at TIMESTAMP NOT NULL
);
```

### Critérios de aceite

- [ ] Mesa cadastrada com status gerenciado via painel
- [ ] Comanda aberta, item adicionado com modificadores
- [ ] Item removido com auditoria
- [ ] Transferência de comanda para outra mesa registrada
- [ ] Conta dividida em N partes iguais
- [ ] Múltiplas formas de pagamento numa comanda
- [ ] Comanda fechada com confirmação de pagamento
- [ ] Histórico de alterações auditável
- [ ] Isolamento por tenant e por branch garantidos
- [ ] `ingredients_applied_at` nulo ao fechar (REST-05 preenche depois)

---

## REST-04 — Pedidos e produção (KDS)

**Status:** NÃO INICIADO  
**Bloqueio:** REST-03 concluído  
**Estimativa:** 5–7 dias

### Objetivo

Fila de produção e painel KDS para a cozinha.

### Funcionalidades do MVP

Ver [RESTAURANTES.md — REST-04](RESTAURANTES.md): KDS-01 a KDS-07.

### Tabela nova

```sql
CREATE TABLE kitchen_orders (
  id CHAR(26) PRIMARY KEY,
  tenant_id CHAR(26) NOT NULL,
  branch_id CHAR(26) NOT NULL,
  tab_id CHAR(26) NOT NULL,
  tab_item_id CHAR(26) NOT NULL,
  status ENUM('WAITING','PREPARING','READY','DELIVERED') DEFAULT 'WAITING',
  sent_at TIMESTAMP NOT NULL,
  started_at TIMESTAMP NULL,     -- início do preparo (KDS-03)
  ready_at TIMESTAMP NULL,       -- pronto (KDS-04)
  delivered_at TIMESTAMP NULL,   -- entregue (KDS-05)
  notes TEXT NULL
);
```

### Critérios de aceite

- [ ] Pedido enviado aparece no KDS
- [ ] Status atualizado pela cozinha
- [ ] Garçom notificado quando pronto (usa `notifications` existente)
- [ ] KDS atualiza automaticamente (polling ≤ 10s)
- [ ] Tela KDS funciona sem sidebar (tela dedicada)

---

## REST-05 — Tipos de produto e fichas técnicas

**Status:** NÃO INICIADO  
**Bloqueio:** REST-02 concluído; decisões C e D aprovadas  
**Estimativa:** 7–10 dias

> Esta fase foi **renomeada e expandida** na revisão 2. Inclui agora a definição
> de `stock_behavior` em products, que é pré-requisito para evitar dupla
> movimentação de estoque.

### Objetivo

1. Adicionar tipagem de produto (`stock_behavior`) para diferenciar comportamentos
   de movimentação de estoque.
2. Implementar fichas técnicas (receitas).
3. Ajustar `AplicarVendaNoEstoque` para pular produtos `PRODUCED`.
4. Implementar baixa de ingredientes ao fechar comanda.

### Funcionalidades do MVP

Ver [RESTAURANTES.md — REST-05](RESTAURANTES.md): FTC-01 a FTC-11.

### Alterações no banco

```sql
-- Retrocompatível: DEFAULT RESALE mantém comportamento atual de todos os produtos
ALTER TABLE products
  ADD COLUMN stock_behavior VARCHAR(20) NOT NULL DEFAULT 'RESALE';
  -- RESALE: baixa direta (supermercado, revenda)
  -- PRODUCED: consome ingredientes via ficha técnica
  -- RAW_MATERIAL: ingrediente; sai via ficha ou ajuste manual
  -- SERVICE: sem movimentação

CREATE TABLE recipe_ingredients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_product_id CHAR(26) NOT NULL,      -- prato (PRODUCED)
  ingredient_product_id CHAR(26) NOT NULL,  -- ingrediente (RAW_MATERIAL)
  quantity DECIMAL(14,4) NOT NULL,
  unit VARCHAR(10) NOT NULL,
  waste_factor DECIMAL(5,4) NOT NULL DEFAULT 0,
  FOREIGN KEY (recipe_product_id) REFERENCES products(id),
  FOREIGN KEY (ingredient_product_id) REFERENCES products(id)
);

CREATE TABLE restaurant_tab_ingredient_consumptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tab_id CHAR(26) NOT NULL,
  recipe_product_id CHAR(26) NOT NULL,
  ingredient_product_id CHAR(26) NOT NULL,
  quantity_consumed DECIMAL(14,4) NOT NULL,
  unit VARCHAR(10) NOT NULL,
  recipe_snapshot_hash CHAR(64) NULL,   -- hash da ficha técnica usada
  applied_at TIMESTAMP NOT NULL
);
```

### Alteração em AplicarVendaNoEstoque (código existente)

```
// ADIÇÃO: verificar stock_behavior antes de baixar
if ($produto->stock_behavior === 'PRODUCED') {
    Log::info('Item PRODUCED pulado na baixa PDV — ingredientes baixados pela comanda.');
    continue;
}
if ($produto->stock_behavior === 'SERVICE') {
    continue;
}
// Código existente continua para RESALE e RAW_MATERIAL
$this->estoque->aplicar(...)
```

Esta é a única alteração no código existente desta fase. É mínima e retrocompatível
(`DEFAULT 'RESALE'` garante que todos os produtos atuais passem direto).

### Critérios de aceite

- [ ] Produto tipo PRODUCED criado com ficha técnica
- [ ] Produto tipo RESALE: comportamento de estoque inalterado (regressão)
- [ ] Fechar comanda com item PRODUCED: ingredientes baixados do estoque
- [ ] `ingredients_applied_at` preenchido após baixa
- [ ] Reprocessamento idempotente: baixar duas vezes não duplica o movimento
- [ ] Venda PDV de produto PRODUCED: `AplicarVendaNoEstoque` pula o item
- [ ] CMV do dia calculado corretamente no dashboard
- [ ] Suíte de testes de Inventory passa sem regressões

---

## REST-01 — Dashboard de restaurante

**Status:** NÃO INICIADO  
**Bloqueio:** MS-03 + REST-03 + REST-04 (parcial) para KPIs operacionais;
REST-05 para CMV  
**Estimativa:** 2–3 dias por iteração (duas iterações previstas)

### Objetivo

Dashboard operacional com KPIs do restaurante, entregue em duas iterações:

**Iteração 1 (após REST-03 + REST-04):** DSH-01 a DSH-05 (operacionais).  
**Iteração 2 (após REST-05):** DSH-06, DSH-07 (CMV e margem).

### Critérios de aceite

- [ ] KPIs corretos com dados reais (não zerados)
- [ ] Dashboard atualiza ao recarregar
- [ ] CMV exibido após REST-05 concluído

---

## REST-06 — Integração PDV Java

**Status:** NÃO INICIADO  
**Bloqueio:** REST-03 e REST-04 estabilizados, decisões de API aprovadas  
**Estimativa:** 10–15 dias

### Objetivo

Extensão da API backend para operação de restaurante via terminal PDV,
mantendo a trilha PDV-BE existente intacta.

### Funcionalidades do MVP

Ver [RESTAURANTES.md — REST-06](RESTAURANTES.md): PDV-R-01 a PDV-R-06.

### Nota sobre o escopo PDV Java

O escopo de desenvolvimento do PDV Java (JavaFX, SQLite, Flyway) deve ser
planejado em documento separado no repositório `lucraone-pdv-java`. Esta fase
cobre apenas os contratos de API backend.

### Critérios de aceite

- [ ] Terminal PDV abre comanda via API
- [ ] Terminal adiciona item com modificador
- [ ] Pedido aparece no KDS
- [ ] Comanda fechada via PDV registra pagamento na retaguarda
- [ ] Sincronização offline: comanda gravada localmente e enviada quando online
- [ ] Idempotência verificada com reenvio simulado

---

## REST-07 — Testes integrados e homologação

**Status:** NÃO INICIADO  
**Bloqueio:** REST-01 a REST-06 concluídos  
**Estimativa:** 5–7 dias

### Objetivo

Validação funcional completa do segmento restaurante em staging, com ciclo
completo de operação e regressão do segmento supermercado.

### Critérios de aceite

- [ ] Ciclo completo: mesa → comanda → pedido → cozinha → entrega → fechamento → baixa de estoque
- [ ] Testes de concorrência: múltiplas comandas simultâneas
- [ ] Regressão do supermercado: todos os testes existentes passam
- [ ] PDV Java: offline + sincronização testados
- [ ] Relatório de homologação com evidências documentado
- [ ] Isolamento entre tenants verificado

---

## Estimativas totais (MS-02 → REST-07)

| Fase | Estimativa |
|---|---|
| MS-02 | 4–6 dias |
| MS-03 | 3–5 dias |
| REST-02 | 5–7 dias |
| REST-03 | 10–15 dias |
| REST-04 | 5–7 dias |
| REST-05 | 7–10 dias |
| REST-01 | 4–6 dias (2 iterações) |
| REST-06 | 10–15 dias |
| REST-07 | 5–7 dias |
| **Total** | **53–78 dias** |

Estimativas são de esforço de desenvolvimento efetivo. Não incluem: revisão,
bugs de integração, ajustes de UX, coordenação com PDV Java, nem testes de
aceitação do responsável de produto.

---

## Decisões bloqueadoras pendentes

Nenhuma fase pode avançar até que as seguintes decisões sejam aprovadas:

| Decisão | Bloqueia | Documento |
|---|---|---|
| A — Segmento na Branch | MS-02 | ARQUITETURA_MULTISSEGMENTO.md §3 |
| B — BranchContext em sessão | MS-02 | ARQUITETURA_MULTISSEGMENTO.md §4 |
| C — `stock_behavior` em products | REST-05 | ARQUITETURA_MULTISSEGMENTO.md §5 |
| D — Consumo de ingredientes no fechamento | REST-05 | ARQUITETURA_MULTISSEGMENTO.md §5 |
| E — Segmentos no MVP (SUPERMARKET + RESTAURANT) | MS-02 | ARQUITETURA_MULTISSEGMENTO.md §7 |
| G — Divisão de conta: partes iguais = MVP | REST-03 | RESTAURANTES.md COM-08/09/10 |
| I — Comanda é entidade nova (não extensão de orders) | REST-03 | ARQUITETURA_MULTISSEGMENTO.md §7 |
