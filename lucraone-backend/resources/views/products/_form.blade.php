@php
    $companyOptions = $companies->mapWithKeys(fn ($company) => [
        $company->id => $company->trade_name ?: $company->legal_name,
    ])->all();

    $selectedCategories = old('category_ids', $selectedCategories ?? []);
@endphp

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-form-group nome="company_id" rotulo="empresa" obrigatorio>
        <x-select nome="company_id" :opcoes="$companyOptions" :valor="$product->company_id" vazio="selecione" />
    </x-form-group>

    <x-form-group nome="status" rotulo="status" obrigatorio>
        <x-select nome="status" :opcoes="$statusOptions" :valor="$product->status" />
    </x-form-group>

    <x-form-group nome="sku" rotulo="sku" obrigatorio>
        <x-input nome="sku" :valor="$product->sku" />
    </x-form-group>

    <x-form-group nome="name" rotulo="nome" obrigatorio>
        <x-input nome="name" :valor="$product->name" />
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
