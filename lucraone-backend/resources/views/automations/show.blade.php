@php
    use App\Modules\Automation\Domain\Operator;

    $badgePorResultado = [
        'executed' => 'sucesso',
        'skipped' => 'neutro',
        'failed' => 'erro',
    ];
@endphp

<x-layouts.app :title="$regra->name" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $regra->active ? 'bg-ok' : 'bg-aco' }}"></span>
        {{ $regra->active ? 'ativa' : 'inativa' }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-automations')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('automations.index') }}">voltar</x-button>
        <x-button variante="secundario" href="{{ route('automations.edit', $regra) }}">editar</x-button>
    </x-slot:acoes>

    @include('automations._help')

    <x-section-label>a regra em uma frase</x-section-label>

    <x-card>
        <p class="text-sm leading-7 text-grafite">
            <span class="font-semibold lowercase text-aco">quando</span>
            <span class="rounded-lg bg-nevoa px-2 py-0.5 font-semibold">{{ $regra->triggerLabel() }}</span>

            @if ($regra->conditions)
                <span class="font-semibold lowercase text-aco">se</span>
                @foreach ($regra->conditions as $condicao)
                    <span class="rounded-lg bg-nevoa px-2 py-0.5">
                        {{ $campos[$condicao['campo']] ?? $condicao['campo'] }}
                        {{ Operator::rotulo($condicao['operador']) }}
                        <strong>{{ $condicao['valor'] }}</strong>
                    </span>
                    @if (! $loop->last)
                        <span class="text-aco">e</span>
                    @endif
                @endforeach
            @endif

            <span class="font-semibold lowercase text-aco">então</span>
            <span class="rounded-lg gradiente-sol-suave px-2 py-0.5 font-semibold">
                {{ $acoes[$regra->action] ?? $regra->action }}
            </span>
        </p>

        @if ($regra->description)
            <p class="mt-4 border-t border-linha pt-4 text-sm text-aco">{{ $regra->description }}</p>
        @endif
    </x-card>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">execuções</dt>
                    <dd class="mt-1 font-comanda text-sm text-grafite">{{ $regra->run_count }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">última execução</dt>
                    <dd class="mt-1 text-sm text-grafite">
                        {{ $regra->last_run_at?->format('d/m/Y H:i') ?: 'ainda não rodou' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">criada em</dt>
                    <dd class="mt-1 text-sm text-aco">{{ $regra->created_at->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">configuração da ação</dt>
                    <dd class="mt-1 space-y-0.5 text-sm text-aco">
                        @forelse ($regra->action_config ?? [] as $chave => $valor)
                            <span class="block">
                                <span class="text-grafite">{{ str_replace('_', ' ', $chave) }}:</span>
                                {{ is_scalar($valor) ? $valor : json_encode($valor) }}
                            </span>
                        @empty
                            <span>sem configuração</span>
                        @endforelse
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">controle</p>

            <form method="POST" action="{{ route('automations.toggle', $regra) }}" class="mt-4">
                @csrf
                <x-button variante="secundario" tipo="submit" class="w-full">
                    {{ $regra->active ? 'desativar regra' : 'ativar regra' }}
                </x-button>
            </form>

            <form method="POST" action="{{ route('automations.destroy', $regra) }}" class="mt-2">
                @csrf
                @method('DELETE')
                <x-button variante="perigo" tipo="submit" class="w-full">remover regra</x-button>
            </form>

            <p class="mt-3 text-xs text-aco">
                desativar para de disparar sem perder a configuração. remover apaga a regra, mas o histórico
                de execuções continua no lugar.
            </p>
        </x-card>
    </div>

    <x-section-label class="mt-8">histórico desta regra</x-section-label>

    <x-table :cabecalhos="['quando', 'resultado', 'mensagem', 'duração']" :paginacao="$execucoes">
        @forelse ($execucoes as $execucao)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-3 text-sm text-aco">{{ $execucao->ran_at->format('d/m/Y H:i:s') }}</td>
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
                <td colspan="4" class="px-5 py-10 text-center text-sm text-aco">
                    esta regra ainda não foi acionada.
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
