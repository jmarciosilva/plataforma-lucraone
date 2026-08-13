# Arquitetura LUCRAONE

**Versão:** 1.0  
**Status:** Foundation Phase  
**Data:** 2026-08-13

---

## 1. Visão Geral

LUCRAONE é uma **plataforma SaaS de automação comercial** construída em **Laravel** com arquitetura **Modular Monolith**, preparada para escalar horizontalmente e futuramente evoluir para microserviços.

### Tecnologias Principais

```
Frontend (Futuro)
       ↓
REST API v1 (Laravel)
       ↓
MySQL (Dados)
       ↓
Redis (Cache, Filas)
       ↓
PDV .NET (Futuro)
```

---

## 2. Padrão Arquitetural: Modular Monolith

### 2.1 Visão Geral

```
app/
└── Modules/
    ├── Core/                # Utilitários e configurações centrais
    ├── Tenancy/             # Contexto multi-tenant
    ├── Companies/           # Empresas/Clientes
    ├── Branches/            # Filiais/Lojas
    ├── Identity/            # Autenticação e usuários
    ├── Authorization/       # Papéis e permissões
    └── Audit/               # Auditoria e logs
```

### 2.2 Estrutura Interna de um Módulo

```
Module/
├── Domain/                  # Lógica de negócio e modelos
│   ├── Models/              # Eloquent Models
│   ├── Events/              # Domain Events
│   └── Exceptions/          # Domain Exceptions
│
├── Application/             # Casos de uso e DTOs
│   ├── Actions/             # Operações compostas
│   └── DTOs/                # Data Transfer Objects
│
├── Infrastructure/          # Persistência e externos
│   └── Persistence/         # Repositories e queries
│
└── Http/                    # Interface HTTP
    ├── Controllers/         # Controllers finos
    ├── Requests/            # Form Requests (validação)
    └── Resources/           # API Resources (serialização)
```

### 2.3 Fluxo de uma Operação HTTP

```
Request
   ↓
Middleware (Tenant resolution)
   ↓
HTTP/Requests (Validação)
   ↓
Controller (Receber → Delegar → Responder)
   ↓
Action (Orquestrar caso de uso)
   ↓
Domain (Regra de negócio)
   ↓
Persistence (Repository/Model)
   ↓
MySQL (Banco de dados)
   ↓
Response (API Resource)
```

---

## 3. Multi-Tenancy

### 3.1 Estratégia: Database Compartilizado com `tenant_id`

Todos os dados incluem coluna `tenant_id` para isolamento lógico.

```sql
users
├── id (ULID)
├── tenant_id (FK → tenants)
├── email
├── password
└── ...

companies
├── id (ULID)
├── tenant_id (FK → tenants)
├── name
└── ...
```

### 3.2 Mecanismos de Isolamento

#### 1. **TenantContext**
Middleware que resolve qual tenant está sendo usado na requisição.

```php
// Em request
$tenantId = auth()->user()->tenant_id;
app('tenant.context')->set($tenantId);
```

#### 2. **Global Scopes**
Todos os modelos multi-tenant aplicam filtro automático.

```php
class User extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $query->where('tenant_id', app('tenant.context')->id());
        });
    }
}
```

#### 3. **Policies**
Verificam propriedade do recurso antes de permitir ação.

```php
class CompanyPolicy
{
    public function update(User $user, Company $company)
    {
        return $user->tenant_id === $company->tenant_id;
    }
}
```

#### 4. **Middleware**
Aplicado a todas as rotas para rejeitar acesso não autorizado.

```php
// Verificar se recurso pertence ao tenant autenticado
```

#### 5. **Testes Obrigatórios**
Validam isolamento em cada operação.

```php
public function test_tenant_a_cannot_access_tenant_b_company()
{
    // Tenant A tenta acessar company de Tenant B
    // Resultado: 404 ou erro
}
```

### 3.3 Boas Práticas

✅ **Faça:**
- Sempre incluir `tenant_id` em queries
- Usar global scopes
- Testar isolamento cross-tenant
- Validar propriedade em policies

❌ **Não Faça:**
- Confiar apenas em `where('tenant_id', ...)` manual
- Esquecer global scopes
- Assumir que middleware resolve tudo
- Retornar dados de outro tenant (mesmo para provar isolamento)

---

## 4. Autenticação: Laravel Sanctum

### 4.1 Fluxo

```
POST /api/v1/auth/login (credenciais)
   ↓
Validar usuário
   ↓
Gerar token (API token)
   ↓
Retornar token
   ↓
Cliente usa: Authorization: Bearer {token}
```

