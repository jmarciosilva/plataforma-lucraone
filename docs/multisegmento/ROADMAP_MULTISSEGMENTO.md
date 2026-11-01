# Roadmap Multissegmento — LUCRAONE

**Criado:** 2026-11-01  
**Última atualização:** 2026-11-01  
**Escopo:** plataforma SaaS multissegmento, começando pelo segmento restaurantes  
**Documento complementar:** [ARQUITETURA_MULTISSEGMENTO.md](ARQUITETURA_MULTISSEGMENTO.md)  
**Roadmap de execução existente:** [ROADMAP.md](../../ROADMAP.md)

> **Atenção:** este roadmap governa o desenvolvimento do multissegmento. Ele
> não substitui o ROADMAP.md existente — coexiste com ele. A F2.6 (Integration
> APIs) permanece na trilha original e pode avançar em paralelo.

---

## Visão executiva

```
┌────────────────────────────────────────────────────────────────┐
│  FASES MULTISSEGMENTO                    STATUS                │
│                                                                 │
│  MS-01  Diagnóstico e arquitetura        ✅ CONCLUÍDO           │
│  MS-02  Segmento e perfil operacional    ○ NÃO INICIADO         │
│  MS-03  Templates e navegação            ○ NÃO INICIADO         │
│  REST-01 Dashboard de restaurante        ○ NÃO INICIADO         │
│  REST-02 Cardápio                        ○ NÃO INICIADO         │
│  REST-03 Mesas e comandas                ○ NÃO INICIADO         │
│  REST-04 Pedidos e produção (KDS)        ○ NÃO INICIADO         │
│  REST-05 Fichas técnicas e custo         ○ NÃO INICIADO         │
│  REST-06 Integração PDV Java             ○ NÃO INICIADO         │
│  REST-07 Testes integrados e homolog.    ○ NÃO INICIADO         │
│                                                                 │
│  Fases planejadas:    10                                        │
│  Concluídas:           1  (MS-01 — diagnóstico)                 │
│  Em andamento:         0                                        │
│  Bloqueadas:           0                                        │
│  Progresso:           10%  (1/10 planejadas)                    │
│  Próxima fase:        MS-02 (aguarda aprovação da arquitetura)  │
│  Última atualização:  2026-11-01                                │
└────────────────────────────────────────────────────────────────┘
```

### Dependências críticas entre fases

```
MS-01 ──→ MS-02 ──→ MS-03 ──→ REST-01
                               REST-02 ──→ REST-03 ──→ REST-04
                                                    ──→ REST-05
                               REST-03 ──→ REST-06
                REST-01 + REST-02 + REST-03 + REST-04 + REST-05 ──→ REST-07
```

---

## MS-01 — Diagnóstico e arquitetura multissegmento

**Status:** CONCLUÍDO  
**Início:** 2026-11-01  
**Conclusão:** 2026-11-01  
**Commits:** a registrar após versionamento destes documentos

### Objetivo

Levantar o estado real do código, identificar o que é reutilizável, definir a
arquitetura de segmentação e produzir o roadmap oficial.

### Escopo

- Leitura de código: models, controllers, migrations, views, rotas, testes.
- Leitura de documentação existente: ROADMAP.md, PROJECT_STATUS.md, ADRs.
- Produção de: ARQUITETURA_MULTISSEGMENTO.md, ROADMAP_MULTISSEGMENTO.md,
  PROJECT_STATUS_MULTISSEGMENTO.md, RESTAURANTES.md.

### Funcionalidades existentes mapeadas

| Área | Módulo | Tabelas |
|---|---|---|
| Multi-tenancy | Tenancy | tenants, tenant_user |
| Empresas / filiais | Companies, Branches | companies, branches |
| Produtos e categorias | Products | products, categories, product_categories, prices, product_packages |
| Estoque | Inventory | inventories, inventory_movements, stock_levels |
| Pedidos (retaguarda) | Sales | orders, order_items |
| Clientes | Sales | customers |
| Relatórios e KPIs | Reporting | (views sobre tabelas acima) |
| Automações | Automation | automation_rules, automation_logs |
| Notificações | Notifications | notifications |
| RBAC | Authorization | roles, permissions, role_permission, user_role |
| PDV — terminais | Terminals, Pdv | terminals, terminal_pairing_codes, terminal_credential_operations |
| PDV — operadores | Pdv | pdv_operator_profiles, pdv_operator_sessions, pdv_operator_branch_assignments |
| PDV — catálogo | Pdv | pdv_catalog_publications, pdv_catalog_deliveries |
| PDV — vendas | Pdv | pdv_received_sales, pdv_received_sale_items, pdv_received_sale_payment_entries |
| Auditoria | Audit | audit_logs |

