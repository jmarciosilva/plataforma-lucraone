<x-layouts.app title="categorias" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        nova categoria
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-products')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('catalog.categories.index') }}">voltar</x-button>
    </x-slot:acoes>

    @include('products._help')

    <x-card>
        <form method="POST" action="{{ route('catalog.categories.store') }}" class="space-y-6">
            @csrf
            @include('categories._form')

            <div class="flex justify-end gap-2">
                <x-button variante="fantasma" href="{{ route('catalog.categories.index') }}">cancelar</x-button>
                <x-button tipo="submit">salvar categoria</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
