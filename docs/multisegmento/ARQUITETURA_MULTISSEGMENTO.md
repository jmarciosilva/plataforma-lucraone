# Arquitetura Multissegmento — LUCRAONE

**Criado:** 2026-11-01  
**Revisado:** 2026-11-01 (revisão 2 — três ressalvas do responsável de produto)  
**Status:** PROPOSTA REVISADA — nenhuma decisão aprovada formalmente  
**Base de análise:** código real do repositório (`main` @ `a5d2faa`)

---

## Histórico de revisões

| Revisão | Data | Motivação |
|---|---|---|
| 1 | 2026-11-01 | Diagnóstico inicial — MS-01 |
| 2 | 2026-11-01 | Três ressalvas: contexto de filial, consistência funcional, estoque |

---

## 1. Diagnóstico do estado atual

### 1.1 Hierarquia de dados existente

```
Tenant (estabelecimento/cliente SaaS)
  └── Company (empresa jurídica)
        └── Branch (filial/unidade operacional)
              └── Terminal (PDV físico)
```

### 1.2 TenantContext — como funciona hoje

O `TenantContext` (`app/Modules/Tenancy/Application/TenantContext.php`) é um
singleton de requisição que armazena apenas `tenant_id`. O `TenantResolver`
resolve esse ID em três estratégias, em ordem:

1. Header `X-Tenant-ID` (APIs, testes).
2. Chave `'tenant_ativo'` na sessão PHP (painel web).
3. Vínculo único — quando o usuário tem exatamente um estabelecimento.

A seleção do tenant é feita pelo `EstabelecimentoController`, que grava o ID na
sessão. Rota `auth.web:sem-tenant` serve as telas de seleção; `auth.web` exige
tenant resolvido e bloqueia com redirecionamento se não houver.

### 1.3 Contexto de filial — o que NÃO existe hoje

**Não existe `BranchContext`, nem mecanismo de sessão para filial ativa.**

O `branch_id` é sempre um **filtro de consulta**, nunca um contexto de sessão.
Evidências no código:

- `PdvSalesDashboardController::resolver()` lê `?branch_id=` do query string e
  valida contra as filiais do tenant. Sem o parâmetro, retorna `null` (visão
  consolidada).
- `BranchController` usa `TenantContext::id()` para filtrar filiais, mas nunca
  "seleciona" uma delas.
- `AutenticarWeb` só resolve tenant, não filial.

### 1.4 RBAC — granularidade efetiva

O RBAC é **100% escopo de tenant**. Evidências:

- `user_role` tem `tenant_id` mas **não tem `branch_id`**.
- `TenantAuthority` filtra tudo por `tenant_id`.
- `StandardRoleMatrix`: `manage-branches`, `view-branches`, `view-all-branches`
  são permissões de tenant — permitem gerir TODAS as filiais do tenant.
- `manage-assigned-branches` / `view-assigned-branches` existem no catálogo mas
  não são atribuídas a nenhum papel padrão por design (remoção deliberada em
  `2026_09_11`).
- Único mecanismo por filial: `pdv_operator_branch_assignments`, restrito ao
  aplicativo de caixa PDV, não ao painel web.

**Conclusão:** não existe hoje autorização por filial específica no painel web.
Um usuário com `view-branches` pode ver todas as filiais do tenant.

### 1.5 Inventário — granularidade efetiva

`Inventory` é escopo `(tenant_id, company_id, product_id)`. Não tem `branch_id`.
`InventoryMovement` idem. O estoque é por empresa, não por filial.

`AplicarVendaNoEstoque` recebe `venda.company_id` (herdado do terminal) e baixa
o produto comercial vendido — sem distinção de tipo de produto, sem fichas técnicas.

`MovimentacaoExternaDeEstoque` permite saldo negativo intencionalmente (best-effort:
a venda aconteceu no caixa; esconder a divergência seria pior).

### 1.6 Produtos — estrutura atual

Tabela `products` (campos relevantes para restaurante):
- `sku`, `name`, `description`, `status`
- `unit` (UN, KG)
- `barcode`
- `ncm_code`, `cest_code`, `default_origin_code` (fiscal)
- `reference_cost_amount` (em `prices`)