### Componentes novos necessários (resumo)

- Campo `segment` + `operational_profile` em `branches`.
- Serviço de resolução de segmento ativo (`SegmentContext`).
- Templates de dashboard por segmento.
- Navegação condicional por segmento.
- Módulo Restaurant completo (mesas, comandas, cardápio, KDS, fichas técnicas).

### Riscos identificados

Ver [ARQUITETURA_MULTISSEGMENTO.md — seção 6](ARQUITETURA_MULTISSEGMENTO.md).

### Critérios de aceite

- [x] Inventário completo dos módulos existentes documentado.
- [x] Decisão arquitetural de granularidade proposta e justificada.
- [x] Roadmap de fases elaborado com dependências.
- [x] Documentos de planejamento criados e versionados.
- [ ] Aprovação da decisão arquitetural pelo responsável de produto.

### Entregáveis

- ARQUITETURA_MULTISSEGMENTO.md ✅
- ROADMAP_MULTISSEGMENTO.md ✅
- PROJECT_STATUS_MULTISSEGMENTO.md ✅
- RESTAURANTES.md ✅

---

## MS-02 — Segmento e perfil operacional

**Status:** NÃO INICIADO  
**Bloqueio:** aguarda aprovação da decisão arquitetural de MS-01  
**Estimativa:** 2–3 dias de desenvolvimento + testes

### Objetivo

Adicionar ao modelo de dados o conceito de segmento e perfil operacional de
filial, preservando retrocompatibilidade com todos os dados e contratos
existentes.

### Justificativa

Sem identificação de segmento na branch, o sistema não pode selecionar o
template de dashboard correto nem habilitar funcionalidades específicas. É o
alicerce de toda a plataforma multissegmento.

### Escopo

- Migration não-destrutiva em `branches`.
- Atualização do model `Branch` e suas Policies.
- Atualização do formulário de cadastro e edição de filial no painel.
- Serviço `SegmentContext` para resolver segmento ativo na requisição.
- Constantes de segmento versionadas.
- Testes de unidade e integração.

### Funcionalidades previstas

1. Campo `segment ENUM('SUPERMARKET','RESTAURANT','BAKERY','BAR','FAST_FOOD','RETAIL')`
   em `branches`, `DEFAULT 'SUPERMARKET'` — branches existentes ficam como
   supermercado automaticamente.
2. Campo `operational_profile JSON NULL` em `branches` — armazena perfis
   como `{ "modalidades": ["salao", "delivery"] }`.
3. Select de segmento no formulário de criação e edição de filial.
4. Classe `SegmentContext` injetável nos controllers para ler o segmento da
   filial ativa.

### Módulos existentes reutilizados

- `Branch` model — estendido com novos campos.
- `BranchController` — estendido com validação dos novos campos.
- `BranchPolicy` — sem alteração.
- `TenantContext` — `SegmentContext` complementa, não substitui.

### Componentes novos

- `SegmentContext` (Application service).
- Migration `add_segment_to_branches_table`.
- Constante `BranchSegment` com valores e labels.

### Alterações no banco

```sql
ALTER TABLE branches
  ADD COLUMN segment VARCHAR(30) NOT NULL DEFAULT 'SUPERMARKET'
    COMMENT 'Segmento operacional: SUPERMARKET, RESTAURANT, BAKERY, BAR, FAST_FOOD, RETAIL',
  ADD COLUMN operational_profile JSON NULL
    COMMENT 'Configurações específicas do perfil operacional em JSON';
```

Migration segura: `DEFAULT 'SUPERMARKET'` garante que todas as filiais
existentes permaneçam funcionando como supermercado sem qualquer intervenção
nos dados.

### Alterações em APIs

- `GET /branches/{id}` → retorna `segment` e `operational_profile` no response.
- `POST/PUT /branches` → aceita `segment` e `operational_profile`.
- Nenhuma rota existente é removida ou quebrada.

### Impactos no PDV Java

