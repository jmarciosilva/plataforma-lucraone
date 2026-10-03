<x-layouts.app title="dashboard" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        visão geral do estabelecimento em {{ $tenantTimezone }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-dashboard')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('dashboard') }}">atualizar</x-button>
        @if ($comercial)
            <x-button variante="secundario" href="{{ route('reports.index') }}">relatórios</x-button>
        @endif
    </x-slot:acoes>

    @include('dashboard._help')
    @include('dashboard._onboarding-checklist')

    @if ($comercial)
        <x-section-label>o negócio nos últimos 30 dias</x-section-label>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi
                rotulo="faturamento"
                :valor="number_format($comercial['faturamento']['valor'], 2, ',', '.')"
                :variacao="$comercial['faturamento']['variacao']"
                comparativo="vs. 30 dias anteriores"
                nota="pedidos enviados e concluídos"
            />
            <x-kpi
                rotulo="pedidos"
                :valor="$comercial['pedidos']['valor']"
                :variacao="$comercial['pedidos']['variacao']"
                comparativo="vs. 30 dias anteriores"
                nota="pedidos faturados"
            />
            <x-kpi
                rotulo="ticket médio"
                :valor="number_format($comercial['ticket_medio']['valor'], 2, ',', '.')"
                :variacao="$comercial['ticket_medio']['variacao']"
                comparativo="vs. 30 dias anteriores"
                nota="faturamento por pedido"
            />
            <x-kpi
                rotulo="estoque a custo"
                :valor="number_format($comercial['estoque']['valor_custo'], 2, ',', '.')"
                nota="{{ $comercial['estoque']['baixo'] }} item(ns) em baixo estoque"
            />
        </div>

        <div class="mt-3">
            <a href="{{ route('reports.index') }}" class="text-sm font-semibold lowercase text-sol transition-opacity hover:opacity-70">
                abrir relatórios completos →
            </a>
        </div>
    @endif

    <x-section-label class="{{ $comercial ? 'mt-8' : '' }}">o painel até agora</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="estabelecimentos" :valor="$metricas['tenants']" nota="disponíveis para o seu acesso" />
        <x-kpi rotulo="usuários" :valor="$metricas['usuarios']" nota="vínculos ativos neste estabelecimento" />
        <x-kpi rotulo="empresas" :valor="$metricas['empresas']" nota="empresas cadastradas" />
        <x-kpi rotulo="produtos" :valor="$metricas['produtos']" nota="produtos deste estabelecimento" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold lowercase text-grafite">último acesso</p>
                    <p class="mt-1 font-comanda text-xl text-grafite">{{ $metricas['ultimoAcesso'] }}</p>
                </div>
                <x-badge tipo="sucesso">sessão ativa</x-badge>
            </div>

            <p class="mt-4 text-sm text-aco">
                Os números desta página consideram apenas o estabelecimento em
                uso. Para ver outro, troque o estabelecimento no menu.
            </p>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">navegação</p>
            <p class="mt-2 text-sm text-aco">
                Use o menu lateral no desktop ou o botão de menu no mobile para
                acessar as próximas áreas do administrativo.
            </p>
        </x-card>
    </div>

    <x-section-label class="mt-8">atalhos</x-section-label>

    @php
        /*
        | Só destinos já entregues.
        |
        | Até o ONB-01A este bloco citava os nomes `tenants`, `usuarios` e
        | `empresas`, que nunca existiram como nomes de rota. O `Route::has()`
        | falhava, os três cartões viravam `href="#"` desabilitados e anunciavam
        | como futuras as telas de cadastro que a F3.4, a F3.5 e a F3.6 já
        | tinham entregue. Agora as rotas são citadas pelo nome real, e
        | DashboardAtalhosTest confere que cada uma existe.
        */
        $atalhos = [
            [
                'rota' => 'users.index',
                'titulo' => 'usuários',
                'texto' => 'cadastre e administre a equipe deste estabelecimento',
            ],
            [
                'rota' => 'companies.index',
                'titulo' => 'empresas',
                'texto' => 'dados fiscais do negócio — necessários antes do primeiro produto',
            ],
            [
                'rota' => 'catalog.products.index',
                'titulo' => 'produtos',
                'texto' => 'catálogo, preços e embalagens',
            ],
        ];

        // Administração da plataforma só para quem a TenantPolicy autoriza:
        // oferecer o caminho a quem receberia 403 é desorientar.
        if (auth()->user()->isPlatformAdmin()) {
            $atalhos[] = [
                'rota' => 'tenants.index',
                'titulo' => 'clientes do LucraOne',
                'texto' => 'cadastre e administre os estabelecimentos que usam a plataforma',
            ];
        }
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($atalhos as $atalho)
            <a
                href="{{ route($atalho['rota']) }}"
                class="cartao p-5 transition-colors hover:bg-nevoa"
            >
                <p class="text-sm font-semibold lowercase text-grafite">{{ $atalho['titulo'] }}</p>
                <p class="mt-2 text-sm text-aco">{{ $atalho['texto'] }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.app>
