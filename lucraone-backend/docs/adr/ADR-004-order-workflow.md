# ADR-004 — Fluxo de Pedidos e Integração com Estoque

**Status:** Aceito
**Data:** 2026-08-16
**Contexto:** FASE 02 — Sprint F2.3 (Sales & Orders)

---

## Contexto

A F2.3 introduz pedidos de venda em cima de um módulo de estoque (F2.2) que já
controla `quantity_on_hand` e `reserved` por produto e empresa. Precisávamos
decidir **em que ponto do ciclo de vida do pedido o estoque é tocado**.

O roadmap define o fluxo:

```text
draft → pending → confirmed → shipped → completed
```

e lista, entre as features, "inventory reservation on order creation".

Três alternativas foram consideradas:

### Opção A — Reservar na criação do pedido

Leitura literal do roadmap: qualquer pedido criado já reserva estoque.

**Problemas:**
- Um rascunho abandonado segura estoque indefinidamente e impede outra venda.
- Rascunho é justamente o estado em que itens entram e saem; cada alteração de
  item exigiria reconciliar a reserva.

### Opção B — Baixa direta, sem reserva

Confirmar o pedido já dá saída definitiva no saldo físico.

**Problemas:**
- Perde a distinção entre "comprometido" e "vendido".
- Cancelamento vira uma entrada de estoque, poluindo o histórico com
  movimentações que não correspondem a um fato físico.
- Desperdiça `reserved`, que a F2.2 já entregou pronto.

### Opção C — Reservar na confirmação (escolhida)

O estoque só é tocado quando o pedido é confirmado.

---

## Decisão

Os efeitos de estoque são atrelados às **transições de status**, não à criação:

| Transição | Efeito no estoque |
|---|---|
| `draft` → `pending` | nenhum |
| `pending` → `confirmed` | `reservation` por item — sai do disponível, continua em mãos |
| `confirmed` → `shipped` | `release` + `out` por item — a reserva vira baixa definitiva |
| `shipped` → `completed` | nenhum |
| `* ` → `cancelled` (vindo de `confirmed`) | `release` por item — a reserva volta ao disponível |
| `*` → `cancelled` (vindo de `draft`/`pending`) | nenhum |

Regras de apoio:

1. **Itens só mudam em `draft` ou `pending`.** Depois de confirmado a reserva já
   foi calculada contra as quantidades atuais; permitir edição sairia do
   sincronismo. `Order::isEditable()` guarda essa regra.
2. **Transições são explícitas.** `Order::TRANSICOES` é o mapa único de origens e
   destinos válidos; a API e a tela consultam o mesmo mapa, então a tela só
   oferece o que o domínio aceita.
3. **Confirmar exige itens.** Um pedido vazio não pode ser confirmado.
4. **Toda transição é atômica.** `OrderService::changeStatus()` roda em
   transação: se a reserva de qualquer item falhar, o status não muda.

## Consequências

**Positivas:**

- **Não há overselling.** `InventoryAdjustmentService` já recusa reserva maior
  que o saldo disponível, e usa `lockForUpdate`. Dois pedidos disputando o mesmo
  saldo resultam em um confirmado e um recusado com mensagem clara, em vez de
  saldo negativo.
- Rascunhos não bloqueiam estoque.
- O histórico de movimentações registra o número do pedido no campo `reason`,
  então a trilha de auditoria da F2.2 explica por que o saldo mudou.
- API e painel se comportam igual, porque ambos passam pelo `OrderService`.

**Negativas / limites aceitos:**

- O fluxo é sequencial: não dá para pular de `draft` direto para `confirmed`. Se
  o balcão pedir agilidade, a mudança é uma linha em `Order::TRANSICOES`.
- Cancelar um pedido já `shipped` não é permitido — devolução é um fato de
  negócio diferente e ainda não modelado.
- A reserva não expira sozinha. Um pedido confirmado e esquecido segura estoque
  até alguém cancelar. Expiração automática fica para a F2.5 (Automation), que
  já prevê regras baseadas em gatilho.

## Alternativa registrada para o futuro

`branch_id` existe em `orders` mas é sempre nulo: o módulo Branches ainda não tem
tela no painel. Quando ganhar, o pedido passa a poder ser atribuído a uma filial,
e a reserva deverá considerar o estoque daquela filial em vez do estoque da
empresa.