Nenhum impacto imediato. O PDV Java não consome dados de `segment` ainda.

### Dependências

- MS-01 aprovado.

### Riscos

- Migration em produção com dados: **baixo risco** pelo uso de `DEFAULT`.
- Validação do JSON `operational_profile`: schema deve ser definido antes da
  migration para evitar dados malformados.

### Testes necessários

- Teste de migration: filiais existentes recebem `SUPERMARKET` automaticamente.
- Teste unitário: `Branch::segment()` retorna o enum correto.
- Teste de integração: criação de filial com segmento `RESTAURANT`.
- Teste de regressão: todos os testes de filial existentes continuam passando.
- Teste de API: response de `GET /branches/{id}` inclui `segment`.

### Critérios de aceite

- [ ] Migration executada em staging sem erros.
- [ ] Filiais existentes continuam funcionando com `segment = SUPERMARKET`.
- [ ] Formulário de filial exibe e persiste o segmento.
- [ ] `SegmentContext` resolve segmento correto para filial ativa.
- [ ] Suite de testes existente passa sem regressões.
- [ ] API de branches retorna `segment` e `operational_profile`.

---

## MS-03 — Templates de dashboard e navegação por segmento

**Status:** NÃO INICIADO  
**Bloqueio:** MS-02 concluído

### Objetivo

Selecionar automaticamente o template de dashboard e os menus de navegação
corretos com base no segmento da filial ativa, sem modificar o comportamento
atual do segmento supermercado.

### Justificativa

Sem seleção automática de template, seria necessário manter dois sistemas
separados — duplicação inaceitável. Com o segmento identificado (MS-02), esta
fase entrega o mecanismo que permite expansão limpa para qualquer novo segmento.

### Escopo

- Refatoração do `DashboardController` para despachar por segmento.
- Extração da navegação atual do `sidebar.blade.php` para partial
  `nav/supermarket.blade.php`.
- Criação de stub `nav/restaurante.blade.php` (estrutura, sem funcionalidade).
- Criação de stub `dashboard/restaurante.blade.php` (estrutura, KPIs zerados).
- Atualização do `sidebar.blade.php` para inclusão condicional.
- Testes de navegação por segmento.

### Funcionalidades previstas

1. `DashboardController` despacha para `DashboardRestauranteService` quando
   segmento for `RESTAURANT`.
2. Sidebar exibe menus de restaurante para filiais com `segment = RESTAURANT`.
3. Sidebar exibe menus atuais (supermercado) sem alteração para demais.
4. Dashboard de restaurante entrega página funcional com KPIs zerados
   (dados virão em REST-01).

### Módulos existentes reutilizados

- Todos os componentes Blade: `x-kpi`, `x-card`, `x-section-label`, `x-button`.
- Design system Tailwind.
- `TenantContext` + `SegmentContext`.

### Componentes novos

- `DashboardRestauranteService` (stub — sem queries ainda).
- `resources/views/dashboard/restaurante.blade.php`.
- `resources/views/components/nav/supermarket.blade.php` (extração).
- `resources/views/components/nav/restaurante.blade.php` (stub).

### Alterações no banco

Nenhuma.

### Alterações em APIs

Nenhuma.

### Impactos no PDV Java

Nenhum.

### Dependências

- MS-02 concluído.

### Riscos

- Extração do nav atual pode quebrar testes de browser/Blade se existirem.
  Verificar antes de extrair.

### Testes necessários

- Teste de feature: usuário com filial `RESTAURANT` vê dashboard de restaurante.
- Teste de feature: usuário com filial `SUPERMARKET` vê dashboard atual
  sem qualquer alteração.
- Teste de regressão: suite completa passa após extração do nav.

### Critérios de aceite

- [ ] Filial supermercado: dashboard e menus idênticos ao estado anterior.
- [ ] Filial restaurante: dashboard de restaurante é exibido (mesmo que com
  KPIs zerados).
- [ ] Sidebar correto por segmento.
- [ ] Nenhuma regressão nos 1458+ testes existentes.

---

## REST-01 — Dashboard de restaurante

**Status:** NÃO INICIADO  
**Bloqueio:** MS-03 concluído

### Objetivo

Dashboard operacional específico para restaurante, com KPIs relevantes ao ramo,
alimentado por dados reais do sistema.

### Justificativa

