<x-layouts.app title="empresas" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        nova empresa no tenant atual
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-companies')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('companies.index') }}">voltar</x-button>
    </x-slot:acoes>

    @include('companies._help')

    <x-section-label>cadastro</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('companies.store') }}">
            @csrf
            @include('companies._form')

            <div class="mt-6 flex justify-end gap-2">
                <x-button variante="fantasma" href="{{ route('companies.index') }}">cancelar</x-button>
                <x-button tipo="submit">criar empresa</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
