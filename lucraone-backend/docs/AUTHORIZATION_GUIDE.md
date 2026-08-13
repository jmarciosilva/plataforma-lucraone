# Authorization Guide — LucraOne

**Versão:** 1.0  
**Data:** 2026-08-13  
**Status:** Implementação completa

---

## 📋 Visão Geral

O sistema de autorização LucraOne implementa **RBAC (Role-Based Access Control)** com isolamento por tenant. Cada usuário pode ter múltiplas roles, e cada role pode ter múltiplas permissões.

### Conceitos Principais

- **Role (Função):** Conjunto nomeado de permissões (ex: Admin, Manager, Viewer)
- **Permission (Permissão):** Ação granular que um usuário pode executar (ex: create-role, delete-user)
- **User:** Pessoa que acessa o sistema, com roles atribuídas
- **Tenant Isolation:** Roles/Permissions de um tenant não são visíveis para outro

---

## 🏗️ Arquitetura

### Modelo de Dados

```
User (1) ─────────────────── (M) Role ───────────────── (M) Permission
         user_role table              role_permission table
         
Cada relacionamento inclui tenant_id para isolamento.
```

### Tabelas

| Tabela | Função | Tenant Scoped |
|--------|--------|---------------|
| roles | Define roles (Admin, Manager, etc) | ✅ Sim |
| permissions | Define permissões granulares | ✅ Sim |
| user_role | Pivot: User ↔ Role | ✅ Sim |
| role_permission | Pivot: Role ↔ Permission | ✅ Sim |

---

## 🔑 Roles Padrão

Cada tenant vem com 4 roles pré-configuradas:

### 1. Admin
- **Descrição:** Acesso total ao sistema
- **Permissões:** Todas (criar/editar/deletar roles, permissions, users, branches)
- **Casos de uso:** Proprietário do tenant, super-usuários

### 2. Manager
- **Descrição:** Gerenciar usuários e branches atribuídos
- **Permissões:** 
  - manage-users
  - manage-assigned-branches
  - view-branches
- **Casos de uso:** Gerentes de departamento, coordenadores

### 3. User
- **Descrição:** Acesso padrão para usuários finais
- **Permissões:**
  - view-users
  - view-assigned-branches
- **Casos de uso:** Funcionários regulares

### 4. Viewer
- **Descrição:** Acesso somente leitura
- **Permissões:**
  - view-branches
- **Casos de uso:** Consultores, auditores

---

## 🔐 Permissões Padrão

### Permissões de Role
```
create-role         Criar nova role
update-role         Editar role existente
delete-role         Deletar role
view-roles          Listar roles
```

### Permissões de User
```
manage-users        Criar/editar/deletar usuários
view-users          Listar usuários
```

### Permissões de Branch
```
create-branch           Criar branch
manage-branches         Gerenciar todas as branches
manage-assigned-branches Gerenciar apenas branches atribuídas
view-branches           Visualizar todas as branches
view-assigned-branches  Visualizar apenas branches atribuídas
view-all-branches       Acesso total a branches
```

---

## 📖 Como Usar

### Verificar Role

```php
$user = auth()->user();

// Verificar uma role
if ($user->hasRole('admin')) {
    // Fazer algo
}

// Verificar múltiplas roles (qualquer uma)
if ($user->hasAnyRole(['admin', 'manager'])) {
    // Fazer algo
}

// Verificar múltiplas roles (todas)
if ($user->hasAllRoles(['admin', 'manager'])) {
    // Fazer algo
}
```

### Verificar Permission

```php
$user = auth()->user();

// Verificar permissão
if ($user->hasPermission('create-role')) {
    // Criar nova role
}

// Verificar múltiplas permissões (qualquer uma)
if ($user->hasAnyPermission(['create-role', 'update-role'])) {
    // Fazer algo
}

// Verificar múltiplas permissões (todas)
if ($user->hasAllPermissions(['create-role', 'delete-role'])) {
    // Fazer algo
}
```

### Atribuir Role

```php
$user = User::find($userId);
$role = Role::where('name', 'manager')->first();

// Atribuir role
$user->assignRole($role);

// Atribuir role por nome
$user->assignRole('manager');

// Sincronizar roles (substituir todas)
$user->syncRoles(['manager', 'user']);
```

### Remover Role

```php
$user = User::find($userId);

// Remover role
$user->removeRole('manager');
```

### Gerenciar Permissions em Role

```php
$role = Role::where('name', 'manager')->first();
$permission = Permission::where('name', 'create-role')->first();

// Conceder permissão
$role->grantPermission($permission);

// Revogar permissão
$role->revokePermission($permission);

// Verificar se tem permissão
if ($role->hasPermission($permission)) {
    // Fazer algo
}
```

---

## 🎯 Policies (Authorization)

As políticas definem quem pode fazer o quê. Use-as em controllers ou views:

### RolePolicy

```php
// Criar role
$this->authorize('create', Role::class);

// Visualizar role
$this->authorize('view', $role);

// Atualizar role
$this->authorize('update', $role);

// Deletar role
$this->authorize('delete', $role);
```

### PermissionPolicy

```php
// Visualizar permissão
$this->authorize('view', $permission);
```

### BranchPolicy

```php
// Visualizar branch
$this->authorize('view', $branch);

// Gerenciar branch
$this->authorize('update', $branch);
```

