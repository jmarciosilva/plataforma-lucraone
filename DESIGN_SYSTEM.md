# Design System — LUCRAONE Admin

**Referência visual:** https://mentor-ia-demo.netlify.app/r/demo/inteligencia  
**Extraído em:** 2026-08-16  
**Aplicação:** FASE 03 — ADMIN FRONTEND (Blade + Tailwind)

> Este documento registra os tokens extraídos da demo de referência aprovada
> pelos sócios. Ele é a fonte da verdade visual para o Sprint F3.1.

---

## 1. Paleta de Cores

Nomes em português, como na referência. Vão para `tailwind.config.js` em `theme.extend.colors`.

| Token | Hex | RGB | Uso |
|-------|-----|-----|-----|
| `sol` | `#FF7A00` | `255,122,0` | **Cor primária** — ações, ícones ativos, destaques |
| `ambar` | `#FFB300` | `255,179,0` | Início do gradiente, avisos suaves |
| `brasa` | `#E11D48` | `225,29,72` | Erro, queda, indicador negativo |
| `ok` | `#16A34A` | `22,163,74` | Sucesso, indicador positivo |
| `alerta` | `#F59E0B` | `245,158,11` | Atenção |
| `ouro` | `#C9954F` | `201,149,79` | Destaque secundário |
| `grafite` | `#1C1917` | `28,25,23` | **Texto principal** e fundo do modo escuro |
| `aco` | `#8A8480` | `138,132,128` | Texto secundário, rótulos, ícones inativos |
| `fumaca` | `#9A9184` | `154,145,132` | Texto terciário |
| `linha` | `#EEE9E4` | `238,233,228` | **Bordas** (cards, divisores, sidebar) |
| `nevoa` | `#FAF8F6` | `250,248,246` | Fundo sutil, hover de menu |
| `branco` | `#FFFFFF` | — | Fundo de cards e da sidebar |

### Gradientes

```css
/* Gradiente primário — ícones ativos, botões de destaque */
.gradiente-sol {
  background-image: linear-gradient(120deg, #FFB300, #FF7A00 48%, #FF4D8D);
}

/* Versão suave — fundo do item de menu ativo */
.gradiente-sol-suave {
  background-image: linear-gradient(120deg, #FFF8E1, #FFF3E8 48%, #FFEEF4);
}

/* Texto com gradiente — usado no ".ia" do logo */
.texto-sol {
  color: transparent;
  background-image: linear-gradient(120deg, #F59E0B, #FF7A00 45%, #FF4D8D);
  background-clip: text;
}
```

---

## 2. Tipografia

A referência carrega 5 famílias via `@font-face`. Para a nossa aplicação, o
essencial são três papéis:

| Papel | Fonte na referência | Uso |
|-------|--------------------|-----|
| Corpo / UI | **Karla** | Texto geral, formulários, tabelas |
| Display / Logo | **Archivo** ou **Sora** | Logo (`.letreiro`), títulos grandes |
| Numérico / Mono | **Martian Mono** | Valores monetários, KPIs (`.comanda`) |

> A fonte mono nos números é uma escolha deliberada: alinha as casas decimais
> em tabelas de valores. Vale manter.

### Escala

| Elemento | Tamanho | Peso | Observação |
|----------|---------|------|------------|
| `h1` (título de página) | `36px` (`text-4xl`) | `700` | minúsculas na referência |
| Valor de KPI | `24px` → `30px` em `lg:` | `500` | fonte mono |
| Rótulo de seção | `14px` | `600` | **MAIÚSCULAS**, `tracking-wide`, cor `aco` |
| Corpo | `16px` | `400` | — |
| Texto secundário | `14px` (`text-sm`) | `400` | cor `aco` |
| Legenda | `12px` (`text-xs`) | `400` | cor `aco` |
| Subtítulo do logo | `0.6rem` | `400` | `tracking-[0.18em]`, MAIÚSCULAS |

**Detalhe de estilo:** títulos e menus da referência usam **letras minúsculas**
(`inteligência · casa alta`, `cozinha`, `caixa`). É uma marca visual do produto.

---

## 3. Componentes Base

### Card (`.cartao`)

```css
.cartao {
  border: 1px solid #EEE9E4;
  border-radius: 14px;
  background: #FFFFFF;
  box-shadow:
    0 1px 2px rgba(28,25,23,0.04),
    0 8px 24px -12px rgba(28,25,23,0.08);
}
```

Padding interno padrão: `p-5` (20px).

