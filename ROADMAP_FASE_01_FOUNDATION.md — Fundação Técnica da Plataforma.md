# ROADMAP_FASE_01_FOUNDATION.md
## Fundação Técnica da Plataforma SaaS de Automação Comercial

**Projeto:** Plataforma SaaS Inteligente de Automação Comercial  
**Fase:** 01 — Fundação Técnica  
**Status:** ✅ COMPLETE (2026-08-16)  
**Prioridade:** Crítica  
**Dependência:** Nenhuma  
**Stack principal:** PHP + Laravel + MySQL + Redis + Docker  
**Métricas Finais:** 189 testes passando | 7/7 sprints concluídas | 100% completo  
**Objetivo da fase:** ✅ Criar uma base técnica segura, testável, auditável e preparada para receber todos os módulos posteriores do ERP.

---

# 1. Contexto

Esta é a primeira fase efetiva de desenvolvimento da plataforma.

Nenhum módulo de negócio complexo deverá ser desenvolvido antes que a fundação esteja minimamente estabilizada.

A plataforma futuramente terá módulos como:

```text
Product Core
Pricing
Inventory
Customers
PDV
Sync Engine
Payments
Fiscal
Purchasing
Financial
Restaurant
Bakery
Customer Intelligence
AI
```

Todos esses módulos dependerão diretamente das decisões tomadas nesta fase.

Portanto, esta fase deve priorizar:

> **consistência arquitetural, segurança, isolamento multi-tenant, testes, auditoria e capacidade de evolução.**

---

# 2. Objetivo Principal

Ao término desta fase deverá existir uma aplicação Laravel funcional capaz de:

```text
Subir o ambiente
    ↓
Conectar ao MySQL
    ↓
Conectar ao Redis
    ↓
Criar um Tenant
    ↓
Criar Empresa
    ↓
Criar Filial
    ↓
Criar Usuário
    ↓
Autenticar
    ↓
Identificar Tenant
    ↓
Autorizar acesso
    ↓
Registrar auditoria
    ↓
Responder pela API
```

Nenhuma regra de produto, estoque ou fiscal deverá ser necessária para considerar essa fase concluída.

---

# 3. Princípios da Fundação

A fundação deverá obedecer aos seguintes princípios:

1. **Multi-tenancy desde o primeiro dia.**
2. **API-first.**
3. **Autenticação centralizada.**
4. **Autorização por papéis e permissões.**
5. **Auditoria de operações sensíveis.**
6. **Configuração por ambiente.**
7. **Testes automatizados obrigatórios.**
8. **Nenhuma regra de negócio dentro de controllers.**
9. **Jobs devem conhecer o tenant que os originou.**
10. **IDs públicos não devem depender exclusivamente de inteiros sequenciais previsíveis.**
11. **Infraestrutura deve ser reproduzível.**
12. **Segurança não será tratada como fase posterior.**

---

# 4. Escopo da Fase

Esta fase contempla os seguintes grandes componentes:

```text
FASE 01 — FOUNDATION
│
├── Setup do Projeto
├── Arquitetura
├── Ambientes
├── Banco de Dados
├── Redis
├── Multi-Tenancy
├── Empresas
├── Filiais
├── Usuários
├── Autenticação
├── Roles & Permissions
├── API
├── Auditoria
├── Logs
├── Segurança
├── Testes
├── CI
└── Documentação
```

---

# 5. Fora de Escopo

Não deverão ser implementados nesta fase:

- produtos;
- categorias comerciais;
- preços;
- estoque;
- vendas;
- caixa;
- PDV;
- pagamentos;
- fiscal;
- NFC-e;
- fornecedores;
- compras;
- financeiro;
- cardápio digital;
- Customer Intelligence;
- IA.

Esses módulos terão roadmaps independentes.

---

# 6. Estrutura Inicial do Repositório

Sugestão:

```text
commercial-platform/
│
├── app/
├── bootstrap/
├── config/
├── database/
├── routes/
├── tests/
│
├── docs/
│   ├── architecture/
│   ├── roadmap/
│   ├── api/
│   ├── security/
│   └── adr/
│
├── docker/
│
├── .github/
│   └── workflows/
│
├── docker-compose.yml
├── README.md
├── SECURITY.md
└── ROADMAP.md
```

---

# 7. Arquitetura da Aplicação

Inicialmente utilizar:

> **Modular Monolith**

Não utilizar microserviços nesta fase.

Estrutura conceitual:

```text
app/
└── Modules/
    │
    ├── Core/
    ├── Tenancy/
    ├── Companies/
    ├── Branches/
    ├── Identity/
    ├── Authorization/
    └── Audit/
```

Cada domínio deverá possuir, conforme necessidade:

```text
Domain
Application
Infrastructure
Http
```

Exemplo:

