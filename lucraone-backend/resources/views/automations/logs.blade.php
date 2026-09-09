@php
    $badgePorResultado = [
        'executed' => 'sucesso',
        'skipped' => 'neutro',
        'failed' => 'erro',
    ];
@endphp

<x-layouts.app title="histórico de automações" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        toda vez que uma regra foi avaliada
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-automations')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('automations.index') }}">voltar</x-button>
    </x-slot:acoes>

    @include('automations._help')

    <x-card class="mb-6">
        <form method="GET" action="{{ route('automations.logs') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <x-form-group nome="result" rotulo="resultado">
                <x-select nome="result" :opcoes="$resultados" :valor="request('result')" />
            </x-form-group>

            <x-form-group nome="trigger" rotulo="gatilho">
                <x-select nome="trigger" :opcoes="$gatilhos" :valor="request('trigger')" vazio="todos" />
            </x-form-group>

            <div class="flex items-end gap-2">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('automations.logs') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-table :cabecalhos="['quando', 'regra', 'gatilho', 'resultado', 'mensagem', 'duração']" :paginacao="$execucoes">
        @forelse ($execucoes as $execucao)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-3 whitespace-nowrap text-sm text-aco">
                    {{ $execucao->ran_at->format('d/m/Y H:i:s') }}
                </td>
                <td class="px-5 py-3 text-sm">
                    @if ($execucao->rule)
                        <a href="{{ route('automations.show', $execucao->rule) }}"
                           class="lowercase text-grafite transition-colors hover:text-sol">
                            {{ $execucao->rule->name }}
                        </a>
                    @else
                        <span class="text-aco">regra removida</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-sm text-aco">{{ $gatilhos[$execucao->trigger] ?? $execucao->trigger }}</td>
                <td class="px-5 py-3">
                    <x-badge :tipo="$badgePorResultado[$execucao->result] ?? 'neutro'">
                        {{ $execucao->resultLabel() }}
                    </x-badge>
                </td>
                <td class="px-5 py-3 text-sm text-aco">{{ $execucao->message }}</td>
                <td class="px-5 py-3 font-comanda text-sm tabular-nums text-aco">
                    {{ $execucao->duration_ms !== null ? $execucao->duration_ms.' ms' : '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-sm text-aco">
                    nenhuma execução registrada ainda.
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
