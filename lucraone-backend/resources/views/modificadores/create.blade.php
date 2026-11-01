<x-layouts.app title="novo grupo" :tenantNome="$tenantNome ?? null" :breadcrumbs="$breadcrumbs ?? []">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-atencao"></span>
        novo grupo de modificadores para <strong>{{ $product->name }}</strong>
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('produtos.modificadores.index', $product) }}">voltar</x-button>
    </x-slot:acoes>

    <x-card>
        <form method="POST" action="{{ route('produtos.modificadores.store', $product) }}" class="space-y-6">
            @csrf

            <x-form-group nome="name" rotulo="nome do grupo" obrigatorio>
                <x-input nome="name" :valor="old('name')" placeholder="ex: escolha o ponto, adicionais, remover ingredientes" autofocus />
            </x-form-group>

            <x-form-group nome="type" rotulo="tipo do grupo" obrigatorio>
                <x-select nome="type" :opcoes="$typeLabels" :valor="old('type')" vazio="selecione o tipo" />
                <p class="mt-1 text-xs text-aco/60">
                    adicional (+preço) · remoção (sem custo, price_delta zerado) · escolha (seleção única ou múltipla)
                </p>
            </x-form-group>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3"
                 x-data="{ required: {{ old('required') ? 'true' : 'false' }} }">
                <x-form-group nome="required" rotulo="obrigatório">
                    <x-select nome="required" :opcoes="['0' => 'não', '1' => 'sim']" :valor="old('required', '0')"
                              x-model="required" />
                </x-form-group>

                <x-form-group nome="min_selections" rotulo="seleção mínima">
                    <x-input tipo="number" nome="min_selections" min="0" max="255" :valor="old('min_selections', '0')" />
                </x-form-group>

                <x-form-group nome="max_selections" rotulo="seleção máxima" ajuda="deixe vazio para ilimitado">
                    <x-input tipo="number" nome="max_selections" min="1" max="255" :valor="old('max_selections')" />
                </x-form-group>
            </div>

            <x-form-group nome="position" rotulo="posição" ajuda="ordem de exibição (0 = primeiro)">
                <x-input tipo="number" nome="position" min="0" :valor="old('position', '0')" />
            </x-form-group>

            <x-section-label>escopo</x-section-label>
            <p class="text-sm text-aco">
                grupos <strong>globais</strong> (branch_id vazio) valem para todas as filiais.<br>
                grupos com filial específica têm precedência total sobre os globais.
            </p>

            <x-form-group nome="branch_id" rotulo="filial específica" ajuda="vazio = global">
                <x-input nome="branch_id" :valor="old('branch_id', $branch?->id)" placeholder="ID da filial ou deixe vazio para global" />
            </x-form-group>

            <div class="flex justify-end gap-2">
                <x-button variante="secundario" href="{{ route('produtos.modificadores.index', $product) }}">cancelar</x-button>
                <x-button tipo="submit">criar grupo</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
