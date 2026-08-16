# FASE 03 — ADMIN FRONTEND

**Objetivo:** Criar interface web administrativa com Blade + Tailwind para gerenciar tenants, usuários, empresas e permissões.

**Status:** 🟡 EM ANDAMENTO (4/6 sprints — F3.1 ✅ F3.2 ✅ F3.3 ✅ F3.4 ✅)
**Duração Estimada:** ~6-8 semanas
**Stack:** Laravel Blade + Tailwind CSS + Alpine.js
**Dependência:** FASE 01 ✅ (inclui F1.8 — modelo de identidade) · F2.1 ✅
**Bloqueia:** F2.2 em diante (FASE 02 pausada até F3.6)
**Referência visual:** [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) — extraído da demo
aprovada pelos sócios (https://mentor-ia-demo.netlify.app/r/demo/inteligencia)

---

## 🎯 Por que esta fase existe

Após F2.1, o backend expõe APIs completas (produtos, categorias, preços) e toda a
fundação multi-tenant, mas **não há nenhuma forma de um usuário entrar no sistema**:

| Falta hoje | Impacto |
|-----------|---------|
| Tela de login | Usuário admin/operador não consegue acessar |
| Painel administrativo | Sem lugar para operar o sistema |
| Cadastro de tenant | Novo estabelecimento só via `php artisan tinker` |
| Cadastro de usuário | Novos usuários só via seeder ou tinker |
| Gestão de permissões | RBAC existe no backend, sem interface |

A FASE 03 fecha essa lacuna antes de acumular mais módulos de negócio.

> **Nota sobre numeração:** o documento estratégico *Roadmap Macro* usa uma
> numeração própria de 30 fases (onde "FASE 3" = Pricing). Os roadmaps de
> implementação deste repositório usam numeração própria e sequencial:
> FASE 01 (Foundation) → FASE 02 (Features) → FASE 03 (Admin Frontend).
> São escalas distintas e independentes.

---

## 📊 Painel de Acompanhamento

| Sprint | Nome | Status | Itens | Testes | Como você valida |
|--------|------|--------|-------|--------|------------------|
| **F3.1** | Frontend Setup & Layout | ✅ DONE | 15/15 | 6/6 | Página abre com Tailwind aplicado |
| **F3.2** | Authentication | ✅ DONE | 22/22 | 14/14 | **Login em `/login` funciona** |
| **F3.3** | Admin Dashboard | ✅ DONE | 15/15 | 6/6 | Painel com menu e widgets |
| **F3.4** | Tenant Management | ✅ DONE | 25/25 | 12/12 | **Criar estabelecimento pela tela** |
| **F3.5** | User Management | 📋 TODO | 0/25 | 0/12 | **Criar usuário e logar com ele** |
| **F3.6** | Company & Roles | 📋 TODO | 0/20 | 0/10 | Empresas + permissões pela tela |

**Total:** 122 itens · ~59 testes · **Progresso: 77/122 itens, 38/59 testes**

**Legenda:** 📋 TODO · 🟡 EM ANDAMENTO · ✅ DONE · ⏸️ PAUSADO

**Suíte completa do projeto:** 282 testes passando, 0 falhando

---

## ✅ Sprint F3.1 — Frontend Setup & Layout (CONCLUÍDO)

**Concluído em:** 2026-08-16 · **Testes:** 6/6 · **Commit:** `e4c9979`

### Checklist (15/15)

> 🎨 Todo o visual segue o [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md).

- [x] Tailwind CSS + Vite configurados (v4 já vinha no Laravel 13)
- [x] **Paleta aplicada em `resources/css/app.css` via `@theme`** — sol `#FF7A00`,
      ambar, rosa, brasa, ok, alerta, ouro, grafite, aco, fumaca, linha, nevoa;
      raio `modulo: 14px`; sombra `cartao`
      <br>*(Tailwind v4 configura por CSS — não existe `tailwind.config.js`)*
- [x] **Utilitários customizados** — `.cartao`, `.rotulo-secao`, `.gradiente-sol`,
      `.gradiente-sol-suave`, `.texto-sol`
- [x] **Fontes auto-hospedadas via `bunny()`** — Karla (corpo), Archivo (logo),
      Martian Mono (valores monetários)
- [x] Estrutura de arquivos (`components/layouts/`, `components/`, views)
- [x] **Sidebar 240px** — logo `LUCRAONE` com **ONE** em `.texto-sol`, subtítulo
      com tenant ativo + ambiente, ícones 32×32, item ativo em gradiente suave
- [x] Layout principal (`<x-layouts.app>`) com slots `subtitulo` e `acoes`
- [x] Layout de autenticação (`<x-layouts.auth>`)
- [x] Componentes reutilizáveis:
  - [x] Button — `primario` (gradiente), `secundario`, `perigo`, `fantasma`
  - [x] Input — text, email, password, textarea; borda muda com erro
  - [x] Select — com `opcoes` e opção vazia
  - [x] Modal — Alpine, abre por evento `abrir-modal`
  - [x] Alert — `sucesso`, `erro`, `atencao`, `info`; dispensável
  - [x] Table — dentro de `.cartao`, com paginação opcional
  - [x] Form Group — label + campo + erro (ou texto de ajuda)
  - [x] **KPI** — rótulo → valor em mono → variação com seta colorida
  - [x] **Rótulo de seção** — MAIÚSCULAS, `tracking-wide`, cor `aco`
  - [x] Badge — pills de status
  - [x] Nav-item — rotas inexistentes ficam desabilitadas sem quebrar o menu
- [x] Dashboard placeholder com KPIs e próximos passos
- [x] Alpine.js instalado e inicializado
- [x] Middlewares web: `auth.web` e `convidado`
- [x] Redirects: `/` → dashboard, visitante → `/login`
- [x] 6 testes em `tests/Feature/Admin/LayoutTest.php`

### Correções de bugs feitas durante o sprint

A suíte completa revelou defeitos reais no F2.1 e no scaffolding:

| Bug | Impacto |
|-----|---------|
| `products.sku` com unique **global** em vez de por tenant | Dois tenants não poderiam usar o mesmo código de produto |
| `categories.slug` com o mesmo defeito | Mesmo problema |
| Pivot `product_categories` exigia `id` que `attach()` não preenche | Associar produto a categoria falhava |
| Soft delete de categoria deixava filhos órfãos apontando para pai invisível | Hierarquia inconsistente |
| `config/auth.php` apontava para `App\Models\User` (scaffolding) | Login por sessão não funcionaria |
| Migration ULID não derrubava o índice morph antes da coluna | Abortava no SQLite |
| `ApiAuthenticationMiddleware` global convertia redirect em JSON 401 | Quebraria o fluxo web |
| CI não compilava assets — `@vite()` falha sem manifest | Testes de view quebrariam no CI |

**Suíte:** 231/237 → **237/237 passando**

---

## ✅ Sprint F3.2 — Authentication (Login/Logout) — CONCLUÍDO

**Concluído em:** 2026-08-16 · **Testes:** 14/14

### Checklist (22/22)

> ✅ **Bypass removido.** A rota `/preview-login`, criada no F3.1 para permitir
> ver o painel antes do login existir, foi apagada.

> ℹ️ **Nota de arquitetura:** o painel web usa o **guard `web` (sessão)**, não
> Sanctum. Sanctum continua servindo a API (`/api/*`) com tokens. São dois
> canais de autenticação distintos sobre o mesmo model `User` — o navegador
> recebe cookie de sessão, o cliente de API recebe bearer token.

> 🔄 **Depende do F1.8 (concluído).** Uma pessoa pode estar associada a vários
> estabelecimentos, então o login não termina na senha: se houver mais de um
> vínculo ativo, é preciso escolher qual. O estabelecimento escolhido fica na
> sessão (`tenant_ativo`) e é lido pelo `TenantResolver`.

**Views:**
- [x] **Remover a rota temporária `/preview-login`**
- [x] Tela de login (`/login`) — substituir o stub por formulário funcional
- [x] **Seletor de estabelecimento** — quando houver mais de um vínculo ativo
- [x] Tela de erro 403 (não autorizado)
- [x] Tela de erro 404 (não encontrado)
- [x] Tela de erro 419 (sessão expirada / CSRF)

**Funcionalidades:**
- [x] Formulário de login (email + senha)
- [x] Validação client-side (Alpine.js) — campos obrigatórios, botão desabilitado
- [x] Validação server-side (`LoginRequest`)
- [x] Autenticação por sessão via guard `web`
- [x] `session()->regenerate()` no login (previne session fixation)
- [x] Vínculo único: entra direto, sem passar pelo seletor
- [x] Vários vínculos: seletor de estabelecimento após a senha
- [x] Estabelecimento escolhido gravado em `session('tenant_ativo')`
- [x] Troca de estabelecimento pelo menu, sem deslogar
- [x] Logout com invalidação de sessão e novo token CSRF
- [x] Opção "lembrar-me"
- [x] Mensagem de erro em credenciais inválidas (sem revelar se o e-mail existe)
- [x] Bloqueio de usuário com status ≠ `ACTIVE`
- [x] Rate limiting por e-mail + IP (freio a força bruta)
- [x] Registro de `last_login_at`
- [x] Redirect pós-login para `/dashboard` (ou destino pretendido)

**Testes (14 — 4 a mais que o previsto):**
- [x] Login com credenciais válidas → dashboard
- [x] Login com credenciais inválidas → erro, permanece deslogado
- [x] **Mensagem idêntica para e-mail inexistente e senha errada**
- [x] Conta `INACTIVE` não consegue entrar
- [x] Vínculo `SUSPENDED` não dá acesso ao estabelecimento
- [x] Vínculo único entra direto no dashboard
- [x] Vários vínculos caem no seletor de estabelecimento
- [x] **Escolher estabelecimento libera o painel**
- [x] **Não é possível escolher estabelecimento sem vínculo**
- [x] Logout invalida a sessão e redireciona para login
- [x] Visitante em rota protegida → login
- [x] Sessão persiste entre requisições
- [x] **Login registra `last_login_at`**
- [x] Rate limiting bloqueia após tentativas repetidas
- [x] **Usuário autenticado não vê a tela de login**

### Notas de implementação

**Chave de sessão centralizada.** `TenantResolver::SESSAO_TENANT` guarda o nome
da chave. Ela vive no resolver, não no controller, porque é ele quem a lê a cada
requisição — quem escreve apenas segue o contrato.

**Middleware com dois modos.** As telas que *definem* o estabelecimento não podem
exigir estabelecimento definido, senão viraria um ciclo. Daí
`auth.web:sem-tenant`, que só exige a sessão.

**Correções encontradas durante a validação no navegador:**

| Item | Problema |
|------|----------|
| `dashboard/index.blade.php` | Ainda usava `auth()->user()->tenant`, relação removida no F1.8 — o título perdia o nome do estabelecimento |
| Páginas de erro | Em rota inexistente a sessão nem inicia, então `@auth` daria sempre "visitante"; o botão passou a apontar para a raiz, que redireciona conforme o estado real |
| `test_logout_encerra_a_sessao` | Forjava a sessão com `withSession()` + `actingAs()` e quebrava na suíte completa; passou a fazer login pelo fluxo real |

---

## ✅ Sprint F3.3 — Admin Dashboard

**Concluído em:** 2026-08-16 · **Testes:** 6/6

### Checklist (15 itens)

**Layout:**
- [x] Sidebar com navegação
- [x] Header com logout + info do usuário
- [x] Breadcrumbs
- [x] Rodapé
- [x] Menu responsivo (mobile)

**Dashboard:**
- [x] Widgets de resumo:
  - [x] Total de tenants
  - [x] Total de usuários
  - [x] Total de empresas
  - [x] Último acesso
- [x] Gráficos iniciais (Chart.js opcional) — adiado; sem volume de dados que justifique dependência nova
- [x] Atalhos para principais seções
- [x] Feed de atividades (opcional) — adiado para uma futura tela de auditoria

**Navegação:**
- [x] Menu: Dashboard → Tenants → Usuários → Empresas → Roles/Permissions
- [x] Links contextuais
- [x] Active state de menu

**Testes:**
- [x] Dashboard carrega corretamente
- [x] Menu renderiza todas opções
- [x] Links funcionam corretamente
- [x] Breadcrumbs atualizados
- [x] Responsivo em mobile
- [x] Widgets exibem contagens reais e isoladas por tenant

---

## ✅ Sprint F3.4 — Tenant Management

**Concluído em:** 2026-08-16 · **Testes:** 12/12

### Checklist (25 itens)

**Views:**
- [x] Lista de tenants (`/tenants`)
- [x] Detalhe de tenant (`/tenants/{id}`)
- [x] Criar tenant (`/tenants/create`)
- [x] Editar tenant (`/tenants/{id}/edit`)
- [x] Deletar tenant (modal confirmação)

**Funcionalidades:**
- [x] Listagem com paginação (20 por página)
- [x] Busca por nome
- [x] Filtro por status (TRIAL, ACTIVE, SUSPENDED, CANCELLED)
- [x] Ordenação (nome, status, criação)
- [x] Criar novo tenant:
  - [x] Nome
  - [x] Slug (auto-gerado)
  - [x] Status (enum)
  - [x] Plan (free, standard, enterprise)
  - [x] Timezone
  - [x] Locale (pt-BR, en-US)
  - [x] Moeda
- [x] Editar tenant (exceto ID)
- [x] Soft delete com recover
- [x] Visualizar detalhes (com info de criação/atualização)

**Componentes:**
- [x] TenantTable (listar com ações)
- [x] TenantForm (create + edit)
- [x] TenantCard (widget)
- [x] DeleteModal (confirmação)

**Testes:**
- [x] Listar tenants renderiza corretamente
- [x] Busca filtra tenants
- [x] Criar tenant salva no BD
- [x] Editar tenant atualiza dados
- [x] Deletar tenant soft deletes
- [x] Validações server-side funcionam
- [x] Paginação funciona
- [x] Usuário sem permissão → 403

**Nota de implementação:** `ARCHIVED` não existe no enum real de `tenants`.
O arquivo/arquivamento foi implementado como `deleted_at` via SoftDeletes,
mantendo os status reais do schema (`TRIAL`, `ACTIVE`, `SUSPENDED`, `CANCELLED`).

---

## 🎯 Sprint F3.5 — User Management

### Checklist (25 itens)

**Views:**
- [ ] Lista de usuários (`/users`)
- [ ] Detalhe de usuário (`/users/{id}`)
- [ ] Criar usuário (`/users/create`)
- [ ] Editar usuário (`/users/{id}/edit`)
- [ ] Alterar password
- [ ] Deletar usuário

**Funcionalidades:**
- [ ] Listagem com filtro por tenant
- [ ] Busca por nome/email
- [ ] Filtro por status
- [ ] Ordenação
- [ ] Criar usuário:
  - [ ] Nome
  - [ ] Email (único por tenant)
  - [ ] Password (gerado ou definido)
  - [ ] Status (ACTIVE, INACTIVE, SUSPENDED)
  - [ ] Roles (multi-select)
  - [ ] Validação de email
- [ ] Editar usuário (nome, status, roles)
- [ ] Alterar password (com validação)
- [ ] Resetar password (enviar link)
- [ ] Visualizar últimas ações do usuário
- [ ] Ativar/Desativar usuário
- [ ] Assign roles dinamicamente

**Componentes:**
- [ ] UserTable (listar com ações)
- [ ] UserForm (create + edit)
- [ ] RoleSelector (multi-checkbox)
- [ ] PasswordForm (reset)

**Testes:**
- [ ] Listar usuários do tenant correto
- [ ] Criar usuário válido
- [ ] Email duplicado → erro
- [ ] Editar usuário funciona
- [ ] Alterar password funciona
- [ ] Soft delete de usuário
- [ ] Assign role a usuário
- [ ] Filtros funcionam

---

## 🎯 Sprint F3.6 — Company & Roles Management

### Checklist (20 itens)

**Company Management:**
- [ ] Lista de empresas (`/companies`)
- [ ] Criar empresa
- [ ] Editar empresa
- [ ] Deletar empresa
- [ ] Listar endereços da empresa
- [ ] Criar endereço

**Roles & Permissions Management:**
- [ ] Lista de roles (`/roles`)
- [ ] Lista de permissions (`/permissions`)
- [ ] Visualizar permissions de cada role
- [ ] Assign permissions a role (modal)
- [ ] Visualizar usuários com determinado role

**Componentes:**
- [ ] CompanyTable
- [ ] CompanyForm
- [ ] RoleTable
- [ ] PermissionTable
- [ ] PermissionAssigner (modal)

**Testes:**
- [ ] CRUD de companies funciona
- [ ] Endereços ligados a empresas
- [ ] Roles e permissions listam corretamente
- [ ] Assign permission a role salva
- [ ] Filtros de roles/permissions funcionam

---

## 📁 Estrutura de Diretórios

`✅` já existe · `⬜` chega no sprint indicado

```
lucraone-backend/
├── resources/
│   ├── css/app.css ................................... ✅ design system (@theme)
│   ├── js/app.js ..................................... ✅ Alpine.js
│   └── views/
│       ├── components/
│       │   ├── layouts/
│       │   │   ├── app.blade.php ..................... ✅ painel com sidebar
│       │   │   └── auth.blade.php .................... ✅ card centrado
│       │   ├── sidebar.blade.php ..................... ✅
│       │   ├── nav-item.blade.php .................... ✅
│       │   ├── button.blade.php ...................... ✅
│       │   ├── input.blade.php ....................... ✅
│       │   ├── select.blade.php ...................... ✅
│       │   ├── form-group.blade.php .................. ✅
│       │   ├── card.blade.php ........................ ✅
│       │   ├── kpi.blade.php ......................... ✅
│       │   ├── badge.blade.php ....................... ✅
│       │   ├── alert.blade.php ....................... ✅
│       │   ├── modal.blade.php ....................... ✅
│       │   ├── table.blade.php ....................... ✅
│       │   └── section-label.blade.php ............... ✅
│       ├── auth/login.blade.php ...................... ✅ stub → F3.2 funcional
│       ├── errors/{403,404,419}.blade.php ............ ⬜ F3.2
│       ├── dashboard/index.blade.php ................. ✅ dados reais no F3.3
│       ├── tenants/ .................................. ✅ F3.4
│       ├── users/ .................................... ⬜ F3.5
│       ├── companies/ ................................ ⬜ F3.6
│       └── roles/ .................................... ⬜ F3.6
├── app/Http/
│   ├── Middleware/
│   │   ├── AutenticarWeb.php ......................... ✅ alias auth.web
│   │   └── RedirecionarSeAutenticado.php ............. ✅ alias convidado
│   ├── Controllers/Web/
│   │   ├── Auth/LoginController.php .................. ⬜ F3.2
│   │   ├── DashboardController.php ................... ✅ F3.3
│   │   ├── TenantController.php ...................... ✅ F3.4
│   │   ├── UserController.php ........................ ⬜ F3.5
│   │   ├── CompanyController.php ..................... ⬜ F3.6
│   │   └── RoleController.php ........................ ⬜ F3.6
│   └── Requests/
│       ├── Auth/LoginRequest.php ..................... ⬜ F3.2
│       ├── StoreTenantRequest.php .................... ✅ F3.4
│       ├── StoreUserRequest.php ...................... ⬜ F3.5
│       └── StoreCompanyRequest.php ................... ⬜ F3.6
├── routes/web.php .................................... ✅
├── vite.config.js .................................... ✅ fontes via bunny()
└── tests/Feature/Admin/LayoutTest.php ................ ✅ 6 testes
```

> Não existe `tailwind.config.js`: no Tailwind v4 a configuração vive em
> `resources/css/app.css`, dentro do bloco `@theme`.

---

## 🧪 Como Testar Cada Sprint (Validação Manual)

Ao final de cada sprint você poderá validar pelo navegador. Nenhuma dessas
validações exige Postman ou tinker.

### ✅ Após F3.1 — Setup & Layout (disponível agora)
```
1. npm run build            (roda no host — node_modules não está no container)
2. Abrir http://localhost:8000/login
   ✅ Card com logo LUCRAONE, ONE em gradiente laranja→rosa
   ✅ Campos e-mail/senha estilizados, botão em gradiente
3. Abrir http://localhost:8000/dashboard sem sessão
   ✅ Redireciona para /login
4. Abrir http://localhost:8000/preview-login       ⚠️ rota temporária
   ✅ Sidebar com menu, KPIs e rodapé com usuário
```

### Após F3.2 — Authentication
```
1. Abrir http://localhost:8000/login
2. E-mail: admin@lucraone-dev.local
   Senha:  password                    (definida no UserSeeder)
3. ✅ Login redireciona para /dashboard
4. ✅ Senha errada mostra erro sem revelar se o e-mail existe
5. ✅ Botão "sair" na sidebar volta para /login
6. ✅ Voltar para /dashboard depois de sair → redireciona para /login
7. ✅ /preview-login não existe mais
```

### Após F3.3 — Dashboard
```
1. Após login, ver o painel
2. ✅ Menu lateral lista: Dashboard, Tenants, Usuários, Empresas, Roles
3. ✅ Widgets mostram contagens reais (tenants, usuários, empresas)
4. ✅ Reduzir janela → menu vira versão mobile
```

### Após F3.4 — Tenant Management
```
1. Menu → Tenants → botão "Novo Estabelecimento"
2. Preencher nome, plano, timezone, moeda
3. ✅ Tenant aparece na listagem
4. ✅ Buscar por nome filtra a lista
5. ✅ Editar altera os dados
6. ✅ Excluir pede confirmação e faz soft delete
```

### Após F3.5 — User Management
```
1. Menu → Usuários → "Novo Usuário"
2. Preencher nome, email, senha, selecionar role "Operador"
3. ✅ Usuário aparece na listagem do tenant
4. ✅ Logout e login com o novo usuário funciona
5. ✅ Email duplicado no mesmo tenant é rejeitado
6. ✅ Usuário desativado não consegue logar
```

### Após F3.6 — Company & Roles
```
1. Menu → Empresas → "Nova Empresa"
2. ✅ Empresa criada e vinculada ao tenant
3. ✅ Adicionar endereço à empresa
4. Menu → Roles → selecionar um papel
5. ✅ Marcar/desmarcar permissões e salvar
6. ✅ Usuário com role limitado não vê telas restritas
```

---

## ✅ Critério de Sucesso FASE 03

- [ ] Usuário consegue fazer login pela interface web
- [ ] Dashboard carrega com dados reais do banco
- [ ] Usuário consegue criar novo tenant (estabelecimento) pela tela
- [ ] Usuário consegue criar novos usuários pela tela
- [ ] Permissões são respeitadas (RBAC) nas telas
- [ ] Layout responsivo em mobile
- [ ] Todos componentes Blade funcionam
- [ ] Validações server-side funcionam
- [ ] Mensagens de sucesso/erro exibem
- [ ] 53+ testes passando

**Ao concluir:** a FASE 02 retoma no Sprint F2.2 — Inventory Management.

---

## 📌 Dívida Técnica

### Resolvida no F3.1

- [x] Migration `personal_access_tokens.tokenable_id` para ULID
      (também corrigida a ordem: índice morph cai antes da coluna)
- [x] 5 testes com falha de constraint — eram bugs reais de migration, não dos testes
- [x] `config/auth.php` apontando para o `User` errado
- [x] Rota de health é `/api/health`, não `/health`
- [x] Container `app` não tem `bash` — usar `docker-compose exec app sh`
- [x] CI não compilava assets

### Aberta

| Item | Onde resolver |
|------|---------------|
| 🔴 Rota `/preview-login` faz bypass de autenticação (restrita a `local`) | **F3.2 — primeira tarefa** |
| `resources/views/welcome.blade.php` ficou órfão após `/` redirecionar | ✅ resolvida no F3.3 |
| Responsividade mobile só verificada por código, sem teste automatizado | ✅ resolvida no F3.3 |

---

**Sprint atual: F3.5 — User Management** 🚀