```text
Modules/
└── Tenancy/
    ├── Domain/
    │   ├── Models/
    │   ├── Events/
    │   └── Exceptions/
    │
    ├── Application/
    │   ├── Actions/
    │   └── DTOs/
    │
    ├── Infrastructure/
    │   └── Persistence/
    │
    └── Http/
        ├── Controllers/
        ├── Requests/
        └── Resources/
```

O objetivo não é criar burocracia arquitetural.

O objetivo é impedir que daqui a alguns meses toda a aplicação esteja concentrada em:

```text
Controllers
Models
Services
```

sem domínio claramente definido.

---

# 8. Decisão Arquitetural — IDs

Para entidades importantes, utilizar identificadores globais não previsíveis.

Exemplo:

```text
UUID
```

ou:

```text
ULID
```

Sugestão:

> **ULID**

Exemplo:

```text
01K2M8Z7TQ9J3G5EPN4BH7XW1C
```

Benefícios:

- não expõe quantidade de registros;
- pode ser gerado independentemente;
- funciona melhor com sincronização;
- facilita referências distribuídas;
- adequado para futura comunicação com PDVs.

---

# 9. Banco de Dados

Banco principal:

```text
MySQL
```

Criar convenções desde o início.

Todas as tabelas de negócio multi-tenant deverão possuir:

```text
tenant_id
```

Quando necessário:

```text
company_id
branch_id
```

Exemplo:

```text
users
companies
branches
```

---

# 10. Modelo Tenant

Tabela:

```text
tenants
```

Campos iniciais:

```text
id
name
slug
status
plan
timezone
locale
active
created_at
updated_at
```

Possíveis estados:

```text
TRIAL
ACTIVE
SUSPENDED
CANCELLED
```

---

# 11. Regras de Tenant

Todo acesso deverá ocorrer dentro de um:

```text
TenantContext
```

Exemplo conceitual:

```text
Request
   ↓
Authentication
   ↓
Resolve Tenant
   ↓
TenantContext
   ↓
Application
```

Nenhuma consulta de negócio deverá executar sem tenant definido, salvo operações explicitamente administrativas da própria plataforma.

---

# 12. Tenant Resolver

Criar mecanismo responsável por determinar o tenant atual.

Possíveis estratégias futuras:

```text
subdomain
header
token
domain
```

Para a API inicial, pode ser utilizado:

```text
Tenant associado ao usuário autenticado
```

A arquitetura deverá permitir evolução posterior.

---

# 13. Isolamento de Tenant

Exemplo:

```text
Tenant A
```

nunca poderá acessar:

```text
Tenant B
```

mesmo que o usuário conheça o ID do registro.

Exemplo de tentativa:

```http
GET /api/v1/branches/01K...
```

Se a filial pertencer a outro tenant:

```text
404
```

ou comportamento equivalente definido pela política de segurança.

Nunca retornar informações que confirmem a existência do recurso de outro tenant.

---

# 14. Testes Obrigatórios de Isolamento

Criar testes como:

```text
tenant_A_cannot_access_tenant_B_company

tenant_A_cannot_update_tenant_B_branch

tenant_A_cannot_delete_tenant_B_user

tenant_scopes_are_applied

queued_job_executes_under_correct_tenant
```

Esses testes deverão fazer parte da suíte permanente de regressão.

---

# 15. Empresa

Tabela:

```text
companies
```

Campos iniciais:

```text
id
tenant_id
legal_name
trade_name
document
state_registration
municipal_registration
email
phone
status
created_at
updated_at
```

Campos fiscais mais complexos ficarão para o módulo fiscal.

---

# 16. Regras de Empresa

Um tenant poderá possuir:

```text
1..N empresas
```

Exemplo:

```text
Tenant
│
├── Empresa A
│
└── Empresa B
```

Isso permitirá futuramente cenários com múltiplos CNPJs.

---

# 17. Filiais

Tabela:

```text
branches
```

Campos:

```text
id
tenant_id
company_id
name
code
document_override nullable
timezone
status
created_at
updated_at
```

Relacionamento:

```text
Tenant
   ↓
Company
   ↓
Branch
```

Uma filial jamais poderá pertencer a uma empresa de outro tenant.

---

# 18. Endereços

Evitar espalhar campos como:

```text
street
number
city
zip_code
```

em várias entidades.

Criar estrutura reutilizável.

Exemplo:

```text
addresses
```

Relacionável inicialmente com:

```text
company
branch
```

Campos:

```text
street
number
complement
district
city
state
postal_code
country
```

---

# 19. Usuários

> ⚠️ **Revisado no Sprint F1.8 (2026-08-16).** O desenho original prendia cada
> usuário a um único tenant via `users.tenant_id`. Isso impedia o caso real de
> uma mesma pessoa atender mais de um estabelecimento — um dono com duas lojas,
> um contador com vários clientes. Ver seção 19.1.

