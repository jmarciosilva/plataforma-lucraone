@php
    $badgePorNivel = [
        'info' => 'neutro',
        'sucesso' => 'sucesso',
        'atencao' => 'atencao',
        'erro' => 'erro',
    ];
@endphp

<x-layouts.app title="avisos" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $naoLidos > 0 ? 'bg-alerta' : 'bg-ok' }}"></span>
        {{ $naoLidos > 0 ? $naoLidos.' não lido(s)' : 'tudo em dia' }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        @if ($naoLidos > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <x-button variante="secundario" tipo="submit">marcar todos como lidos</x-button>
            </form>
        @endif
        <x-button variante="secundario" href="{{ route('automations.index') }}">automações</x-button>
    </x-slot:acoes>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('notifications.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <x-form-group nome="status" rotulo="situação">
                <x-select nome="status" :opcoes="$statusOptions" :valor="request('status')" />
            </x-form-group>

            <x-form-group nome="level" rotulo="nível">
                <x-select nome="level" :opcoes="$niveis" :valor="request('level')" />
            </x-form-group>

            <div class="flex items-end gap-2">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('notifications.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>avisos</x-section-label>

    <div class="space-y-3">
        @forelse ($avisos as $aviso)
            <x-card class="{{ $aviso->isRead() ? 'opacity-60' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :tipo="$badgePorNivel[$aviso->level] ?? 'neutro'">
                                {{ $aviso->levelLabel() }}
                            </x-badge>
                            <p class="text-sm font-semibold lowercase text-grafite">{{ $aviso->title }}</p>
                        </div>

                        <p class="mt-2 text-sm text-aco">{{ $aviso->message }}</p>

                        <p class="mt-2 text-xs text-aco">
                            {{ $aviso->created_at->format('d/m/Y H:i') }}
                            @if ($aviso->rule)
                                · regra
                                <a href="{{ route('automations.show', $aviso->rule) }}"
                                   class="text-sol transition-opacity hover:opacity-70">{{ $aviso->rule->name }}</a>
                            @endif
                        </p>
                    </div>

                    @unless ($aviso->isRead())
                        <form method="POST" action="{{ route('notifications.read', $aviso) }}">
                            @csrf
                            <x-button variante="fantasma" tipo="submit">marcar como lido</x-button>
                        </form>
                    @endunless
                </div>
            </x-card>
        @empty
            <x-card>
                <p class="py-10 text-center text-sm text-aco">
                    nenhum aviso ainda. eles aparecem aqui quando uma automação dispara.
                </p>
            </x-card>
        @endforelse
    </div>

    @if ($avisos->hasPages())
        <div class="mt-6">{{ $avisos->links() }}</div>
    @endif
</x-layouts.app>
