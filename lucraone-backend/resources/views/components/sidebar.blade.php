@props(['class' => ''])

@php
    // O estabelecimento em uso vem do contexto da requisição — uma pessoa pode
    // estar associada a vários e alternar entre eles.
    $contexto = app(\App\Modules\Tenancy\Application\TenantContext::class);
    $filial = app(\App\Modules\Tenancy\Application\BranchContext::class);

    $tenantNome = $contexto->resolved()
        ? $contexto->tenant()->name
        : config('app.name');

    $ambiente = strtoupper(app()->environment());

    // Só faz sentido oferecer a troca para quem tem mais de um vínculo
    $podeTrocar = auth()->check()
        && auth()->user()->estabelecimentosDisponiveis()->count() > 1;

    // Trocar filial: só quando o tenant está resolvido e existem 2+ filiais ativas.
    $podeTrocarFilial = $contexto->resolved()
        && \App\Modules\Branches\Domain\Models\Branch::where('tenant_id', $contexto->id())
            ->where('status', 'ACTIVE')
            ->limit(2)
            ->count() > 1;

    // Contador de avisos: só consulta com estabelecimento resolvido, senão o
    // escopo de tenant não filtraria nada.
    $avisosNaoLidos = auth()->check() && $contexto->resolved()
        ? \App\Http\Controllers\Web\NotificationWebController::naoLidos(auth()->id())
        : 0;

    $eRestaurante = $filial->resolved()
        && $filial->segment() === \App\Modules\Branches\Domain\BranchSegment::RESTAURANT;
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

        @if ($podeTrocarFilial)
            <a
                href="{{ route('filiais.escolher') }}"
                class="mt-1 inline-flex items-center gap-1 text-xs font-semibold lowercase text-sol transition-opacity hover:opacity-70"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true">
                    <path d="M3 21h18" />
                    <path d="M5 21V7l7-4 7 4v14" />
                    <path d="M9 21v-4h6v4" />
                </svg>
                {{ $filial->resolved() ? 'trocar filial' : 'selecionar filial' }}
            </a>
        @endif
    </div>

    {{-- Navegação —— despacha pelo segmento da filial ativa (MS-03). --}}
    {{--
        min-h-0 + overflow-y-auto: sem o min-h-0 o item flex se recusa a encolher
        abaixo do conteúdo e a lista vaza por baixo do rodapé em telas baixas.
    --}}
    <nav class="mt-4 flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 pb-2" aria-label="navegação principal">
        @if ($eRestaurante)
            @include('components._nav-restaurant', ['contexto' => $contexto, 'avisosNaoLidos' => $avisosNaoLidos])
        @else
            @include('components._nav-supermarket', ['contexto' => $contexto, 'avisosNaoLidos' => $avisosNaoLidos])
        @endif
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
