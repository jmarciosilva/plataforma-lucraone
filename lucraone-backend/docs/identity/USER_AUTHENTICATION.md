# User Authentication & Identity

**Versão:** 1.0  
**Data:** 2026-08-13  
**Escopo:** Sprint F1.4 — Identity / Authentication

---

## 📚 Índice

1. [User Model](#user-model)
2. [Multi-Tenancy & User Isolation](#multi-tenancy--user-isolation)
3. [Status Management](#status-management)
4. [Password Security](#password-security)
5. [Sanctum Integration](#sanctum-integration)
6. [Testing Users](#testing-users)

---

## User Model

### Locação

```
app/Modules/Identity/Domain/Models/User.php
```

### Propriedades

| Propriedade | Tipo | Descrição |
|-------------|------|-----------|
| `id` | ULID | Primary key (não-previsível) |
| `tenant_id` | ULID FK | Pertence ao tenant |
| `name` | string | Nome do usuário |
| `email` | string | Email (unique per tenant) |
| `password` | string | Hash bcrypt |
| `status` | enum | ACTIVE, INACTIVE, SUSPENDED, INVITED |
| `email_verified_at` | datetime | Timestamp verificação email |
| `last_login_at` | datetime | Último login |
| `remember_token` | string | Laravel remember me |
| `created_at` | datetime | Criação |
| `updated_at` | datetime | Última atualização |

### Relacionamentos

```php
// Acesso automático via HasTenant trait
$user->getTenantId();  // Retorna tenant_id

// Relação com Tenant
$tenant = $user->tenant(); // belongsTo Tenant

// API Tokens (via HasApiTokens)
$token = $user->createToken('auth_token');
$user->tokens()->delete();  // Logout
```

### Methods

```php
// Validação de Status
$user->isActive();    // bool — status === 'ACTIVE'
$user->isInvited();   // bool — status === 'INVITED'

// Helpers
$user->getTenantId();
$user->setTenantId($tenantId);
$user->forCurrentTenant();  // Query scoped to current tenant
```

---

## Multi-Tenancy & User Isolation

### Isolamento Automático

O trait `HasTenant` registra `TenantScope` globalmente:

```php
// Query automaticamente filtrada
User::all();  // Apenas users do tenant atual

// Não consegue acessar usuários de outro tenant
User::find($otherTenantUserId);  // NULL (se de outro tenant)
```

### Email Único por Tenant

```php
// Tenant A
$userA = User::create([
    'tenant_id' => $tenantA->id,
    'email' => 'admin@example.com',  // OK
]);

// Tenant B
$userB = User::create([
    'tenant_id' => $tenantB->id,
    'email' => 'admin@example.com',  // OK — diferentes tenants
]);
```

### Garantindo Isolamento em Queries

```php
// ✅ Seguro — Global scope automático
$users = User::all();

// ✅ Seguro — Com HasTenant
$user = User::find($id);

// ⚠️ Cuidado — Bypassa global scope (evitar)
$user = User::withoutGlobalScope(TenantScope::class)->find($id);
```

---

## Status Management

### Estados do User

```
┌─────────────┐
│  INVITED    │  (User recebeu convite, não ativou)
└──────┬──────┘
       │ Aceita convite
       ▼
┌─────────────┐
│   ACTIVE    │  (User funcional, pode fazer login)
└──────┬──────┘
       │ Desativar temporário
       ▼
┌─────────────┐
│ INACTIVE    │  (User criado, não pode fazer login)
└──────┬──────┘
       │ Deletar permanente (não restaurável)
       ▼
    DELETED

Alternativa:
┌──────────┐
│SUSPENDED │  (User bloqueado por compliance)
└──────────┘
```

### Transições Esperadas

```php
// Criar usuário convidado
$user = User::factory()
    ->invited()
    ->forCurrentTenant($tenant->id)
    ->create();

// User aceita convite → ativa
$user->status = 'ACTIVE';
$user->email_verified_at = now();
$user->save();

// Admin desativa
$user->status = 'INACTIVE';
$user->save();

// Admin suspende (abuse)
$user->status = 'SUSPENDED';
$user->save();
```

---

## Password Security

### Hashing Automático

Laravel/Illuminate automaticamente faz hash de passwords:

```php
// Model define cast
protected $casts = [
    'password' => 'hashed',  // ← Automático
];

// Ao criar
$user = User::create([
    'password' => 'plain_text',  // ← É hashado internamente
]);

// Resultado
$user->password;  // Never plain text (sempre bcrypt)
```

### Verificação

```php
// Login endpoint
if (Hash::check($plainPassword, $user->password)) {
    // Password correto
}

// ✅ Seguro — Hash::check compara bcrypt
// ❌ Inseguro — $user->password === $plainPassword (nunca!)
```

### Reset Flow (Pendente — F1.4 Continuação)

```php
// Será implementado com queue job
Mail::send(new ForgotPasswordMail($user, $token));

// Token é gerado com expiração
// URL: /reset-password/{token}

// Validação
$user = DB::table('password_reset_tokens')
    ->where('email', $email)
    ->where('token', Hash::check($token, stored_token))  // timing safe
    ->where('created_at', '>', now()->subMinutes(60))
    ->first();
```

---

## Sanctum Integration

### Setup (Já Feito — F1.1)

```php
// composer.json
"laravel/sanctum": "^4.3"

// User model
use Laravel\Sanctum\HasApiTokens;

class User extends Model {
    use HasApiTokens;
}
```

### Geração de Token

```php
// Login bem-sucedido
$user = User::where('email', $email)->first();

$token = $user->createToken('auth_token');

return response()->json([
    'token' => $token->plainTextToken,  // Enviar ao cliente
    'user' => $user,
]);
```

### Validação de Token

```php
// Middleware automático (via Sanctum)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();  // Current user
});
```

### Token Expiration (Configurável)

```php
// config/sanctum.php
'expiration' => 525600,  // 1 ano em minutos

// Revoke individual token
$request->user()->tokens()->where('id', $tokenId)->delete();

// Revoke all tokens (logout all devices)
$request->user()->tokens()->delete();

// Revoke specific token name
$request->user()->tokens()
    ->where('name', 'auth_token')
    ->delete();
```

---

## Testing Users

### Factory Usage

```php
// Criar usuário ativo
$user = User::factory()
    ->active()
    ->forCurrentTenant($tenant->id)
    ->create();

// Criar usuário convidado
$user = User::factory()
    ->invited()
    ->forCurrentTenant($tenant->id)
    ->create(['email' => 'custom@example.com']);

// Criar múltiplos
$users = User::factory(5)
    ->active()
    ->forCurrentTenant($tenant->id)
    ->create();

// Aceder tenant
$this->assertSame($tenant->id, $user->tenant_id);
```

### Isolation Testing

```php
class UserIsolationTest extends TenancyTestCase {
    public function test_users_isolated_by_tenant() {
        $userA = User::factory()->forCurrentTenant($this->tenantA->id)->create();
        $userB = User::factory()->forCurrentTenant($this->tenantB->id)->create();

        // Tenant A context
        $this->tenantContext->set($this->tenantA->id);
        $users = User::all();
        
        $this->assertCount(1, $users);
        $this->assertTrue($users->contains($userA));
        $this->assertFalse($users->contains($userB));  // IDOR prevented
    }
}
```

### Login Testing (Pendente)

```php
// Será testado quando endpoints forem implementados
$response = $this->post('/api/auth/login', [
    'email' => $user->email,
    'password' => 'password',  // Factory default
]);

$response->assertStatus(200);
$response->assertJsonStructure(['token', 'user']);
```

---

## 🔄 Próximas Etapas (F1.4 Continuação)

### Login Endpoint
- [ ] POST `/api/auth/login`
- [ ] Validar email + password
- [ ] Gerar token Sanctum
- [ ] Update last_login_at
- [ ] Teste de isolamento

### Password Reset
- [ ] POST `/api/auth/forgot-password`
- [ ] Email com token
- [ ] POST `/api/auth/reset-password`
- [ ] Validar token expirado
- [ ] Update password

### User Invitation
- [ ] POST `/api/users/invite`
- [ ] Email com token
- [ ] GET `/api/invitations/{token}/verify`
- [ ] POST `/api/invitations/{token}/accept`
- [ ] Status INVITED → ACTIVE

---

## 📋 Segurança

| Aspecto | Status | Detalhe |
|--------|--------|---------|
| **Multi-Tenancy** | ✅ | Global scope, FK cascade |
| **Password** | ✅ | Bcrypt, never logged |
| **IDOR** | ✅ | tenant_id validado em queries |
| **Tokens** | ✅ | Sanctum (configurável TTL) |
| **Email Verification** | 🟡 | Logic ready, endpoint pendente |
| **Rate Limiting** | ⬜ | Middleware pronto, não ativado |
| **2FA** | ⬜ | Futuro — F1.5+ |

---

## 📚 Referências Rápidas

### Arquivos
- Model: `app/Modules/Identity/Domain/Models/User.php`
- Migration: `database/migrations/0001_01_01_000000_create_users_table.php`
- Factory: `database/factories/UserFactory.php`
- Seeder: `database/seeders/UserSeeder.php`
- Tests: `tests/Feature/Identity/UserIsolationTest.php`

### Docs
- [AUDIT_CHECKLIST.md](../AUDIT_CHECKLIST.md) — Validações
- [SPRINT_F1.4_REPORT.md](../SPRINT_F1.4_REPORT.md) — Progresso
- [TENANT_ISOLATION.md](../tenancy/TENANT_ISOLATION.md) — Multi-tenancy

---

**Versão:** 1.0 — User model base (F1.4 base)  
**Próxima:** Login/Logout endpoints (F1.4 continuação)  
**Atualizado:** 2026-08-13
