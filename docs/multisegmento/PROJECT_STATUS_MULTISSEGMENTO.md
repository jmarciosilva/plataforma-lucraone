# Status do Projeto — LUCRAONE Multissegmento

**Atualizado em:** 2026-11-01 (revisão 2 — três ressalvas analisadas)  
**Fase atual:** MS-01 concluído — aguardando aprovação das decisões arquiteturais antes de MS-02

---

## Resumo executivo

| Indicador | Valor |
|---|---|
| Fases planejadas | 10 |
| Fases concluídas | 1 (MS-01) |
| Em andamento | 0 |
| Bloqueadas | 0 (decisões pendentes, mas nenhuma fase bloqueada por impedimento técnico) |
| Progresso | 10% (1/10 fases com critérios verificados) |
| Próxima fase elegível | MS-02 (aguarda aprovação das decisões A, B, E) |
| Baseline de testes | 1458 (1456 PASS) — main @ `a5d2faa` |
| Última atualização | 2026-11-01 |

---

## Decisões abertas — aguardam aprovação

| ID | Decisão | Recomendação técnica | Bloqueia |
|---|---|---|---|
| A | Granularidade do segmento: Branch (Opção C) | Branch | MS-02 |
| B | BranchContext em sessão PHP (Alternativa B-2) | Sessão com auto-resolve | MS-02 |
| C | `stock_behavior` em products | Campo com DEFAULT RESALE | REST-05 |
| D | Momento do consumo de ingredientes: fechamento da comanda | Fechamento (MVP) | REST-05 |
| E | Segmentos no MVP: SUPERMARKET + RESTAURANT | SUPERMARKET + RESTAURANT | MS-02 |
| F | Perfil operacional em JSON | JSON com schema documentado | MS-02 |
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
| Status | NÃO INICIADO |
| Bloqueio | Decisões A, B, E aprovadas |
| Estimativa | 4–6 dias |

---

### MS-03 — Templates e navegação

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | MS-02 concluído |
| Estimativa | 3–5 dias |

---

### REST-02 — Cardápio

| Campo | Valor |
|---|---|
| Status | NÃO INICIADO |
| Bloqueio | MS-02 concluído |
| Estimativa | 5–7 dias |

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
| 2026-11-01 | MS-01 | Revisão 2: três ressalvas analisadas. BranchContext, consistência funcional, estoque. Documentos atualizados. Commit pendente. |

---

## Processo de atualização

1. Iniciar fase → registrar data de início e status EM DESENVOLVIMENTO.
2. Concluir fase → registrar commit, evidências de teste e data.
3. Encontrar bloqueio → registrar impedimento e data.
4. Aprovar decisão → registrar decisão na tabela de decisões abertas (tachado/removido) e no documento de arquitetura.
5. Nenhuma fase marcada CONCLUÍDA sem critérios de aceite verificados.
6. Nenhuma decisão marcada como aprovada sem autorização explícita do responsável de produto.
