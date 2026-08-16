# Prompt de Continuidade — LUCRAONE

> Copie tudo o que está abaixo da linha e entregue ao CODEX.

---

Você vai continuar o desenvolvimento do **LUCRAONE**, uma plataforma SaaS de
automação comercial já em andamento. O projeto está em `D:\PROJETO-LUCRAONE`,
com o backend em `D:\PROJETO-LUCRAONE\lucraone-backend`.

**Não escreva nenhuma linha de código antes de cumprir a Etapa 1.**

---

## Etapa 1 — Leia a documentação antes de qualquer coisa

Leia nesta ordem. Estes documentos são a especificação oficial: onde eles
divergirem da sua intuição, **eles vencem**.

| # | Arquivo | Para quê |
|---|---------|----------|
| 1 | `PROJECT_STATUS.md` | Estado central: fases, sprints, dívida técnica, decisões |
| 2 | `DEVELOPMENT_DASHBOARD.md` | Progresso visual e histórico de cada sprint |
| 3 | `ROADMAP_FASE_03_ADMIN_FRONTEND.md` | **A fase ativa.** Contém o checklist do seu próximo sprint |
| 4 | `DESIGN_SYSTEM.md` | **Obrigatório antes de escrever qualquer view.** Paleta, tipografia, componentes |
| 5 | `ROADMAP_FASE_01_FOUNDATION.md — Fundação Técnica da Plataforma.md` | Fundação. **Leia com atenção as seções 19 e 19.1** — modelo de identidade |
| 6 | `ROADMAP_FASE_02_FEATURES.md` | Fase pausada; leia para entender por que parou |
| 7 | `TESTING_GUIDE.md` | Como testar a API manualmente |
| 8 | `lucraone-backend/docs/` | ADRs, arquitetura, guias de tenancy e autorização |

---

## Etapa 2 — Verifique o projeto por conta própria

Não confie apenas na documentação: confirme que o estado real bate com ela.

```bash
cd D:\PROJETO-LUCRAONE\lucraone-backend

git log --oneline -12          # últimos commits
php artisan test --env=testing # deve dar 264/264 passando
php artisan route:list         # rotas web e api
docker-compose ps              # containers de pé
```

Se a suíte não der **264/264**, pare e investigue antes de escrever qualquer
código novo. Um teste vermelho herdado é um problema a resolver, não um
detalhe a contornar.

---

## Etapa 3 — Onde o projeto parou

```
FASE 01 — FOUNDATION        ✅ COMPLETO   8 sprints (F1.1 a F1.8), 227 testes
FASE 02 — FEATURES          ⏸️  PAUSADO   F2.1 ✅ · F2.2 a F2.6 aguardando
FASE 03 — ADMIN FRONTEND    🟡 ATIVO      F3.1 ✅ F3.2 ✅ · F3.3 é o próximo
```

**Suíte atual: 264 testes passando, 0 falhando.**

A FASE 02 foi pausada de propósito: o sistema tinha APIs mas nenhuma interface
de acesso. A FASE 03 entrega o painel administrativo e só então a FASE 02
retoma no F2.2.

### Seu próximo sprint: F3.3 — Admin Dashboard

O checklist está em `ROADMAP_FASE_03_ADMIN_FRONTEND.md`. Em resumo:

- Widgets do dashboard com **contagens reais** (hoje mostram traços)
- Breadcrumbs e rodapé
- Teste automatizado de responsividade mobile
- Remover `resources/views/welcome.blade.php`, órfão desde que `/` passou a redirecionar
- 6 testes

---

## Etapa 4 — Conhecimento que não está óbvio no código

Estas são armadilhas que já custaram tempo. Leia antes de começar.

### Arquitetura

**Modelo de identidade (F1.8).** `users` é uma identidade **global** — uma
pessoa, uma conta, uma senha. Não existe `users.tenant_id`. O vínculo com cada
estabelecimento vive em `tenant_user`, com status próprio. Uma pessoa pode
atender vários estabelecimentos (um dono com duas lojas, um contador com vários
clientes).

```php
$usuario->canAccessTenant($tenantId);      // conta ativa E vínculo ativo
$usuario->estabelecimentosDisponiveis();   // para seletor e troca
$usuario->joinTenant($tenantId, $status);
```

**`TenantScope` não filtra nada quando o contexto não está resolvido.** Ele
falha em aberto, não em fechado. Se o `TenantContext` não for definido, as
consultas passam a enxergar dados de **todos** os estabelecimentos. Sempre
garanta que o contexto esteja resolvido em rotas que leem dados.

**`ResolveTenantMiddleware` NUNCA pode ser middleware global.** Ele precisa
rodar **depois** da autenticação — sem usuário conhecido não há como validar o
vínculo, e um `X-Tenant-ID` apontando para outro estabelecimento passaria sem
checagem. Isso já foi uma brecha real no projeto. Uso correto:
`['auth:sanctum', 'tenant']`, nessa ordem. No painel web, quem resolve é o
middleware `AutenticarWeb`.

