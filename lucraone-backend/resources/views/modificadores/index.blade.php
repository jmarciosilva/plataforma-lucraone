<x-layouts.app title="modificadores" :tenantNome="$tenantNome ?? null" :breadcrumbs="$breadcrumbs ?? []">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        grupos de modificadores de <strong>{{ $product->name }}</strong>
        @if ($branch)
            · <span class="text-aco/60">{{ $branch->name }}</span>
        @endif
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('catalog.products.show', $product) }}">produto</x-button>
        <x-button href="{{ route('produtos.modificadores.create', $product) }}">novo grupo</x-button>
    </x-slot:acoes>

    @if (session('success'))
        <x-alert tipo="sucesso" class="mb-6">{{ session('success') }}</x-alert>
    @endif

    @if ($hasOverride)
        <x-alert tipo="destaque" class="mb-4">este produto tem modificadores específicos para esta filial (override ativo).</x-alert>
    @endif

    @foreach ($alerts as $alertGroup)
        <x-alert tipo="atencao" class="mb-2">
            grupo <strong>{{ $alertGroup->name }}</strong> ({{ $typeLabels[$alertGroup->type] ?? $alertGroup->type }}) é obrigatório mas não tem opções disponíveis.
        </x-alert>
    @endforeach

    @if ($groups->isEmpty())
        <x-card>
            <p class="text-sm text-aco">nenhum grupo de modificadores cadastrado.
                <a href="{{ route('produtos.modificadores.create', $product) }}" class="font-semibold text-primario underline">criar o primeiro grupo</a>
            </p>
        </x-card>
    @else
        <div class="space-y-4">
            @foreach ($groups as $group)
                <x-card>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-grafite">{{ $group->name }}</span>
                                <x-badge variante="neutro">{{ $typeLabels[$group->type] ?? $group->type }}</x-badge>
                                @if ($group->required)
                                    <x-badge variante="atencao">obrigatório</x-badge>
                                @endif
                                @if ($group->isGlobal())
                                    <x-badge variante="destaque">global</x-badge>
                                @else
                                    <x-badge variante="sucesso">override filial</x-badge>
                                @endif
                            </div>
                            @if ($group->min_selections || $group->max_selections)
                                <p class="mt-1 text-xs text-aco/60">
                                    seleção: mín {{ $group->min_selections }}
                                    @if ($group->max_selections)· máx {{ $group->max_selections }}@endif
                                </p>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($group->modifiers as $modifier)
                                    <span class="rounded-md bg-nevoa px-2 py-0.5 text-xs text-aco {{ $modifier->available ? '' : 'line-through opacity-50' }}">
                                        {{ $modifier->name }}
                                        @if ($modifier->price_delta != 0)
                                            @if ($modifier->price_delta > 0)+@endif{{ number_format($modifier->price_delta, 2, ',', '.') }}
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-button variante="secundario" tamanho="sm" href="{{ route('produtos.modificadores.opcoes.create', [$product, $group]) }}">+ opção</x-button>
                            <x-button variante="fantasma" tamanho="sm" href="{{ route('produtos.modificadores.edit', [$product, $group]) }}">editar</x-button>
                            <form method="POST" action="{{ route('produtos.modificadores.destroy', [$product, $group]) }}" onsubmit="return confirm('Remover grupo {{ addslashes($group->name) }} e todas as opções?')">
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
