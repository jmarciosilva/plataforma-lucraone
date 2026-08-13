# Projeto — Plataforma SaaS Inteligente de Automação Comercial

**Versão:** 0.2 — Documento de Visão e Arquitetura  
**Status:** Planejamento  
**Stack principal:** PHP / Laravel + MySQL + Redis + .NET/C# + SQLite  
**Modelo:** SaaS Multi-Tenant + PDV Desktop Offline-First

---

# 1. Visão do Projeto

O objetivo é desenvolver uma **plataforma SaaS de automação comercial inteligente**, capaz de atender diferentes segmentos do comércio brasileiro, inicialmente:

- supermercados;
- minimercados;
- lojas;
- sapatarias;
- restaurantes;
- lanchonetes;
- cafeterias;
- bares;
- padarias.

A plataforma não deverá ser apenas um sistema de PDV.

A visão é criar um ecossistema que conecte:

```text
Cliente
   ↓
Catálogo / Cardápio
   ↓
Produto
   ↓
Pedido
   ↓
PDV
   ↓
Pagamento
   ↓
Fiscal
   ↓
Estoque
   ↓
Financeiro
   ↓
Customer Intelligence
   ↓
Insights e Recomendações
```

O principal diferencial de longo prazo será transformar os dados operacionais e comportamentais gerados pelo estabelecimento em **inteligência capaz de aumentar vendas, melhorar margens e reduzir desperdícios**.

---

# 2. Arquitetura Tecnológica

## 2.1 Plataforma SaaS

```text
PHP
Laravel
MySQL
Redis
Laravel Queues
REST API
WebSockets quando necessário
Docker
```

O **MySQL será o banco de dados relacional central da plataforma**, armazenando dados como:

- tenants;
- empresas;
- filiais;
- usuários;
- produtos;
- categorias;
- variações;
- preços;
- estoque;
- fornecedores;
- clientes;
- vendas;
- pagamentos;
- documentos fiscais;
- configurações;
- eventos de Customer Intelligence.

---

## 2.2 PDV Desktop

```text
.NET / C#
Windows
SQLite
Arquitetura Offline-First
Outbox Pattern
Sincronização assíncrona
```

O SQLite será utilizado exclusivamente como banco operacional local do PDV.

```text
                 NUVEM

        Laravel + MySQL + Redis
                  │
                  │ HTTPS / REST
                  │
        ┌─────────▼─────────┐
        │     PDV .NET      │
        │        C#         │
        │                   │
        │      SQLite       │
        └─────────┬─────────┘
                  │
       Hardware / TEF / POS
```

O **MySQL não deverá fazer parte do caminho crítico da venda**.

A leitura de produtos, construção do carrinho e persistência inicial da venda deverão acontecer localmente no PDV.

---

# 3. Estratégia Multi-Tenant com MySQL

A plataforma deverá nascer preparada para atender múltiplos clientes:

```text
Plataforma
│
├── Empresa A
│   ├── Matriz
│   └── Filial
│
├── Empresa B
│   ├── Loja 01
│   ├── Loja 02
│   └── Loja 03
│
└── Empresa C
    └── Restaurante
```

Inicialmente poderá ser adotada a estratégia:

```text
Database
   ↓
MySQL

products
sales
customers
inventory_movements
...

        ↓

tenant_id
```

Exemplo:

```text
products

id
tenant_id
name
sku
...
```

Todas as consultas deverão respeitar obrigatoriamente o tenant.

No Laravel, essa separação deverá ser reforçada arquiteturalmente através de mecanismos como:

```text
Tenant Context
Global Scopes
Policies
Middleware
Jobs conscientes do Tenant
Testes automatizados de isolamento
```

O isolamento entre tenants será considerado um requisito de segurança, e não apenas uma convenção de programação.

---

# 4. Product Core

O cadastro de produtos será uma das principais fundações do ERP.

```text
PRODUCT CORE
│
├── Identidade Comercial
├── Classificação
├── Variações
├── Preços
├── Estoque
├── Fiscal
├── Composição
└── Inteligência
```

Tipos inicialmente previstos:

```text
SIMPLE
VARIANT
WEIGHT
COMPOSITE
KIT
SERVICE
```

Permitindo atender:

```text
Supermercado
→ milhares de SKUs
→ GTIN
→ peso
→ balança

Sapataria
→ produto
→ cor
→ tamanho
→ grade
→ SKU por variação

Restaurante
→ prato
→ receita
→ ingredientes
→ ficha técnica
```

---

# 5. Estrutura Conceitual MySQL

## products

```text
id
tenant_id
name
description
sku
category_id
brand_id
product_type
commercial_unit_id
controls_inventory
active
created_at
updated_at
```

## product_variants

```text
id
product_id
sku
gtin
attributes
price_override
active
```

O campo `attributes` poderá utilizar o tipo `JSON` do MySQL para informações flexíveis de variação.

Exemplo:

```json
{
    "color": "Preto",
    "size": "42"
}
```

---

## product_fiscal_classifications

```text
id
product_id
ncm
cest
origin
taxable_unit
valid_from
valid_until
created_at
updated_at
```

---

## product_components

```text
id
product_id
component_product_id
quantity
unit
loss_percentage
```

---

## product_prices

```text
id
product_id
branch_id
price_table_id
price
valid_from
valid_until
```

---

# 6. Estoque

O estoque deverá trabalhar prioritariamente através de movimentações.

```text
inventory_movements

id
tenant_id
branch_id
product_id
variant_id
movement_type
quantity
reference_type
reference_id
created_at
```

Tipos:

```text
PURCHASE
SALE
RETURN
TRANSFER
ADJUSTMENT
PRODUCTION
LOSS
```

Exemplo:

```text
Compra          +100
Venda             -5
Perda             -2
Transferência    -10
```

Isso permitirá rastreabilidade e auditoria.

---

# 7. Fiscal

A classificação fiscal permanecerá separada das regras tributárias.

```text
Produto
   ↓
Classificação Fiscal
   ↓
Venda
   ↓
Contexto Tributário
   ↓
Motor Fiscal
   ↓
Documento Fiscal
   ↓
NFC-e / NF-e
   ↓
SEFAZ
```

O backend Laravel continuará sendo responsável pela centralização fiscal.

O MySQL armazenará:

```text
fiscal_documents
fiscal_document_items
fiscal_events
fiscal_rejections
fiscal_certificates
fiscal_sequences
```

O XML autorizado deverá possuir armazenamento seguro apropriado, enquanto o banco mantém suas referências e metadados.

---

# 8. PDV e Sincronização

O PDV utilizará SQLite:

```text
PDV
│
├── products
├── variants
├── prices
├── barcodes
├── customers_cache
├── sales
├── sale_items
├── payments
├── outbox
└── sync_state
```

Fluxo:

```text
VENDA

.NET/C#
   ↓
SQLite
   ↓
Venda concluída localmente
   ↓
Outbox
   ↓
Sync Worker
   ↓
Laravel API
   ↓
MySQL
```

Cada evento deverá possuir:

```text
event_id
device_id
tenant_id
branch_id
sequence
event_type
payload
created_at
sync_status
```

A API Laravel deverá utilizar **idempotência** para impedir duplicidade.

---

# 9. Redis

MySQL será responsável pela persistência principal.

Redis terá responsabilidades diferentes:

```text
Redis
│
├── Cache
├── Queues
├── Locks
├── Rate Limiting
├── Sessions quando necessário
└── processamento assíncrono
```

Exemplo:

```text
PDV
 ↓
Laravel API
 ↓
MySQL
 ↓
Queue
 ↓
Redis
 ↓
Worker
 ↓
Processamento secundário
```

Isso evita transformar requisições importantes em operações excessivamente demoradas.

---

# 10. Customer Intelligence

Os eventos poderão inicialmente ser armazenados utilizando a própria infraestrutura Laravel + MySQL.

Exemplo:

```text
customer_events

id
tenant_id
branch_id
session_id
device_id
customer_id
channel
event_type
occurred_at
metadata JSON
```

Eventos:

```text
menu_viewed
product_viewed
product_searched
product_added
product_removed
order_started
order_abandoned
order_completed
sale_completed
```

O campo `metadata` poderá utilizar JSON para dados específicos de cada evento.

Entretanto, a arquitetura deverá manter o **Event Collector desacoplado do armazenamento definitivo**.

Assim, futuramente:

```text
Event Collector
       │
       ├── MySQL
       │
       └── Data Platform
               ↓
          Analytics
```

Se o volume de eventos crescer muito, será possível migrar a camada analítica para uma tecnologia especializada sem reconstruir o Product Core, PDV ou ERP.

---

# 11. Arquitetura Consolidada

```text
                         CLIENTE
                            │
                     NFC / QR Code
                            │
                    Cardápio Digital
                            │
                          Pedido
                            │
                            ▼
               ┌────────────────────────┐
               │      Laravel SaaS      │
               │                        │
               │ PHP                    │
               │ MySQL                  │
               │ Redis                  │
               │ Queues                 │
               │ REST API               │
               └────────────┬───────────┘
                            │
                       HTTPS / REST
                            │
               ┌────────────▼───────────┐
               │        PDV .NET        │
               │                        │
               │ C#                     │
               │ SQLite                 │
               │ Offline-First          │
               │ Outbox                 │
               │ Sync Engine            │
               └────────────┬───────────┘
                            │
             ┌──────────────┼──────────────┐
             │              │              │
         Impressora       Balança         TEF
             │
           Gaveta
             │
           Leitor

                    DADOS OPERACIONAIS
                            │
                            ▼
                  Customer Intelligence
                            │
                ┌───────────┼───────────┐
                │           │           │
            Analytics   Recomendação  Previsão
                │           │           │
                └───────────┼───────────┘
                            ↓
                      INSIGHTS / IA
```

---

# 12. Stack Final Proposta

| Camada | Tecnologia |
|---|---|
| Backend | PHP + Laravel |
| Banco SaaS | **MySQL** |
| Cache / filas | Redis |
| API | REST/HTTPS |
| PDV | .NET / C# |
| Banco PDV | SQLite |
| Fiscal | Laravel + NFePHP/provedor |
| Desktop | Windows |
| Infraestrutura | Docker |
| Analytics inicial | Laravel + MySQL |
| Customer Intelligence | Event-driven |
| IA | Etapa posterior |

---

# 13. Princípio Final

A arquitetura passa a ter três tecnologias de armazenamento com responsabilidades bastante claras:

```text
MySQL
→ verdade central do ERP

SQLite
→ operação local e offline do PDV

Redis
→ velocidade, filas, cache e coordenação
```

Isso mantém a arquitetura simples o suficiente para uma primeira versão desenvolvida por uma equipe pequena, sem impedir crescimento futuro.

> **Não adicionar complexidade de infraestrutura antes que o volume do negócio justifique essa complexidade.**

A plataforma deverá começar com **Laravel + MySQL + Redis + .NET/C# + SQLite**, validar o produto em estabelecimentos reais e evoluir a infraestrutura conforme dados concretos de utilização, volume e desempenho.