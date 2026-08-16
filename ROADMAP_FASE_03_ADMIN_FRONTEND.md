# FASE 03 — ADMIN FRONTEND

**Objetivo:** Criar interface web administrativa com Blade + Tailwind para gerenciar tenants, usuários, empresas e permissões.

**Status:** 📋 A INICIAR (0/6 sprints)  
**Duração Estimada:** ~6-8 semanas  
**Stack:** Laravel Blade + Tailwind CSS + Alpine.js  
**Dependência:** FASE 01 ✅ · F2.1 ✅  
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
| **F3.1** | Frontend Setup & Layout | 📋 TODO | 0/15 | 0/5 | Página abre com Tailwind aplicado |
| **F3.2** | Authentication | 📋 TODO | 0/20 | 0/8 | **Login em `/login` funciona** |
| **F3.3** | Admin Dashboard | 📋 TODO | 0/15 | 0/6 | Painel com menu e widgets |
| **F3.4** | Tenant Management | 📋 TODO | 0/25 | 0/12 | **Criar estabelecimento pela tela** |
| **F3.5** | User Management | 📋 TODO | 0/25 | 0/12 | **Criar usuário e logar com ele** |
| **F3.6** | Company & Roles | 📋 TODO | 0/20 | 0/10 | Empresas + permissões pela tela |

**Total:** 120 itens · ~53 testes

**Legenda:** 📋 TODO · 🟡 IN PROGRESS · ✅ DONE · ⏸️ PAUSADO

---

## 🎯 Sprint F3.1 — Frontend Setup & Layout

### Checklist (15 itens)

> 🎨 Todo o visual segue o [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md).

- [ ] Instalar Tailwind CSS via npm + configurar Vite
- [ ] **Aplicar paleta no `tailwind.config.js`** — sol `#FF7A00`, ambar, brasa,
      ok, grafite, aco, linha, nevoa; raio `modulo: 14px`; sombra `cartao`
- [ ] **Criar utilitários customizados** — `.cartao`, `.gradiente-sol`,
      `.gradiente-sol-suave`, `.texto-sol`, `.rotulo-secao`
- [ ] **Carregar fontes** — Karla (corpo), Archivo (logo), Martian Mono (valores)
- [ ] Configurar estrutura de arquivos (layouts, components, views)
- [ ] **Sidebar 240px** — logo `lucra.one` com `.texto-sol`, subtítulo com nome
      do tenant ativo, itens de menu com ícone 32×32 e estado ativo em gradiente
- [ ] Criar layout principal (sidebar + área de conteúdo)
- [ ] Criar componentes reutilizáveis:
  - [ ] Button (primário com `gradiente-sol`, secundário, danger com `brasa`)
  - [ ] Input (text, email, password, textarea) — borda `linha`, foco `sol`
  - [ ] Select (dropdown)
  - [ ] Modal
  - [ ] Alert/Toast (sucesso `ok`, erro `brasa`, atenção `alerta`)
  - [ ] Table (dentro de `.cartao`, com paginação)
  - [ ] Form Group (label + input + error)
  - [ ] **Card de KPI** — rótulo pequeno → valor grande em mono → variação com seta
  - [ ] **Rótulo de seção** — MAIÚSCULAS, `tracking-wide`, cor `aco`
- [ ] Criar página de welcome/placeholder
- [ ] Setup Alpine.js para interatividade
- [ ] Criar middleware para auth em web routes
- [ ] Configurar redirects (login → dashboard)
- [ ] Testes de componentes renderizarem corretamente

---

## 🎯 Sprint F3.2 — Authentication (Login/Logout)

### Checklist (20 itens)

**Views:**
- [ ] Tela de login (`/login`)
- [ ] Tela de logout (redirect)
- [ ] Tela de "não autorizado" (403)
- [ ] Tela de "não encontrado" (404)

