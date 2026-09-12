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
        <x-kpi rotulo="tenants" :valor="$metricas['tenants']" nota="estabelecimentos acessíveis" />
        <x-kpi rotulo="usuários" :valor="$metricas['usuarios']" nota="vínculos ativos neste tenant" />
        <x-kpi rotulo="empresas" :valor="$metricas['empresas']" nota="empresas cadastradas" />
        <x-kpi rotulo="produtos" :valor="$metricas['produtos']" nota="produtos do tenant atual" />
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
                O dashboard usa o estabelecimento resolvido na sessão. As contagens
                de empresas e produtos vêm do escopo automático de tenant.
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

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @php
            $atalhos = [
                ['rota' => 'dashboard', 'titulo' => 'dashboard', 'texto' => 'voltar para a visão geral'],
                ['rota' => 'tenants', 'titulo' => 'tenants', 'texto' => 'cadastro chega no F3.4'],
                ['rota' => 'usuarios', 'titulo' => 'usuários', 'texto' => 'cadastro chega no F3.5'],
                ['rota' => 'empresas', 'titulo' => 'empresas', 'texto' => 'gestão chega no F3.6'],
            ];
        @endphp

        @foreach ($atalhos as $atalho)
            @php
                $existe = \Illuminate\Support\Facades\Route::has($atalho['rota']);
            @endphp

            <a
                href="{{ $existe ? route($atalho['rota']) : '#' }}"
                @unless ($existe) aria-disabled="true" @endunless
                class="cartao p-5 transition-colors {{ $existe ? 'hover:bg-nevoa' : 'cursor-not-allowed opacity-60' }}"
            >
                <p class="text-sm font-semibold lowercase text-grafite">{{ $atalho['titulo'] }}</p>
                <p class="mt-2 text-sm text-aco">{{ $atalho['texto'] }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.app>
