# Arquitetura Multissegmento — LUCRAONE

**Criado:** 2026-11-01  
**Status:** PROPOSTA — aguardando aprovação antes de qualquer implementação  
**Autores:** diagnóstico técnico elaborado com base no código real do repositório

---

## 1. Diagnóstico do estado atual

### 1.1 Hierarquia de dados existente

```
Tenant (estabelecimento/cliente SaaS)
  └── Company (empresa jurídica)
        └── Branch (filial/unidade operacional)
              └── Terminal (PDV físico)
```

A hierarquia está completamente implementada e operacional. O tenant é o
isolamento raiz. Cada request autenticado carrega um `tenant_id` resolvido
pelo `TenantContext`.

### 1.2 Módulos existentes e reutilizáveis

| Módulo | Status | Reutilizável em restaurante |
|---|---|---|
| Tenancy (tenant, company, branch) | ✅ produção | 100% |
| Identity + RBAC (user, role, permission) | ✅ produção | 100% |
| Products + Categories | ✅ produção | Base do cardápio |
| Inventory (estoque + movimentos) | ✅ produção | Base de ingredientes |
| Sales / Orders | ✅ produção | Base de pedidos |
| Customers | ✅ produção | 100% |
| Reporting (dashboard, KPIs) | ✅ produção | Parcialmente |
| Automation (regras + logs) | ✅ produção | 100% |
| Notifications | ✅ produção | 100% |
| PDV (terminais, catálogo, vendas) | ✅ produção | Estendido para comandas |
| Audit logs | ✅ produção | 100% |

### 1.3 O que NÃO existe hoje

Nenhum campo de **segmento, ramo de atividade ou perfil operacional** existe em
qualquer tabela do banco. A plataforma não possui templates de dashboard
alternados, menus condicionais por segmento nem funcionalidades exclusivas de
restaurante.

### 1.4 Modelo Order atual

O modelo `Order` representa um pedido de retaguarda com ciclo de vida:
`draft → pending → confirmed → shipped → completed | cancelled`.

Esse ciclo não representa comanda de restaurante (abrir mesa, adicionar itens
ao longo do tempo, dividir conta, fechar). A tabela `orders` pode ser
reutilizada como estrutura base, mas o ciclo de vida de restaurante é distinto.

### 1.5 Dashboard atual

O `DashboardController` monta um único template (`dashboard/index.blade.php`)
com KPIs de varejo (faturamento, pedidos, ticket médio, estoque) e vendas do
PDV do dia. Não há switch de template por segmento.

---

## 2. Conceitos fundamentais

É importante diferenciar quatro conceitos que se sobrepõem, mas são
independentes:

### 2.1 Segmento

O **ramo de negócio macro** da unidade operacional. Define quais funcionalidades
fazem sentido e qual dashboard é apresentado.

Exemplos: `SUPERMARKET`, `RESTAURANT`, `BAKERY`, `BAR`, `FAST_FOOD`, `RETAIL`.

### 2.2 Perfil operacional

Configurações específicas do negócio dentro de um segmento. Um restaurante pode
operar como "apenas salão", "delivery + salão", "dark kitchen", etc. Determina
sub-funcionalidades habilitadas.

### 2.3 Funcionalidades habilitadas

Recursos específicos ativados por plano de contratação ou configuração.
Independente do segmento (ex.: um supermercado pode não ter PDV habilitado).

### 2.4 Permissões de usuários

RBAC já existente — quem pode fazer o quê dentro da funcionalidade habilitada.
Não confundir com habilitação de funcionalidade.

---

## 3. Decisão arquitetural: onde fica o segmento?

### Opção A — Segmento no Tenant

```sql
ALTER TABLE tenants ADD COLUMN segment VARCHAR(30);
```

**Prós:** simples, um cliente = um segmento.  
**Contras:** impede cliente com unidades mistas (padaria + café, loja + açougue).

### Opção B — Segmento na Company

```sql
ALTER TABLE companies ADD COLUMN segment VARCHAR(30);
```

**Prós:** agrupa filiais por empresa do mesmo ramo.  
**Contras:** uma empresa pode ter lojas de tipos diferentes.

### Opção C — Segmento na Branch *(RECOMENDAÇÃO)*

