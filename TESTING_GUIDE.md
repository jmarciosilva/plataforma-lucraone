# Guia de Testes Manuais — F2.1 Products Module

> ℹ️ **Escopo:** este guia cobre a **API** do módulo de Produtos (F2.1), testada via
> cURL/Postman. A interface web (login e painel administrativo) será entregue na
> [FASE 03 — ADMIN FRONTEND](ROADMAP_FASE_03_ADMIN_FRONTEND.md).

> ⚠️ **Correções importantes (2026-08-16):**
> - A rota de health é **`/api/health`**, não `/health`
> - O container `app` **não possui `bash`** — use `docker-compose exec app sh`
> - Antes de gerar tokens, aplique a migration de ULID:
>   `docker-compose exec app php artisan migrate --force`
>   (sem ela, `createToken()` falha com *Data truncated for column 'tokenable_id'*)

## 1️⃣ Pré-Requisitos

Certifique-se de que os containers estão rodando:

```bash
# No diretório do projeto
docker-compose up -d

# Verificar status
docker-compose ps
```

**Esperado:** app, mysql, redis, mailpit todos em `Up`

---

## 2️⃣ Preparar a Aplicação

### A. Executar Migrações

```bash
docker-compose exec app php artisan migrate --force
```

**Esperado:**
```
Migrating: 2024_01_01_000000_create_tenants_table
Migrating: 2024_01_01_000001_create_companies_table
...
Migrating: 2026_08_16_100004_create_price_histories_table
✓ Migration completed
```

### B. Popular Dados de Teste

```bash
# Rodar seeders
docker-compose exec app php artisan db:seed --force

# Ou seeders específicos
docker-compose exec app php artisan db:seed --class=AuthorizationSeeder --force
```

### C. Gerar Token de Teste

```bash
# Entre no container (use sh — o container não tem bash)
docker-compose exec app sh

# Dentro do container, rode:
php artisan tinker

# No tinker, execute:
$tenant = \App\Modules\Tenancy\Domain\Models\Tenant::first();
$user = \App\Modules\Identity\Domain\Models\User::where('tenant_id', $tenant->id)->first();
$token = $user->createToken('test-token')->plainTextToken;
echo "Token: $token\n";
echo "Tenant ID: $tenant->id\n";
exit
```

---

## 3️⃣ Acessar a Aplicação

### URL Base
```
http://localhost:8000
```

### Health Check
```
GET http://localhost:8000/api/health
```

**Esperado:** 
```json
{
  "status": "ok",
  "database": "connected",
  "cache": "connected"
}
```

---

## 4️⃣ Testar Endpoints F2.1

### Headers Necessários

```
Authorization: Bearer YOUR_TOKEN_HERE
X-Tenant-ID: YOUR_TENANT_ID_HERE
Content-Type: application/json
```

### 4.1 Products API

#### Listar Produtos
```bash
curl -X GET http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

#### Criar Produto
```bash
curl -X POST http://localhost:8000/api/v1/products \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": "COMPANY_ID",
    "sku": "PROD-001",
    "name": "Product Name",
    "description": "Product description",
    "status": "active"
  }'
```

#### Buscar Produto
```bash
curl -X GET http://localhost:8000/api/v1/products/{id} \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

#### Atualizar Produto
```bash
curl -X PUT http://localhost:8000/api/v1/products/{id} \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name",
    "status": "inactive"
  }'
```

#### Deletar Produto (soft delete)
```bash
curl -X DELETE http://localhost:8000/api/v1/products/{id} \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

#### Buscar Produtos por SKU
```bash
curl -X GET http://localhost:8000/api/v1/products/search/PROD-001 \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

#### Listar por Status
```bash
curl -X GET http://localhost:8000/api/v1/products/status/active \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

### 4.2 Categories API

#### Listar Categorias Raiz
```bash
curl -X GET http://localhost:8000/api/v1/categories \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

#### Criar Categoria
```bash
curl -X POST http://localhost:8000/api/v1/categories \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Electronics",
    "slug": "electronics",
    "description": "Electronic products"
  }'
```

#### Criar Subcategoria
```bash
curl -X POST http://localhost:8000/api/v1/categories \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Smartphones",
    "slug": "smartphones",
    "parent_id": "PARENT_CATEGORY_ID"
  }'
```