**Dois canais de autenticação sobre o mesmo `User`:** o navegador usa sessão
(guard `web`); a API usa Sanctum com bearer token. Não misture.

### Frontend

**Tailwind v4 — não existe `tailwind.config.js`.** A configuração vive em
`resources/css/app.css`, dentro do bloco `@theme`. Os prefixos são
significativos: `--color-*` gera `bg-sol`, `--radius-*` gera `rounded-modulo`,
`--shadow-*` gera `shadow-cartao`.

**Layouts ficam em `resources/views/components/layouts/`**, não em
`resources/views/layouts/` — é o que permite usá-los como `<x-layouts.app>`.

**Componentes Blade já prontos** (não recrie): `layouts.app`, `layouts.auth`,
`layouts.erro`, `sidebar`, `nav-item`, `button`, `input`, `select`,
`form-group`, `card`, `kpi`, `badge`, `alert`, `modal`, `table`,
`section-label`.

### Ambiente (Windows + Docker)

- O container `app` **não tem `bash`** — use `docker-compose exec app sh`
- `npm` roda **no host**, não no container (`node_modules` não está lá dentro)
- A rota de health é **`/api/health`**, não `/health`
- **`Set-Content -Encoding utf8` do PowerShell insere BOM e quebra arquivos
  PHP.** Para editar arquivos por script, use
  `[System.IO.File]::WriteAllText($caminho, $conteudo, (New-Object System.Text.UTF8Encoding $false))`

### Testes

- `HasUlid` **não dispara em models Pivot**. O SQLite tolera (rowid implícito),
  o MySQL rejeita. Atribua o ULID explicitamente ao criar pivots.
- **Não forje sessões** com `withSession()` + `actingAs()` para testar fluxos de
  autenticação: passa isolado e quebra na suíte completa. Faça login pelo fluxo
  real.
- O CI compila assets (`npm ci && npm run build`) porque os testes de view usam
  `@vite()`, que falha sem o manifest.

---

## Etapa 5 — Como trabalhar

### Convenções de código

- **Comentários em português; identificadores em inglês** (esta é a convenção
  do projeto — siga-a)
- Comente o **porquê**, não o quê. Comentário que narra a linha seguinte é ruído
- Títulos de página e itens de menu em **minúsculas** (decisão de marca)
- Logo: `LUCRA<span class="texto-sol">ONE</span>`
- Commits estruturados: `feat(F3.3): ...`, `fix: ...`, `docs: ...`

### Regras de qualidade

1. **Nunca afrouxe um teste para fazê-lo passar.** Se um teste falha,
   investigue a causa. Se concluir que o teste é que está errado, corrija a
   **medição** e explique o raciocínio — não o limite.
2. **Rode a suíte completa várias vezes** antes de considerar um sprint pronto.
   Teste que passa às vezes é teste quebrado.
3. **Valide no navegador**, não só por teste automatizado.
4. **Se encontrar um bug fora do escopo do sprint, conserte e registre.** O
   projeto tem histórico disso: sprints anteriores revelaram unique constraints
   globais que deveriam ser por tenant, e uma brecha de autenticação.
5. **Marque o checklist do roadmap conforme concluir**, e mantenha
   `PROJECT_STATUS.md` e `DEVELOPMENT_DASHBOARD.md` sincronizados. Documentação
   desatualizada já foi motivo de correção neste projeto.

### Quando parar e perguntar

Pare e consulte o responsável antes de:

- Alterar schema de módulos já concluídos (FASE 01 ou F2.1)
- Mudar decisões de produto (identidade, autenticação, multi-tenancy)
- Introduzir dependência ou stack nova
- Qualquer coisa que exija `migrate:fresh` com dados existentes

O responsável prefere **ser consultado com uma recomendação clara** a receber
uma decisão tomada por conta própria. Apresente as opções, indique a que você
recomenda e o porquê, e aguarde.

---

## Etapa 6 — Antes de dizer que terminou

- [ ] Suíte completa passando, rodada ao menos 3 vezes seguidas
- [ ] Fluxo validado no navegador
- [ ] Checklist do sprint marcado no roadmap
- [ ] `PROJECT_STATUS.md` e `DEVELOPMENT_DASHBOARD.md` atualizados
- [ ] Commit estruturado explicando o **porquê** das decisões
- [ ] Dívida técnica nova registrada, se houver

---

## Credenciais de desenvolvimento

```
http://localhost:8000/login

admin@lucraone-dev.local     senha: password    (1 vínculo  → dashboard direto)
contador@escritorio.local    senha: password    (2 vínculos → seletor)
```

Recriar o banco: `docker-compose exec app php artisan migrate:fresh --seed --force`

---

**Comece pela Etapa 1.** Depois de ler tudo, me diga em poucas linhas o que
entendeu do estado atual e qual é o seu plano para o F3.3, antes de escrever
código.
