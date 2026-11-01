# Segmento Restaurantes — Especificação funcional

**Criado:** 2026-11-01  
**Revisado:** 2026-11-01 (revisão 2 — consistência com ROADMAP, fluxo de comanda detalhado)  
**Status:** RASCUNHO REVISADO — documento de referência único para MVP/V2/Futuro

> Este documento é a **definição oficial de escopo** para o segmento restaurante.
> O ROADMAP_MULTISSEGMENTO.md referencia este arquivo. Em caso de divergência,
> este arquivo prevalece.

---

## 1. Fluxo operacional do MVP — sequência obrigatória

```
Chegada do cliente
    ↓
[REST-03] Abrir mesa → criar comanda (mesa ou balcão)
    ↓
[REST-03] Adicionar itens ao longo do atendimento (com modificadores do cardápio)
    ↓
[REST-04] Enviar pedido(s) para a cozinha (pode ser por rodada)
    ↓
[REST-04] KDS — cozinha recebe, prepara, marca como pronto
    ↓
[REST-04] Garçom é notificado; entrega os pratos
    ↓
[REST-03] Fechar comanda → registrar pagamento(s)
    ↓
[REST-05] Baixar ingredientes do estoque (via ficha técnica, no fechamento)
    ↓
[REST-01] Dashboard atualiza: faturamento, comandas abertas, CMV
```

---

## 2. Catálogo de funcionalidades — classificação oficial

### Identificadores de funcionalidade

Cada funcionalidade recebe um ID único para rastreabilidade entre documentos.

---

### REST-02: Cardápio

| ID | Funcionalidade | Classe | Fase | Dependência | Impacto PDV |
|---|---|---|---|---|---|
| CAR-01 | Categorias do cardápio | MVP | REST-02 | MS-02 | Estende catálogo PDV |
| CAR-02 | Pratos e bebidas | MVP | REST-02 | MS-02 | Estende catálogo PDV |
| CAR-03 | Preços por apresentação | MVP | REST-02 | MS-02 | Estende catálogo PDV |
| CAR-04 | Disponibilidade diária do item (flag `is_available`) | MVP | REST-02 | CAR-02 | PDV precisa ler flag |
| CAR-05 | Tempo estimado de preparo (`preparation_time_minutes`) | MVP | REST-02 | CAR-02 | Não |
| CAR-06 | Grupos de modificadores ("Ponto da carne", "Adicionais") | MVP | REST-02 | CAR-02 | PDV precisa ler grupos |
| CAR-07 | Opções de modificador ("Mal passado", "Queijo extra", preço adicional) | MVP | REST-02 | CAR-06 | PDV precisa ler opções |
| CAR-08 | Combos (produto composto com preço especial) | V2 | pós-REST-02 | CAR-02 | V2 |
| CAR-09 | Fotos dos pratos | V2 | pós-REST-02 | CAR-02 | Não |
| CAR-10 | Cardápio digital / QR code | Futuro | — | — | Não |

**Justificativa das exclusões do MVP:**
- CAR-08 (combos): exige lógica de preço composto e baixa de múltiplos produtos; complexidade não justificada para v1.
- CAR-09 (fotos): requer gestão de assets/imagens; infraestrutura fora do escopo.

---

### REST-03: Mesas e comandas

