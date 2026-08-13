# Audit & Observability Guide — LucraOne

**Versão:** 1.0  
**Data:** 2026-08-13  
**Status:** Implementação completa

---

## 📋 Visão Geral

O sistema de auditoria e observabilidade LucraOne fornece rastreamento completo de ações, logging estruturado e health checks para monitoramento do sistema.

### Componentes

- **Audit Log:** Rastreia todas as ações (who, what, when, where)
- **Structured Logging:** JSON logging com contexto de requisição
- **Request Correlation:** Rastreia requisições através da aplicação
- **Health Checks:** Monitora status do sistema (database, cache)

---

## 🔍 Audit Logs

### Modelo de Dados

```
AuditLog (por tenant):
  - id: ULID único
  - tenant_id: FK para tenants
  - user_id: FK para users (nullable)
  - action: create, update, delete, login, logout, export, view, etc
  - entity_type: User, Role, Permission, Branch, Company, etc
  - entity_id: ID da entidade afetada
  - changes: JSON com old_values e new_values
  - ip_address: IP do cliente
  - user_agent: User-Agent do navegador
  - request_id: Correlação com log de requisição
  - endpoint: Path da API chamada
  - method: GET, POST, PUT, DELETE, etc
  - status_code: HTTP status da requisição
  - description: Descrição adicional
  - created_at, updated_at: Timestamps
```

### Criando Audit Logs

```php
use App\Modules\Audit\Domain\Models\AuditLog;

// Forma básica
AuditLog::logAction('create', 'User', 'user-123');

// Com changes (para update/delete)
AuditLog::logAction(
    'update',
    'Role',
    'role-456',
    [
        'old_values' => ['name' => 'Admin'],
        'new_values' => ['name' => 'Administrator'],
    ]
);

// Com descrição
AuditLog::logAction(
    'delete',
    'Permission',
    'perm-789',
    description: 'Removed legacy permission'
);
```

### Buscando Audit Logs

```php
// Por tenant
$logs = AuditLog::forTenant($tenantId)->get();

// Por usuário
$logs = AuditLog::forUser($userId)->get();

// Por entity
$logs = AuditLog::forEntity('User', 'user-123')->get();

// Combinado
$logs = AuditLog::where('tenant_id', $tenantId)
    ->where('action', 'delete')
    ->whereDate('created_at', today())
    ->get();
```

### Casos de Uso

**Auditoria de Segurança:**
```php
// Registrar login
AuditLog::logAction('login', 'User', auth()->id(), 
    description: 'User logged in');

// Registrar acesso negado
AuditLog::logAction('access_denied', 'Branch', $branchId,
    description: 'User attempted to access unauthorized branch');
```

**Rastreamento de Mudanças:**
```php
// Antes de deletar
$changes = ['deleted_name' => $role->name];
AuditLog::logAction('delete', 'Role', $role->id, 
    ['old_values' => $changes]);

// Durante update
$changes = $model->getChanges();
AuditLog::logAction('update', $entityType, $model->id, 
    ['changes' => $changes]);
```

**Rastreamento de Exports:**
```php
AuditLog::logAction('export', 'Report', 'report-123',
    description: 'User exported monthly sales report (5000 rows)');
```

---

## 📊 Structured Logging

### StructuredLoggingService

```php
use App\Modules\Audit\Application\Services\StructuredLoggingService;

// Injetar no controller ou service
public function __construct(StructuredLoggingService $logger)
{
    $this->logger = $logger;
}

// Usar
$this->logger->info('User registered', [
    'user_email' => $user->email,
    'registration_method' => 'email',
]);

$this->logger->error('Payment processing failed', [
    'payment_id' => $payment->id,
    'error_code' => 'TIMEOUT',
    'retry_count' => 3,
]);
```

### Logging Automático

O logging é enriquecido automaticamente com contexto:

```json
{
  "message": "User registered",
  "level": "info",
  "request_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "user_id": "01ARZ3NDEKTSV4RRFFQ69G5FBW",
  "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FBX",
  "ip_address": "192.168.1.1",
  "path": "/api/auth/register",
  "method": "POST",
  "timestamp": "2026-08-13T21:15:00Z",
  "user_email": "user@example.com",
  "registration_method": "email"
}
```

### Níveis de Log

```php
$logger->debug('Debug information', [...]);     // Development only
$logger->info('Informational message', [...]);  // Normal operations
$logger->warning('Warning condition', [...]);   // Possible issues
$logger->error('Error condition', [...]);       // Errors occurred
```

---

## 🔗 Request Correlation

### RequestCorrelationMiddleware

Adiciona request ID a todas as requisições:

```
Request Header: X-Request-ID: 01ARZ3NDEKTSV4RRFFQ69G5FAV
Response Header: X-Request-ID: 01ARZ3NDEKTSV4RRFFQ69G5FAV
```

### Usar Request ID em Logs

```php
// Automaticamente incluído em StructuredLoggingService
$this->logger->info('Processing request', ['order_id' => $orderId]);

// Resultado:
// {
//   "request_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
//   "order_id": "order-123",
//   ...
// }
```

### Rastrear Requisições Específicas