---

## 🔒 Tenant Isolation

Todas as operações de autorização são automaticamente isoladas por tenant:

```php
// Usuário do Tenant A
$userA = User::find($userId);  // tenant_id = A

// Role do Tenant A
$roleA = Role::factory()->forTenant($userA->tenant_id)->create();

// Atribuir role A ao user A — Funciona ✅
$userA->assignRole($roleA);

// Role do Tenant B
$roleB = Role::factory()->forTenant('tenant-b-id')->create();

// Atribuir role B ao user A — Falha ❌
// O trait HasRole verifica tenant_id automaticamente
$userA->assignRole($roleB);  // Será ignorado ou falhará
```

### Como Funciona

1. **Global Scope:** Todos os modelos com `HasTenant` aplicam `TenantScope`
2. **Query Filtering:** `Role::all()` retorna apenas roles do tenant atual
3. **Relationship Filtering:** `$user->roles()` retorna apenas roles do tenant do usuário
4. **Unique Constraints:** Nomes de roles são únicos por tenant, não globalmente

---

## 🧪 Testes

### Testes Inclusos

**RBACTest.php** (12 testes)
- ✅ Atribuir role a usuário
- ✅ Remover role de usuário
- ✅ Herança de permissões
- ✅ Usuário sem role não tem permissões
- ✅ Conceder permissão a role
- ✅ Revogar permissão de role
- ✅ Isolamento de tenant (roles)
- ✅ Isolamento de tenant (permissions)
- ✅ Múltiplas roles por usuário
- ✅ hasAnyRole
- ✅ syncRoles
- ✅ getPermissions

**AuthorizationIsolationTest.php** (5 testes)
- ✅ Roles de tenant A não visíveis para tenant B
- ✅ Permissions de tenant A não acessíveis para tenant B
- ✅ Usuário de tenant A não pode ter role de tenant B
- ✅ Mesma role name em diferentes tenants
- ✅ Roles automaticamente scoped por tenant

**Total:** 17 testes, 100% passing

---

## 📝 Padrões de Desenvolvimento

### Criar Nova Permission

```php
// Via factory
$permission = Permission::factory()
    ->forTenant($tenantId)
    ->create(['name' => 'export-reports']);

// Manual
$permission = Permission::create([
    'id' => Str::ulid(),
    'tenant_id' => $tenantId,
    'name' => 'export-reports',
    'description' => 'Export reports to PDF',
]);
```

### Criar Nova Role

```php
// Via factory
$role = Role::factory()
    ->forTenant($tenantId)
    ->create(['name' => 'accountant']);

// Manual
$role = Role::create([
    'id' => Str::ulid(),
    'tenant_id' => $tenantId,
    'name' => 'accountant',
    'description' => 'Accountant role',
]);

// Atribuir permissões
$role->grantPermission('view-reports');
$role->grantPermission('export-reports');
```

### Usar em Controller

```php
class RoleController extends Controller
{
    public function store(StoreRoleRequest $request)
    {
        // Policy verificada automaticamente
        $this->authorize('create', Role::class);

        $role = Role::create([
            'id' => Str::ulid(),
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->authorize('update', $role);

        $role->update($request->validated());

        return new RoleResource($role);
    }
}
```

---

## ⚠️ Casos Especiais

### Sistema Roles (Imutável)

Roles 'admin', 'manager', 'user', 'viewer' são sistema e não podem ser deletadas:

```php
$role = Role::where('name', 'admin')->first();

// Tentar deletar
$role->delete();  // Será prevenido por política

// Verificar se é role de sistema
if ($role->name === 'admin') {
    // Proteger deletação
}
```

### Escalação de Privilégio

Um usuário não pode conceder permissões que não tem:

```php
$user = auth()->user();  // tem role 'manager'

// Tentar criar role 'admin'
$this->authorize('create', Role::class);  // Falha! manager não tem 'create-role'
```

### Limpeza em Cascata

Ao deletar um usuário, suas roles são removidas automaticamente:

```php
$user->delete();  // Via FK cascade delete em user_role table
```

---

## 📊 Performance

### Índices Criados

```sql
roles: (tenant_id), (tenant_id, name)
permissions: (tenant_id), (tenant_id, name)
role_permission: (tenant_id), (role_id), (permission_id)
user_role: (tenant_id), (user_id), (role_id)
```

### Consultas Otimizadas

```php
// Eager load permissões
$roles = $user->roles()->with('permissions')->get();

// Verificação eficiente
$user->hasPermission('create-role');  // 1 query com cache possível
```

---

## 🔄 Roadmap (Futuro)

- [ ] Dynamic roles (criar roles customizadas por tenant)
- [ ] Role hierarchy (herança entre roles)
- [ ] Audit log para mudanças de autorização
- [ ] Cache de permissões para performance
- [ ] API endpoints para CRUD de roles/permissions
- [ ] UI para gerenciamento visual de roles

---

## 📞 Suporte

Para dúvidas sobre autorização:

1. Verificar testes em `tests/Feature/Authorization/`
2. Consultar modelos em `app/Modules/Authorization/`
3. Revisar SPRINT_F1.5_PLAN.md para detalhes de design

---

*Última atualização: 2026-08-13 20:50 UTC*
