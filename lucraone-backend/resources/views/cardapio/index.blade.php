<x-layouts.app title="cardápio" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        seções e itens do cardápio
        @if ($branch)
            · <span class="text-aco/60">{{ $branch->name }}</span>
        @endif
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button href="{{ route('cardapio.create') }}">nova seção</x-button>
    </x-slot:acoes>

    @if (session('success'))
        <x-alert tipo="sucesso" class="mb-6">{{ session('success') }}</x-alert>
    @endif

    @if ($sections->isEmpty())
        <x-card>
            <p class="text-sm text-aco">nenhuma seção cadastrada ainda. crie a primeira seção para montar o cardápio.</p>
        </x-card>
    @else
        <div class="space-y-4">
            @foreach ($sections as $section)
                <x-card>
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-grafite">{{ $section->name }}</span>
                            @if (! $section->available)
                                <x-badge variante="neutro">indisponível</x-badge>
                            @else
                                <x-badge variante="sucesso">disponível</x-badge>
                            @endif
                            <span class="text-xs text-aco/50">{{ $section->items->count() }} {{ Str::plural('item', $section->items->count()) }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <form method="POST" action="{{ route('cardapio.toggle-available', $section) }}">
                                @csrf
                                <x-button tipo="submit" variante="fantasma" tamanho="sm">
                                    {{ $section->available ? 'indisponibilizar' : 'disponibilizar' }}
                                </x-button>
                            </form>
                            <x-button variante="secundario" tamanho="sm" href="{{ route('cardapio.show', $section) }}">ver itens</x-button>
                            <x-button variante="fantasma" tamanho="sm" href="{{ route('cardapio.edit', $section) }}">editar</x-button>
                            <form method="POST" action="{{ route('cardapio.destroy', $section) }}" onsubmit="return confirm('Remover seção {{ addslashes($section->name) }}?')">
                                @csrf @method('DELETE')
                                <x-button tipo="submit" variante="perigo" tamanho="sm">remover</x-button>
                            </form>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