Usuário é uma **identidade global**: uma pessoa, uma conta, uma senha.

Tabela:

```text
users
```

Campos:

```text
id
name
email          (único GLOBALMENTE)
password
status
email_verified_at
last_login_at
remember_token
created_at
updated_at
```

Estados da conta:

```text
ACTIVE      — a pessoa pode entrar no sistema
INACTIVE    — conta desativada; não entra em estabelecimento nenhum
```

---

# 19.1. Vínculo Usuário × Estabelecimento

O que liga uma pessoa a um estabelecimento é um vínculo próprio, com status
independente. Desativar alguém numa loja não afeta o acesso dela nas outras.

Tabela:

```text
tenant_user
```

Campos:

```text
id
tenant_id
user_id
status
joined_at
created_at
updated_at
```

Restrição:

```text
unique(tenant_id, user_id)   -- no máximo um vínculo por estabelecimento
```

Estados do vínculo:

```text
ACTIVE      — opera normalmente neste estabelecimento
INVITED     — convidado, ainda não aceitou; sem acesso
INACTIVE    — acesso revogado por este estabelecimento
SUSPENDED   — acesso suspenso temporariamente
```

**Regra de acesso:** operar num estabelecimento exige conta `ACTIVE`
**e** vínculo `ACTIVE`. As duas condições, sempre.

```php
$usuario->canAccessTenant($tenantId);   // conta ativa + vínculo ativo
$usuario->estabelecimentosDisponiveis(); // para o seletor de login
$usuario->joinTenant($tenantId, $status);
```

**Papéis são por estabelecimento.** A tabela `user_role` já tinha
`unique(user_id, role_id, tenant_id)` desde o F1.5 — o RBAC sempre assumiu
este modelo. A mesma pessoa pode ser `admin` numa loja e `viewer` em outra.

---

# 20. Relação Usuário × Empresa × Filial

Não assumir que um usuário trabalha somente em uma filial.

Criar arquitetura que permita:

```text
Usuário
  ↓
N empresas
  ↓
N filiais
```

Possível tabela:

```text
user_branch_access
```

Campos:

```text
user_id
branch_id
```

---

# 21. Autenticação

Para o primeiro momento:

```text
Laravel Sanctum
```

Casos:

```text
Login
Logout
Refresh strategy quando aplicável
Forgot password
Reset password
Password change
```

Endpoints:

```http
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
GET  /api/v1/auth/me
```

---

# 22. Política de Senhas

Definir minimamente:

- comprimento mínimo;
- confirmação;
- hash seguro;
- tentativa de login limitada;
- reset com expiração;
- invalidação adequada de tokens.

Nunca armazenar senha em texto puro.

---

# 23. Rate Limiting

Proteger:

```text
login
forgot-password
reset-password
```

e endpoints sensíveis.

Exemplo:

```text
5 tentativas
↓
janela configurável
↓
temporariamente bloqueado
```

---

# 24. Roles

Criar papéis iniciais.

Exemplo:

```text
OWNER
ADMIN
MANAGER
OPERATOR
VIEWER
```

Esses nomes poderão ser refinados futuramente.

---

# 25. Permissions

Permissões devem ser granulares.

Exemplos:

```text
company.view
company.create
company.update

branch.view
branch.create
branch.update

user.view
user.create
user.update
user.disable

role.view
role.manage
```

---

# 26. RBAC

Fluxo:

```text
User
 ↓
Role
 ↓
Permission
 ↓
Resource
```

Um usuário poderá possuir:

```text
1..N roles
```

dependendo da estratégia escolhida.

---

# 27. Escopo de Permissão

A arquitetura deverá permitir futuramente permissões como:

```text
Gerente pode administrar:
→ somente Filial 01
```

enquanto:

```text
Diretor:
→ todas as filiais
```

Portanto, autorização não deverá depender apenas de:

```text
role = admin
```

---

# 28. Auditoria

Criar tabela:

```text
audit_logs
```

Campos:

```text
id
tenant_id
user_id
action
entity_type
entity_id
old_values
new_values
ip
user_agent
created_at
```

Utilizar JSON para:

```text
old_values
new_values
```

---

# 29. Eventos Auditáveis Iniciais

Registrar:

```text
USER_LOGIN
USER_LOGIN_FAILED
USER_CREATED
USER_UPDATED
USER_DISABLED

COMPANY_CREATED
COMPANY_UPDATED

BRANCH_CREATED
BRANCH_UPDATED

ROLE_CHANGED
PERMISSION_CHANGED
```

---

# 30. Auditoria não é Log Técnico

Separar:

```text
Audit Log
```

de:

```text
Application Log
```

Audit Log responde:

> Quem mudou o quê?

Application Log responde:

> O que aconteceu tecnicamente?

---

# 31. Logs de Aplicação

Padronizar logs estruturados.

Exemplo:

