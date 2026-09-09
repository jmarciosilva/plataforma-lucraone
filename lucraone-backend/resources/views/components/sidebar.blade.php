@props(['class' => ''])

@php
    // O estabelecimento em uso vem do contexto da requisição — uma pessoa pode
    // estar associada a vários e alternar entre eles.
    $contexto = app(\App\Modules\Tenancy\Application\TenantContext::class);

    $tenantNome = $contexto->resolved()
        ? $contexto->tenant()->name
        : config('app.name');

    $ambiente = strtoupper(app()->environment());

    // Só faz sentido oferecer a troca para quem tem mais de um vínculo
    $podeTrocar = auth()->check()
        && auth()->user()->estabelecimentosDisponiveis()->count() > 1;

    // Contador de avisos: só consulta com estabelecimento resolvido, senão o
    // escopo de tenant não filtraria nada.
    $avisosNaoLidos = auth()->check() && $contexto->resolved()
        ? \App\Http\Controllers\Web\NotificationWebController::naoLidos(auth()->id())
        : 0;
@endphp

<aside {{ $attributes->merge(['class' => 'w-60 shrink-0 flex-col border-r border-linha bg-white flex ' . $class]) }}>

    {{-- Marca --}}
    <div class="shrink-0 px-5 pb-2 pt-6">
        <p class="font-letreiro text-lg leading-none tracking-tight text-grafite">
            LUCRA<span class="texto-sol">ONE</span>
        </p>
        <p class="mt-2 font-comanda text-[0.6rem] uppercase tracking-[0.18em] text-aco">
            {{ \Illuminate\Support\Str::limit($tenantNome, 18) }} · {{ $ambiente }}
        </p>

        @if ($podeTrocar)
            <a
                href="{{ route('estabelecimentos.escolher') }}"
                class="mt-2 inline-flex items-center gap-1 text-xs font-semibold lowercase text-sol transition-opacity hover:opacity-70"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true">
                    <path d="M7 16H3v-4" />
                    <path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
                    <path d="M17 8h4v4" />
                    <path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
                </svg>
                trocar estabelecimento
            </a>
        @endif
    </div>

    {{-- Navegação --}}
    {{--
        min-h-0 + overflow-y-auto: sem o min-h-0 o item flex se recusa a encolher
        abaixo do conteúdo e a lista vaza por baixo do rodapé em telas baixas.
    --}}
    <nav class="mt-4 flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 pb-2" aria-label="navegação principal">
        <x-nav-item rota="dashboard" rotulo="dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <path d="M3 17l6-6 4 4 7-7" />
                <path d="M14 8h6v6" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="tenants.index" rotulo="tenants">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <path d="M3 21h18" />
                <path d="M5 21V7l7-4 7 4v14" />
                <path d="M10 21v-5h4v5" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="users.index" rotulo="usuários">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="companies.index" rotulo="empresas">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <rect x="3" y="7" width="18" height="14" rx="2" />
                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="roles.index" rotulo="permissões">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <rect x="4" y="10" width="16" height="11" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="catalog.products.index" rotulo="produtos">
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
    </nav>

    {{-- Rodapé: usuário e saída --}}
    <div class="mt-auto shrink-0 border-t border-linha px-3 py-4">
        @auth
            <p class="px-2 text-sm font-semibold lowercase text-grafite">
                {{ auth()->user()->name }}
            </p>
            <p class="px-2 text-xs text-aco">
                {{ auth()->user()->email }}
            </p>

            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button
                    type="submit"
                    class="flex min-h-11 w-full items-center gap-3 rounded-xl px-2 text-sm font-semibold text-aco transition-colors hover:bg-nevoa hover:text-grafite"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <path d="M16 17l5-5-5-5" />
                        <path d="M21 12H9" />
                    </svg>
                    sair
                </button>
            </form>
        @endauth
    </div>
</aside>
