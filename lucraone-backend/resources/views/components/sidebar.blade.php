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
@endphp

<aside {{ $attributes->merge(['class' => 'w-60 shrink-0 flex-col border-r border-linha bg-white flex ' . $class]) }}>

    {{-- Marca --}}
    <div class="px-5 pb-2 pt-6">
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
    <nav class="mt-4 flex flex-1 flex-col gap-1 px-3" aria-label="navegação principal">
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

        <x-nav-item rota="usuarios" rotulo="usuários">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="empresas" rotulo="empresas">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <rect x="3" y="7" width="18" height="14" rx="2" />
                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="permissoes" rotulo="permissões">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <rect x="4" y="10" width="16" height="11" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg>
        </x-nav-item>

        <x-nav-item rota="produtos" rotulo="produtos">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <path d="M21 8l-9-5-9 5 9 5 9-5z" />
                <path d="M3 8v8l9 5 9-5V8" />
                <path d="M12 13v8" />
            </svg>
        </x-nav-item>
    </nav>

    {{-- Rodapé: usuário e saída --}}
    <div class="mt-auto border-t border-linha px-3 py-4">
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