O dashboard atual (supermercado) mostra KPIs de estoque e pedidos de retaguarda.
Restaurante precisa de KPIs operacionais em tempo real: mesas, comandas, pedidos
em preparo, ticket médio, faturamento do turno.

### Escopo (MVP)

KPIs prioritários para o painel inicial (dados disponíveis após REST-03 e REST-04):

- Faturamento do dia / do turno.
- Número de comandas abertas.
- Número de mesas ocupadas vs. disponíveis.
- Ticket médio do dia.
- Pedidos em preparo (aguardando cozinha).
- Tempo médio de atendimento (quando disponível).

KPIs de lucratividade (dependem de REST-05):
- CMV do dia.
- Margem estimada.

### Módulos existentes reutilizados

- Componentes `x-kpi`, `x-card`, `x-section-label`.
- `DashboardRestauranteService` criado em MS-03 — preenchido nesta fase.

### Componentes novos

- Queries de agregação de comandas, mesas e pedidos.
- Atualização de `dashboard/restaurante.blade.php`.

### Alterações no banco

Nenhuma própria — depende das tabelas de REST-03 e REST-04.

### Dependências

- MS-03 concluído.
- REST-03 concluído (dados de mesas e comandas).
- REST-04 concluído (dados de pedidos em preparo) — *pode ser parcial*.

### Critérios de aceite

- [ ] Dashboard exibe KPIs corretos com dados reais.
- [ ] Dashboard atualiza ao recarregar a página.
- [ ] KPIs zerados são exibidos de forma clara, não como erros.
- [ ] Testes de feature cobrem os principais KPIs.

---

## REST-02 — Cardápio

**Status:** NÃO INICIADO  
**Bloqueio:** MS-02 concluído (segmento identificável)

### Objetivo

Gestão do cardápio do restaurante: categorias, pratos, bebidas, adicionais,
modificadores e disponibilidade.

### Justificativa

O cardápio é o catálogo de produtos do restaurante. A estrutura de `products` e
`categories` existente cobre o caso básico, mas restaurante exige modificadores
("sem cebola", "ponto da carne"), grupos de adicionais e indicadores de
disponibilidade ("item esgotado no dia").

### Escopo (MVP)

1. Reuso do módulo `Products` para pratos e bebidas.
2. Extensão da tabela `products` com campos de restaurante:
   - `preparation_time_minutes` (tempo estimado de preparo).
   - `is_available` (disponível hoje, flag operacional).
3. Tabela `menu_modifier_groups` (grupos de opções: "ponto", "adicional").
4. Tabela `menu_modifiers` (opções dentro de cada grupo).
5. Associação produto ↔ grupos de modificadores.
6. Interface de gestão do cardápio no painel.
7. Publicação do cardápio para o PDV Java (via API existente de catálogo, estendida).

### Módulos existentes reutilizados

- `products`, `categories`, `product_categories`, `prices` — base do cardápio.
- `ProductWebController` — estendido com visão de restaurante.
- Módulo Pdv / catálogo — publicação do cardápio para o PDV.

### Componentes novos

- Migration: campos de restaurante em `products`.
- Tabelas `menu_modifier_groups` e `menu_modifiers`.
- Controller e views de gestão de cardápio (separadas de produtos genéricos).
- Extensão da publicação de catálogo PDV para incluir modificadores.

### Alterações no banco

```sql
-- Extensão de products (nullable — retrocompatível)
ALTER TABLE products
  ADD COLUMN preparation_time_minutes SMALLINT UNSIGNED NULL,
  ADD COLUMN is_available BOOLEAN NOT NULL DEFAULT TRUE;

-- Grupos de modificadores (ex.: "Ponto da Carne", "Adicionais")
CREATE TABLE menu_modifier_groups (
  id CHAR(26) PRIMARY KEY,
  tenant_id CHAR(26) NOT NULL,
  name VARCHAR(120) NOT NULL,
  required BOOLEAN NOT NULL DEFAULT FALSE,
  min_choices TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_choices TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ...
);

-- Opções dentro de cada grupo
CREATE TABLE menu_modifiers (
  id CHAR(26) PRIMARY KEY,
  group_id CHAR(26) NOT NULL,
  name VARCHAR(120) NOT NULL,
  price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_available BOOLEAN NOT NULL DEFAULT TRUE,
  ...
);

-- Associação produto ↔ grupos
CREATE TABLE product_modifier_groups (
  product_id CHAR(26) NOT NULL,
  group_id CHAR(26) NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, group_id)
);
```