```json
{
  "level": "error",
  "tenant_id": "01K...",
  "request_id": "req_...",
  "user_id": "01K...",
  "message": "Unexpected error"
}
```

Nunca registrar:

- senha;
- token;
- segredo;
- certificado;
- dados sensíveis desnecessários.

---

# 32. Correlation ID

Toda requisição deverá possuir identificador.

Exemplo:

```text
X-Request-ID
```

Fluxo:

```text
Request
 ↓
Request ID
 ↓
Controller
 ↓
Service
 ↓
Job
 ↓
Log
```

Isso será extremamente útil em produção.

---

# 33. Tratamento Global de Erros

Criar padrão de resposta.

Exemplo:

```json
{
  "error": {
    "code": "BRANCH_NOT_FOUND",
    "message": "Filial não encontrada.",
    "request_id": "..."
  }
}
```

Nunca retornar stack trace ao usuário em produção.

---

# 34. Versionamento de API

Desde o início:

```text
/api/v1/
```

Exemplo:

```http
GET /api/v1/companies
GET /api/v1/branches
GET /api/v1/users
```

Isso evitará problemas quando o PDV estiver instalado em várias versões.

---

# 35. API Resources

Padronizar saída através de:

```text
Laravel API Resources
```

Evitar retornar Models diretamente.

---

# 36. Validation Requests

Todas as entradas devem possuir:

```text
FormRequest
```

ou estrutura equivalente.

Nunca validar manualmente de forma dispersa nos controllers.

---

# 37. DTOs e Actions

Para operações relevantes utilizar:

```text
DTO
   ↓
Action
   ↓
Domain
```

Exemplo:

```text
CreateCompanyDTO
      ↓
CreateCompanyAction
      ↓
Company
```

Controller:

```text
Receber HTTP
Validar
Delegar
Responder
```

---

# 38. Controllers

Controllers devem ser finos.

Evitar:

```text
Controller
 ↓
200 linhas
 ↓
Query
 ↓
Business Rule
 ↓
Email
 ↓
Audit
```

Preferir:

```text
Controller
 ↓
Action
 ↓
Domain
```

---

# 39. Eventos de Domínio

Preparar estrutura para eventos.

Exemplo:

```text
CompanyCreated
BranchCreated
UserCreated
```

Inicialmente podem utilizar eventos internos do Laravel.

Futuramente poderão alimentar:

```text
Queues
Notifications
Customer Intelligence
Integrations
```

---

# 40. Redis

Responsabilidades:

```text
Cache
Queues
Locks
Rate Limiting
```

Não usar Redis como banco principal.

---

# 41. Queue

Criar fila desde a fundação.

Exemplo:

```text
default
notifications
audit
```

Posteriormente:

```text
fiscal
sync
intelligence
```

---

# 42. Jobs Multi-Tenant

Um Job deverá transportar:

```text
tenant_id
```

e restaurar o contexto antes de executar.

Exemplo:

```text
Job
 ↓
TenantContext
 ↓
Processamento
```

Adicionar testes específicos para isso.

---

# 43. Locks

Preparar Redis Locks para operações futuras.

Exemplo:

```text
fiscal_sequence
inventory_reconciliation
sync_device
```

Nesta fase basta validar a infraestrutura.

---

# 44. Cache

Criar convenção:

```text
tenant:{tenant_id}:resource:{id}
```

Evitar colisões de cache entre tenants.

---

# 45. Ambientes

Criar pelo menos:

```text
local
testing
staging
production
```

Nunca testar funcionalidades críticas diretamente em produção.

---

# 46. Configuração

Toda configuração sensível deverá utilizar:

```text
.env
```

ou serviço de secrets no futuro.

Nunca versionar:

```text
password
API key
certificate
token
```

---

# 47. Docker

Ambiente local recomendado:

```text
app
nginx
mysql
redis
queue-worker
```

Opcional:

```text
mailpit
```

Estrutura:

```text
docker-compose.yml
```

O projeto deverá ser executável de forma reproduzível.

---

# 48. Seeders

Criar seeders somente para:

```text
roles
permissions
dados técnicos
```

Dados comerciais de demonstração deverão ser separados.

---

# 49. Factories

Criar factories:

```text
TenantFactory
CompanyFactory
BranchFactory
UserFactory
```

Fundamental para testes.

---

# 50. Migrations

Migrations deverão ser:

- pequenas;
- reversíveis quando possível;
- revisadas;
- não destrutivas sem justificativa;
- testadas.

Não editar migrations já executadas em produção.

---

# 51. Soft Delete

Não utilizar Soft Deletes indiscriminadamente.

Avaliar entidade por entidade.

Provavelmente útil para:

```text
users
companies
branches
```

mas a política deverá ser definida explicitamente.

---

# 52. Datas e Horários

Banco:

```text
UTC
```

Apresentação:

