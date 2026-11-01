<x-layouts.app :title="$section->name" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $section->available ? 'bg-ok' : 'bg-aco/40' }}"></span>
        itens da seção
        @if ($branch)
            · <span class="text-aco/60">{{ $branch->name }}</span>
        @endif
    </x-slot:subtitulo>

    <x-slot:acoes>
        <form method="POST" action="{{ route('cardapio.toggle-available', $section) }}">
            @csrf
            <x-button tipo="submit" variante="secundario">
                {{ $section->available ? 'indisponibilizar seção' : 'disponibilizar seção' }}
            </x-button>
        </form>
        <x-button variante="secundario" href="{{ route('cardapio.edit', $section) }}">editar seção</x-button>
        <x-button href="{{ route('cardapio.itens.create', $section) }}">adicionar item</x-button>
    </x-slot:acoes>

    @if (session('success'))
        <x-alert tipo="sucesso" class="mb-6">{{ session('success') }}</x-alert>
    @endif

    <x-section-label>{{ $section->name }}</x-section-label>

    @if ($section->items->isEmpty())
        <x-card>
            <p class="text-sm text-aco">nenhum item nesta seção ainda.
                <a href="{{ route('cardapio.itens.create', $section) }}" class="font-semibold text-primario underline">adicionar o primeiro item</a>
            </p>
        </x-card>
    @else
        <x-table :cabecalhos="['produto', 'preço efetivo', 'situação', '']">
            @foreach ($section->items as $item)
                @php $effective = $item->effectivePrice(); @endphp
                <tr class="border-b border-linha last:border-0">
                    <td class="px-4 py-3">
                        <span class="font-medium text-grafite">{{ $item->product->name }}</span>
                        @if ($item->description)
                            <p class="text-xs text-aco/60">{{ $item->description }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm">
                        @if ($effective)
                            <span class="font-medium">{{ number_format($effective['amount'], 2, ',', '.') }}</span>
                            @if ($effective['source'] === 'override')
                                <span class="ml-1 text-xs text-atencao">override</span>
                            @endif
                        @else
                            <span class="text-aco/40">sem preço</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($item->available)
                            <x-badge variante="sucesso">disponível</x-badge>
                        @else
                            <x-badge variante="neutro">indisponível</x-badge>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <form method="POST" action="{{ route('cardapio.itens.toggle-available', [$section, $item]) }}">
                                @csrf
                                <x-button tipo="submit" variante="fantasma" tamanho="sm">
                                    {{ $item->available ? 'indisponibilizar' : 'disponibilizar' }}
                                </x-button>
                            </form>
                            <x-button variante="fantasma" tamanho="sm" href="{{ route('cardapio.itens.edit', [$section, $item]) }}">editar</x-button>
                            <form method="POST" action="{{ route('cardapio.itens.destroy', [$section, $item]) }}" onsubmit="return confirm('Remover item?')">
                                @csrf @method('DELETE')
                                <x-button tipo="submit" variante="perigo" tamanho="sm">remover</x-button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-layouts.app>