### Alterações em APIs

- `GET /api/v1/pdv/catalog` → inclui `modifiers` por produto quando segmento
  for `RESTAURANT`.
- Novos endpoints de gestão de grupos e modificadores (autenticação humana).

### Impactos no PDV Java

O PDV Java precisará de atualização para consumir e renderizar modificadores.
Escopo detalhado em REST-06.

### Dependências

- MS-02 concluído.
- MS-03 concluído (para os menus do painel).

### Critérios de aceite

- [ ] Prato cadastrado com grupo de modificadores e opções.
- [ ] Disponibilidade de item alterada via painel.
- [ ] Catálogo publicado para o PDV inclui modificadores.
- [ ] Produtos de supermercado não são afetados pelos novos campos.
- [ ] Testes de regressão do módulo Products passam.

---

## REST-03 — Mesas e comandas

**Status:** NÃO INICIADO  
**Bloqueio:** MS-02 concluído

### Objetivo

Gestão de mesas e comandas: cadastro, abertura, movimentação, transferência e
encerramento.

### Justificativa

Mesas e comandas são o coração operacional de um restaurante de salão. Não
existem no sistema atual.

### Escopo (MVP)

**Mesas:**
- Cadastro de mesas por filial (número, capacidade, setor/salão).
- Status: DISPONÍVEL, OCUPADA, RESERVADA, MANUTENÇÃO.
- Associação de garçom responsável.

**Comandas:**
- Abertura de comanda vinculada a mesa ou balcão.
- Adição e remoção controlada de itens (com modificadores).
- Status: ABERTA, FECHANDO, FECHADA, CANCELADA.
- Divisão de conta (N partes iguais ou valores manuais).
- Transferência de itens entre comandas.
- Transferência de comanda para outra mesa.
- Encerramento com forma de pagamento.
- Histórico de alterações (auditoria).

### Módulos existentes reutilizados

- `orders`/`order_items` como referência de design — comanda é entidade nova,
  mas o padrão ULID, HasTenant, SoftDeletes e ciclo de transições é o mesmo.
- RBAC existente — novas permissões: `manage-tables`, `manage-tabs`.
- Componentes Blade de tabela, card, modal.

### Componentes novos

```
Tabelas novas:
  restaurant_tables         (mesas)
  restaurant_tabs           (comandas)
  restaurant_tab_items      (itens da comanda)
  restaurant_tab_payments   (registros de pagamento da comanda)
  restaurant_tab_audit_log  (log de alterações da comanda)

Models: Table, Tab, TabItem, TabPayment
Services: TabService (abre, adiciona item, remove item, fecha, divide)
Controllers: TableController, TabController (web + API)
Views: tables/index, tables/show, tabs/index, tabs/show
```

### Alterações no banco

Ver tabelas acima. Todas são novas — sem risco para dados existentes.

### Alterações em APIs

Novos endpoints:
- `GET|POST /api/v1/restaurant/tables`
- `GET|PUT /api/v1/restaurant/tables/{id}`
- `POST /api/v1/restaurant/tabs` (abrir comanda)
- `POST /api/v1/restaurant/tabs/{id}/items` (adicionar item)
- `DELETE /api/v1/restaurant/tabs/{id}/items/{itemId}`
- `POST /api/v1/restaurant/tabs/{id}/close`
- `POST /api/v1/restaurant/tabs/{id}/transfer`
- `POST /api/v1/restaurant/tabs/{id}/split`

### Impactos no PDV Java

O PDV Java precisará consumir as APIs de comanda para operação no salão.
Escopo em REST-06.

### Dependências

- MS-02 concluído (segment na branch).
- REST-02 concluído (cardápio para adicionar itens).

### Critérios de aceite

- [ ] Mesa cadastrada, status gerenciado via painel.
- [ ] Comanda aberta vinculada a mesa.
- [ ] Item adicionado à comanda com modificadores.
- [ ] Item removido com registro de auditoria.
- [ ] Comanda transferida para outra mesa.
- [ ] Conta dividida em N partes.
- [ ] Comanda fechada com forma de pagamento.
- [ ] Histórico de alterações auditável.
- [ ] Isolamento por tenant garantido em todos os endpoints.

