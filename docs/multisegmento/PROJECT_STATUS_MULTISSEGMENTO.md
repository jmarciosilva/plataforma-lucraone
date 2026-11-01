# Status do Projeto — LUCRAONE Multissegmento

**Atualizado em:** 2026-11-01  
**Responsável:** a registrar  
**Fase atual:** MS-01 concluído — aguardando aprovação da arquitetura para iniciar MS-02

---

## Resumo executivo

| Indicador | Valor |
|---|---|
| Fases planejadas | 10 |
| Fases concluídas | 1 (MS-01) |
| Fases em andamento | 0 |
| Fases bloqueadas | 0 |
| Percentual de progresso | 10% (1/10 planejadas) |
| Próxima fase elegível | MS-02 (aguarda aprovação arquitetural) |
| Última atualização | 2026-11-01 |

> O percentual de progresso conta fases concluídas com critérios de aceite
> verificados sobre o total de fases planejadas. Não é uma estimativa de esforço.

---

## Estado detalhado por fase

### MS-01 — Diagnóstico e arquitetura multissegmento

| Campo | Valor |
|---|---|
| Status | CONCLUÍDO |
| Início | 2026-11-01 |
| Conclusão | 2026-11-01 |
| Commit | a registrar após versionamento |
| Entregáveis | ARQUITETURA_MULTISSEGMENTO.md, ROADMAP_MULTISSEGMENTO.md, PROJECT_STATUS_MULTISSEGMENTO.md, RESTAURANTES.md |

**Critérios de aceite:**
- [x] Inventário de módulos existentes documentado
- [x] Decisão arquitetural proposta e justificada (Opção C — Branch)
- [x] Roadmap elaborado com fases e dependências
- [x] Documentos criados
- [ ] Aprovação da decisão arquitetural pelo responsável de produto ← **PENDENTE**

**Pendências:**
- Aprovação da decisão arquitetural (segmento na Branch vs. outras opções).

---

### MS-02 — Segmento e perfil operacional

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | Aprovação de MS-01 |
| Início | — |
| Conclusão | — |

---

### MS-03 — Templates de dashboard e navegação

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | MS-02 concluído |

---

### REST-01 — Dashboard de restaurante

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | MS-03 + REST-03 + REST-04 (parcial) |

---

### REST-02 — Cardápio

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | MS-02 concluído |

---

### REST-03 — Mesas e comandas

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | MS-02 + REST-02 concluídos |

---

### REST-04 — Pedidos e produção (KDS)

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | REST-03 concluído |

---

### REST-05 — Fichas técnicas e custo

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | REST-02 concluído |

---

### REST-06 — Integração PDV Java

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | REST-03 + REST-04 estabilizados |

---

### REST-07 — Testes integrados e homologação

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | REST-01 a REST-06 concluídos |

---

## Histórico de atualizações

| Data | Fase | Atualização |
|---|---|---|
| 2026-11-01 | MS-01 | Diagnóstico concluído. Documentos criados. Aguarda aprovação arquitetural. |

---

## Decisões abertas

| # | Decisão | Alternativas | Recomendação técnica | Status |
|---|---|---|---|---|
| 1 | Granularidade do segmento | Tenant / Company / Branch | Branch (Opção C) | AGUARDA APROVAÇÃO |
| 2 | Segmentos no MVP | SUPERMARKET + RESTAURANT | Manter mínimo no início | AGUARDA APROVAÇÃO |
| 3 | Perfil operacional em JSON vs. colunas | JSON (flexível) vs. colunas (tipado) | JSON com schema documentado | AGUARDA APROVAÇÃO |
| 4 | KDS: polling vs. SSE vs. WebSocket | Polling (simples) / SSE / WS | Polling 10s no MVP | AGUARDA REST-04 |
| 5 | Comanda offline no PDV Java | Salvar local → sync | Mesmo padrão das vendas | AGUARDA REST-06 |

---

## Baseline de testes atual (supermercado)

Antes de iniciar qualquer fase, o baseline deve ser verificado:

- **Testes na main em 2026-11-01:** `a5d2faa` — baseline do ROADMAP.md: 1458 testes,
  1456 PASS, 0 FAIL, 0 ERROR.
- **Critério de regressão:** nenhuma fase pode ser marcada como CONCLUÍDA se
  qualquer teste que estava passando antes da fase deixar de passar.

---

## Processo de atualização deste documento

Este documento deve ser atualizado:
1. Ao iniciar uma fase → registrar data de início e mudança de status.
2. Ao concluir uma fase → registrar commit, evidências de teste e data.
3. Ao encontrar bloqueio → registrar impedimento e data.
4. Ao aprovar uma decisão em aberto → registrar decisão e remover da lista.
