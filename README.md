# LUCRAONE

Plataforma SaaS de automação comercial para o varejo brasileiro — supermercados,
lojas, restaurantes, padarias, bares. Multi-tenant, com painel administrativo
web e API REST versionada.

A visão completa do produto está em
[Projeto — Plataforma SaaS Inteligente de Automação Comercial](<Projeto — Plataforma SaaS Inteligente de Automação Comercial.md>).
O plano de execução está em **[ROADMAP.md](ROADMAP.md)**.

---

## Estado atual

| | |
|---|---|
| **Fase ativa** | FASE 02 — Features (6 de 7 sprints entregues) |
| **Próxima sprint** | F2.6 — Integration APIs |
| **Testes** | 417 passando |
| **Módulos** | 12 |
| **Superfícies** | Painel web (sessão) + API REST `/api/v1` (Sanctum) |

O sistema é operável por uma pessoa desde a F3.2: há login em `/login`, e quem
tem vínculo com vários estabelecimentos escolhe onde vai trabalhar e troca pelo
menu, sem deslogar.

---

## Subir o ambiente

Único pré-requisito: **Docker Desktop**. PHP, Composer e Node rodam dentro dos
containers.

```bash
git clone git@github.com:jmarciosilva/plataforma-lucraone.git
cd plataforma-lucraone
docker compose up -d --build
docker compose exec app php artisan db:seed
```

Não é preciso copiar `.env` nem migrar à mão — o entrypoint cria o `.env`, gera
a `APP_KEY`, espera o MySQL e aplica as migrations na subida.

| Endereço | O quê |
|---|---|
| http://localhost:8000 | Painel e API |
| http://localhost:8025 | Mailpit — todo e-mail enviado cai aqui |

Usuário de teste após o seed: `admin@lucraone-dev.local` / `password`.

📖 Guia completo do ambiente — containers, comandos, produção e problemas
comuns — em **[DOCKER.md](DOCKER.md)**.

---

## Stack

| Camada | Escolha |
|---|---|
| Backend | PHP 8.3 · Laravel 13 |
| Banco | MySQL 8.4 |
| Cache, fila e locks | Redis 7 |
| Painel | Blade · Tailwind 4 · Alpine.js · Vite |
| Autenticação | Sanctum (API) · sessão (painel) |
| Infra | Docker — nginx + php-fpm + fila + agendador |

---

## Arquitetura

**Monolito modular.** Cada módulo em `lucraone-backend/app/Modules/` tem suas
próprias camadas `Domain`, `Application`, `Http` e `Infrastructure`:

```
Tenancy        Multi-tenancy: contexto, resolução e escopo global
Identity       Identidade global — uma pessoa, vários estabelecimentos
Authorization  RBAC por estabelecimento (roles e permissions)
Companies      Empresas e endereços        Branches   Filiais
Products       Produtos, categorias, preços e histórico
Inventory      Saldos, movimentações e níveis de reposição
Sales          Clientes, pedidos e itens
Reporting      Relatórios, dashboard e análise de tendências
Automation     Regras "quando X, faça Y", notificações e e-mail
Audit          Auditoria e logs estruturados
Core           Utilitários compartilhados
```

**Multi-tenancy por escopo global.** Os modelos usam a trait `HasTenant`, que
aplica um global scope filtrando por `tenant_id`. O contexto é resolvido em
middleware, sempre **depois** da autenticação — é o vínculo da pessoa que
autoriza o acesso ao estabelecimento pedido.

**Identidade separada de vínculo.** Uma pessoa tem uma conta e uma senha, e se
associa a N estabelecimentos por `tenant_user`. Papéis e permissões são sempre
relativos ao estabelecimento em uso: a mesma pessoa pode ser admin numa loja e
somente-leitura em outra.

Detalhes em [`lucraone-backend/docs/architecture/`](lucraone-backend/docs/architecture/)
e nos ADRs em [`lucraone-backend/docs/adr/`](lucraone-backend/docs/adr/).

---

## Estrutura do repositório

```
├── docker-compose.yml            # ambiente de desenvolvimento
├── docker-compose.prod.yml       # sobreposição de produção
├── DOCKER.md                     # guia do ambiente
├── ROADMAP.md                    # plano de execução e pendências
├── DESIGN_SYSTEM.md              # referência visual do painel
├── PROJECT_STATUS.md             # tracking detalhado por sprint
├── DEVELOPMENT_DASHBOARD.md      # visão de progresso
├── TESTING_GUIDE.md              # como testar
└── lucraone-backend/             # a aplicação Laravel
    ├── app/Modules/              # os 12 módulos
    ├── docker/                   # Dockerfile, nginx, php, entrypoint
    ├── docs/                     # arquitetura, ADRs, guias
    └── README.md                 # detalhe técnico do backend
```

---

## Desenvolvimento

```bash
docker compose exec app php artisan test        # 417 testes
docker compose exec app ./vendor/bin/pint       # estilo
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app php artisan tinker

docker compose logs -f app      # aplicação
docker compose logs -f queue    # automações e e-mail
```

Não há CI: o projeto tem um desenvolvedor só, e as verificações rodam sob
demanda antes do commit.

---

## Limitações conhecidas

Levantadas em auditoria de 2026-09-09. A lista completa, com severidade e
caminho de correção, está em [ROADMAP.md](ROADMAP.md#pendências-abertas).

- **A API v1 não verifica autorização.** 12 dos 14 controllers de API não
  chamam `Gate::authorize`. As Policies existem e o painel web as usa, mas
  qualquer token válido opera o estabelecimento inteiro, independente do papel.
- **`POST /api/auth/login` não tem rate limiting.** Nenhuma rota `/api` tem.
  O login web tem (5 tentativas por e-mail+IP); a API não herdou.
- **Índices únicos ignoram `deleted_at`.** O SKU de um produto arquivado fica
  ocupado para sempre e impede recriar o mesmo código.

---

## Licença

Proprietário — LUCRAONE
