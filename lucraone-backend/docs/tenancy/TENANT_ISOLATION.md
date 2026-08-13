# Isolamento Multi-Tenant — LUCRAONE

**Versão:** 1.0  
**Status:** Foundation Phase (F1.2)  
**Data:** 2026-08-13

---

## 📋 Índice

1. [Conceito](#conceito)
2. [TenantContext](#tenantcontext)
3. [Global Scopes](#global-scopes)
4. [HasTenant Trait](#hastenant-trait)
5. [Middleware](#middleware)
6. [Testes](#testes)
7. [Boas Práticas](#boas-práticas)

---

## Conceito

LUCRAONE usa **isolamento lógico multi-tenant** com estratégia de **banco compartilhado**.

```
Banco de dados único (lucraone)
       ↓
Todas as tabelas multi-tenant possuem coluna tenant_id
       ↓
Cada query filtra automaticamente pelo tenant atual
       ↓
TenantContext mantém tenant_id na memória durante a requisição
```

---

## TenantContext

Singleton que mantém o ID do tenant atual durante toda a requisição.

### Usar em Controllers / Services

```php
// Obter ID do tenant atual
$tenantId = app(\App\Modules\Tenancy\Application\TenantContext::class)->id();

// Ou usar injeção
public function store(TenantContext $context)
{
    $tenantId = $context->id();
}
```

### Verificar se Tenant Foi Resolvido

```php
$context = app(TenantContext::class);

if ($context->resolved()) {
    echo "Tenant: " . $context->id();
} else {
    echo "Sem tenant resolvido";
}
```

### Executar Código em Contexto de Outro Tenant

```php
// Útil para Jobs e operações assíncronas
$context->withTenant($tenantId, function () {
    // Dentro deste bloco, queries usam o tenant específico
    $companies = Company::all(); // Filtrado pelo tenant
});

// Após sair, contexto volta ao anterior
```

---

## Global Scopes

**O mecanismo automático de isolamento.**

Todos os modelos multi-tenant usam `TenantScope`, que filtra **automaticamente** por `tenant_id`.

### Como Funciona

```php
class Company extends Model
{
    use HasTenant; // ← Ativa o TenantScope automaticamente
}

// Query:
Company::all(); // ← Automaticamente WHERE tenant_id = {contexto_atual}

// Sem filtrar tenant:
Company::withoutGlobalScope('tenant')->all(); // ← Retorna TODOS (cuidado!)
```

### Protegido Contra Bypass?

❌ **Não.** Se um desenvolvedor usar `withoutGlobalScope`, consegue acessar dados de outro tenant.

✅ **Por isso existe:** Testes automatizados que validam isolamento.

---

## HasTenant Trait

Adiciona comportamentos a modelos multi-tenant.

### Como Usar

```php
<?php

namespace App\Modules\Companies\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenancy\Domain\Models\HasTenant;

class Company extends Model
{
    use HasTenant;

    // ...
}
```

### Métodos Disponíveis

```php
// Obter o tenant_id do modelo
$company->getTenantId();

// Definir o tenant_id
$company->setTenantId($tenantId);

// Criar modelo com tenant atual automaticamente
$company = Company::forCurrentTenant([
    'name' => 'Nova Empresa'
]);
// Preencheu tenant_id automaticamente
```

---

## Middleware

`ResolveTenantMiddleware` executa em **cada requisição** e:

1. ✅ Determina qual tenant está sendo usado
2. ✅ Popula o TenantContext
3. ❌ Bloqueia se não conseguir resolver (para rotas autenticadas)

### Rotas Públicas (Sem Tenant)

```
GET    /health              ← Público
POST   /api/v1/auth/login   ← Público
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
```

### Rotas Autenticadas (Com Tenant)

```
GET    /api/v1/companies    ← Requer tenant resolvido
POST   /api/v1/companies    ← Requer tenant resolvido
```

---

## Testes

### Base Test Case

```php
use Tests\Feature\Tenancy\TenancyTestCase;

class CompanyTest extends TenancyTestCase
{
    public function test_tenant_a_cannot_access_tenant_b_data()
    {
        // $this->tenantA e $this->tenantB já existem

        $this->withTenant($this->tenantA, function () {
            // Dentro, Company::all() filtra por tenantA
            $companies = Company::all();
        });
    }
}
```

### Validar Isolamento

```php
// Assert que modelo pertence ao tenant correto
$this->assertBelongsToTenant($company, $this->tenantA);

// Assert que modelo não é acessível para outro tenant
$this->assertNotAccessibleToTenant(Company::class, $company->id, $this->tenantB);
```

---

## Boas Práticas

### ✅ FAÇA

1. **Usar HasTenant em modelos multi-tenant**
   ```php
   class User extends Model
   {
       use HasTenant; // Sempre!
   }
   ```

2. **Testar isolamento automaticamente**
   ```php
   public function test_cross_tenant_access()
   {
       // Sempre testar que Tenant A não acessa Tenant B
   }
   ```

3. **Usar TenantContext.withTenant em Jobs**
   ```php
   public function handle()
   {
       $context = app(TenantContext::class);
       $context->withTenant($this->tenantId, function () {
           // Queries aqui usam o tenant correto
       });
   }
   ```

4. **Validar tenant em Policies**
   ```php
   public function update(User $user, Company $company)
   {
       return $user->tenant_id === $company->tenant_id;
   }
   ```

### ❌ NÃO FAÇA

1. **Confiar apenas em where('tenant_id', ...) manual**
   ```php
   // ❌ ERRADO
   Company::where('tenant_id', $tenantId)->all();
   
   // ✅ CORRETO
   // Usar HasTenant + Global Scope
   ```

2. **Esquecer HasTenant em novo modelo**
   ```php
   // ❌ ERRADO
   class Product extends Model {}
   
   // ✅ CORRETO
   class Product extends Model
   {
       use HasTenant;
   }
   ```

3. **Usar withoutGlobalScope em produção**
   ```php
   // ❌ ERRADO
   Company::withoutGlobalScope('tenant')->all();
   
   // Apenas em casos muito específicos (admin, migrações)
   ```

4. **Esquecer de testar isolamento**
   ```php
   // ❌ ERRADO
   public function test_create_company()
   {
       // Sem validar que outro tenant não vê
   }
   
   // ✅ CORRETO
   public function test_tenant_cannot_see_other_company()
   {
       // Sempre testar cross-tenant
   }
   ```

---

## Fluxo Completo

```
1. Requisição HTTP
       ↓
2. ResolveTenantMiddleware
       ├─ Obter tenant do usuário autenticado
       └─ Chamar context->set($tenantId)
       ↓
3. Controller
       ├─ Company::all()
       │  └─ (Internamente, TenantScope filtra)
       └─ Retorna apenas dados do tenant atual
       ↓
4. Response
```

---

## Cenários de Teste Críticos

### Cenário 01: Isolamento Básico
```
Given: Tenant A possui Company A
And: Tenant B possui Company B

When: Tenant A faz GET /companies

Then: Retorna apenas Company A
And: Company B não é retornada
```

### Cenário 02: IDOR Prevention
```
Given: Company B existe e pertence a Tenant B

When: Usuário de Tenant A tenta GET /companies/{B.id}

Then: Retorna 404
And: Não retorna informação que confir a existência de Company B
```

### Cenário 03: Cross-Tenant Create
```
Given: Usuário de Tenant A cria Company

When: POST /companies com { tenant_id: Tenant B }

Then: Company é criada com tenant_id = Tenant A (não B)
And: Payload não consegue manipular tenant_id
```

---

## Referências

- **TenantContext:** `app/Modules/Tenancy/Application/TenantContext.php`
- **TenantScope:** `app/Modules/Tenancy/Infrastructure/Persistence/TenantScope.php`
- **HasTenant:** `app/Modules/Tenancy/Domain/Models/HasTenant.php`
- **Middleware:** `app/Modules/Tenancy/Http/Middleware/ResolveTenantMiddleware.php`
- **Testes:** `tests/Feature/Tenancy/TenantIsolationTest.php`

---

**Última atualização:** 2026-08-13