### 4.2 Operações

- `POST /api/v1/auth/login` — Autenticar
- `POST /api/v1/auth/logout` — Desautenticar
- `GET /api/v1/auth/me` — Dados do usuário
- `POST /api/v1/auth/forgot-password` — Solicitar reset
- `POST /api/v1/auth/reset-password` — Definir nova senha

### 4.3 Proteção

- Rate limiting em login (5 tentativas / 15 min)
- Hash bcrypt com 12 rounds
- Tokens com expiração configurável
- Invalidação ao logout

---

## 5. Autorização: RBAC (Role-Based Access Control)

### 5.1 Estrutura

```
User
  ↓ (has many)
Role
  ↓ (has many)
Permission
  ↓ (protege)
Resource/Action
```

### 5.2 Papéis Iniciais

| Papel | Descrição | Permissões |
|-------|-----------|-----------|
| OWNER | Proprietário do tenant | Todas |
| ADMIN | Administrador | Empresas, filiais, usuários, configurações |
| MANAGER | Gerente | Dados da filial, usuários da filial |
| OPERATOR | Operador | Operações do dia-a-dia (futuro: vendas, caixa) |
| VIEWER | Visualizador | Leitura apenas |

### 5.3 Permissões Granulares

```
company.view
company.create
company.update
company.delete

branch.view
branch.create
branch.update
branch.delete

user.view
user.create
user.update
user.delete
user.disable
```

### 5.4 Exemplo de Policy

```php
class CompanyPolicy
{
    public function view(User $user, Company $company)
    {
        // Verificar tenant + permissão
        return $user->tenant_id === $company->tenant_id
            && $user->can('company.view');
    }

    public function update(User $user, Company $company)
    {
        return $user->tenant_id === $company->tenant_id
            && $user->can('company.update');
    }
}
```

---

## 6. Auditoria

### 6.1 Dois Conceitos Distintos

#### Audit Log (Quem fez o quê?)

```
user_id | action | entity | old_values | new_values | created_at
```

Rastreia ações de usuários em entidades críticas.

**Eventos auditáveis:**
- `USER_LOGIN` / `USER_LOGIN_FAILED`
- `COMPANY_CREATED` / `COMPANY_UPDATED`
- `BRANCH_CREATED` / `BRANCH_UPDATED`
- `USER_CREATED` / `USER_UPDATED` / `USER_DISABLED`
- `ROLE_CHANGED` / `PERMISSION_CHANGED`

#### Application Log (O que aconteceu?)

```json
{
  "level": "error",
  "channel": "stack",
  "request_id": "req_...",
  "tenant_id": "01K...",
  "message": "Database connection failed"
}
```

Rastreia eventos técnicos da aplicação.

### 6.2 Padrão de Estrutura de Log

```php
Log::info('Operação importante', [
    'request_id' => request()->header('X-Request-ID'),
    'tenant_id' => auth()->user()->tenant_id,
    'user_id' => auth()->user()->id,
    'action' => 'company.created',
    'data' => [...],
    'timestamp' => now()->toIso8601String(),
]);
```

---

## 7. API REST

### 7.1 Versionamento

Todas as rotas começam com `/api/v1/`

```
/api/v1/auth/...
/api/v1/companies/...
/api/v1/branches/...
/api/v1/users/...
```

### 7.2 Padrão de Resposta

**Sucesso (200)**

```json
{
  "data": {
    "id": "01K...",
    "name": "Empresa X",
    ...
  }
}
```

**Erro (4xx/5xx)**

```json
{
  "error": {
    "code": "COMPANY_NOT_FOUND",
    "message": "Filial não encontrada.",
    "request_id": "req_abc123"
  }
}
```

### 7.3 Recursos (API Resources)

Todos os modelos retornados via API usam **API Resources** para serialização consistente.

```php
class CompanyResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tenant_id' => $this->tenant_id,
            ...
        ];
    }
}
```

---

## 8. Banco de Dados: MySQL

### 8.1 Convenções

- **Identificadores:** ULID (não previsível, ordenável)
- **Multi-tenant:** coluna `tenant_id` em tabelas relacionadas a dados de negócio
- **Timestamps:** `created_at`, `updated_at` (UTC)
- **Status:** quando aplicável, coluna `status` com ENUM

### 8.2 Exemplo de Tabela

