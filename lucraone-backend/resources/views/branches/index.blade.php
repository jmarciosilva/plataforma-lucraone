<x-layouts.app title="unidades" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        matriz e filiais deste estabelecimento
    </x-slot:subtitulo>

    <x-slot:acoes>
        @if ($podeAdministrar)
            <x-button href="{{ route('branches.create') }}">nova unidade</x-button>
        @endif
    </x-slot:acoes>

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('branches.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="nome ou código" />
            </x-form-group>

            <x-form-group nome="status" rotulo="situação">
                <x-select nome="status" :opcoes="$statusOptions" :valor="request('status')" vazio="todas" />
            </x-form-group>

            <div class="flex items-end gap-2 lg:col-span-3">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('branches.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>unidades</x-section-label>

    <x-table
        :cabecalhos="['unidade', 'código', 'situação', '']"
        :paginacao="$filiais"
    >
        @forelse ($filiais as $filial)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('branches.show', $filial) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $filial->name }}
                    </a>
                    @if ($filial->is_headquarters)
                        <x-badge tipo="destaque" class="ml-2">matriz</x-badge>
                    @endif
                    <p class="mt-0.5 font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                        {{ $filial->company?->legal_name ?? '—' }}
                    </p>
                </td>
                <td class="px-5 py-4 font-comanda text-xs uppercase tracking-wider text-aco">
                    {{ $filial->code ?? '—' }}
                </td>
                <td class="px-5 py-4">
                    @if ($filial->status === 'ACTIVE')
                        <x-badge tipo="ok">ativa</x-badge>
                    @elseif ($filial->status === 'SUSPENDED')
                        <x-badge tipo="alerta">suspensa</x-badge>
                    @else
                        <x-badge tipo="inativo">inativa</x-badge>
                    @endif
                </td>
                <td class="px-5 py-4 text-right">
                    <a href="{{ route('branches.show', $filial) }}" class="text-sm font-semibold lowercase text-sol transition-opacity hover:opacity-70">ver</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-5 py-8 text-center text-sm text-aco">
                    nenhuma unidade cadastrada ainda.
                    @if ($podeAdministrar)
                        <a href="{{ route('branches.create') }}" class="font-semibold text-sol transition-opacity hover:opacity-70">Cadastrar a primeira.</a>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
