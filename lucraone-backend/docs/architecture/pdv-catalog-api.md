# API de catálogo do PDV — Fase 6.1

API de leitura para o PDV Java. Não cria venda, item, pagamento, movimento de
estoque ou desconto. O contexto é derivado do Terminal autenticado; filtros do
cliente nunca selecionam tenant, empresa ou filial.

## Autenticação e escopo

As duas rotas exigem `Authorization: Bearer <credencial-de-maquina>` e
`X-PDV-Operator-Session: <credencial-da-sessao>`. A máquina precisa ter a
ability explícita `pdv:catalog:read`; a sessão do operador é autenticada pela
mesma pilha da Fase 5. `X-Tenant-ID` não é aceito pelo contexto de máquina e a
credencial de operador não pode ser enviada na URL ou no corpo.

O servidor limita produtos ao `tenant_id` e `company_id` do Terminal, além de
`status=active` e `deleted_at IS NULL`. O schema atual define
`products.company_id` como obrigatório; não existe regra de compartilhamento de
produto com empresa nula. Embalagens e preços são carregados pela relação com o
produto, dentro do tenant já resolvido. Nenhum filtro enviado pelo Java altera
esse escopo.

Credenciais já emitidas mantêm exatamente as abilities que possuem. Não há
ampliação retroativa nem wildcard. Pareamento novo e confirmação de uma rotação
normal passam a emitir a ability de catálogo explícita. Um token antigo que não
a possua recebe 403 até a rotação segura normal do Terminal; deploy não rotaciona
nem revoga credencial. Não há mudança nos contratos da Fase 5.

## Rotas

### `GET /api/v1/pdv/catalog/products`

Parâmetros opcionais:

| Parâmetro | Tipo | Regra |
| --- | --- | --- |
| `search` | string | 1–100 caracteres; busca parcial em nome, SKU e barcode |
| `per_page` | inteiro | padrão 50, mínimo 1, máximo 100 |
| `cursor` | string | cursor opaco devolvido em `meta.next_cursor` |

Ordenação estável por ULID `id` crescente. Continue enviando os mesmos filtros
com `cursor`; a página final tem `meta.has_more=false` e `meta.next_cursor=null`.
Os preços são strings decimais na precisão persistida; todos os preços `sale`
por moeda são listados em `sale_prices`. Lista vazia significa que não há preço
de venda cadastrado. Nunca é enviado preço de custo, margem, histórico,
descrição fiscal ou saldo de estoque.

```json
{
  "data": [{
    "id": "01...",
    "sku": "ARROZ-5KG",
    "barcode": "0001234567890",
    "name": "ARROZ TIPO 1",
    "unit": "KG",
    "status": "active",
    "sale_prices": [{"amount": "29.90", "currency": "BRL"}],
    "packages": [{"id": "01...", "name": "Fardo 6", "barcode": "0009876543210", "factor": 6}],
    "updated_at": "2026-10-12T12:00:00.000000Z"
  }],
  "meta": {"per_page": 50, "next_cursor": null, "has_more": false}
}
```

A paginação é keyset e evita carregar todos os produtos na memória. Ela mantém
a ordem durante alterações, mas **não cria snapshot transacional entre
requisições**: atualizações, desativações ou inclusões concorrentes podem fazer
uma varredura refletir momentos diferentes. Esta API não é um mecanismo de
sincronização incremental nem informa tombstones. Para substituir catálogo local,
o cliente deve concluir uma varredura integral e, para consistência estrita sob
escritas simultâneas, repetir a varredura após a sincronização; o protocolo de
snapshot/tombstone fica para uma etapa específica antes de sincronização
incremental.

### `GET /api/v1/pdv/catalog/resolve-barcode/{barcode}`

Resolve comparação exata como string (1–14 caracteres), preservando zeros à
esquerda. Reutiliza `ProductBarcodeResolver`, agora com escopo opcional de
empresa para esta chamada. Código de produto retorna quantidade base 1;
código de embalagem retorna o produto base e `factor`. A unidade `KG` não
interpreta peso embutido.

```json
{"data": {
  "barcode": "0009876543210", "source": "package", "quantity": 6,
  "unit": "UN", "product": {"id": "01...", "sale_prices": []},
  "package": {"id": "01...", "name": "Fardo 6", "barcode": "0009876543210", "factor": 6}
}}
```

## Erros e limites

As respostas de erro usam o envelope do módulo PDV: `error.code`, `message` e
`request_id`.

| HTTP | Significado |
| --- | --- |
| 401 | máquina ou sessão operacional ausente/inválida |
| 403 | Terminal inelegível, ability ausente, `X-Tenant-ID` ou sessão em URL |
| 404 | barcode inexistente ou não pertencente à empresa do Terminal |
| 409 | barcode em conflito dentro do catálogo da empresa |
| 422 | barcode/filtros/cursor inválidos |
| 429 | limite de 120 requisições por minuto por credencial de máquina (chave de cache usa SHA-256 do bearer) |
| 5xx | falha operacional sanitizada |

As recusas de contrato não retornam dados de outras empresas. Credenciais e
headers sensíveis não fazem parte da resposta nem de logs de aplicação.

## Testes

`tests/Feature/Pdv/PdvCatalogApiTest.php` cobre as identidades da Fase 5,
credencial sem ability de catálogo, escopo tenant/empresa, produtos elegíveis,
preço de venda sem custo, unidades, embalagem, códigos com zeros à esquerda,
busca, cursor e ausência de mutações em pedidos/estoque.