### Raios de borda

| Token | Valor | Uso |
|-------|-------|-----|
| `rounded-modulo` | `14px` | Cards |
| `rounded-xl` | `12px` | Item de menu, botões |
| `rounded-lg` | `8px` | Ícones (quadrado 32×32) |
| `rounded-full` | — | Badges, pills, contadores |

### Sidebar

```
Largura:     240px (w-60)
Fundo:       #FFFFFF
Borda:       1px sólida #EEE9E4 à direita
Altura:      100dvh, sticky top-0
Visibilidade: oculta abaixo de lg: (menu mobile separado)
```

Estrutura:
```
┌─ px-5 pt-6 pb-2 ────────────┐
│  mentor.ia        ← logo    │  (.ia com gradiente)
│  CASA ALTA · DEMO ← contexto│  (0.6rem, tracking largo, aco)
├─ nav mt-4 px-3 gap-1 ───────┤
│  [ícone] inteligência       │  ← ativo
│  [ícone] cozinha            │
│  [ícone] caixa              │
│  [ícone] mesas              │
├─ mt-auto ───────────────────┤
│  cardápio público ↗         │  (link secundário, borda superior)
└─────────────────────────────┘
```

### Item de menu

```html
<!-- Inativo -->
<a class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold
          transition-colors text-aco hover:bg-nevoa hover:text-grafite">
  <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-nevoa text-aco">
    <svg>…</svg>
  </span>
  rótulo
</a>

<!-- Ativo -->
<a aria-current="page"
   class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold
          transition-colors gradiente-sol-suave text-grafite">
  <span class="flex h-8 w-8 items-center justify-center rounded-lg gradiente-sol text-white">
    <svg>…</svg>
  </span>
  rótulo
</a>
```

**Regra:** altura mínima `44px` (`min-h-11`) — alvo de toque acessível.

### Card de KPI

```html
<div class="cartao p-5">
  <p class="text-xs text-aco">faturamento</p>
  <p class="comanda mt-1.5 text-2xl font-medium text-grafite lg:text-3xl">R$ 0,00</p>
  <p class="mt-2 flex items-center gap-1 text-xs font-semibold text-brasa">
    <svg class="h-3.5 w-3.5 rotate-180">…</svg>
    <span>-100% <span class="font-normal text-aco">vs. ontem</span></span>
  </p>
</div>
```

Padrão: **rótulo pequeno → valor grande → variação com seta e comparativo**.
Seta para cima + `text-ok` quando positivo; rotacionada 180° + `text-brasa` quando negativo.

### Rótulo de seção

```html
<p class="mb-3 text-sm font-semibold uppercase tracking-wide text-aco">
  O DIA ATÉ AGORA
</p>
```

Agrupa blocos da página. Usado como separador hierárquico em vez de `h2` visualmente pesado.

---

## 4. Layout

```
┌──────────┬────────────────────────────────────────────┐
│          │  Título da página · contexto      [ações]  │
│ Sidebar  │  ● subtítulo com status                    │
│  240px   │                                            │
│          │  RÓTULO DE SEÇÃO                           │
│          │  ┌────┐ ┌────┐ ┌────┐ ┌────┐ ┌────┐        │
│          │  │KPI │ │KPI │ │KPI │ │KPI │ │KPI │        │
│          │  └────┘ └────┘ └────┘ └────┘ └────┘        │
│          │                                            │
│          │  RÓTULO DE SEÇÃO                           │
│          │  ┌──────────────────────────────────────┐  │
│          │  │  card largo (gráfico / tabela)       │  │
│          │  └──────────────────────────────────────┘  │
├──────────┤                                            │
│ rodapé   │                                            │
└──────────┴────────────────────────────────────────────┘
```

- Grade de KPIs: 5 colunas em desktop, empilha em mobile
- Espaçamento entre blocos: `gap-4` / `gap-6`
- Fundo da área de conteúdo: `#FFFFFF` (cards se destacam pela borda + sombra)

### Cabeçalho de página

```html
<h1 class="text-4xl font-bold text-grafite">
  inteligência <span class="text-aco">·</span> <span class="texto-sol">casa alta</span>
</h1>
<p class="text-sm text-aco flex items-center gap-2">
  <span class="h-2 w-2 rounded-full bg-ok"></span>
  dados ao vivo do salão, da cozinha e do caixa
</p>
```

Padrão: **`contexto · nome-do-tenant`**, com o nome do tenant em gradiente.
Para o LUCRAONE: `usuários · Casa Alta`, `produtos · Casa Alta` etc.

