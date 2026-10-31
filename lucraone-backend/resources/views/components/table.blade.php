@props([
    'cabecalhos' => [],
    'vazio' => 'nenhum registro encontrado.',
    'paginacao' => null,
    // Classe de altura máxima (ex.: "max-h-[28rem]"). Quando informada, o corpo
    // da tabela rola na vertical e o cabeçalho fica fixo no topo. Sem ela, o
    // comportamento é o de sempre (tabela cresce com o conteúdo).
    'altura' => null,
])

<div {{ $attributes->merge(['class' => 'cartao overflow-hidden']) }}>
    <div class="overflow-x-auto {{ $altura ? 'overflow-y-auto ' . $altura : '' }}">
        <table class="w-full text-left text-sm">
            @if (count($cabecalhos))
                <thead @class(['sticky top-0 z-10 bg-white' => $altura])>
                    <tr class="border-b border-linha">
                        @foreach ($cabecalhos as $cabecalho)
                            <th scope="col" class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-aco">
                                {{ $cabecalho }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($estaVazio)
        <p class="px-5 py-10 text-center text-sm text-aco">{{ $vazio }}</p>
    @endisset

    @if ($paginacao && $paginacao->hasPages())
        <div class="border-t border-linha px-5 py-3">
            {{ $paginacao->links() }}
        </div>
    @endif
</div>
