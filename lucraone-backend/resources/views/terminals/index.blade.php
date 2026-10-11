<x-layouts.app title="terminais pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        caixas registrados neste estabelecimento
    </x-slot:subtitulo>

    <x-slot:acoes>
        @if ($podeAdministrar)
            <x-button href="{{ route('terminals.create') }}">novo terminal</x-button>
        @endif
    </x-slot:acoes>

    @if (session('sucesso'))
        <x-alert tipo="sucesso" class="mb-6">{{ session('sucesso') }}</x-alert>
    @endif

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('terminals.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="nome do terminal" />
            </x-form-group>

            <x-form-group nome="branch_id" rotulo="filial">
                <x-select nome="branch_id" :opcoes="$filiais->pluck('name', 'id')->toArray()" :valor="request('branch_id')" vazio="todas" />
            </x-form-group>

            <x-form-group nome="status" rotulo="situação">
                <x-select nome="status" :opcoes="$statusOptions" :valor="request('status')" vazio="todas" />
            </x-form-group>

            <div class="flex items-end gap-2 lg:col-span-4">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('terminals.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>lista</x-section-label>

    <x-table
        :cabecalhos="['terminal', 'filial', 'situação', 'último acesso', '']"
        :paginacao="$terminais"
    >
        @forelse ($terminais as $terminal)
            <tr class="group hover:bg-nevoa/40">
                <td class="py-3 pl-4 pr-3 text-sm sm:pl-6">
                    <p class="font-semibold lowercase text-grafite">{{ $terminal->name }}</p>
                    <p class="mt-0.5 font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $terminal->id }}</p>
                </td>
                <td class="px-3 py-3 text-sm text-aco">
                    {{ $terminal->branch?->name ?? '—' }}
                </td>
                <td class="px-3 py-3 text-sm">
                    @php
                        $cores = [
                            'PENDING' => 'bg-sol/10 text-sol',
                            'ACTIVE' => 'bg-ok/10 text-ok',
                            'BLOCKED' => 'bg-brasa/10 text-brasa',
                            'REVOKED' => 'bg-aco/10 text-aco',
                        ];
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold lowercase {{ $cores[$terminal->status] ?? 'bg-aco/10 text-aco' }}">
                        {{ $statusOptions[$terminal->status] ?? strtolower($terminal->status) }}
                    </span>
                </td>
                <td class="px-3 py-3 text-sm text-aco">
                    {{ $terminal->last_seen_at?->format('d/m/Y H:i') ?? '—' }}
                </td>
                <td class="py-3 pl-3 pr-4 text-right sm:pr-6">
                    <a href="{{ route('terminals.show', $terminal) }}"
                       class="text-sm font-semibold text-sol transition-opacity hover:opacity-70 lowercase">
                        detalhes
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-6 py-10 text-center text-sm text-aco">
                    @if (request()->hasAny(['search', 'branch_id', 'status']))
                        nenhum terminal encontrado com esses filtros.
                    @else
                        nenhum terminal cadastrado ainda.
                        @if ($podeAdministrar)
                            <a href="{{ route('terminals.create') }}" class="font-semibold text-sol hover:opacity-70">cadastrar o primeiro</a>
                        @endif
                    @endif
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