| ID | Funcionalidade | Classe | Fase | Dependência | Impacto PDV |
|---|---|---|---|---|---|
| MES-01 | Cadastro de mesa (número, capacidade, setor) | MVP | REST-03 | MS-02 | Não |
| MES-02 | Status de mesa: DISPONÍVEL, OCUPADA, RESERVADA, MANUTENÇÃO | MVP | REST-03 | MES-01 | Não |
| MES-03 | Associação de garçom responsável pela mesa | MVP | REST-03 | MES-01 | Não |
| MES-04 | Mapa visual de mesas (drag-and-drop) | Futuro | — | — | Não |
| MES-05 | Reservas de mesa com agenda | Futuro | — | — | Não |
| COM-01 | Abertura de comanda vinculada a mesa | MVP | REST-03 | MES-01, CAR-02 | PDV abre comanda |
| COM-02 | Abertura de comanda de balcão (sem mesa) | MVP | REST-03 | CAR-02 | PDV abre comanda |
| COM-03 | Adição de item à comanda com modificadores | MVP | REST-03 | CAR-06, CAR-07 | PDV adiciona item |
| COM-04 | Remoção de item com registro de auditoria | MVP | REST-03 | COM-03 | PDV remove item |
| COM-05 | Cancelamento de item (auditoria + motivo) | MVP | REST-03 | COM-03 | PDV cancela item |
| COM-06 | Transferência de comanda para outra mesa | MVP | REST-03 | COM-01, MES-01 | V2 no PDV |
| COM-07 | Transferência de itens entre comandas | V2 | pós-REST-03 | COM-03 | Não |
| COM-08 | Divisão de conta em N partes iguais | MVP | REST-03 | COM-01 | Não |
| COM-09 | Divisão de conta por valores manuais | V2 | pós-REST-03 | COM-08 | Não |
| COM-10 | Divisão de conta por item (cada item vai para uma conta) | V2 | pós-REST-03 | COM-08 | Não |
| COM-11 | Encerramento da comanda com forma de pagamento única | MVP | REST-03 | COM-01 | PDV fecha comanda |
| COM-12 | Múltiplas formas de pagamento na mesma comanda | MVP | REST-03 | COM-11 | PDV registra |
| COM-13 | Pagamento parcial (sinal antecipado, parte da conta) | V2 | pós-REST-03 | COM-11 | Não |
| COM-14 | Histórico de alterações da comanda (auditoria) | MVP | REST-03 | COM-03 | Não |
| COM-15 | Cancelamento de comanda inteira com motivo | MVP | REST-03 | COM-01 | PDV cancela |
| COM-16 | Estorno de pagamento | V2 | pós-REST-03 | COM-11 | Não |

**Justificativa das classificações:**

- COM-07 (transferência de itens): complexidade de auditoria entre comandas distintas não é obrigatória para operar. Garçom pode reabrir nova comanda.
- COM-09, COM-10 (divisão manual/por item): partes iguais cobrem 80% dos casos no MVP.
- COM-13 (pagamento parcial): edge case que complica o ciclo de fechamento.
- COM-12 (múltiplas formas de pagamento): incluída no MVP porque a estrutura `pdv_received_sale_payment_entries` já existe no backend; é uma necessidade operacional real.

---

### REST-04: Pedidos e produção (KDS)

| ID | Funcionalidade | Classe | Fase | Dependência | Impacto PDV |
|---|---|---|---|---|---|
| KDS-01 | Envio de itens da comanda para a cozinha | MVP | REST-04 | COM-03 | PDV envia para cozinha |
| KDS-02 | Painel KDS (tela dedicada, sem sidebar) | MVP | REST-04 | KDS-01 | Tablet de cozinha |
| KDS-03 | KDS: marcar item como EM PREPARO | MVP | REST-04 | KDS-01 | Sim |
| KDS-04 | KDS: marcar item como PRONTO | MVP | REST-04 | KDS-03 | Sim |
| KDS-05 | KDS: marcar como ENTREGUE | MVP | REST-04 | KDS-04 | Não |
| KDS-06 | Notificação ao garçom quando prato pronto | MVP | REST-04 | KDS-04 | Não |
| KDS-07 | Atualização automática do KDS (polling ≤ 10s) | MVP | REST-04 | KDS-01 | Não |
| KDS-08 | Tempo médio de preparo por prato | V2 | pós-REST-04 | KDS-03, KDS-04 | Não |
| KDS-09 | KDS em tempo real (SSE/WebSocket) | V2 | pós-REST-04 | KDS-01 | Não |
| KDS-10 | Múltiplas estações de cozinha (frios/quentes/sobremesas) | V2 | pós-REST-04 | KDS-01 | Não |
| KDS-11 | Relatório de desempenho da cozinha | Futuro | — | — | Não |

**Justificativa de KDS-08 como V2:**
O tempo médio exige dados de `started_at` e `ready_at` para cálculo estatístico. A infraestrutura (timestamps no `kitchen_orders`) é criada no MVP, mas o relatório de desempenho aguarda dados reais para validar utilidade.

