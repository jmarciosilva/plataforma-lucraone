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
| **Status da F2.6** | 🟢 **Liberada e não iniciada** — o hardening pré-F2.6 foi concluído |
| **Trilha backend PDV** | PDV-BE-01 a PDV-BE-05 concluídos; trilha completa. Separada da F2.6; backend apto para a integração inicial do PDV |
| **Testes** | 1302 no total · 1300 PASS · 0 FAIL/ERROR · 2 risky preexistentes · 0 skipped · 5060 assertions |
| **Módulos** | 13 |
| **Superfícies** | Painel web (sessão) + API REST `/api/v1` (Sanctum) |

O hardening pré-F2.6 está concluído: os quatro bloqueadores obrigatórios
(**SEC-01** a **SEC-04**) e os três recomendados (**SEC-05**, **SEC-06** e
**COR-01**) estão resolvidos, cada um com os testes que provam a correção.
**A F2.6 está liberada e não foi iniciada** — liberar e começar são coisas
diferentes. Essas pendências não foram uma fase nova e não reabriram a F1.7: eram
correções identificadas depois que os módulos da FASE 02 cresceram. Lista em
[Limitações conhecidas](#limitações-conhecidas); detalhe e critério de liberação
em [ROADMAP.md](ROADMAP.md#pendências-bloqueadoras-pré-f26).

ONB-01B está concluído: criação guiada em quatro passos, ajuda permanente para
Platform Admin, atalhos de cadastro e checklist inicial numerado. O próximo item
de produto é **PM-04A**, planejado e não iniciado; ONB e PM são trilhas paralelas.

A rodada de 2026-10-04 também entregou máscara de telefone, SKU assistido no Web,
nomes de produtos em maiúsculas e cadastro de preços com moeda selecionável,
entrada brasileira e sugestão de venda pela margem desejada no CUSTO. A sugestão
não salva VENDA automaticamente. Margem efetiva e custo de referência são
persistidos com histórico decimal, pela fórmula sobre custo, usando o mesmo
serviço em Web/API/automação. Histórico permanece após remoção de Price; não há
reconstrução fictícia de margens antigas. Relatórios completos continuam futuros.
Detalhes e estado do staging em [ROADMAP.md](ROADMAP.md).

O SEC-04 foi concluído. Nele foram corrigidos:

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
  `viewer` e `user` cabem no `manager`, que cabe no `admin`;
- o contexto de estabelecimento nas Policies (E7): o route binding do painel
  acontecia antes de o contexto ser resolvido, então o escopo de estabelecimento
  não filtrava a entidade carregada. Agora a autenticação tem prioridade sobre o
  binding, e uma entidade de outro estabelecimento simplesmente não é encontrada —
  retorna 404 —, mesmo com permissão no estabelecimento ativo.

Com o E7 fechado, o SEC-04 está **resolvido**: 151 testes do vetor, todos verdes.
SEC-01, SEC-02 e SEC-03 foram resolvidos depois dele, nessa ordem, e os
recomendados SEC-05, SEC-06 e COR-01 em seguida.

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
docker compose exec app php artisan test --do-not-cache-result # 1302 testes; 1300 PASS e 2 risky preexistentes
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

Levantadas em auditoria de 2026-09-09, conferidas contra o código em 2026-09-10
e **encerradas em 2026-10-07**, exceto PERF-01, que nunca bloqueou. Eram as
**pendências pré-F2.6**; evidências, política adotada em cada caso e os testes
que provam cada correção estão em
[ROADMAP.md](ROADMAP.md#pendências-bloqueadoras-pré-f26).

| ID | Pendência | Prioridade | Bloqueia F2.6 | Status |
|---|---|---|---|---|
| SEC-01 | 7 dos 14 controllers da API não verificavam permissão | Crítica | Sim | ✅ `3a14703` |
| SEC-02 | Login da API sem rate limiting; tokens sem expiração nem abilities | Crítica | Sim | ✅ `e306494` |
| SEC-03 | `TenantResolver` aceitava `X-Tenant-ID` sem usuário autenticado | Alta | Sim | ✅ `342f4ad` |
| SEC-04 | Platform Admin/X1, coringa `create-role`, identidade global (E3), delegação por `update-role` (E1), administração por `manage-users` (E2) e contexto no route binding (E7) | Crítica | Sim | ✅ `7433c5b` |
| SEC-05 | Automações enviavam e-mail para qualquer destinatário, sem limite | Média | Recomendado | ✅ `6b271ef` |
| SEC-06 | `APP_KEY` versionada em `.env.testing` | Média | Recomendado | ✅ `693a8ef` |
| COR-01 | Validação não acompanhava os índices únicos sob soft delete | Alta | Recomendado | ✅ `18eaa10` |
| PERF-01 | `hasAnyPermission()` repete consultas a cada permissão verificada | Média | Não | 🔴 Pendente |

O backend está apto para a **integração inicial** do PDV: Terminal, vínculos,
pareamento, credencial de máquina, autenticação/autorização de máquina e as três
rotas `/api/v1/pdv/*` existem e foram validadas ponta a ponta em staging. Isso
não é "PDV completo" nem "produção fiscal pronta": não há endpoint comercial —
produto, preço, estoque, venda, pagamento, caixa, sincronização, fiscal —, a
ability de máquina é apenas `pdv:terminal:read`, e credencial perdida exige novo
pareamento presencial.

A trilha oficial [PDV-BE](ROADMAP.md#pdv-be--backend-para-integração-com-pdv)
prepara essa integração separadamente da F2.6. PDV-BE-01 entrega BranchPolicy,
correlação da API e o
[desenho de Terminal](lucraone-backend/docs/architecture/pdv-backend-foundation.md);
PDV-BE-02 entrega Terminal, vínculos, invariantes e Policy inicial;
PDV-BE-03 entrega pairing/provisionamento sem HTTP ou credencial;
PDV-BE-04 entrega credencial de máquina, autenticação e autorização;
PDV-BE-05 entrega os três contratos HTTP (`health`, `terminals/pair`, `terminal`),
freio do pareamento e contrato público de erro. A Fase 4 do Java PDV está
desbloqueada pelos contratos reais do backend.

---

## Licença

Proprietário — LUCRAONE