```text
Timezone do tenant/filial
```

Exemplo:

```text
Banco:
2026-08-13T14:30:00Z

Exibição:
13/08/2026 11:30 America/Sao_Paulo
```

Isso será muito importante quando houver clientes em diferentes regiões.

---

# 53. Locale

Tenant poderá possuir:

```text
locale
timezone
currency
```

Inicialmente:

```text
pt-BR
America/Sao_Paulo
BRL
```

Arquitetura preparada para evolução.

---

# 54. Segurança — Mass Assignment

Revisar todos os Models.

Nunca permitir que campos sensíveis sejam enviados indiscriminadamente.

Exemplo crítico:

```text
tenant_id
role
is_admin
```

não devem ser controlados diretamente pelo cliente HTTP sem validação.

---

# 55. Segurança — SQL Injection

Utilizar:

```text
Eloquent
Query Builder
Prepared Statements
```

Evitar SQL concatenado.

---

# 56. Segurança — XSS

Mesmo sendo API-first, sanitizar e validar entradas apropriadas.

Frontend futuro não deverá confiar em conteúdo armazenado.

---

# 57. Segurança — CORS

Configurar explicitamente.

Não utilizar:

```text
*
```

indiscriminadamente em produção.

---

# 58. Segurança — Headers

Adicionar configuração apropriada para:

- HTTPS;
- HSTS quando produção estiver preparada;
- Content-Type;
- proteção de origem quando aplicável.

---

# 59. Segurança — Secrets

Criar documentação:

```text
docs/security/SECRETS.md
```

Definir:

- onde ficam;
- quem acessa;
- como rotacionar;
- como revogar.

---

# 60. Testes Unitários

Criar testes para regras isoladas.

Exemplos:

```text
TenantStatusTest
CompanyCreationTest
BranchOwnershipTest
PermissionTest
```

---

# 61. Testes Feature / API

Exemplos:

```text
user_can_login
invalid_credentials_are_rejected

owner_can_create_company
operator_cannot_create_company

user_can_list_own_tenant_branches
user_cannot_access_other_tenant_branch
```

---

# 62. Testes de Autorização

Obrigatórios:

```text
guest_cannot_access_authenticated_endpoint

inactive_user_cannot_login

user_without_permission_cannot_create_company

manager_only_accesses_allowed_branches
```

---

# 63. Testes Multi-Tenant

Esta é uma suíte crítica.

Criar um grupo dedicado:

```text
tests/Feature/Tenancy/
```

Cobrir:

```text
READ
CREATE
UPDATE
DELETE
LIST
SEARCH
JOBS
CACHE
```

---

# 64. Testes de Banco

Validar:

- foreign keys;
- unique indexes;
- tenant constraints;
- cascade rules;
- índices.

---

# 65. Testes de Queue

Testar:

```text
dispatch
tenant restoration
failure
retry
```

---

# 66. Teste de Redis

No CI deverá existir Redis real ou ambiente equivalente.

Não deixar integração crítica somente mockada.

---

# 67. Cobertura de Testes

Não utilizar apenas percentual como indicador.

Meta inicial sugerida:

```text
Core crítico: alta cobertura
```

Mais importante:

> fluxos críticos e regras de isolamento precisam ter testes explícitos.

---

# 68. Static Analysis

Adicionar ferramenta de análise estática PHP.

Objetivo:

- detectar erros;
- melhorar tipos;
- reduzir bugs;
- manter padrão.

Executar no CI.

---

# 69. Code Style

Utilizar padrão automático.

Exemplo:

```text
Laravel Pint
```

CI deverá rejeitar código fora do padrão.

---

# 70. CI

Pipeline mínimo:

```text
Checkout
 ↓
Install Dependencies
 ↓
Code Style
 ↓
Static Analysis
 ↓
Migrations
 ↓
Tests
 ↓
Build
```

Nenhum código deverá ser considerado pronto se o pipeline falhar.

---

# 71. Branch Strategy

Sugestão simples:

```text
main
develop opcional
feature/*
fix/*
```

Evitar processo excessivamente complexo enquanto o projeto tiver poucos desenvolvedores.

---

# 72. Commits

Adotar padrão consistente.

Exemplo:

```text
feat:
fix:
refactor:
test:
docs:
chore:
```

Exemplos:

```text
feat(tenancy): add tenant context middleware

test(tenancy): ensure tenant isolation

fix(auth): block inactive users
```

---

# 73. Pull Request Checklist

Mesmo desenvolvendo sozinho, utilizar PR ou checklist equivalente.

```text
- [ ] Código compila/executa
- [ ] Migrations revisadas
- [ ] Testes adicionados
- [ ] Testes passando
- [ ] Segurança avaliada
- [ ] Tenant isolation verificado
- [ ] Logs revisados
- [ ] Documentação atualizada
```

---

