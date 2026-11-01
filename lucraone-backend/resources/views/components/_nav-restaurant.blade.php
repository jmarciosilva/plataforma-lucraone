{{--
    Nav do segmento RESTAURANT — MS-03.
    Itens ativos: rotas administrativas compartilhadas.
    Itens futuros: estáticos (div, sem href, sem tabindex) com badge "em breve".
    Variáveis esperadas via @include: $contexto (TenantContext), $avisosNaoLidos (int).
--}}

<x-nav-item rota="dashboard" rotulo="dashboard">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M3 17l6-6 4 4 7-7" />
        <path d="M14 8h6v6" />
    </svg>
</x-nav-item>

@if (auth()->user()->isPlatformAdmin())
    <x-nav-item rota="tenants.index" rotulo="clientes do LucraOne">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <path d="M3 21h18" />
            <path d="M5 21V7l7-4 7 4v14" />
            <path d="M10 21v-5h4v5" />
        </svg>
    </x-nav-item>
@endif

<x-nav-item rota="users.index" rotulo="usuários">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
    </svg>
</x-nav-item>

@if ($contexto->resolved() && auth()->user()->hasAnyPermission(['view-branches', 'view-all-branches', 'manage-branches'], $contexto->id()))
    <x-nav-item rota="branches.index" rotulo="unidades / filiais">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <path d="M3 21h18" />
            <path d="M5 21V7l7-4 7 4v14" />
            <path d="M9 21v-4h6v4" />
            <path d="M12 7v4" />
        </svg>
    </x-nav-item>
@endif

<x-nav-item rota="companies.index" rotulo="empresas">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <rect x="3" y="7" width="18" height="14" rx="2" />
        <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
    </svg>
</x-nav-item>

<x-nav-item rota="catalog.products.index" rotulo="produtos e categorias">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M21 8l-9-5-9 5 9 5 9-5z" />
        <path d="M3 8v8l9 5 9-5V8" />
        <path d="M12 13v8" />
    </svg>
</x-nav-item>

<x-nav-item rota="inventory.index" rotulo="estoque">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M4 20V8l8-4 8 4v12" />
        <path d="M4 8l8 4 8-4" />
        <path d="M12 12v8" />
        <path d="M8 16h8" />
    </svg>
</x-nav-item>

<x-nav-item rota="roles.index" rotulo="permissões">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <rect x="4" y="10" width="16" height="11" rx="2" />
        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
    </svg>
</x-nav-item>

<x-nav-item rota="reports.index" rotulo="relatórios">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M3 3v18h18" />
        <path d="M7 15v-4" />
        <path d="M12 15V7" />
        <path d="M17 15v-6" />
    </svg>
</x-nav-item>

<x-nav-item rota="automations.index" rotulo="automações">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M13 2L4.5 13H12l-1 9 8.5-11H12l1-9z" />
    </svg>
</x-nav-item>

<x-nav-item rota="notifications.index" rotulo="avisos" :contador="$avisosNaoLidos">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
        <path d="M13.7 21a2 2 0 0 1-3.4 0" />
    </svg>
</x-nav-item>

{{-- ──────────────────────────────────────────────────────────────────────────
     Funcionalidades do restaurante — em desenvolvimento (REST-02 a REST-04).
     Renderizadas como div estático: sem href, sem tabindex, sem role interativo.
     aria-hidden oculta do leitor de tela — não há destino navegável.
     ────────────────────────────────────────────────────────────────────── --}}

<div class="mt-1 px-1">
    <p class="px-2 py-1 font-comanda text-[0.6rem] uppercase tracking-widest text-aco/50">restaurante</p>
</div>

<x-nav-item rota="cardapio.index" rotulo="cardápio">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M12 2v10" />
        <path d="M18.4 6.6a9 9 0 1 1-12.77.04" />
        <path d="M8 12h8" />
    </svg>
</x-nav-item>

<div
    class="flex min-h-11 cursor-default select-none items-center gap-3 rounded-xl px-3 text-sm font-semibold lowercase"
    aria-hidden="true"
>
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-nevoa text-aco/40">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <path d="M9 9h6" />
            <path d="M9 12h6" />
            <path d="M9 15h4" />
        </svg>
    </span>
    <span class="text-aco/40">mesas e comandas</span>
    <span class="ml-auto font-comanda text-[0.6rem] uppercase tracking-widest text-aco/40">em breve</span>
</div>

<div
    class="flex min-h-11 cursor-default select-none items-center gap-3 rounded-xl px-3 text-sm font-semibold lowercase"
    aria-hidden="true"
>
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-nevoa text-aco/40">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <path d="M3 6h18" />
            <path d="M7 12h10" />
            <path d="M10 18h4" />
        </svg>
    </span>
    <span class="text-aco/40">cozinha / kds</span>
    <span class="ml-auto font-comanda text-[0.6rem] uppercase tracking-widest text-aco/40">em breve</span>
</div>
