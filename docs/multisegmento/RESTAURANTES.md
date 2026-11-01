# Segmento Restaurantes — Especificação funcional

**Criado:** 2026-11-01  
**Status:** RASCUNHO — complementa o ROADMAP_MULTISSEGMENTO.md

---

## 1. Visão do segmento

Um restaurante no LucraOne é uma filial (`branch`) com `segment = RESTAURANT`.
A mesma estrutura de tenant → empresa → filial é mantida. O segmento não cria
um sistema paralelo — ativa funcionalidades e templates específicos dentro da
plataforma existente.

---

## 2. Funcionalidades por fase e classificação de MVP

### Legenda

- **MVP** — necessário para o primeiro lançamento funcional.
- **V2** — útil, mas pode aguardar uma segunda iteração.
- **Futuro** — fora do escopo deste roadmap.

---

### REST-02 — Cardápio

| Funcionalidade | MVP/V2/Futuro | Observação |
|---|---|---|
| Categorias do cardápio | MVP | Reusa módulo Categories existente |
| Pratos e bebidas | MVP | Reusa módulo Products existente |
| Preços por apresentação | MVP | Reusa módulo Prices existente |
| Disponibilidade de item (flag do dia) | MVP | Campo novo em `products` |
| Tempo estimado de preparo | MVP | Campo novo em `products` |
| Grupos de modificadores | MVP | Tabela nova `menu_modifier_groups` |
| Opções de modificadores ("sem cebola") | MVP | Tabela nova `menu_modifiers` |
| Combos | V2 | Produto composto — exige lógica de preço |
| Fotos dos pratos | V2 | Armazenamento de assets |
| QR code do cardápio digital | Futuro | Canal de pedido externo |

---

### REST-03 — Mesas e comandas

| Funcionalidade | MVP/V2/Futuro | Observação |
|---|---|---|
| Cadastro de mesas (nº, capacidade, setor) | MVP | |
| Status de mesa (disponível/ocupada/reservada/manutenção) | MVP | |
| Abertura de comanda por mesa | MVP | |
| Abertura de comanda de balcão (sem mesa) | MVP | |
| Adição de item com modificadores | MVP | |
| Remoção de item (com auditoria) | MVP | |
| Encerramento da comanda com pagamento | MVP | |
| Transferência de comanda para outra mesa | MVP | |
| Divisão de conta (partes iguais) | MVP | |
| Divisão de conta (valores manuais) | V2 | |
| Divisão de conta (por item) | V2 | |
| Transferência de itens entre comandas | V2 | |
| Associação de garçom à mesa | MVP | |
| Histórico de alterações (auditoria) | MVP | |
| Reservas de mesa | Futuro | |

---

### REST-04 — Pedidos e produção

| Funcionalidade | MVP/V2/Futuro | Observação |
|---|---|---|
| Envio de pedido da comanda para cozinha | MVP | |
| Painel KDS (status de preparo) | MVP | Tela dedicada, sem sidebar |
| KDS: marcar como "em preparo" | MVP | |
| KDS: marcar como "pronto" | MVP | |
| KDS: marcar como "entregue" | MVP | |
| Notificação ao garçom quando pronto | MVP | Reusa notifications |
| Polling automático do KDS | MVP | Intervalo de 10s |
| SSE / WebSocket no KDS | V2 | Latência menor |
| Múltiplas estações de cozinha | V2 | Frios / quentes / sobremesas |
| Tempo médio de preparo | V2 | Calculado a partir de timestamps |
| Relatório de desempenho da cozinha | Futuro | |

---

### REST-05 — Fichas técnicas e custo