**Não existe** campo de tipo de produto (`RESALE`, `PRODUCED`, `RAW_MATERIAL`).
Não existe ficha técnica. `ProductPackage` representa embalagem comercial
(fardo, caixa), não composição por receita.

---

## 2. Conceitos fundamentais

### 2.1 Segmento

Ramo de negócio macro da unidade operacional. Define dashboard e funcionalidades.
Valores propostos: `SUPERMARKET`, `RESTAURANT`.

### 2.2 Perfil operacional

Configurações dentro do segmento. Exemplos de restaurante: `salao`, `delivery`,
`dark_kitchen`. Armazenado em JSON (flexível, sem proliferar colunas).

### 2.3 Funcionalidades habilitadas

Recursos ativados por plano ou configuração, independentes do segmento.

### 2.4 Permissões de usuários

RBAC existente — o que cada pessoa pode fazer dentro das funcionalidades
habilitadas. Hoje escopo de tenant. Escopo de filial é uma necessidade futura,
não do MVP multissegmento.

---

## 3. Decisão A — Granularidade do segmento

### Alternativas

| Opção | Tabela | Prós | Contras |
|---|---|---|---|
| A | `tenants` | Mais simples | Impede misto (supermercado + restaurante no mesmo tenant) |
| B | `companies` | Agrupa filiais de mesma empresa | Empresa pode ter filiais de tipos diferentes |
| **C** | **`branches`** | **Máxima flexibilidade; alinha com PDV (terminal é por filial); DEFAULT retrocompatível** | **Requer BranchContext — ver Decisão B** |

### Recomendação técnica: Opção C (Branch)

Migration segura:

```sql
ALTER TABLE branches
  ADD COLUMN segment VARCHAR(30) NOT NULL DEFAULT 'SUPERMARKET',
  ADD COLUMN operational_profile JSON NULL;
```

Todas as branches existentes recebem `SUPERMARKET` automaticamente. Nenhum
dado existente é alterado. Nenhuma rota existente quebra.

**Status: PROPOSTA — aguarda aprovação.**

---

## 4. Decisão B — Contexto de filial (BranchContext)

Esta é a decisão mais impactante da arquitetura multissegmento, levantada pela
primeira ressalva. Ela precede a implementação de MS-02.

### Problema concreto

Se o segmento está na filial (Opção C), o sistema precisa saber qual filial
está ativa para:
- Selecionar o template de dashboard.
- Condicionar os menus de navegação.
- Filtrar dados operacionais (comandas, mesas, pedidos de cozinha).

Hoje essa informação não existe como contexto de sessão.

### Como o PDV resolve isso hoje

O Terminal já conhece sua filial (campo `branch_id` no model `Terminal`). Não
há seleção de filial no PDV — o terminal é fisicamente instalado em uma filial.
Esse mecanismo não se aplica ao painel web.

### Alternativas para o painel web

**Alternativa B-1: branch_id como filtro de query string (estado atual)**

Manter o padrão do `PdvSalesDashboardController`: o `branch_id` vem de
`?branch_id=...` validado pelo controller. Sem contexto de sessão.

- Prós: sem mudança de arquitetura; consistente com o padrão atual.
- Contras: a URL com `branch_id` vira parâmetro obrigatório em toda rota de
  restaurante; o dashboard principal não consegue alternar o template sem
  receber o parâmetro; UX de troca de filial fragmentada.

**Alternativa B-2: branch gravado na sessão (simétrica ao tenant)**

Adicionar `SESSAO_FILIAL = 'filial_ativa'` na sessão, um `BranchContext` e
um `BranchResolver` análogos ao `TenantContext`/`TenantResolver`.

- Prós: o dashboard lê o segmento do `BranchContext` sem depender da URL;
  arquitetura consistente com o mecanismo de tenant; se branch inválida na
  sessão, redireciona para seleção (igual ao tenant).
- Contras: acrescenta complexidade; requer tela de seleção de filial e
  middleware adicional.
- Mitigação: quando o tenant tem apenas uma filial (caso mais comum no MVP),
  o `BranchResolver` auto-seleciona sem interação do usuário.

**Alternativa B-3: contexto derivado da company (sem seleção explícita)**

Selecionar a filial implicitamente pelo contexto da empresa ativa. A empresa
poderia ser selecionada na sessão.

- Prós: uma camada a menos que B-2.
- Contras: um tenant pode ter várias filiais por empresa, tornando a
  derivação ambígua. Menos segura.

