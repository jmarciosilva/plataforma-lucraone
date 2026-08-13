# LUCRAONE — Plataforma SaaS Inteligente de Automação Comercial

**Versão:** 0.1.0 (Foundation Phase)  
**Status:** 🟡 IN_PROGRESS  
**Data:** 2026-08-13

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

### Fase Atual

**FASE 01 — FOUNDATION** (Sprint F1.1 — Bootstrap)

Status: 🟡 IN_PROGRESS

### Próximas Sprints

- F1.2 — Tenancy
- F1.3 — Companies & Branches
- F1.4 — Identity
- F1.5 — Authorization
- F1.6 — Audit & Observability
- F1.7 — Hardening

---

## Status Atual

Veja `PROJECT_STATUS.md` para tracking detalhado.

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

**Última atualização:** 2026-08-13