---

## REST-04 — Pedidos e produção (KDS)

**Status:** NÃO INICIADO  
**Bloqueio:** REST-03 concluído

### Objetivo

Fluxo de encaminhamento de pedidos para a cozinha e painel KDS (Kitchen Display
System) para gestão da fila de preparo.

### Justificativa

Sem o KDS, o restaurante não tem visibilidade do que está sendo produzido.
A cozinha precisa de uma tela com os pedidos na ordem certa e o status de
preparo.

### Escopo (MVP)

1. Pedido enviado da comanda para a cozinha com status `AGUARDANDO`.
2. Tela KDS (web, atualizável por polling ou SSE) exibindo:
   - Pedidos aguardando preparo.
   - Pedidos em preparo.
   - Pedidos prontos para entrega.
3. Ações da cozinha: marcar como "em preparo", "pronto".
4. Notificação ao garçom quando pedido está pronto (sistema de notificações
   existente pode ser reutilizado).
5. Relatório simples de tempo médio de preparo.

### Módulos existentes reutilizados

- `notifications` — garçom é notificado quando pedido está pronto.
- Componentes Blade para tela de cozinha.

### Componentes novos

- Tabela `kitchen_orders` (pedidos encaminhados à cozinha).
- `KitchenOrderController` (web — KDS) e API.
- View `kitchen/kds.blade.php` (tela de cozinha, full-screen, sem sidebar).
- Polling ou SSE para atualização em tempo real do KDS.

### Alterações no banco

```sql
CREATE TABLE kitchen_orders (
  id CHAR(26) PRIMARY KEY,
  tenant_id CHAR(26) NOT NULL,
  branch_id CHAR(26) NOT NULL,
  tab_id CHAR(26) NOT NULL,            -- comanda origem
  tab_item_id CHAR(26) NOT NULL,       -- item da comanda
  status ENUM('WAITING','PREPARING','READY','DELIVERED'),
  sent_at TIMESTAMP NOT NULL,
  started_at TIMESTAMP NULL,
  ready_at TIMESTAMP NULL,
  delivered_at TIMESTAMP NULL,
  notes TEXT NULL,
  ...
);
```

### Dependências

- REST-03 concluído (mesas e comandas).

### Critérios de aceite

- [ ] Pedido encaminhado à cozinha aparece no KDS.
- [ ] Status de preparo atualizado pela cozinha.
- [ ] Garçom notificado quando pedido pronto.
- [ ] KDS atualiza automaticamente (polling mínimo de 10s).
- [ ] Tempo médio de preparo calculado corretamente.

---

## REST-05 — Fichas técnicas e custo de produção

**Status:** NÃO INICIADO  
**Bloqueio:** REST-02 concluído (cardápio), Inventory existente

### Objetivo

Gestão de fichas técnicas (receitas), cálculo de CMV por prato e baixa de
ingredientes do estoque.

### Justificativa

Sem ficha técnica, o restaurante não conhece o custo real de cada prato e não
pode calcular margem ou CMV. A baixa de ingredientes fecha o ciclo de estoque.

### Escopo (MVP)

1. Tabela de ingredientes (mapeados sobre `products` com `unit = KG|G|L|ML|UN`).
2. Ficha técnica: produto (prato) → lista de ingredientes + quantidades.
3. Custo calculado da receita com base no preço de custo dos ingredientes.
4. Baixa de ingredientes ao fechar comanda (integrada ao `InventoryService`).
5. Relatório de CMV por prato e por período.

### Módulos existentes reutilizados

- `Inventory` module completo — `InventoryMovement`, `StockLevel`.
- `products` — ingredientes são produtos com flags de matéria-prima.
- `prices` — custo dos ingredientes.

### Componentes novos

- Tabela `recipe_ingredients` (ficha técnica).
- `RecipeService` (calcula custo, dispara baixa).
- Views de gestão de fichas técnicas.

### Alterações no banco

```sql
CREATE TABLE recipe_ingredients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_product_id CHAR(26) NOT NULL,   -- prato
  ingredient_product_id CHAR(26) NOT NULL, -- ingrediente
  quantity DECIMAL(14,4) NOT NULL,
  unit VARCHAR(10) NOT NULL,
  waste_factor DECIMAL(5,4) NOT NULL DEFAULT 0,  -- fator de perda
  ...
);
```