```bash
# Buscar todos os logs de uma requisição específica
grep "01ARZ3NDEKTSV4RRFFQ69G5FAV" storage/logs/laravel.log

# No banco de dados
AuditLog::where('request_id', '01ARZ3NDEKTSV4RRFFQ69G5FAV')->get();
```

---

## 💚 Health Checks

### Endpoint de Health

```
GET /up
```

### Resposta (Healthy)

```json
{
  "status": "healthy",
  "timestamp": "2026-08-13T21:15:00Z",
  "version": "1.0.0",
  "checks": {
    "database": {
      "status": "healthy",
      "message": "Database connection OK"
    },
    "cache": {
      "status": "healthy",
      "message": "Cache connection OK"
    }
  }
}
```

### Resposta (Degraded)

```json
{
  "status": "degraded",
  "timestamp": "2026-08-13T21:15:00Z",
  "version": "1.0.0",
  "checks": {
    "database": {
      "status": "healthy",
      "message": "Database connection OK"
    },
    "cache": {
      "status": "unhealthy",
      "message": "Cache connection failed: Connection refused"
    }
  }
}
```

### Monitoramento

```bash
# Health check
curl -s https://api.lucraone.com/up | jq

# Webhook de status (exemplo)
# Enviar para Slack, PagerDuty, etc se status != healthy
```

---

## 🔒 Tenant Isolation

Todos os audit logs são automaticamente isolados por tenant:

```php
// User do Tenant A
$userA = auth()->user();  // tenant_id = A

// Log será criado com tenant_id = A
AuditLog::logAction('create', 'User', 'user-123');

// Outro tenant não pode ver este log
AuditLog::forTenant('tenant-b-id')->get();  // Vazio
```

---

## 📈 Performance

### Índices Criados

```sql
audit_logs:
  - (tenant_id)
  - (tenant_id, entity_type)
  - (tenant_id, user_id)
  - (tenant_id, created_at)
  - (request_id)
```

### Optimizações

1. **Paginação:** Use cursor pagination para grandes volumes
   ```php
   $logs = AuditLog::forTenant($tenantId)
       ->cursorPaginate(50);
   ```

2. **Retenção:** Archive logs antigos mensalmente
   ```php
   AuditLog::where('created_at', '<', now()->subMonths(1))
       ->delete();
   ```

3. **Índices Customizados:** Para queries frequentes
   ```sql
   CREATE INDEX idx_audit_user_action 
   ON audit_logs(tenant_id, user_id, action);
   ```

---

## 🧪 Testes

### Testes Inclusos

**AuditLogTest.php** (10 testes)
- ✅ AuditLog criado com dados corretos
- ✅ Captura IP e User-Agent
- ✅ Captura request_id
- ✅ Isolamento por tenant
- ✅ Filtro por usuário
- ✅ Filtro por entity
- ✅ Sem usuário autenticado
- ✅ Armazena changes como JSON
- ✅ Múltiplas ações em sequência
- ✅ Descrição de log

**HealthCheckTest.php** (5 testes)
- ✅ Retorna JSON
- ✅ Inclui timestamp
- ✅ Database check
- ✅ Cache check
- ✅ Database check passa

**Total:** 15 testes, 100% passing

---

## 📝 Padrões de Desenvolvimento

### Auditoria em Models

```php
// Observer para auditar mudanças automáticas
class UserObserver
{
    public function created(User $user)
    {
        AuditLog::logAction('create', 'User', $user->id,
            description: "Created user {$user->email}");
    }

    public function updated(User $user)
    {
        AuditLog::logAction('update', 'User', $user->id,
            ['changes' => $user->getChanges()]);
    }

    public function deleted(User $user)
    {
        AuditLog::logAction('delete', 'User', $user->id,
            description: "Deleted user {$user->email}");
    }
}
```

### Auditoria em Controllers

```php
public function store(StoreRoleRequest $request, StructuredLoggingService $logger)
{
    $role = Role::create($request->validated());
    
    $logger->auditLog('create', 'Role', $role->id,
        description: "Created role {$role->name}");
    
    return new RoleResource($role);
}
```

---

## ⚠️ Considerações de Segurança

### Dados Sensíveis

❌ NUNCA registre:
- Senhas
- Tokens
- Números de cartão
- SSN/CPF

✅ FAÇA registre:
- Ações de login/logout
- Tentativas de acesso negado
- Mudanças de permissão
- Exports de dados

### Acesso Aos Logs

- Apenas admins podem visualizar audit logs
- Aplicar policies para filtro por tenant
- Logs nunca podem ser editados (apenas inseridos)

---

## 🔄 Roadmap (Futuro)

- [ ] Retenção automática de logs (política)
- [ ] Análise de anomalias (tentativas de login falhadas)
- [ ] Relatório de atividades por usuário/tenant
- [ ] Integração com SIEM (Splunk, ELK)
- [ ] Webhook para eventos críticos
- [ ] Dashboard de activity feed

---

## 📞 Suporte

Para dúvidas sobre auditoria:

1. Verificar testes em `tests/Feature/Audit/`
2. Consultar modelo em `app/Modules/Audit/`
3. Revisar SPRINT_F1.6_PLAN.md para detalhes de design

---

*Última atualização: 2026-08-13 21:15 UTC*