**Funcionalidades:**
- [ ] Formulário de login (email + password)
- [ ] Validação client-side (Alpine.js)
- [ ] Validação server-side (FormRequest)
- [ ] Autenticação via Sanctum
- [ ] Armazenar token em session/cookie
- [ ] Logout (limpar sessão)
- [ ] Verificação de tenant na sessão
- [ ] "Lembrar-me" (optional)
- [ ] Tratamento de erros (credenciais inválidas)
- [ ] Redirect para dashboard após login
- [ ] Middleware web-only (não permite API)

**Testes:**
- [ ] Login com credenciais válidas → dashboard
- [ ] Login com credenciais inválidas → erro
- [ ] Logout redireciona para login
- [ ] Usuário não autenticado → login
- [ ] Session persiste entre requisições
- [ ] Token é armazenado corretamente
- [ ] Múltiplos usuários mesmo tenant não veem dados um do outro
- [ ] Logout limpa session corretamente

---

## 🎯 Sprint F3.3 — Admin Dashboard

### Checklist (15 itens)

**Layout:**
- [ ] Sidebar com navegação
- [ ] Header com logout + info do usuário
- [ ] Breadcrumbs
- [ ] Rodapé
- [ ] Menu responsivo (mobile)

**Dashboard:**
- [ ] Widgets de resumo:
  - [ ] Total de tenants
  - [ ] Total de usuários
  - [ ] Total de empresas
  - [ ] Último acesso
- [ ] Gráficos iniciais (Chart.js opcional)
- [ ] Atalhos para principais seções
- [ ] Feed de atividades (opcional)

**Navegação:**
- [ ] Menu: Dashboard → Tenants → Usuários → Empresas → Roles/Permissions
- [ ] Links contextuais
- [ ] Active state de menu

**Testes:**
- [ ] Dashboard carrega corretamente
- [ ] Menu renderiza todas opções
- [ ] Links funcionam corretamente
- [ ] Breadcrumbs atualizados
- [ ] Responsivo em mobile

---

## 🎯 Sprint F3.4 — Tenant Management

### Checklist (25 itens)

**Views:**
- [ ] Lista de tenants (`/tenants`)
- [ ] Detalhe de tenant (`/tenants/{id}`)
- [ ] Criar tenant (`/tenants/create`)
- [ ] Editar tenant (`/tenants/{id}/edit`)
- [ ] Deletar tenant (modal confirmação)

**Funcionalidades:**
- [ ] Listagem com paginação (20 por página)
- [ ] Busca por nome
- [ ] Filtro por status (ACTIVE, INACTIVE, TRIAL, ARCHIVED)
- [ ] Ordenação (nome, status, criação)
- [ ] Criar novo tenant:
  - [ ] Nome
  - [ ] Slug (auto-gerado)
  - [ ] Status (enum)
  - [ ] Plan (free, standard, enterprise)
  - [ ] Timezone
  - [ ] Locale (pt-BR, en-US)
  - [ ] Moeda
- [ ] Editar tenant (exceto ID)
- [ ] Soft delete com recover
- [ ] Visualizar detalhes (com info de criação/atualização)

**Componentes:**
- [ ] TenantTable (listar com ações)
- [ ] TenantForm (create + edit)
- [ ] TenantCard (widget)
- [ ] DeleteModal (confirmação)

**Testes:**
- [ ] Listar tenants renderiza corretamente
- [ ] Busca filtra tenants
- [ ] Criar tenant salva no BD
- [ ] Editar tenant atualiza dados
- [ ] Deletar tenant soft deletes
- [ ] Validações server-side funcionam
- [ ] Paginação funciona
- [ ] Usuário sem permissão → 403

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

## 📁 Estrutura de Diretórios (Nova)

