<x-layouts.app title="operadores de pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        quem pode abrir o caixa neste estabelecimento
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-pdv-operators')">ajuda</x-button>
        @if ($podeAdministrar)
            <x-button href="{{ route('pdv-operators.create') }}">novo operador</x-button>
        @endif
    </x-slot:acoes>

    @include('pdv-operators._help')

    <x-section-label>filtros</x-section-label>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('pdv-operators.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <x-form-group nome="search" rotulo="busca" class="lg:col-span-2">
                <x-input nome="search" :valor="request('search')" placeholder="nome ou usuário do pdv" />
            </x-form-group>

            <x-form-group nome="status" rotulo="situação">
                <x-select nome="status" :opcoes="$statusOptions" :valor="request('status')" vazio="todas" />
            </x-form-group>

            <x-form-group nome="sort" rotulo="ordem">
                <x-select nome="sort" :opcoes="$sortOptions" :valor="request('sort', 'username')" />
            </x-form-group>

            <div class="flex items-end gap-2 lg:col-span-4">
                <x-button tipo="submit">filtrar</x-button>
                <x-button variante="fantasma" href="{{ route('pdv-operators.index') }}">limpar</x-button>
            </div>
        </form>
    </x-card>

    <x-section-label>lista</x-section-label>

    <x-table
        :cabecalhos="['operador', 'usuário do pdv', 'situação', 'filiais', 'caixa agora', '']"
        :paginacao="$operadores"
    >
        @forelse ($operadores as $operador)
            @php
                $sessao = $sessoesAtivas[$operador->user_id] ?? null;
                $quantasFiliais = $filiaisPorOperador[$operador->user_id] ?? 0;
            @endphp

            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <a href="{{ route('pdv-operators.show', $operador) }}" class="font-semibold lowercase text-grafite transition-colors hover:text-sol">
                        {{ $operador->user?->name ?? 'pessoa removida' }}
                    </a>
                    <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                        {{ $operador->user?->email ?? 'sem e-mail' }}
                    </p>
                </td>
                <td class="px-5 py-4">
                    <span class="font-comanda text-sm text-grafite">{{ $operador->username }}</span>
                </td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$operador->isActive() ? 'sucesso' : 'atencao'">
                        {{ $operador->isActive() ? 'ativo' : 'inativo' }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 text-sm text-aco">
                    {{ $quantasFiliais === 0 ? 'nenhuma' : $quantasFiliais . ($quantasFiliais === 1 ? ' filial' : ' filiais') }}
                </td>
                <td class="px-5 py-4 text-sm">
                    @if ($sessao)
                        <span class="inline-flex items-center gap-1.5 font-semibold text-ok">
                            <span class="h-2 w-2 rounded-full bg-ok"></span>
                            {{ $sessao->terminal?->name ?? 'terminal' }}
                        </span>
                        <p class="text-xs text-aco">desde {{ $sessao->authenticated_at?->format('H:i') }}</p>
                    @else
                        <span class="text-aco">—</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-right">
                    <x-button variante="fantasma" href="{{ route('pdv-operators.show', $operador) }}">abrir</x-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-5 py-12 text-center">
                    <p class="text-sm font-semibold lowercase text-grafite">nenhum operador cadastrado</p>
                    <p class="mt-1 text-sm text-aco">
                        cadastre quem vai abrir o caixa. cada pessoa entra no pdv com um usuário curto e a senha dela.
                    </p>
                    @if ($podeAdministrar)
                        <div class="mt-4 flex justify-center">
                            <x-button href="{{ route('pdv-operators.create') }}">cadastrar o primeiro operador</x-button>
                        </div>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-table>

    @if ($podeAdministrar)
        <x-section-label class="mt-8">política do caixa</x-section-label>

        <x-card>
            <form method="POST" action="{{ route('pdv-operators.settings') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @csrf

                <x-form-group
                    nome="screen_lock_minutes"
                    rotulo="bloqueio de tela"
                    ajuda="minutos de caixa parado antes de pedir a senha de novo. deixe vazio para desligar."
                    class="lg:col-span-2"
                >
                    <x-input
                        tipo="number"
                        nome="screen_lock_minutes"
                        :valor="$politica->screen_lock_minutes"
                        min="1"
                        max="720"
                        placeholder="desligado"
                    />
                </x-form-group>

                <div class="flex items-end">
                    <x-button tipo="submit" variante="secundario">salvar política</x-button>
                </div>
            </form>

            <p class="mt-4 text-xs text-aco">
                bloquear a tela não encerra o caixa nem fecha o movimento do dia: o operador continua logado e só
                precisa confirmar a senha para voltar a operar. a jornada não tem limite de horas — o caixa fica
                aberto até o logout, a desativação do operador ou a retirada do acesso.
            </p>
        </x-card>
    @endif
</x-layouts.app>