# 74. Documentação Arquitetural

Criar:

```text
docs/architecture/ARCHITECTURE.md
```

Explicar:

- modular monolith;
- multi-tenancy;
- Laravel;
- MySQL;
- Redis;
- API;
- futuro PDV.

---

# 75. ADRs

Utilizar:

```text
Architecture Decision Records
```

Exemplo:

```text
ADR-001-use-laravel.md
ADR-002-use-mysql.md
ADR-003-shared-database-multitenancy.md
ADR-004-use-ulid.md
ADR-005-use-redis.md
```

Cada ADR:

```text
Context
Decision
Alternatives
Consequences
```

---

# 76. OpenAPI

Criar documentação da API desde o início.

Preferência:

```text
OpenAPI 3.x
```

Primeiros endpoints deverão estar documentados.

Isso será essencial posteriormente para o PDV .NET.

---

# 77. Endpoints da Fundação

Exemplo:

```http
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

Tenant:

```http
GET /api/v1/tenant
```

Companies:

```http
GET    /api/v1/companies
POST   /api/v1/companies
GET    /api/v1/companies/{id}
PATCH  /api/v1/companies/{id}
```

Branches:

```http
GET    /api/v1/branches
POST   /api/v1/branches
GET    /api/v1/branches/{id}
PATCH  /api/v1/branches/{id}
```

Users:

```http
GET    /api/v1/users
POST   /api/v1/users
GET    /api/v1/users/{id}
PATCH  /api/v1/users/{id}
```

---

# 78. Health Checks

Criar:

```http
GET /health
```

Testar:

```text
Application
MySQL
Redis
Queue
```

Resposta interna conceitual:

```text
APP      OK
MYSQL    OK
REDIS    OK
QUEUE    OK
```

Não expor informações sensíveis publicamente.

---

# 79. Readiness e Liveness

Preparar:

```text
/health/live
/health/ready
```

Útil futuramente para containers e orquestração.

---

# 80. Observabilidade Inicial

Mesmo antes de uma plataforma completa de observabilidade, registrar:

```text
request_id
tenant_id
user_id
endpoint
duration
status
```

Não armazenar payload completo indiscriminadamente.

---

# 81. Performance Baseline

Estabelecer métricas iniciais.

Exemplo:

```text
Login: < 500 ms
Listagem simples: < 300 ms
Criação simples: < 500 ms
```

Não são SLAs definitivos.

Servem como baseline para detectar regressões.

---

# 82. Índices MySQL

Revisar desde esta fase:

```text
tenant_id
tenant_id + status
tenant_id + email
company_id
branch_id
```

Evitar esperar o sistema ficar lento para começar a pensar em índices.

---

# 83. Paginação

Toda listagem potencialmente grande deverá possuir paginação.

Evitar:

```text
SELECT * FROM users
```

com crescimento ilimitado.

---

# 84. Filtros

Criar padrão para APIs.

Exemplo:

```http
GET /api/v1/users?status=ACTIVE&search=jose
```

Padronizar comportamento.

---

# 85. Ordenação

Padronizar:

```http
?sort=name
?sort=-created_at
```

Caso essa convenção seja adotada, documentá-la.

---

# 86. API Error Codes

Criar catálogo.

Exemplo:

```text
AUTH_INVALID_CREDENTIALS
AUTH_USER_BLOCKED

TENANT_NOT_FOUND

COMPANY_NOT_FOUND
BRANCH_NOT_FOUND

VALIDATION_ERROR
FORBIDDEN
```

---

# 87. Seed Inicial de Permissões

Criar algo como:

```text
OWNER
└── todas

ADMIN
├── company.*
├── branch.*
└── user.*

MANAGER
├── branch.view
├── user.view
└── future operational permissions

OPERATOR
└── future POS permissions
```

---

# 88. Owner Inicial

No onboarding técnico:

```text
Tenant
 ↓
Company
 ↓
Owner User
```

deverá ser criado de forma transacional.

Se algo falhar:

```text
ROLLBACK
```

Evitar tenant parcialmente provisionado.

---

# 89. Provisionamento

Criar Action:

```text
ProvisionTenantAction
```

Responsável por:

```text
Tenant
Company
Branch inicial
Owner
Roles
Permissions
```

Executar em transação.

---

# 90. Transações

Operações compostas críticas deverão usar:

```text
DB transaction
```

Exemplo:

```text
Create Tenant
+
Create Company
+
Create Owner
```

ou tudo conclui ou nada fica persistido.

---

# 91. Idempotência do Provisionamento

Preparar proteção contra submissão dupla.

Isso será importante futuramente para onboarding e billing.

---

# 92. Email

Configurar camada de email.

Local:

```text
Mailpit
```

Casos:

```text
reset password
user invitation
```

Não incluir marketing nesta fase.

---

# 93. Convite de Usuário

Fluxo recomendado:

```text
Admin cria usuário
 ↓
