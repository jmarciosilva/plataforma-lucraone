<x-layouts.app title="tenants" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-alerta"></span>
        editar estabelecimento
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('tenants.show', $tenant) }}">voltar</x-button>
    </x-slot:acoes>

    <x-section-label>edição</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('tenants.update', $tenant) }}" class="space-y-5">
            @csrf
            @method('PUT')

            @include('tenants._form')

            <div class="flex flex-wrap justify-end gap-2 border-t border-linha pt-5">
                <x-button variante="secundario" href="{{ route('tenants.show', $tenant) }}">cancelar</x-button>
                <x-button tipo="submit">salvar alterações</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
