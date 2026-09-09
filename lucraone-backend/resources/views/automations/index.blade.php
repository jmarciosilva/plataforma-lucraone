<x-layouts.app title="automações" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        regras que o sistema executa sozinho
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-automations')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('automations.logs') }}">histórico</x-button>
        <x-button href="{{ route('automations.create') }}">nova regra</x-button>
    </x-slot:acoes>

    @include('automations._help')

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi rotulo="regras" :valor="$resumo['total']" nota="cadastradas" />
        <x-kpi rotulo="ativas" :valor="$resumo['ativas']" nota="valendo agora" />
        <x-kpi rotulo="execuções" :valor="$resumo['execucoes']" nota="no histórico" />
        <x-kpi rotulo="falhas" :valor="$resumo['falhas']" nota="precisam de atenção" />
    </div>

    @if ($resumo['falhas'] > 0)
        <x-alert tipo="atencao" class="mt-4">
            {{ $resumo['falhas'] }} execução(ões) falharam.
            <a href="{{ route('automations.logs', ['result' => 'failed']) }}" class="font-semibold underline">
                ver o que aconteceu
            </a>
        </x-alert>
    @endif

    <x-section-label class="mt-8">filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('automations.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <x-form-group nome="search" rotulo="busca">
                <x-input nome="search" :valor="request('search')" placeholder="nome da regra" />
            </x-form-group>

            <x-form-group nome="trigger" rotulo="gatilho">
                <x-select nome="trigger" :opcoes="$gatilhos" :valor="request('trigger')" vazio="todos" />
            </x-form-group>

            <x-form-group nome="status" rotulo="situação">
                <x-select nome="status" :opcoes="$statusOptions" :valor="request('status')" />
            </x-form-group>

            <div class="flex items-end gap-2">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('automations.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>regras</x-section-label>

    <x-table :cabecalhos="['regra', 'quando', 'então', 'execuções', 'situação', '']" :paginacao="$regras">
        @forelse ($regras as $regra)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('automations.show', $regra) }}"
                       class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $regra->name }}
                    </a>
                    @if ($regra->conditions)
                        <p class="text-[0.65rem] uppercase tracking-wider text-aco">
                            {{ count($regra->conditions) }} condição(ões)
                        </p>
                    @else
                        <p class="text-[0.65rem] uppercase tracking-wider text-aco">sem condição</p>
                    @endif
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $gatilhos[$regra->trigger] ?? $regra->trigger }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $acoes[$regra->action] ?? $regra->action }}</td>
                <td class="px-5 py-4 font-comanda text-sm tabular-nums text-grafite">{{ $regra->run_count }}</td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$regra->active ? 'sucesso' : 'neutro'">
                        {{ $regra->active ? 'ativa' : 'inativa' }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 text-right">
                    <div class="flex items-center justify-end gap-1">
                        <form method="POST" action="{{ route('automations.toggle', $regra) }}">
                            @csrf
                            <x-button variante="fantasma" tipo="submit">
                                {{ $regra->active ? 'desativar' : 'ativar' }}
                            </x-button>
                        </form>
                        <x-button variante="fantasma" href="{{ route('automations.show', $regra) }}">abrir</x-button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-sm text-aco">
                    nenhuma regra cadastrada. comece com "avisar quando o estoque ficar baixo".
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