#### Listar Subcategorias
```bash
curl -X GET http://localhost:8000/api/v1/categories/{parent_id}/children \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

### 4.3 Prices API

#### Listar Preços de um Produto
```bash
curl -X GET http://localhost:8000/api/v1/prices/product/{product_id} \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

#### Criar/Atualizar Preço
```bash
curl -X POST http://localhost:8000/api/v1/prices \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": "PRODUCT_ID",
    "amount": 99.99,
    "currency": "BRL",
    "type": "sale"
  }'
```

**Tipos válidos:** `cost`, `sale`, `suggested_retail`

#### Histórico de Preços
```bash
curl -X GET http://localhost:8000/api/v1/prices/history/{product_id} \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

#### Preço de Venda Atual
```bash
curl -X GET http://localhost:8000/api/v1/prices/sale/{product_id} \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

#### Preço de Custo
```bash
curl -X GET http://localhost:8000/api/v1/prices/cost/{product_id} \
  -H "Authorization: Bearer TOKEN" \
  -H "X-Tenant-ID: TENANT_ID"
```

---

## 5️⃣ Usando Postman/Insomnia

1. **Importe a URL base:** `http://localhost:8000`
2. **Configure variáveis:**
   - `token` = Bearer token obtido via tinker
   - `tenant_id` = ID do tenant
   - `company_id` = ID da company
3. **Crie requests** com os exemplos acima

---

## 6️⃣ Esperado em Testes

### Respostas Bem-Sucedidas

**Criação (201 Created):**
```json
{
  "data": {
    "id": "01M05JBJNM62GH3B55KX18Q228",
    "sku": "PROD-001",
    "name": "Product Name",
    "description": "Product description",
    "status": "active",
    "company_id": "01M05JBJKJ35PGJVG4ZESZXCNB",
    "categories": [],
    "created_at": "2026-08-16T15:17:01.000000Z",
    "updated_at": "2026-08-16T15:17:01.000000Z"
  }
}
```

**Listagem (200 OK):**
```json
{
  "data": [
    {
      "id": "...",
      "sku": "...",
      "name": "...",
      ...
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/v1/products?page=1",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "http://localhost:8000/api/v1/products",
    "per_page": 20,
    "to": 5,
    "total": 5
  }
}
```

### Erros Esperados

**Não autenticado (401):**
```json
{
  "message": "Unauthenticated."
}
```

**Validação falhou (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "sku": ["SKU é obrigatório"],
    "name": ["Nome do produto é obrigatório"]
  }
}
```

**Recurso não encontrado (404):**
```json
{
  "message": "Not found"
}
```

---

## 7️⃣ Verificar Dados no Banco

```bash
# Entre no MySQL
docker-compose exec mysql mysql -u lucraone -p lucraone

# Comandos úteis
SHOW TABLES;
SELECT * FROM products LIMIT 5;
SELECT * FROM categories LIMIT 5;
SELECT * FROM prices LIMIT 5;
SELECT * FROM price_histories LIMIT 5;
```

---

## 8️⃣ Logs

```bash
# Ver logs da aplicação
docker-compose logs -f app

# Ver logs do MySQL
docker-compose logs -f mysql

# Ver logs específicos
docker-compose logs app | grep "ERROR"
```

---

## ✅ Checklist de Teste

- [ ] Health check respondendo
- [ ] Conseguiu gerar token via tinker
- [ ] Criar produto com sucesso
- [ ] Listar produtos retorna dados
- [ ] Criar categoria raiz
- [ ] Criar subcategoria com parent_id
- [ ] Listar filhos de categoria
- [ ] Criar preço para produto
- [ ] Visualizar histórico de preços
- [ ] Atualizar produto
- [ ] Deletar produto (soft delete)
- [ ] Buscar produtos por SKU
- [ ] Filtrar por status

---

## 🆘 Troubleshooting

### MySQL não conecta
```bash
docker-compose restart mysql
docker-compose exec app php artisan migrate
```

### Redis não conecta
```bash
docker-compose restart redis
```

### Permissão negada em storage/
```bash
docker-compose exec app chmod -R 777 storage bootstrap/cache
```

### Migrations não correm
```bash
docker-compose exec app php artisan migrate:fresh --force
docker-compose exec app php artisan db:seed --force
```

---

Após seguir este guia, você terá a aplicação totalmente funcional para testes manuais! 🚀
