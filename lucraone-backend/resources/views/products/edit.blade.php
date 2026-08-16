<x-layouts.app title="produtos" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-alerta"></span>
        editar item do catálogo
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-products')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('catalog.products.show', $product) }}">voltar</x-button>
    </x-slot:acoes>

    @include('products._help')

    <x-section-label>dados</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('catalog.products.update', $product) }}">
            @csrf
            @method('PUT')
            @include('products._form')

            <div class="mt-6 flex justify-end gap-2">
                <x-button variante="fantasma" href="{{ route('catalog.products.show', $product) }}">cancelar</x-button>
                <x-button tipo="submit">salvar alterações</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
