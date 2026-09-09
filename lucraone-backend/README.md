# LUCRAONE — Plataforma SaaS Inteligente de Automação Comercial

**Versão:** 0.5.0  
**Status:** FASE 01 e FASE 03 concluídas · FASE 02 em andamento (F2.5 entregue)  
**Testes:** 417 passando  
**Data:** 2026-09-09

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

- Docker Desktop (Docker Engine 24+ e Compose v2)
- Git

Só isso. PHP, Composer e Node rodam dentro dos containers.

---

## Instalação

O `docker-compose.yml` fica na **raiz do repositório**, um nível acima desta
pasta — todos os comandos abaixo saem de lá.

### 1. Clone o repositório

```bash
git clone <repositório>
cd PROJETO-LUCRAONE
```

### 2. Suba o ambiente

```bash
docker compose up -d --build
```

Não é preciso copiar o `.env` nem rodar as migrations à mão: o entrypoint cria
o `.env` a partir do `.env.example`, gera a `APP_KEY`, espera o MySQL responder
e aplica as migrations na subida.

A primeira execução leva alguns minutos (compila extensões PHP e instala as
dependências). Acompanhe com `docker compose logs -f app`.

### 3. Popule o banco (opcional)

```bash
docker compose exec app php artisan db:seed
```

O painel fica em **http://localhost:8000** e os e-mails em
**http://localhost:8025**.

📖 O guia completo do ambiente — containers, portas, produção e problemas
comuns — está em [`DOCKER.md`](../DOCKER.md).

---

## Desenvolvimento Local

### Iniciar e parar

```bash
docker compose up -d     # subir
docker compose down      # parar (mantém os dados)
docker compose down -v   # parar e apagar banco, redis e dependências
```

### Ver logs

```bash
docker compose logs -f app         # aplicação
docker compose logs -f queue       # fila (automações, e-mail)
docker compose logs -f scheduler   # tarefas agendadas
docker compose logs -f node        # Vite / assets
docker compose logs -f mysql
```

### Tinker (REPL)

```bash
docker compose exec app php artisan tinker
```

---

## Testes

```bash
# Todos os testes
docker compose exec app php artisan test

# Testes específicos
docker compose exec app php artisan test --filter=TenancyTest

# Com cobertura
docker compose exec app php artisan test --coverage
```

---

## Code Quality

### Lint (PHP Pint)

```bash
docker compose exec app ./vendor/bin/pint
```

### Análise Estática (PHPStan)

```bash
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
```

> Ambos rodam sob demanda. O projeto tem um desenvolvedor só, então não há
> pipeline de CI: a verificação acontece aqui, antes do commit.

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
docker compose exec mysql mysqldump -u lucraone -p lucraone > backup.sql
```

### Restore

```bash
docker compose exec -T mysql mysql -u lucraone -p lucraone < backup.sql
```

---

## Redis

### Acesso

```bash
docker compose exec redis redis-cli
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

### FASE 01 — FOUNDATION ✅

- ✅ F1.1 — Bootstrap (Docker, estrutura modular, code quality, health checks)
- ✅ F1.2 — Tenancy (multi-tenant com isolamento por global scope)
- ✅ F1.3 — Companies & Branches (estrutura organizacional)
- ✅ F1.4 — Identity (autenticação com Sanctum)
- ✅ F1.5 — Authorization (RBAC com roles e permissions por estabelecimento)
- ✅ F1.6 — Audit & Observability (auditoria e logs estruturados)
- ✅ F1.7 — Hardening (security audit e performance baseline)
- ✅ F1.8 — Identity Refactor (uma pessoa, vários estabelecimentos)

### FASE 03 — ADMIN FRONTEND ✅

Entrou na frente da F2.2: sem tela de login o sistema só era operável por
Postman. Blade + Tailwind + Alpine.

- ✅ F3.1 — Frontend setup e layout do painel
- ✅ F3.2 — Login web com sessão e escolha de estabelecimento
- ✅ F3.3 — Dashboard administrativo
- ✅ F3.4 — Gerenciar tenants
- ✅ F3.5 — Gerenciar usuários
- ✅ F3.6 — Gerenciar empresas e permissões

### FASE 02 — FEATURES 🟡

- ✅ F2.1 — Core Features (Products, Categories, Prices) — API
- ✅ F2.1b — Produtos pelo painel
- ✅ F2.2 — Inventory Management
- ✅ F2.3 — Sales & Orders
- ✅ F2.4 — Reporting & Analytics
- ✅ F2.5 — Advanced Automation (regras, gatilhos, notificações, e-mail)
- ⬜ F2.6 — Integration APIs

### Pendências conhecidas

- **A API v1 não verifica autorização.** As Policies existem e o painel web as
  usa, mas nenhum controller de `/api/v1` chama `Gate::authorize`. Qualquer
  token válido opera o estabelecimento inteiro, independente do papel.
- **`POST /api/auth/login` não tem rate limiting.** O login web tem (5
  tentativas por e-mail+IP); a API não herdou.
- **Índices únicos ignoram `deleted_at`.** O SKU de um produto arquivado
  continua ocupado e impede recriar o mesmo código.

---

## Métricas

| Métrica | Resultado |
|---------|-----------|
| Testes automatizados | 417 passando |
| Sprints concluídas | FASE 01: 8/8 · FASE 03: 6/6 · FASE 02: 6/7 |
| Módulos | 12 (`app/Modules/`) |
| Multi-tenancy | Isolamento testado (cross-tenant e RBAC) |
| Performance baseline | Estabelecido |
| Análise estática | PHPStan nível 4 (11 apontamentos abertos) |
| Estilo | Pint (18 arquivos fora do padrão) |

## Status Detalhado

Veja `PROJECT_STATUS.md` para tracking completo de todas as sprints e tarefas.

---

## Contribuindo

Projeto de um desenvolvedor só — não há PR nem CI. A disciplina fica no commit:

1. Branch por feature: `git checkout -b feature/nome`
2. Mensagens: `feat:`, `fix:`, `test:`, `docs:`, `refactor:`
3. Antes de commitar, rodar no container:
   ```bash
   docker compose exec app php artisan test
   docker compose exec app ./vendor/bin/pint
   docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
   ```

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

**Última atualização:** 2026-09-09 (F2.5 entregue · ambiente Docker reorganizado)
