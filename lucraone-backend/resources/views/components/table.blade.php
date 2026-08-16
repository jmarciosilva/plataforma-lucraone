@props([
    'cabecalhos' => [],
    'vazio' => 'nenhum registro encontrado.',
    'paginacao' => null,
])

<div {{ $attributes->merge(['class' => 'cartao overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            @if (count($cabecalhos))
                <thead>
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
