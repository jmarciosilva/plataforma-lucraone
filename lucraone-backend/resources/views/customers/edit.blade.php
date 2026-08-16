<x-layouts.app title="editar cliente" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        {{ $customer->name }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-customers')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('sales.customers.show', $customer) }}">voltar</x-button>
    </x-slot:acoes>

    @include('customers._help')

    <x-card>
        <form method="POST" action="{{ route('sales.customers.update', $customer) }}" class="space-y-6">
            @csrf
            @method('PUT')

            @include('customers._form')

            <div class="flex flex-wrap gap-2">
                <x-button tipo="submit">salvar cliente</x-button>
                <x-button variante="fantasma" href="{{ route('sales.customers.show', $customer) }}">cancelar</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