```sql
ALTER TABLE branches ADD COLUMN segment VARCHAR(30) NOT NULL DEFAULT 'SUPERMARKET';
ALTER TABLE branches ADD COLUMN operational_profile JSON NULL;
```

**Prós:**
- Alinha com a granularidade do PDV (terminal já é por filial).
- Permite um tenant com filial-supermercado e filial-restaurante.
- `operational_profile` JSON acomoda perfis sem proliferar colunas.
- Migration é não-destrutiva com `DEFAULT 'SUPERMARKET'` — dados existentes
  continuam funcionando exatamente como hoje.
- Coerente com `PdvTenantSetting` que já armazena configs por tenant;
  o perfil de segmento fica na filial, que é a unidade operacional real.

**Contras:** lógica de dashboard precisa consultar a filial ativa, não apenas
o tenant — mas o `TenantContext` já resolve isso.

> **Recomendação técnica: Opção C (Branch).**  
> Decisão final cabe ao responsável de produto. Registrar aqui após aprovação.

### Consequência da Opção C no fluxo

1. Usuário faz login e seleciona estabelecimento (tenant).
2. Se tenant tem mais de uma filial, sistema identifica filial ativa no contexto
   (fluxo já existe parcialmente com o PDV).
3. `$branch->segment` determina qual dashboard renderizar e quais menus exibir.
4. Se branch é `null` (usuário sem filial definida), usa visão consolidada de tenant.

---

## 4. Arquitetura de templates de dashboard

### 4.1 Padrão proposto

```
DashboardController
  ↓ lê branch ativa do contexto
  ↓ switch($segment)
    SUPERMARKET → DashboardSummaryService → dashboard/supermarket.blade.php
    RESTAURANT  → DashboardRestauranteService → dashboard/restaurante.blade.php
    (default)   → dashboard/index.blade.php (atual, para compatibilidade)
```

### 4.2 Componentes compartilhados

Os componentes Blade existentes (`x-kpi`, `x-card`, `x-section-label`,
`x-chart-barras`, etc.) são reutilizáveis sem alteração. O design system
(Tailwind + Alpine.js) é comum a todos os segmentos.

### 4.3 Menus condicionais

O `sidebar.blade.php` atual é monolítico. A refatoração mínima é:

```blade
@if ($segment === 'RESTAURANT')
    @include('components.nav.restaurante')
@else
    @include('components.nav.supermarket')  {{-- extração do atual --}}
@endif
```

Sem reescrita do sistema de navegação — apenas inclusão condicional.

---

## 5. Compatibilidade com PDV Java

O PDV Java consome as APIs de:
- Autenticação de terminal (`/api/v1/pdv/terminals/pair`)
- Catálogo de produtos (`/api/v1/pdv/catalog/*`)
- Ingestão de vendas (`/api/v1/pdv/sales`)
- Sessão de operador (`/api/v1/pdv/operator-session/*`)

Para restaurantes, o PDV Java precisará de endpoints adicionais:
- Abertura e movimentação de comanda
- Envio de pedidos para a cozinha
- Consulta de cardápio com modificadores

Esses endpoints são novos, não substituem os existentes. A trilha PDV-BE
existente permanece intacta.

---

## 6. Riscos identificados

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Migration de `branches` afeta dados em produção | Baixa | Alto | `DEFAULT 'SUPERMARKET'`, `nullable` onde possível, testar em staging |
| Sidebar condicional quebra testes de autorização existentes | Média | Médio | Extrair nav atual antes de condicionar |
| Módulo de comanda conflita com `orders` existentes | Média | Médio | Tabelas separadas com FK opcional para `orders` |
| PDV Java não preparado para comandas | Alta | Alto | REST-06 só começa depois de REST-03 e REST-04 estabilizados |
| Dados de `branches` sem segment em produção | Certo | Baixo | `DEFAULT 'SUPERMARKET'` resolve automaticamente |

---

## 7. Status desta decisão

| Questão | Status |
|---|---|
| Granularidade do segmento (tenant/company/branch) | PROPOSTA — aguarda aprovação |
| Estrutura do `operational_profile` JSON | A definir em MS-02 |
| Segmentos disponíveis no MVP | PROPOSTA: SUPERMARKET, RESTAURANT |
| Compatibilidade retroativa das APIs do PDV | Garantida por design |