---

## 5. Modo Escuro (contextual)

A tela de **cozinha** da referência usa fundo escuro — não é preferência do
usuário, é uma decisão por contexto de uso (tela fixa em ambiente de cozinha,
lida à distância).

```
Fundo:            #1C1917 (grafite)
Texto principal:  #FFFFFF
Texto secundário: #8A8480 (aco)
Cards:            borda sutil, fundo levemente mais claro que o fundo
Colunas de status: NOVOS (sol) · EM PREPARO (ambar) · PRONTOS (ok)
```

Para o admin do LUCRAONE, o modo claro é o padrão. Reservar o escuro para telas
operacionais futuras (PDV, KDS), se surgirem.

---

## 6. Configuração Tailwind

```js
// tailwind.config.js
export default {
  content: ['./resources/**/*.blade.php', './resources/**/*.js'],
  theme: {
    extend: {
      colors: {
        sol:     '#FF7A00',
        ambar:   '#FFB300',
        brasa:   '#E11D48',
        ok:      '#16A34A',
        alerta:  '#F59E0B',
        ouro:    '#C9954F',
        grafite: '#1C1917',
        aco:     '#8A8480',
        fumaca:  '#9A9184',
        linha:   '#EEE9E4',
        nevoa:   '#FAF8F6',
      },
      borderRadius: {
        modulo: '14px',
      },
      boxShadow: {
        cartao: '0 1px 2px rgba(28,25,23,0.04), 0 8px 24px -12px rgba(28,25,23,0.08)',
      },
      fontFamily: {
        sans:      ['Karla', 'system-ui', 'sans-serif'],
        letreiro:  ['Archivo', 'system-ui', 'sans-serif'],
        comanda:   ['"Martian Mono"', 'ui-monospace', 'monospace'],
      },
    },
  },
}
```

### Classes utilitárias customizadas

```css
/* resources/css/app.css */
@layer components {
  .cartao {
    @apply rounded-modulo border border-linha bg-white shadow-cartao;
  }
  .gradiente-sol {
    background-image: linear-gradient(120deg, #FFB300, #FF7A00 48%, #FF4D8D);
  }
  .gradiente-sol-suave {
    background-image: linear-gradient(120deg, #FFF8E1, #FFF3E8 48%, #FFEEF4);
  }
  .texto-sol {
    color: transparent;
    background-image: linear-gradient(120deg, #F59E0B, #FF7A00 45%, #FF4D8D);
    background-clip: text;
  }
  .rotulo-secao {
    @apply mb-3 text-sm font-semibold uppercase tracking-wide text-aco;
  }
}
```

---

## 7. Aplicação nas Telas da FASE 03

| Sprint | Tela | Como aplica a referência |
|--------|------|--------------------------|
| F3.1 | Componentes | `.cartao`, botões com `gradiente-sol`, inputs com borda `linha` |
| F3.2 | Login | Card centralizado com `.cartao`, logo `lucra.one` com `.texto-sol` |
| F3.3 | Dashboard | Grade de KPIs (tenants, usuários, empresas) no padrão da referência |
| F3.4 | Tenants | Tabela dentro de `.cartao`, rótulo de seção, badge de status colorido |
| F3.5 | Usuários | Mesma estrutura de tabela + badges de role |
| F3.6 | Empresas/Roles | Cards de permissão em grade |

### Adaptação da marca

A referência é do produto "mentor.ia". Para o LUCRAONE:

```html
<p class="letreiro text-lg leading-none text-grafite">
  lucra<span class="texto-sol">.one</span>
</p>
<p class="comanda mt-2 text-[0.6rem] tracking-[0.18em] text-aco">
  NOME DO TENANT · AMBIENTE
</p>
```

O subtítulo mostra o **tenant ativo** — resolve visualmente o multi-tenancy,
que hoje só existe no backend.

---

## 8. Decisões a Confirmar

- [ ] Fontes: licenciar Karla/Archivo/Martian Mono (Google Fonts, todas livres)
      ou usar alternativas do sistema?
- [ ] Menu do admin: quais itens? Sugestão inicial —
      `dashboard · tenants · usuários · empresas · permissões · produtos`
- [ ] Nome exibido no logo: `lucra.one`? `LUCRAONE`?
- [ ] Modo escuro: fora do escopo da FASE 03 (só claro) — confirma?

---

**Referência viva:** https://mentor-ia-demo.netlify.app/r/demo/inteligencia
