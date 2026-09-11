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
| **Fase funcional atual** | FASE 02 — Features (6 de 7 sprints entregues) |
| **Última sprint concluída** | F2.5 — Advanced Automation |
| **Próxima sprint funcional** | F2.6 — Integration APIs |
| **Status da F2.6** | 🔴 **Bloqueada temporariamente** pelo hardening de segurança pré-F2.6 |
| **Testes** | 568 no total · 552 aprovados · 14 falhas SEC-04 esperadas · 2 risky preexistentes |
| **Módulos** | 12 |
| **Superfícies** | Painel web (sessão) + API REST `/api/v1` (Sanctum) |

A F2.6 só começa quando os bloqueadores obrigatórios **SEC-01 a SEC-04** estiverem
resolvidos. Essas pendências não são uma fase nova e não reabrem a F1.7: são
correções identificadas depois que os módulos da FASE 02 cresceram. Lista em
[Limitações conhecidas](#limitações-conhecidas); detalhe e critério de liberação
em [ROADMAP.md](ROADMAP.md#pendências-bloqueadoras-pré-f26).

O hardening está em andamento. No SEC-04, já foram corrigidos:

- Platform Admin explícito, separado do RBAC dos estabelecimentos, e o vetor X1;
- o uso de `create-role` como coringa nos módulos de negócio;
- o papel `admin` do estabelecimento, que passou a ter uma matriz explícita de 26
  permissões — aplicada aos estabelecimentos novos, que antes recebiam 19, e, por
  data fix, aos já existentes;
- a identidade global administrada por autoridade local (E3): `manage-users` não
  altera mais nome, e-mail, senha ou status da conta, nem arquiva ou restaura a
  identidade, de quem também tem vínculo com outro estabelecimento ou é Platform
  Admin. Vínculo e papéis continuam sendo geridos por cada estabelecimento;
- a delegação de permissões por `update-role` (E1): ninguém sincroniza as
  permissões do próprio papel, nem adiciona ou retira de outro papel uma permissão
  que não possui naquele estabelecimento. A proteção vem da autoridade de quem
  executa, não do nome do papel;
- a administração de usuários por `manage-users` (E2): administrar uma pessoa
  exige dominar a autoridade dela no estabelecimento — a atual e a dos papéis
  pedidos —, e ninguém altera os próprios papéis. Isso fecha a atribuição indevida
  de papéis, a retirada, o arquivamento e a suspensão de quem tem mais autoridade e
  a tomada de conta pela senha ou pelo e-mail. As regras da identidade global (E3)
  continuam valendo junto. Para isso, os papéis padrão `manager` e `user` deixaram
  de receber duas permissões de filiais sem uso, e a matriz ficou aninhada:
  `viewer` e `user` cabem no `manager`, que cabe no `admin`.

Continua pendente o E7 (entidade e permissão avaliadas em estabelecimentos
diferentes), que é o próximo. A F2.6 permanece bloqueada.

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
somente-leitura em outra. Os dados da conta — nome, e-mail, senha e status — são
globais: um estabelecimento só os altera quando a pessoa pertence exclusivamente
a ele e não é Platform Admin.

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
docker compose exec app php artisan test        # 568 testes; 14 falhas SEC-04 esperadas
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

Levantadas em auditoria de 2026-09-09 e conferidas contra o código em
2026-09-10. São as **pendências pré-F2.6**; evidências, objetivo de cada
correção e ordem recomendada estão em
[ROADMAP.md](ROADMAP.md#pendências-bloqueadoras-pré-f26).

| ID | Pendência | Prioridade | Bloqueia F2.6 |
|---|---|---|---|
| SEC-01 | 7 dos 14 controllers da API não verificam permissão | Crítica | Sim |
| SEC-02 | Login da API sem rate limiting; tokens sem expiração nem abilities | Crítica | Sim |
| SEC-03 | `TenantResolver` aceita `X-Tenant-ID` sem usuário autenticado | Alta | Sim |
| SEC-04 | Em andamento: Platform Admin/X1, coringa `create-role` nos módulos, identidade global (E3), delegação por `update-role` (E1) e administração de usuários por `manage-users` (E2) corrigidos; vetor E7 ainda pendente | Crítica | Sim |
| SEC-05 | Automações enviam e-mail para qualquer destinatário | Média | Recomendado |
| SEC-06 | `APP_KEY` versionada em `.env.testing` | Média | Recomendado |
| COR-01 | Índices únicos ignoram soft delete (SKU, slug, e-mail) | Alta | Recomendado |
| PERF-01 | `hasAnyPermission()` repete consultas a cada permissão verificada | Média | Não |

---

## Licença

Proprietário — LUCRAONE
