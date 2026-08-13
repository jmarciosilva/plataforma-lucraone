# Sprint F1.5 — Authorization (Roles & Permissions)

**Status:** 🟡 IN_PROGRESS  
**Data Início:** 2026-08-13 20:50 UTC  
**Objetivo:** Implementar RBAC (Role-Based Access Control) com isolamento por tenant

---

## 📋 Requisitos

### Modelos
- [ ] Role model (name, description, tenant_id)
- [ ] Permission model (name, description, tenant_id)
- [ ] User ↔ Role many-to-many relationship
- [ ] Role ↔ Permission many-to-many relationship

### Banco de Dados
- [ ] roles table (id, name, description, tenant_id, created_at, updated_at)
- [ ] permissions table (id, name, description, tenant_id, created_at, updated_at)
- [ ] role_permission pivot (role_id, permission_id, tenant_id)
- [ ] user_role pivot (user_id, role_id, tenant_id)

### Policies & Authorization
- [ ] RolePolicy (create, update, delete, assign)
- [ ] PermissionPolicy (manage)
- [ ] BranchPolicy com role-based access
- [ ] HasRole trait no User model

### Testes (12+)
- [ ] Role assignment tests (6)
- [ ] Permission inheritance tests (3)
- [ ] Tenant isolation tests (2)
- [ ] Branch access control tests (3)

### Documentação
- [ ] AUTHORIZATION.md guide
- [ ] Role hierarchy documentation
- [ ] Usage examples

---

## 🏗️ Arquitetura

### Estrutura de Diretórios
```
app/Modules/Authorization/
├── Domain/
│   ├── Models/
│   │   ├── Role.php
│   │   └── Permission.php
│   └── Exceptions/
│       └── UnauthorizedException.php
├── Http/
│   └── Policies/
│       ├── RolePolicy.php
│       ├── PermissionPolicy.php
│       └── BranchPolicy.php
├── Application/
│   ├── Services/
│   │   └── AuthorizationService.php
│   └── Traits/
│       └── HasRole.php
├── AuthorizationServiceProvider.php
└── Seeders/
    ├── RoleSeeder.php
    └── PermissionSeeder.php
```

### Relacionamentos
```
User (1) ──── (M) Role ──── (M) Permission
       ↓
    tenant_id      tenant_id      tenant_id
```

---

## 🗄️ Migrations

### roles table
```sql
CREATE TABLE roles (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY (tenant_id, name),
    INDEX (tenant_id)
)
```

### permissions table
```sql
CREATE TABLE permissions (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY (tenant_id, name),
    INDEX (tenant_id)
)
```

### user_role pivot
```sql
CREATE TABLE user_role (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id CHAR(26) NOT NULL,
    role_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    created_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, role_id, tenant_id),
    INDEX (tenant_id),
    INDEX (role_id)
)
```

### role_permission pivot
```sql
CREATE TABLE role_permission (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    role_id CHAR(26) NOT NULL,
    permission_id CHAR(26) NOT NULL,
    tenant_id CHAR(26) NOT NULL,
    created_at TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY (role_id, permission_id, tenant_id),
    INDEX (tenant_id),
    INDEX (permission_id)
)
```

---

## 📝 Padrões de Implementação

### Role Model
```php
class Role extends Model {
    use HasFactory, HasTenant;
    
    protected $fillable = ['name', 'description', 'tenant_id'];
    
    public function permissions() {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->where('role_permission.tenant_id', $this->tenant_id);
    }
    
    public function users() {
        return $this->belongsToMany(User::class, 'user_role')
            ->where('user_role.tenant_id', $this->tenant_id);
    }
}
```

### HasRole Trait
```php
trait HasRole {
    public function roles() {
        return $this->belongsToMany(Role::class, 'user_role')
            ->where('user_role.tenant_id', $this->tenant_id);
    }
    
    public function hasRole($role) {
        // Check if user has role
    }
    
    public function hasPermission($permission) {
        // Check if user has permission through any role
    }
    
    public function assignRole($role) {
        // Assign role to user
    }
}
```

### RolePolicy
```php
class RolePolicy {
    public function viewAny(User $user) {
        return true;
    }
    
    public function create(User $user) {
        return $user->hasPermission('create-role');
    }
    
    public function update(User $user, Role $role) {
        return $user->tenant_id === $role->tenant_id 
            && $user->hasPermission('update-role');
    }
    
    public function delete(User $user, Role $role) {
        return $user->tenant_id === $role->tenant_id 
            && $user->hasPermission('delete-role');
    }
}
```

---

## 🧪 Testes Planejados

### 1. RoleCreationTest
- [ ] User com permission pode criar role
- [ ] User sem permission não pode criar role
- [ ] Role criado com tenant_id correto
- [ ] Roles de diferentes tenants não se misturam

### 2. PermissionInheritanceTest
- [ ] User com role tem todas as permissions da role
- [ ] User sem role não tem permissions
- [ ] Permission removida de role não está mais no user

### 3. BranchAccessTest
- [ ] User com branch admin role pode gerenciar branch
- [ ] User com company admin role pode gerenciar todas branches
- [ ] User sem role não pode acessar branch

### 4. TenantIsolationTest
- [ ] Roles de tenant A não acessíveis por tenant B
- [ ] Permissions isoladas por tenant
- [ ] User_role isolado por tenant

---

## 🔄 Workflow de Implementação

1. **Migrations** → Criar tabelas (roles, permissions, pivots)
2. **Models** → Role, Permission com relacionamentos
3. **Traits** → HasRole no User
4. **Factories** → RoleFactory, PermissionFactory
5. **Policies** → RolePolicy, PermissionPolicy, BranchPolicy
6. **Services** → AuthorizationService para operações
7. **Seeders** → Default roles/permissions
8. **Tests** → Feature tests para todos os cenários
9. **Docs** → AUTHORIZATION.md guide

---

## ✅ Definição de Pronto (DoD)

- [x] Plano de sprint criado
- [ ] Todos os modelos implementados
- [ ] Todas migrations executadas
- [ ] Todas policies implementadas
- [ ] 12+ testes passando (100%)
- [ ] Documentação completa
- [ ] Tenant isolation validado
- [ ] Code review passando
- [ ] Commit estruturado criado

---

## 📊 Progresso

| Tarefa | Status | Testes |
|--------|--------|--------|
| Modelos | ⬜ TODO | — |
| Migrations | ⬜ TODO | — |
| Policies | ⬜ TODO | — |
| Testes | ⬜ TODO | 0/12 |
| Documentação | ⬜ TODO | — |
| **TOTAL** | **⬜ 0%** | **0/12** |

---

*Atualizado: 2026-08-13 20:50 UTC*
