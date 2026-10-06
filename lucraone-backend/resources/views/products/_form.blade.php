@php
    $companyOptions = $companies->mapWithKeys(fn ($company) => [
        $company->id => $company->trade_name ?: $company->legal_name,
    ])->all();

    $selectedCategories = old('category_ids', $selectedCategories ?? []);
    $skuInicial = [
        'nome' => old('name', $product->name ?? ''),
        'sku' => old('sku', $product->sku ?? ''),
        'editando' => $product->exists,
        'automatico' => (bool) old('sku_automatico', !filled(old('sku', $product->sku))),
    ];
@endphp

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2" x-data="skuProduto(@js($skuInicial))">
    <x-form-group nome="company_id" rotulo="empresa" obrigatorio>
        <x-select nome="company_id" :opcoes="$companyOptions" :valor="$product->company_id" vazio="selecione" />
    </x-form-group>

    <x-form-group nome="status" rotulo="status" obrigatorio>
        <x-select nome="status" :opcoes="$statusOptions" :valor="$product->status" />
    </x-form-group>

    <x-form-group nome="name" rotulo="nome" obrigatorio>
        <x-input class="uppercase" nome="name" :valor="$product->name" x-model="nome" @input="nome = $event.target.value; nomeAlterado()" />
    </x-form-group>

    <x-form-group nome="sku" rotulo="SKU — código interno" :obrigatorio="$product->exists"
        ajuda="Usamos este código para identificar o produto dentro do LucraOne. Se você não informar um código próprio, podemos gerar um para você. Não é o código de barras da embalagem.">
        <x-input nome="sku" :valor="$product->sku" x-model="sku" @input="sku = $event.target.value; codigoAlterado()" maxlength="100" placeholder="LEITE-ITALAC-1L" />
        @if (! $product->exists)
            <input type="hidden" name="sku_automatico" value="{{ old('sku_automatico', '0') }}" :value="automatico ? '1' : '0'">
        @endif
        <x-button variante="secundario" class="mt-2" @click="gerar()">Gerar código</x-button>
        <p class="mt-2 text-xs text-aco">Na criação, o sistema ajusta o código gerado se ele já estiver em uso. Seu código próprio é mantido.</p>
    </x-form-group>

    <x-form-group nome="barcode" rotulo="código de barras (EAN/GTIN)" ajuda="Digite ou escaneie o número impresso abaixo do código de barras da embalagem. Este campo é opcional e não substitui o código interno.">
        <x-input nome="barcode" :valor="$product->barcode" inputmode="numeric" maxlength="14" autocomplete="off" />
    </x-form-group>

    <x-form-group nome="unit" rotulo="unidade de venda/estoque" obrigatorio ajuda="Escolha como o estoque e a venda deste produto serão contabilizados. Use KG só para venda a peso (ex.: 0,350 kg); pacote de 1 kg ou garrafa de 2 L vendidos inteiros são UN.">
        <x-select nome="unit" :opcoes="$unitOptions" :valor="$product->unit" />
    </x-form-group>

    <x-form-group nome="description" rotulo="descrição" class="lg:col-span-2">
        <x-input tipo="textarea" nome="description" :valor="$product->description" rows="4" />
    </x-form-group>
</div>

<div class="mt-6">
    <p class="text-sm font-semibold lowercase text-grafite">categorias</p>
    <p class="mt-1 text-xs text-aco">use categorias para organizar busca, estoque e relatórios futuros.</p>

    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($categories as $category)
            <label class="flex min-h-11 items-center gap-3 rounded-xl border border-linha bg-white px-3 text-sm text-grafite transition-colors hover:bg-nevoa">
                <input
                    type="checkbox"
                    name="category_ids[]"
                    value="{{ $category->id }}"
                    class="h-4 w-4 rounded border-linha text-sol focus:ring-sol/30"
                    @checked(in_array($category->id, $selectedCategories, true))
                >
                <span>
                    <span class="block font-semibold lowercase">{{ $category->name }}</span>
                    <span class="block text-xs text-aco">{{ $category->parent?->name ? 'em '.$category->parent->name : 'categoria raiz' }}</span>
                </span>
            </label>
        @empty
            <p class="text-sm text-aco">nenhuma categoria cadastrada.</p>
        @endforelse
    </div>
</div>

<fieldset class="mt-6">
    <legend class="text-sm font-semibold text-grafite">Dados fiscais</legend>
    <p class="mt-1 text-xs text-aco">Opcionais para o cadastro comercial. O formato informado não confirma o enquadramento fiscal.</p>
    <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-form-group nome="ncm_code" rotulo="NCM" ajuda="Classificação fiscal do produto. Informe 8 dígitos, sem pontuação. A validade oficial não é verificada nesta etapa.">
            <x-input nome="ncm_code" :valor="$product->ncm_code" inputmode="numeric" maxlength="8" autocomplete="off" />
        </x-form-group>
        <x-form-group nome="cest_code" rotulo="CEST" ajuda="Opcional, quando aplicável ao produto. Informe 7 dígitos, sem pontuação. O preenchimento não determina a tributação automaticamente.">
            <x-input nome="cest_code" :valor="$product->cest_code" inputmode="numeric" maxlength="7" autocomplete="off" />
        </x-form-group>
        <x-form-group nome="default_origin_code" rotulo="Origem padrão" ajuda="Origem de referência cadastrada para o produto. A informação efetiva usada futuramente na emissão fiscal poderá depender do contexto da operação.">
            <x-select nome="default_origin_code" :opcoes="$defaultOriginOptions" :valor="$product->default_origin_code" vazio="Não informada" />
        </x-form-group>
    </div>
</fieldset>
