# Status do Projeto — LUCRAONE Multissegmento

**Atualizado em:** 2026-11-01 (revisão 5 — REST-02 EM IMPLEMENTAÇÃO)  
**Fase atual:** REST-02 — Cardápio e Modificadores (EM DESENVOLVIMENTO)

---

## Resumo executivo

| Indicador | Valor |
|---|---|
| Fases planejadas | 10 |
| Fases concluídas | 3 (MS-01, MS-02, MS-03) |
| Em andamento | 0 |
| Bloqueadas | 0 |
| Progresso | 30% (3/10 fases com critérios verificados) |
| Próxima fase elegível | REST-02 (depende de MS-02 ✅ e MS-03 ✅) |
| Baseline de testes | 1939 (1939 PASS) — main @ `fc6aaa0` |
| Última atualização | 2026-11-01 |

---

## Decisões aprovadas (MS-02)

| ID | Decisão | Resolução |
|---|---|---|
| A | Granularidade do segmento | Branch — coluna `segment` na tabela `branches` |
| B | BranchContext em sessão PHP | Alternativa B-2 — sessão 'filial_ativa' com auto-resolve |
| E | Segmentos no MVP | SUPERMARKET + RESTAURANT |

## Decisões abertas — aguardam aprovação

| ID | Decisão | Recomendação técnica | Bloqueia |
|---|---|---|---|
| C | `stock_behavior` em products | Campo com DEFAULT RESALE | REST-05 |
| D | Momento do consumo de ingredientes: fechamento da comanda | Fechamento (MVP) | REST-05 |
| F | Perfil operacional em JSON | JSON com schema documentado | MS-02 (parcial — campo não adicionado ainda) |
| G | Divisão de conta: partes iguais = MVP | MVP; valores manuais = V2 | REST-03 |
| H | KDS: polling 10s no MVP | Polling 10s | REST-04 |
| I | Comanda: entidade nova (não extensão de orders) | Entidade nova | REST-03 |

---

## Estado detalhado por fase

### MS-01 — Diagnóstico e arquitetura

| Campo | Valor |
|---|---|
| Status | CONCLUÍDO |
| Início | 2026-11-01 |
| Conclusão | 2026-11-01 |
| Commits | `e83afab` (rev 1) · revisão 2 a commitar |

**Critérios verificados:**
- [x] Inventário de módulos documentado
- [x] Proposta arquitetural justificada (rev 1)
- [x] Três ressalvas analisadas (rev 2): BranchContext, consistência funcional, estoque
- [x] Documentos criados e versionados
- [ ] Aprovação das decisões A–I ← **PENDENTE**

---

### MS-02 — Segmento, BranchContext e perfil operacional

| Campo | Valor |
|---|---|
| Status | CONCLUÍDO (parcial — `operational_profile` aguarda Decisão F) |
| Início | 2026-11-01 |
| Conclusão | 2026-11-01 |
| Commit | `f781b35` |

**Critérios verificados:**
- [x] Migration retrocompatível — `DEFAULT 'SUPERMARKET'` em `branches.segment`
- [x] `BranchSegment` com constantes SUPERMARKET e RESTAURANT
- [x] `BranchContext` singleton de requisição — id(), branch(), segment()
- [x] `BranchResolver` — três estratégias (sessão, filial única, consolidada)
- [x] `AutenticarWeb` resolve branch após tenant (não-bloqueante)
- [x] Troca de tenant invalida sessão de filial
- [x] `FilialController` — escolher e definir filial no painel web
- [x] Forms branches/create e branches/edit com seletor de segmento
- [x] View `auth/escolher-filial` para seleção múltipla
- [x] 17 novos testes — suíte completa 1927 PASS, 0 FAIL
- [ ] `operational_profile` JSON — aguarda aprovação da Decisão F

---

### MS-03 — Templates e navegação

| Campo | Valor |
|---|---|
| Status | CONCLUÍDO |
| Início | 2026-11-01 |
| Conclusão | 2026-11-01 |
| Commit | `fc6aaa0` |

**Critérios verificados:**
- [x] DashboardController despacha por segmento (RESTAURANT → dashboard.restaurante, demais → dashboard.index)
- [x] dashboard/restaurante.blade.php sem KPIs numéricos, sem zeros interpretáveis como dados reais
- [x] Funcionalidades futuras exibidas como cards "em desenvolvimento" sem links desabilitados
- [x] _nav-supermarket.blade.php: extração literal do nav original — comportamento preservado
- [x] _nav-restaurant.blade.php: itens futuros como div estático (sem href, sem tabindex) — D4 cumprida
- [x] sidebar.blade.php injeta BranchContext, adiciona "trocar/selecionar filial" quando 2+ filiais
- [x] Visão consolidada identificada com banner informativo (modoConsolidado)
- [x] 12 novos testes — DashboardSegmentTest (grupo ms03) — suíte completa 1939 PASS, 0 FAIL