```sql
CREATE TABLE companies (
    id CHAR(26) PRIMARY KEY,
    tenant_id CHAR(26) NOT NULL,
    legal_name VARCHAR(255) NOT NULL,
    trade_name VARCHAR(255),
    document VARCHAR(20),
    email VARCHAR(255),
    status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_document_per_tenant (tenant_id, document),
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_created_at (created_at)
);
```

### 8.3 Migrations

- Pequenas e reversíveis
- Nunca alterar migrations após produção
- Sempre testar rollback

---

## 9. Cache: Redis

### 9.1 Responsabilidades

```
Cache       → Reduzir queries repetidas
Queues      → Processamento assíncrono
Locks       → Operações críticas
Rate Limit  → Proteção de abuso
```

### 9.2 Convenção de Chave

```
lucraone_cache_{module}:{tenant_id}:{resource_id}
```

**Exemplo:**
```
lucraone_cache_company:01K2M8Z7TQ9J3G5EPN4BH7XW1C:01K2M8Z7TQ9J3G5EPN4BH7XW2D
```

### 9.3 Invalidação

Sempre invalidar ao atualizar:

```php
Cache::forget("lucraone_cache_company:{$tenantId}:{$companyId}");
```

---

## 10. Filas: Redis + Laravel Queues

### 10.1 Canais (Channels)

```
default       → Operações gerais
notifications → Emails e notificações
audit         → Processamento de audit logs
fiscal        → Futuro: emissão de fiscal
sync          → Futuro: sincronização PDV
```

### 10.2 Exemplo de Job

```php
class SendWelcomeEmail implements ShouldQueue
{
    public function __construct(
        public User $user,
        public string $tenantId
    ) {}

    public function handle()
    {
        // Restaurar contexto de tenant
        app('tenant.context')->set($this->tenantId);

        // Processar
        Mail::send(new WelcomeEmail($this->user));
    }
}
```

---

## 11. Testes

### 11.1 Tipos

- **Unit:** Testes de classe isolada
- **Feature:** Testes de caso de uso (HTTP)
- **Integration:** Testes envolvendo múltiplos componentes
- **Tenancy Isolation:** Testes específicos de multi-tenant

### 11.2 Exemplo: Tenant Isolation

```php
public function test_tenant_a_cannot_access_company_of_tenant_b()
{
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    
    $userA = User::factory()->for($tenantA)->create();
    $companyB = Company::factory()->for($tenantB)->create();
    
    $this->actingAs($userA)
        ->getJson("/api/v1/companies/{$companyB->id}")
        ->assertNotFound();
}
```

---

## 12. Segurança

### 12.1 Checklist

- [x] Autenticação obrigatória em endpoints de dados
- [x] Autorização por policies
- [x] Isolamento multi-tenant em cada query
- [ ] Rate limiting
- [ ] CORS configurado
- [ ] HTTPS em produção
- [ ] Secrets seguros (env vars)
- [ ] SQL Injection prevention (Eloquent)
- [ ] Mass assignment protection
- [ ] IDOR prevention
- [ ] Error handling sem exposição de stack trace

### 12.2 IDOR Prevention

Sempre validar propriedade antes de modificar:

```php
// ❌ ERRADO
$company = Company::findOrFail($id);

// ✅ CORRETO
$company = Company::where('id', $id)
    ->where('tenant_id', auth()->user()->tenant_id)
    ->firstOrFail();
```

---

## 13. Observabilidade

### 13.1 Request ID

Toda requisição recebe identificador único:

```
X-Request-ID: req_abc123xyz789
```

Usado em logs e auditoria para rastreabilidade.

### 13.2 Health Checks

```
GET /health          → Status geral
GET /health/live     → Liveness (app em pé)
GET /health/ready    → Readiness (pronto para receber tráfego)
```

---

## 14. Evolução Arquitetural

### 14.1 Futuros Passos

```
Fase 01: Foundation ✅ (atual)
    ↓
Fase 02: Product Core
    ↓
Fase 03-11: Domínios de negócio
    ↓
Fase 12-23: Especializações e inteligência
    ↓
Fase 24+: Escalabilidade e integração
```

### 14.2 Para Microserviços

Se/quando necessário:

1. Cada módulo pode se tornar um serviço independente
2. API Gateway coordena requisições
3. Event bus (RabbitMQ/Kafka) sincroniza dados críticos
4. Sem quebra de contratos (APIs)

---

## Referências

- **Laravel Documentation:** https://laravel.com/docs
- **ADR-001:** Modular Monolith decision
- **Roadmap:** ROADMAP_FASE_01_FOUNDATION.md
- **Security:** docs/security/SECURITY.md

---

**Última atualização:** 2026-08-13
