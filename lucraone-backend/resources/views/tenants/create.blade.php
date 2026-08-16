<x-layouts.app title="tenants" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        novo estabelecimento
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('tenants.index') }}">voltar</x-button>
    </x-slot:acoes>

    <x-section-label>cadastro</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('tenants.store') }}" class="space-y-5">
            @csrf

            @include('tenants._form')

            <div class="flex flex-wrap justify-end gap-2 border-t border-linha pt-5">
                <x-button variante="secundario" href="{{ route('tenants.index') }}">cancelar</x-button>
                <x-button tipo="submit">criar tenant</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