---

### REST-02 — Cardápio

| Campo | Valor |
|---|---|
| Status | EM DESENVOLVIMENTO |
| Bloqueio | nenhum — MS-02 e MS-03 concluídos, decisões R2-A a R2-D aprovadas |
| Estimativa | 5–7 dias |
| Iniciado em | 2026-11-01 |

**Decisões aprovadas:** R2-A (modificadores: template global + override por filial), R2-B (preços: price_override nullable com effectivePrice()), R2-C (disponibilidade: boolean manual persistente por filial), R2-D (imagens: fora do escopo V2).

**Implementado até agora:**
- Migrations: menu_sections, menu_section_items, modifier_groups, modifiers
- Models: MenuSection, MenuSectionItem, ModifierGroup, Modifier, enum ModifierGroupType
- Application: MenuService (resolveModifierGroups, hasBranchOverride, choiceGroupsWithoutAvailableModifiers)
- Policies: MenuSectionPolicy, ModifierGroupPolicy (registradas no AppServiceProvider)
- Form Requests: Store/Update para MenuSection, MenuSectionItem, ModifierGroup, Modifier
- Factories: MenuSectionFactory, MenuSectionItemFactory, ModifierGroupFactory, ModifierFactory
- Controllers Web: CardapioWebController, CardapioItemWebController, ModificadorGrupoWebController, ModificadorWebController
- Views Blade: cardapio/index, create, show, edit; cardapio/itens/create, edit; modificadores/index, create, edit; modificadores/opcoes/create, edit
- Rotas: /cardapio e /produtos/{produto}/modificadores em web.php
- Nav: link de cardápio ativado em _nav-restaurant.blade.php
- Testes: MenuSectionTest, MenuSectionItemTest, ModifierGroupTest, ModifierTest, MenuPriceResolutionTest, MenuAvailabilityTest, MenuTenantIsolationTest

**Pendente:** executar suíte completa de testes → commit → relatório de entrega.

---

### REST-03 — Mesas e comandas

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | MS-02, REST-02 concluídos; decisões G, I aprovadas |
| Estimativa | 10–15 dias |

---

### REST-04 — Pedidos e KDS

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | REST-03 concluído; decisão H aprovada |
| Estimativa | 5–7 dias |

---

### REST-05 — Tipos de produto e fichas técnicas

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | REST-02 concluído; decisões C, D aprovadas |
| Estimativa | 7–10 dias |

---

### REST-01 — Dashboard de restaurante

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | MS-03 + REST-03 + REST-04 (iteração 1); REST-05 (iteração 2) |
| Estimativa | 4–6 dias (2 iterações) |

---

### REST-06 — Integração PDV Java

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | REST-03 e REST-04 estabilizados |
| Estimativa | 10–15 dias |

---

### REST-07 — Homologação

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | REST-01 a REST-06 concluídos |
| Estimativa | 5–7 dias |

---

## Histórico de atualizações

| Data | Fase | Evento |
|---|---|---|
| 2026-11-01 | MS-01 | Diagnóstico inicial concluído. Documentos criados. Commit `e83afab`. |
| 2026-11-01 | MS-01 | Revisão 2: três ressalvas analisadas. Documentos atualizados. Commit `7c7935b`. |
| 2026-11-01 | MS-02 | Implementação concluída. BranchSegment, BranchContext, BranchResolver, FilialController, migration, views, 17 testes. Commit `f781b35`. |
| 2026-11-01 | MS-03 | Implementação concluída. Despacho por segmento, nav por segmento, dashboard/restaurante, visão consolidada, 12 testes. Commit `fc6aaa0`. |
| 2026-11-01 | REST-02 | Implementação iniciada. Decisões R2-A a R2-D aprovadas. Models, migrations, service, policies, requests, factories, controllers, views, rotas, nav e testes criados. Suíte pendente de execução. |

---

## Processo de atualização

1. Iniciar fase → registrar data de início e status EM DESENVOLVIMENTO.
2. Concluir fase → registrar commit, evidências de teste e data.
3. Encontrar bloqueio → registrar impedimento e data.
4. Aprovar decisão → registrar decisão na tabela de decisões abertas (tachado/removido) e no documento de arquitetura.
5. Nenhuma fase marcada CONCLUÍDA sem critérios de aceite verificados.
6. Nenhuma decisão marcada como aprovada sem autorização explícita do responsável de produto.