### Recomendação técnica: Alternativa B-2

`BranchContext` em sessão, com resolução automática quando há filial única.

**Regras de resolução propostas (análogas ao TenantResolver):**

1. Filial na sessão (`'filial_ativa'`) — se pertence ao tenant atual e está ativa.
2. Filial única do tenant — auto-seleciona sem interação.
3. Sem resolução — dashboard renderiza visão consolidada de tenant (comportamento
   atual do supermercado é preservado desta forma).

**Visão consolidada (branch = null):** renderiza o dashboard atual, que agrega
todos os dados do tenant. Qualquer tenant sem segmento explícito fica aqui.

**Segurança:**
- O `BranchContext` nunca confia no ID que vem da URL/body. Só na sessão
  assinada do Laravel.
- A troca de filial ativa valida o vínculo `branches.tenant_id = tenant_ativo`.
- Filial desativada ou acesso revogado: `BranchContext` limpa, redireciona
  para seleção (igual ao tratamento de tenant inválido na sessão).

**Autorização por filial:**
O RBAC atual é de tenant — não muda. A visibilidade de filiais já é controlada
por `view-branches` / `manage-branches`. No MVP, essas permissões são suficientes:
quem tem `view-branches` pode ver qualquer filial do tenant. Permissão granular
por filial específica é necessidade futura, não do MVP.

**Status: PROPOSTA — aguarda aprovação.**

---

## 5. Decisão C — Tipos de produto e movimentação de estoque

Esta decisão é crítica para o módulo de restaurante e precede REST-05.

### Estado atual do estoque

`AplicarVendaNoEstoque` baixa o produto comercializado na proporção 1:1. Não há
fichas técnicas. O mecanismo é best-effort: venda aceita → baixa tentada; falha
na baixa não desfaz a venda.

### O problema do restaurante

Um hambúrguer é vendido como produto comercial (`product_id` no PDV). Mas seu
estoque real é composto de ingredientes. Se o sistema baixar o "hambúrguer" do
estoque (comportamento atual) E consumir os ingredientes pela ficha técnica,
haverá **dupla movimentação**.

### Proposta: campo `stock_behavior` em products

```sql
ALTER TABLE products
  ADD COLUMN stock_behavior VARCHAR(20) NOT NULL DEFAULT 'RESALE';
  -- RESALE:     baixa direta do produto (comportamento atual — supermercado)
  -- PRODUCED:   sem baixa direta; consome ingredientes via ficha técnica
  -- RAW_MATERIAL: ingrediente; só sai via consumo de receita ou ajuste manual
  -- SERVICE:    sem movimentação de estoque
```

**Retrocompatibilidade:** `DEFAULT 'RESALE'` mantém todos os produtos existentes
com comportamento atual.

### Regras de movimentação por tipo

| Tipo | Evento | Ação |
|---|---|---|
| RESALE | Venda PDV confirmada | Baixa direta via `AplicarVendaNoEstoque` (sem mudança) |
| RESALE | Venda PDV cancelada | Estorno direto (sem mudança) |
| PRODUCED | Comanda fechada | Consome ingredientes via ficha técnica |
| PRODUCED | Venda PDV de PRODUCED | `AplicarVendaNoEstoque` pula o item (log) |
| RAW_MATERIAL | Comanda fechada (como ingrediente) | Consumido pela ficha técnica |
| RAW_MATERIAL | Venda direta | Baixa direta (ex.: açougue vende ingrediente cru) |
| SERVICE | Qualquer venda | Nenhuma movimentação |

### Evento que dispara consumo de ingredientes

| Opção | Momento | Vantagem | Risco |
|---|---|---|---|
| Pedido enviado à cozinha | Quando a cozinha recebe | Reflete realidade de produção | Ingredientes consumidos antes de confirmar se prato será servido |
| **Início do preparo (KDS: PREPARING)** | **Quando cozinheiro aceita** | **Mais preciso — produção começou** | **Requer integração KDS** |
| Fechamento da comanda | Quando comanda é fechada e paga | Simples, sem integração extra | Estoque "virtual" enquanto prato está na cozinha |