| Funcionalidade | MVP/V2/Futuro | Observação |
|---|---|---|
| Ingredientes como produtos (matéria-prima) | MVP | Flag em `products` |
| Ficha técnica: ingrediente + quantidade | MVP | Tabela `recipe_ingredients` |
| Cálculo de custo do prato | MVP | Com base em `prices` dos ingredientes |
| Fator de perda (waste_factor) | MVP | Coluna em `recipe_ingredients` |
| Baixa de ingredientes ao fechar comanda | MVP | Reusa `InventoryService` |
| CMV do dia no dashboard | MVP | Calculado na REST-01 |
| Controle de desperdícios | V2 | Registro de descarte |
| Fichas técnicas de modificadores | V2 | Custo do adicional |

---

### REST-06 — Integração PDV Java

| Funcionalidade | MVP/V2/Futuro | Observação |
|---|---|---|
| Abrir comanda via terminal PDV | MVP | |
| Adicionar item com modificadores via PDV | MVP | |
| Enviar pedido para cozinha via PDV | MVP | |
| Fechar comanda e registrar pagamento via PDV | MVP | |
| Operação offline (comanda local) | MVP | Padrão já estabelecido pelo PDV |
| Sincronização confiável (idempotência) | MVP | Padrão já estabelecido |
| Consulta de status de mesas via PDV | V2 | |
| KDS via PDV (tablet de cozinha) | V2 | |

---

## 3. Modelo de dados do restaurante (visão consolidada)

```
Branch (segment = RESTAURANT)
  ├── restaurant_tables            (mesas)
  │     └── restaurant_tabs       (comandas abertas)
  │           ├── restaurant_tab_items       (itens com modificadores)
  │           ├── restaurant_tab_payments    (pagamentos)
  │           └── restaurant_tab_audit_log   (auditoria)
  │
  ├── kitchen_orders               (pedidos na fila da cozinha)
  │
  ├── products                     (pratos e bebidas — tabela compartilhada)
  │     ├── menu_modifier_groups   (grupos: "Ponto", "Adicionais")
  │     │     └── menu_modifiers   (opções: "Mal passado", "Queijo extra")
  │     └── recipe_ingredients     (ficha técnica: prato → ingredientes)
  │
  └── products                     (ingredientes — mesma tabela, flag matéria-prima)
        └── inventories            (estoque de ingredientes — tabela compartilhada)
```

---

## 4. Permissões novas previstas para o segmento restaurante

As permissões seguem o padrão do `AdminPermissionMatrix` existente:

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

Essas permissões não conflitam com as existentes. São aditivas.

---

## 5. Fluxo operacional do MVP

```
Chegada do cliente
    ↓
[Painel / PDV] Abrir mesa → criar comanda
    ↓
[Painel / PDV] Adicionar itens com modificadores ao longo do atendimento
    ↓
[Painel / PDV] Enviar pedido para cozinha
    ↓
[KDS — cozinha] Receber pedido → marcar "em preparo" → marcar "pronto"
    ↓
[Painel] Notificação ao garçom
    ↓
[Painel / PDV] Fechar comanda → selecionar forma de pagamento
    ↓
[Retaguarda] Registrar venda → baixar ingredientes do estoque
    ↓
[Dashboard] Atualizar KPIs (faturamento, comandas, ticket médio, CMV)
```

---

## 6. Decisões de design abertas

| # | Questão | Alternativas | Status |
|---|---|---|---|
| 1 | Comanda é entidade independente de `orders`? | Entidade nova vs. extensão de `orders` | Recomendo entidade nova — ciclo de vida é diferente. AGUARDA APROVAÇÃO |
| 2 | KDS: polling vs. SSE no MVP | Polling 10s (simples) vs. SSE (instantâneo) | Recomendo polling no MVP. AGUARDA APROVAÇÃO |
| 3 | Cardápio restaurante vs. produtos genéricos | Mesma tabela `products` com campos extras vs. tabela `menu_items` separada | Recomendo manter `products` com campos opcionais — retrocompatível. AGUARDA APROVAÇÃO |
| 4 | Baixa de ingredientes: síncrona ou assíncrona? | Síncrona no fechamento vs. fila | Síncrona no MVP — fila quando volume exigir. AGUARDA APROVAÇÃO |
