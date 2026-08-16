# LUCRAONE — Plataforma SaaS Inteligente de Automação Comercial

**Versão:** 0.1.0 (Foundation Phase)  
**Status:** ✅ COMPLETE (FASE 01)  
**Data:** 2026-08-16

---

## Visão Geral

LUCRAONE é uma plataforma SaaS de automação comercial inteligente, desenvolvida para atender múltiplos segmentos do varejo brasileiro (supermercados, restaurantes, lojas, etc.).

### Stack Principal

- **Backend:** PHP 8.3 + Laravel 13
- **Banco de Dados:** MySQL 8.4
- **Cache / Filas:** Redis 7
- **Infraestrutura:** Docker
- **API:** REST/HTTPS (versionada)
- **Autenticação:** Laravel Sanctum

---

## Arquitetura

### Padrão: Modular Monolith

```
app/Modules/
├── Core/              # Utilitários e funcionalidades centrais
├── Tenancy/           # Multi-tenancy
├── Companies/         # Empresas
├── Branches/          # Filiais
├── Identity/          # Autenticação
├── Authorization/     # Autorização e permissões
└── Audit/             # Auditoria
```

Cada módulo possui estrutura:
```
Module/
├── Domain/            # Modelos de domínio e regras
├── Application/       # Actions e DTOs
├── Infrastructure/    # Persistência
└── Http/              # Controllers, Requests, Resources
```

### Multi-Tenancy

- **Estratégia:** Database com isolamento por `tenant_id`
- **Mecanismos:** TenantContext, Global Scopes, Policies, Middleware
- **IDs:** ULID para não previsibilidade
- **Isolamento:** Testado obrigatoriamente em cada operação

---

## Pré-requisitos

- Docker & Docker Compose
- Git
- PHP 8.3+ (local, opcional se usar Docker)
- Composer (local, opcional se usar Docker)

---

## Instalação

### 1. Clone o repositório

```bash
git clone <repositório>
cd lucraone-backend
```

### 2. Configure o ambiente

```bash
cp .env.example .env
# Edite .env se necessário
```

### 3. Docker

```bash
docker-compose up -d
```

Aguarde MySQL e Redis ficarem saudáveis (verificar com `docker-compose logs`).

### 4. Migrações

```bash
docker-compose exec app php artisan migrate
```

### 5. Seeders (Opcional)

```bash
docker-compose exec app php artisan db:seed
```

---

## Desenvolvimento Local

### Iniciar ambiente

```bash
docker-compose up -d
```

### Ver logs

```bash
docker-compose logs -f app
docker-compose logs -f queue-worker
docker-compose logs -f mysql
docker-compose logs -f redis
```

### Parar

```bash
docker-compose down
```

### Tinker (REPL)

```bash
docker-compose exec app php artisan tinker
```

---

## Testes

```bash
# Todos os testes
docker-compose exec app php artisan test

# Testes específicos
docker-compose exec app php artisan test --filter=TenancyTest

# Com cobertura
docker-compose exec app php artisan test --coverage
```

---

## Code Quality

### Lint (PHP Pint)

```bash
docker-compose exec app ./vendor/bin/pint
```

### Static Analysis

```bash
# Será adicionado na próxima etapa
```

---

## API

### Base URL

```
http://localhost:8000/api/v1
```

### Documentação

Veja `docs/api/` para especificação OpenAPI.

### Healthcheck

```http
GET /health
```

---

## Banco de Dados

### Conexão Local

```
Host: 127.0.0.1:3306
User: lucraone
Password: lucraone_dev_pwd_2026
Database: lucraone
```

### Backup

```bash
docker-compose exec mysql mysqldump -u lucraone -p lucraone > backup.sql
```

### Restore

```bash
docker-compose exec -T mysql mysql -u lucraone -p lucraone < backup.sql
```

---

## Redis

### Acesso

```bash
docker-compose exec redis redis-cli
```

### Cache

Prefixo: `lucraone_cache_`

Exemplo: `lucraone_cache_tenant:01K...`

### Filas

Connection: `redis`

---

## Email (Local)

**Mailpit** é usado em desenvolvimento.

- Web UI: http://localhost:8025
- SMTP: localhost:1025

---

## Documentação Arquitetural

- `docs/architecture/ARCHITECTURE.md` — Visão geral
- `docs/architecture/MULTI_TENANCY.md` — Estratégia multi-tenant
- `docs/adr/` — Architecture Decision Records

---

## Roadmap

### Fase Concluída

**FASE 01 — FOUNDATION** ✅

Status: 🟢 COMPLETE (189 testes, 100%)

**Sprints Concluídas:**
- ✅ F1.1 — Bootstrap (100% — Docker, estrutura modular, code quality, CI/CD, health checks)
- ✅ F1.2 — Tenancy (100% — Multi-tenant com isolamento)
- ✅ F1.3 — Companies & Branches (100% — Estrutura organizacional)
- ✅ F1.4 — Identity (100% — Autenticação com Sanctum)
- ✅ F1.5 — Authorization (100% — RBAC com roles & permissions)
- ✅ F1.6 — Audit & Observability (100% — Auditoria e logs estruturados)
- ✅ F1.7 — Hardening & Final Validation (100% — Security audit e performance baseline)

### Próxima Fase

**FASE 02 — FEATURES** (Ready to start)
- F2.1 — Core Features (Products, Categories, Prices)
- F2.2 — Inventory Management
- F2.3 — Sales & Orders
- F2.4 — Reporting & Analytics
- F2.5 — Advanced Automation
- F2.6 — Integration APIs

---

## Métricas FASE 01

| Métrica | Resultado |
|---------|-----------|
| Testes Automatizados | ✅ 189/189 passing (100%) |
| Sprints Concluídas | ✅ 7/7 (100%) |
| Cobertura de Segurança | ✅ OWASP Top 10 validado |
| Multi-Tenancy | ✅ Isolamento testado |
| Documentação | ✅ Completa |
| Performance Baseline | ✅ Estabelecido |

## Status Detalhado

Veja `PROJECT_STATUS.md` para tracking completo de todas as sprints e tarefas.

---

## Contribuindo

1. Feature branch: `git checkout -b feature/name`
2. Commit mensagens: `feat:`, `fix:`, `test:`, `docs:`, `refactor:`
3. Testes: garantir cobertura
4. Code style: executar `pint`
5. PR com checklist

---

## Segurança

- Veja `docs/security/SECURITY.md`
- Secrets: `.env` (não versionado)
- Dependências: verificadas com Composer Audit

---

## Suporte

Em caso de dúvidas, consulte:

1. Documentação técnica em `docs/`
2. Roadmap em raiz do projeto
3. Logs em `storage/logs/`

---

## Licença

Proprietário — LUCRAONE

---

**Última atualização:** 2026-08-16 (FASE 01 — Foundation concluída)