---

### REST-05: Fichas técnicas e custo de produção

| ID | Funcionalidade | Classe | Fase | Dependência | Impacto PDV |
|---|---|---|---|---|---|
| FTC-01 | Produtos tipo RAW_MATERIAL (ingredientes) | MVP | REST-05 | CAR-02 | Não |
| FTC-02 | Produtos tipo PRODUCED (prato preparado) | MVP | REST-05 | CAR-02 | Baixa via ficha |
| FTC-03 | Produtos tipo RESALE (revenda direta, ex.: refrigerante) | MVP | REST-05 | CAR-02 | Baixa direta |
| FTC-04 | Campo `stock_behavior` em products | MVP | REST-05 | — | Ajusta `AplicarVendaNoEstoque` |
| FTC-05 | Ficha técnica: prato → ingredientes + quantidades | MVP | REST-05 | FTC-01, FTC-02 | Não |
| FTC-06 | Fator de perda (waste_factor) na ficha técnica | MVP | REST-05 | FTC-05 | Não |
| FTC-07 | Custo calculado do prato (soma de ingredientes × custo) | MVP | REST-05 | FTC-05 | Não |
| FTC-08 | Baixa de ingredientes ao fechar comanda | MVP | REST-05 | FTC-05, COM-11 | Via reconciliação |
| FTC-09 | Snapshot da ficha técnica usada no consumo | MVP | REST-05 | FTC-08 | Não |
| FTC-10 | Idempotência do consumo (`ingredients_applied_at`) | MVP | REST-05 | FTC-08 | Essencial |
| FTC-11 | CMV do dia no dashboard | MVP | REST-01 | FTC-08 | Não |
| FTC-12 | Descarte de ingredientes (waste manual) | V2 | pós-REST-05 | FTC-01 | Não |
| FTC-13 | Fichas técnicas de modificadores (custo do adicional) | V2 | pós-REST-05 | FTC-05, CAR-07 | Não |
| FTC-14 | Custo por receita com múltiplos subprodutos (ex.: molho caseiro) | Futuro | — | — | Não |

---

### REST-01: Dashboard de restaurante

| ID | Funcionalidade | Classe | Fase | Dependência | Notas |
|---|---|---|---|---|---|
| DSH-01 | Faturamento do turno / do dia | MVP | REST-01 | REST-03 (dados de comanda) | |
| DSH-02 | Comandas abertas (contagem) | MVP | REST-01 | REST-03 | |
| DSH-03 | Mesas ocupadas vs. disponíveis | MVP | REST-01 | REST-03 | |
| DSH-04 | Ticket médio do dia | MVP | REST-01 | REST-03 | |
| DSH-05 | Pedidos em preparo (contagem no KDS) | MVP | REST-01 | REST-04 | |
| DSH-06 | CMV do dia | MVP | REST-01 | REST-05 | Pode vir em iteração de REST-01 pós-REST-05 |
| DSH-07 | Margem estimada do dia | MVP | REST-01 | REST-05 | Idem |
| DSH-08 | Tempo médio de atendimento total | V2 | pós-REST-01 | REST-04 dados históricos | |
| DSH-09 | Pratos mais vendidos do dia | V2 | pós-REST-01 | REST-03 | |
| DSH-10 | Lucratividade por prato | Futuro | — | — | |

**Observação importante:** DSH-01 a DSH-05 podem ser entregues logo após REST-03/REST-04. DSH-06 e DSH-07 aguardam REST-05. O dashboard é entregue em duas iterações.

---

### REST-06: Integração PDV Java

