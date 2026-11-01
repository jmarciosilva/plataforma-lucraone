{{--
    Nav do segmento SUPERMARKET — extraído de sidebar.blade.php (MS-03).
    Conteúdo idêntico ao nav original: zero mudança de comportamento.
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

@if ($contexto->resolved() && auth()->user()->hasAnyPermission(['view-branches', 'view-all-branches', 'manage-branches'], $contexto->id()))
    <x-nav-group
        rotulo="pdv"
        :rotas="['terminals.index', 'pdv-operators.index', 'pdv.sales.index', 'pdv.catalog-loads.index']"
    >
        <x-slot:icone>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <rect x="2" y="3" width="20" height="14" rx="2" />
                <path d="M8 21h8" />
                <path d="M12 17v4" />
                <path d="M7 8h4" />
                <path d="M7 11h6" />
            </svg>
        </x-slot:icone>

        <x-nav-item rota="terminals.index" rotulo="terminais de pdv">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <rect x="2" y="3" width="20" height="14" rx="2" />
                <path d="M8 21h8" />
                <path d="M12 17v4" />
                <path d="M7 8h4" />
                <path d="M7 11h6" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="pdv-operators.index" rotulo="operadores de pdv">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <rect x="3" y="4" width="18" height="12" rx="2" />
                <path d="M7 20h10" />
                <path d="M9 16v4" />
                <path d="M15 16v4" />
                <path d="M7 9h4" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="pdv.sales.index" rotulo="vendas de pdv">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <path d="M3 3v18h18" />
                <path d="M7 15l4-4 3 3 5-6" />
            </svg>
        </x-nav-item>

        @if ((config('pdv_catalog.enabled', false) || config('pdv_catalog.ui_preview_enabled', false)) && auth()->user()->canAccessTenant($contexto->id())
            && auth()->user()->hasAnyPermission(['view-products', 'manage-products'], $contexto->id())
            && auth()->user()->hasAnyPermission(['view-companies', 'manage-companies'], $contexto->id()))
            <x-nav-item rota="pdv.catalog-loads.index" rotulo="central de cargas pdv">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                    <path d="M12 3v12" /><path d="m7 10 5 5 5-5" /><path d="M4 19h16" />
                </svg>
            </x-nav-item>
        @endif
    </x-nav-group>
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

<x-nav-item rota="sales.orders.index" rotulo="vendas">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M3 4h2l2.4 12.3a2 2 0 0 0 2 1.7h7.7a2 2 0 0 0 2-1.6L21 8H6" />
        <circle cx="10" cy="20" r="1" />
        <circle cx="18" cy="20" r="1" />
    </svg>
</x-nav-item>

<x-nav-item rota="sales.customers.index" rotulo="clientes">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
        <circle cx="12" cy="7" r="4" />
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