**Recomendação MVP: fechamento da comanda.** O estoque reflete o que foi vendido
e pago. Itens cancelados antes do fechamento simplesmente não entram na conta.
Itens cancelados depois que a cozinha começou a preparar geram uma anotação de
descarte, não um estorno físico.

### Idempotência do consumo de ingredientes

O fechamento de comanda deve ser idempotente. Proposta: coluna
`ingredients_applied_at TIMESTAMP NULL` na tabela de comandas. Se não for nulo,
o consumo já foi registrado e o processo é encurtado.

### Cancelamento e desperdício

| Cenário | Ação proposta |
|---|---|
| Item cancelado antes do preparo (KDS: WAITING) | Remove da comanda; sem movimentação de estoque |
| Item cancelado durante preparo (KDS: PREPARING/READY) | Ingredientes já foram consumidos; registrar como `waste` no inventário com motivo |
| Comanda cancelada inteira antes do preparo | Sem movimentação |
| Comanda cancelada após preparo parcial | Consumo dos itens já preparados; restante sem movimentação |

### Conciliação com vendas do PDV (para restaurantes com PDV)

Quando um restaurante usa o PDV Java para fechar a comanda:

1. O PDV envia `SALE_CONFIRMED` para `ReceivePdvSale`.
2. `AplicarVendaNoEstoque` verifica `stock_behavior` do produto.
3. Se `PRODUCED`: pula a baixa direta (os ingredientes já foram baixados
   pelo fechamento da comanda no backend).
4. Log registra a conciliação para auditoria.

Se o PDV fechar a comanda sem que o backend já tenha fechado (ordem de eventos
invertida), o módulo de comanda detecta `ingredients_applied_at` nulo e aplica
na reconciliação.

### Histórico da ficha técnica

A ficha técnica pode mudar depois que um prato foi produzido. Para auditoria,
o consumo deve registrar **snapshot** das quantidades no momento do uso:

```
restaurant_tab_ingredient_consumptions:
  tab_id, recipe_product_id, ingredient_product_id,
  quantity_consumed, unit, recipe_snapshot_hash,
  applied_at
```

**Status: PROPOSTA — aguarda aprovação.**

---

## 6. Riscos atualizados

| Risco | Prob. | Impacto | Mitigação |
|---|---|---|---|
| Migration de `branches` afeta dados em produção | Baixa | Alto | `DEFAULT 'SUPERMARKET'`, testar em staging |
| `BranchContext` introduz regressão no TenantResolver | Média | Alto | Implementar como camada adicional, não substituta |
| `stock_behavior` muda comportamento de produtos existentes | Nula | — | `DEFAULT 'RESALE'` — produtos existentes inalterados |
| Dupla movimentação PRODUCED via PDV + comanda | Alta (sem mitigação) | Alto | Verificar `stock_behavior` em `AplicarVendaNoEstoque` |
| Histórico de ficha técnica não preservado | Média | Médio | Snapshot no momento do consumo |
| KDS e fechamento de comanda em ordem invertida | Média | Médio | `ingredients_applied_at` como idempotency flag |
| Sidebar condicional quebra testes existentes | Média | Médio | Extrair nav atual antes de condicionar |

---

## 7. Decisões e status

| # | Decisão | Recomendação técnica | Status |
|---|---|---|---|
| A | Granularidade do segmento | Branch — Opção C | AGUARDA APROVAÇÃO |
| B | Mecanismo de BranchContext | Sessão PHP — Alternativa B-2 | AGUARDA APROVAÇÃO |
| C | Tipos de produto e movimentação | Campo `stock_behavior` + consumo na comanda | AGUARDA APROVAÇÃO |
| D | Momento do consumo de ingredientes | Fechamento de comanda (MVP) | AGUARDA APROVAÇÃO |
| E | Segmentos no MVP | SUPERMARKET + RESTAURANT | AGUARDA APROVAÇÃO |
| F | Perfil operacional: JSON vs. colunas | JSON com schema documentado | AGUARDA APROVAÇÃO |
| G | Divisão de conta por tipo | Partes iguais = MVP; valores manuais = V2 | AGUARDA APROVAÇÃO |
| H | KDS: polling vs. SSE | Polling 10s no MVP | AGUARDA APROVAÇÃO |
| I | Comanda: entidade nova vs. extensão de orders | Entidade nova (ciclo de vida diferente) | AGUARDA APROVAÇÃO |