| ID | Funcionalidade | Classe | Fase | Dependência | Notas |
|---|---|---|---|---|---|
| PDV-R-01 | Endpoint: abrir comanda via terminal | MVP | REST-06 | REST-03 estável | Novo endpoint API |
| PDV-R-02 | Endpoint: adicionar item com modificadores via terminal | MVP | REST-06 | REST-03, REST-02 | Novo endpoint API |
| PDV-R-03 | Endpoint: enviar pedido para cozinha via terminal | MVP | REST-06 | REST-04 estável | Novo endpoint API |
| PDV-R-04 | Endpoint: fechar comanda e registrar pagamento via terminal | MVP | REST-06 | REST-03, REST-05 | Novo endpoint API |
| PDV-R-05 | Offline-first: comanda gravada localmente no PDV | MVP | REST-06 | PDV Java | Escopo do PDV Java |
| PDV-R-06 | Sincronização idempotente (reenvio, dedup) | MVP | REST-06 | PDV-R-01..04 | Padrão existente |
| PDV-R-07 | Consulta de status de mesas via PDV | V2 | pós-REST-06 | PDV-R-01 | |
| PDV-R-08 | KDS em tablet do PDV Java | V2 | pós-REST-06 | REST-04 | |

---

## 3. Dependências de dados entre módulos

```
CAR-02 (produtos) ──→ COM-03 (itens na comanda)
                  ──→ FTC-05 (ficha técnica)
CAR-06 (modificadores) ──→ COM-03

MES-01 (mesas) ──→ COM-01 (comanda de mesa)

COM-01 ──→ COM-03 ──→ KDS-01 ──→ KDS-03 ──→ KDS-04 ──→ KDS-06
COM-11 (fechamento) ──→ FTC-08 (baixa ingredientes)
                    ──→ DSH-01 (faturamento)

FTC-04 (`stock_behavior`) ──→ `AplicarVendaNoEstoque` (evita dupla baixa)
FTC-10 (`ingredients_applied_at`) ──→ idempotência do consumo
```

---

## 4. O que NÃO entra no MVP do restaurante

| Funcionalidade | Motivo da exclusão |
|---|---|
| Reservas com agenda | Canal de entrada distinto, não operação de salão |
| Mapa visual de mesas | UX complementar, não operacional |
| Cardápio digital / QR code | Canal externo (app ou web público) fora do escopo |
| Transferência de itens entre comandas | Complexidade de auditoria; reabrir comanda resolve |
| Divisão por item / valores manuais | Partes iguais cobrem 80% dos casos |
| Pagamento parcial antecipado | Edge case que complica fechamento |
| CMV em tempo real (antes de fechar) | Dado só existe após REST-05 |
| Múltiplas estações de cozinha | Configuração que exige modelagem adicional |
| SSE/WebSocket no KDS | Polling 10s suficiente para MVP |
| Relatório de desempenho da cozinha | Requer dados históricos acumulados |

---

## 5. Modelo de dados do restaurante (visão consolidada)

```
Branch (segment = RESTAURANT)
  │
  ├── restaurant_tables        (mesas por filial)
  │     └── restaurant_tabs    (comandas abertas)
  │           ├── restaurant_tab_items         (itens + modificadores)
  │           ├── restaurant_tab_payments      (formas de pagamento)
  │           └── restaurant_tab_audit_log     (auditoria de alterações)
  │
  ├── kitchen_orders           (fila da cozinha, gerada a partir de tab_items)
  │
  ├── products (stock_behavior = PRODUCED | RESALE)
  │     ├── menu_modifier_groups   (grupos: "Ponto", "Adicionais")
  │     │     └── menu_modifiers   (opções com preço extra)
  │     └── recipe_ingredients     (ficha técnica: prato → ingredientes)
  │
  ├── products (stock_behavior = RAW_MATERIAL)
  │     └── inventories            (estoque dos ingredientes)
  │
  └── restaurant_tab_ingredient_consumptions  (snapshot do consumo ao fechar)
```

---

## 6. Permissões novas previstas

| Permissão | Descrição |
|---|---|
| `manage-tables` | Cadastrar e editar mesas |
| `view-tables` | Ver mesas e status |
| `manage-tabs` | Abrir, editar e fechar comandas |
| `view-tabs` | Consultar comandas |
| `manage-menu` | Gerenciar cardápio e modificadores |
| `view-menu` | Consultar cardápio |
| `operate-kitchen` | Atualizar status na cozinha (KDS) |
| `manage-recipes` | Gerenciar fichas técnicas |

Essas permissões são aditivas às existentes. Não alteram a `StandardRoleMatrix` atual.
Precisarão ser provisionadas na criação de estabelecimento de segmento RESTAURANT.