Sistema envia convite
 ↓
Usuário cria senha
 ↓
Conta ACTIVE
```

Evitar administrador definir senha permanente de outro usuário.

---

# 94. Audit Trail do Usuário

Registrar:

```text
created_by
updated_by
```

quando apropriado, além do audit log geral.

---

# 95. Política de Exclusão

Na fundação, preferir:

```text
desativação
```

em vez de excluir entidades principais.

Exemplo:

```text
User → INACTIVE
Branch → INACTIVE
Company → INACTIVE
```

Evita perda histórica futura.

---

# 96. Backup

Mesmo antes de produção real, documentar estratégia.

```text
MySQL
 ↓
backup
 ↓
retenção
 ↓
restore test
```

Backup não validado por restauração não deve ser considerado confiável.

---

# 97. Restore

Criar procedimento:

```text
docs/operations/DATABASE_RESTORE.md
```

e realizar pelo menos um teste de restauração antes da entrada em produção.

---

# 98. Segurança de Dependências

Adicionar verificação de vulnerabilidades nas dependências.

Pipeline deverá sinalizar dependências vulneráveis.

---

# 99. README

README deverá conter:

```text
Visão do projeto
Stack
Pré-requisitos
Instalação
Docker
Environment
Migrations
Seed
Tests
Architecture
API docs
```

---

# 100. ROADMAP.md

O roadmap macro deverá apontar esta fase como:

```text
FASE 01 — FOUNDATION
Status: IN_PROGRESS
```

Quando finalizada:

```text
Status: DONE
```

com referência para:

```text
ROADMAP_FASE_01_FOUNDATION.md
```

---

# 101. Estratégia de Implementação em Sprints

Sugestão:

## Sprint F1.1 — Bootstrap

Implementar:

- Laravel;
- MySQL;
- Redis;
- Docker;
- configuração;
- lint;
- static analysis;
- CI;
- estrutura modular.

### Resultado

Aplicação sobe e pipeline passa.

---

## Sprint F1.2 — Tenancy

Implementar:

- Tenant;
- TenantContext;
- TenantResolver;
- scopes;
- middleware;
- testes de isolamento.

### Resultado

Dois tenants coexistem sem acesso cruzado.

---

## Sprint F1.3 — Empresas e Filiais

Implementar:

- Company;
- Branch;
- Address;
- CRUD;
- validações;
- policies;
- auditoria;
- testes.

### Resultado

Estrutura organizacional funcionando.

---

## Sprint F1.4 — Identity

Implementar:

- User;
- login;
- logout;
- reset;
- convite;
- status;
- tokens.

### Resultado

Usuários autenticados com segurança.

---

## Sprint F1.5 — Authorization

Implementar:

- roles;
- permissions;
- policies;
- branch access;
- testes.

### Resultado

Usuário acessa somente operações autorizadas.

---

## Sprint F1.6 — Audit & Observability

Implementar:

- Audit Log;
- request ID;
- logs estruturados;
- health checks;
- error handling.

### Resultado

Operações críticas rastreáveis.

---

## Sprint F1.7 — Hardening

Executar:

- auditoria de segurança;
- testes cross-tenant;
- testes API;
- performance baseline;
- documentação;
- backup/restore;
- revisão final.

### Resultado

Fundação pronta para Product Core.

---

# 102. Cenários de Teste Críticos

## Cenário 01 — Isolamento

```text
Given:
Tenant A possui Empresa A

And:
Tenant B possui Empresa B

When:
Usuário A tenta acessar Empresa B

Then:
Acesso negado
And:
Nenhum dado de Empresa B é retornado
```

---

## Cenário 02 — Usuário Inativo

```text
Given:
Usuário está INACTIVE

When:
Tenta autenticar

Then:
Login é recusado
And:
Tentativa é registrada
```

---

## Cenário 03 — Permissão

```text
Given:
Usuário possui role VIEWER

When:
Tenta criar filial

Then:
403 Forbidden
```

---

## Cenário 04 — Job Multi-Tenant

```text
Given:
Job pertence ao Tenant A

When:
Worker executa o job

Then:
TenantContext = Tenant A
And:
Nenhum dado de outro tenant é acessado
```

---

## Cenário 05 — Cache

```text
Given:
Tenant A e Tenant B possuem recursos com mesmo identificador interno

Then:
Cache de A não pode retornar objeto de B
```

---

## Cenário 06 — Provisionamento

```text
Given:
Falha ocorre na criação do Owner

When:
ProvisionTenantAction falha