### Dependências

- REST-02 concluído (cardápio).
- `Inventory` module (já existe).

### Critérios de aceite

- [ ] Ficha técnica cadastrada para ao menos um prato.
- [ ] Custo do prato calculado corretamente com base nos ingredientes.
- [ ] Estoque de ingredientes baixado ao fechar comanda.
- [ ] CMV do dia calculado e exibido no dashboard (REST-01).
- [ ] Testes de integração cobrem o ciclo receita → baixa → estoque.

---

## REST-06 — Integração com PDV Java

**Status:** NÃO INICIADO  
**Bloqueio:** REST-03 e REST-04 estabilizados

### Objetivo

Estender o PDV Java para operar como terminal de restaurante: abertura de
comandas, adição de itens com modificadores, envio para cozinha e fechamento.

### Justificativa

O PDV Java é o terminal físico do restaurante. Sem integração, toda a operação
de comanda fica restrita ao painel web.

### Escopo (MVP)

**No backend (retaguarda):**
- Novos endpoints de comanda acessíveis com credencial de terminal:
  - `POST /api/v1/pdv/restaurant/tabs`
  - `POST /api/v1/pdv/restaurant/tabs/{id}/items`
  - `POST /api/v1/pdv/restaurant/tabs/{id}/close`
- Catálogo PDV estendido com modificadores (já planejado em REST-02).

**No PDV Java (lucraone-pdv-java):**
- Telas de operação de restaurante.
- Sincronização offline-first das comandas.
- Exibição de modificadores na seleção de item.

### Observação importante

O escopo do PDV Java é definido no repositório `lucraone-pdv-java`. Este
roadmap cobre apenas os contratos da API backend. A implementação no PDV Java
deve ser planejada em documento próprio após REST-03 e REST-04 estarem
estáveis.

### Dependências

- REST-03 concluído (mesas e comandas — API estável).
- REST-04 concluído (pedidos e KDS — API estável).
- REST-02 concluído (modificadores no catálogo).

### Critérios de aceite

- [ ] Terminal PDV abre comanda via API.
- [ ] Terminal PDV adiciona item com modificador.
- [ ] Pedido enviado para cozinha aparece no KDS.
- [ ] Comanda fechada via PDV registra pagamento na retaguarda.
- [ ] Operação offline e sincronização testadas com falha de rede simulada.

---

## REST-07 — Testes integrados e homologação

**Status:** NÃO INICIADO  
**Bloqueio:** REST-01 a REST-06 concluídos

### Objetivo

Validação funcional completa do segmento restaurante em ambiente equivalente à
produção, com casos de uso reais.

### Escopo

- Cenário completo: abrir mesa → comanda → pedido → cozinha → entrega →
  fechamento → baixa de estoque.
- Testes de concorrência: múltiplas comandas simultâneas.
- Testes de regressão: segmento supermercado intacto.
- Testes de integração PDV Java ↔ retaguarda.
- Validação de isolamento multi-tenant.
- Validação de RBAC para papéis de restaurante.

### Critérios de aceite

- [ ] Ciclo completo executado sem erros em staging.
- [ ] Testes de regressão supermercado passam sem alteração.
- [ ] PDV Java opera comandas offline e sincroniza ao reconectar.
- [ ] Isolamento entre tenants verificado.
- [ ] Relatório de homologação documentado com evidências.

---

## Apêndice — Fases não iniciadas: estimativas preliminares

Estas estimativas são orientativas e devem ser revisadas antes de cada fase:

| Fase | Complexidade | Estimativa |
|---|---|---|
| MS-02 | Baixa | 2–3 dias |
| MS-03 | Média | 3–5 dias |
| REST-01 | Média | 2–3 dias (depende de REST-03/04) |
| REST-02 | Média | 5–7 dias |
| REST-03 | Alta | 10–15 dias |
| REST-04 | Média | 5–7 dias |
| REST-05 | Média | 5–7 dias |
| REST-06 | Alta | 10–15 dias (coordenado com PDV Java) |
| REST-07 | Média | 5–7 dias |

**Total estimado (após MS-01):** 47–69 dias de desenvolvimento efetivo.

Estas estimativas não incluem revisão, correções de bugs de integração,
ajustes de UX, nem coordenação com o desenvolvimento do PDV Java.