```
lucraone-backend/
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── app.blade.php        (layout principal)
│   │   │   ├── auth.blade.php       (layout login)
│   │   │   └── error.blade.php      (erro)
│   │   ├── components/              (componentes reutilizáveis)
│   │   │   ├── button.blade.php
│   │   │   ├── input.blade.php
│   │   │   ├── modal.blade.php
│   │   │   ├── table.blade.php
│   │   │   └── sidebar.blade.php
│   │   ├── auth/
│   │   │   ├── login.blade.php
│   │   │   └── logout.blade.php
│   │   ├── dashboard/
│   │   │   └── index.blade.php
│   │   ├── tenants/
│   │   │   ├── index.blade.php
│   │   │   ├── create.blade.php
│   │   │   ├── edit.blade.php
│   │   │   └── show.blade.php
│   │   ├── users/
│   │   │   ├── index.blade.php
│   │   │   ├── create.blade.php
│   │   │   ├── edit.blade.php
│   │   │   └── show.blade.php
│   │   ├── companies/
│   │   │   ├── index.blade.php
│   │   │   ├── create.blade.php
│   │   │   └── edit.blade.php
│   │   └── roles/
│   │       ├── index.blade.php
│   │       └── show.blade.php
│   └── css/
│       └── app.css                  (Tailwind + custom)
│   └── js/
│       └── app.js                   (Alpine.js + bundle)
├── app/
│   └── Http/
│       ├── Controllers/
│       │   ├── Web/
│       │   │   ├── DashboardController.php
│       │   │   ├── TenantController.php
│       │   │   ├── UserController.php
│       │   │   ├── CompanyController.php
│       │   │   └── RoleController.php
│       │   └── Auth/
│       │       ├── LoginController.php
│       │       └── LogoutController.php
│       └── Requests/
│           ├── LoginRequest.php
│           ├── StoreTenantRequest.php
│           ├── StoreUserRequest.php
│           └── StoreCompanyRequest.php
├── routes/
│   └── web.php                      (web routes para Blade)
└── tailwind.config.js               (Tailwind customizado)
```

---

## 🚀 Próximos Passos

### Fase 03.1 Iniciará com:
1. **Instalação do Tailwind CSS**
2. **Criação de layout base**
3. **Componentes Blade reutilizáveis**
4. **Setup de routes web**
5. **Autenticação web middleware**

### Dependências Novas:
```bash
npm install -D tailwindcss postcss autoprefixer
npm install alpinejs
```

### Controllers Necessários:
- `Web/DashboardController`
- `Web/Auth/LoginController`
- `Web/Auth/LogoutController`
- `Web/TenantController`
- `Web/UserController`
- `Web/CompanyController`
- `Web/RoleController`

---

## 🧪 Como Testar Cada Sprint (Validação Manual)

Ao final de cada sprint você poderá validar pelo navegador. Nenhuma dessas
validações exige Postman ou tinker.

### Após F3.1 — Setup & Layout
```
1. docker-compose exec app npm run build
2. Abrir http://localhost:8000
3. ✅ Página renderiza com estilos Tailwind (fontes, cores, espaçamento)
4. ✅ Acessar /dashboard sem login → redireciona para /login
```

### Após F3.2 — Authentication
```
1. Abrir http://localhost:8000/login
2. Email: admin@lucraone-dev.local
3. Senha: (a definida no UserSeeder)
4. ✅ Login redireciona para /dashboard
5. ✅ Senha errada mostra mensagem de erro
6. ✅ Botão logout volta para /login
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

## 📌 Pré-requisitos Técnicos (resolver no F3.1)

Pendências identificadas durante os testes manuais de F2.1:

- [ ] Aplicar migration `2026_08_16_000000_update_personal_access_tokens_for_ulid.php`
      (coluna `tokenable_id` era `bigint`, incompatível com ULID)
- [ ] Corrigir 5 testes com falha de constraint no SQLite in-memory
- [ ] Documentar rota de health correta: `/api/health` (não `/health`)
- [ ] Container `app` não tem `bash` — usar `docker-compose exec app sh`

---

**Pronto para começar F3.1?** 🚀