Then:
Tenant, Company e Branch não permanecem parcialmente criados
```

---

# 103. Teste de Segurança da Fase

Antes do encerramento, executar auditoria específica para:

```text
Authentication
Authorization
Tenant Isolation
Mass Assignment
IDOR
Rate Limiting
Sensitive Logs
Secrets
SQL Injection
Error Disclosure
```

Especial atenção para:

> **IDOR + multi-tenancy.**

Um usuário jamais pode alterar o ID de uma URL e acessar dados de outro cliente.

---

# 104. Testes de Performance

Executar baseline mínimo:

```text
100 usuários concorrentes
```

em operações simples como:

```text
login
me
list companies
list branches
list users
```

O objetivo não é provar escala final.

É criar uma referência inicial.

---

# 105. Critérios de Aceite

A fase poderá ser considerada aprovada somente se:

- aplicação Laravel operacional;
- MySQL funcionando;
- Redis funcionando;
- ambiente Docker reproduzível;
- CI ativo;
- multi-tenancy implementado;
- isolamento testado;
- empresas implementadas;
- filiais implementadas;
- usuários implementados;
- autenticação implementada;
- autorização implementada;
- auditoria implementada;
- logs estruturados;
- queues operacionais;
- health checks;
- API versionada;
- OpenAPI inicial;
- testes automatizados passando;
- análise estática passando;
- code style passando;
- segurança revisada;
- documentação atualizada;
- procedimento de backup documentado;
- restauração validada.

---

# 106. Definition of Done da Fase

```text
IMPLEMENTAÇÃO
      ↓
UNIT TESTS
      ↓
FEATURE TESTS
      ↓
TENANT ISOLATION TESTS
      ↓
SECURITY REVIEW
      ↓
STATIC ANALYSIS
      ↓
REGRESSION
      ↓
PERFORMANCE BASELINE
      ↓
DOCUMENTATION
      ↓
BACKUP / RESTORE TEST
      ↓
FINAL AUDIT
      ↓
DONE
```

---

# 107. Checklist Final de Auditoria

## Arquitetura

- [ ] Modular Monolith documentado
- [ ] Módulos iniciais separados
- [ ] Controllers sem regras complexas
- [ ] Actions/Services organizados
- [ ] ADRs criados

## Banco

- [ ] Migrations revisadas
- [ ] Foreign Keys
- [ ] Índices
- [ ] Tenant ID
- [ ] Constraints
- [ ] Factories
- [ ] Seeders

## Multi-Tenancy

- [ ] TenantContext
- [ ] TenantResolver
- [ ] Isolamento
- [ ] Jobs
- [ ] Cache
- [ ] APIs
- [ ] Testes cross-tenant

## Segurança

- [ ] Autenticação
- [ ] Autorização
- [ ] Rate limiting
- [ ] Secrets
- [ ] Mass assignment
- [ ] IDOR
- [ ] Logs sensíveis
- [ ] Error handling

## Qualidade

- [ ] Unit Tests
- [ ] Feature Tests
- [ ] Integration Tests
- [ ] Static Analysis
- [ ] Pint
- [ ] CI

## Operação

- [ ] Logs
- [ ] Health Check
- [ ] Queue
- [ ] Redis
- [ ] Backup
- [ ] Restore

## Documentação

- [ ] README
- [ ] Architecture
- [ ] API
- [ ] ADRs
- [ ] Security
- [ ] Roadmap atualizado

---

# 108. Entregável Final

Ao final desta fase deverá ser possível demonstrar:

```text
Usuário acessa plataforma
        ↓
Login
        ↓
Tenant identificado
        ↓
Empresa carregada
        ↓
Filiais disponíveis
        ↓
Permissões verificadas
        ↓
Operação realizada
        ↓
Audit Log registrado
        ↓
API responde
```

E simultaneamente provar:

```text
Tenant A
   X
Tenant B
```

não possuem qualquer possibilidade legítima de cruzamento de dados.

---

# 109. Próxima Fase

Após aprovação completa da Fundação, iniciar:

```text
FASE 02 — PRODUCT CORE
```

O próximo roadmap deverá detalhar:

```text
Produtos
Categorias
Marcas
Unidades
SKU
GTIN
Variações
Grade
Produtos por peso
Produtos compostos
Kit/Combo
Classificação fiscal
NCM
CEST
Estrutura de preços
Auditoria
Importação
APIs
Testes
```

Essa fase deverá utilizar toda a infraestrutura de:

```text
Tenant
Company
Branch
Identity
Authorization
Audit
API
Tests
```

construída na Fundação.

---

# 110. Princípio de Encerramento

> **A Fundação não está pronta quando é possível cadastrar uma empresa. Ela está pronta quando é possível provar, por testes e auditoria, que empresas, filiais e usuários estão corretamente isolados, protegidos, rastreáveis e preparados para sustentar os módulos críticos que virão depois.**

O objetivo desta fase é construir uma base que permita desenvolver cada módulo subsequente com confiança, sem precisar reconstruir arquitetura, autenticação, multi-tenancy, auditoria e padrões técnicos a cada nova funcionalidade.