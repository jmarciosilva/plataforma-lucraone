<x-layouts.app title="editar automação" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        {{ $regra->name }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-automations')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('automations.show', $regra) }}">voltar</x-button>
    </x-slot:acoes>

    @include('automations._help')

    <form method="POST" action="{{ route('automations.update', $regra) }}" class="space-y-8">
        @csrf
        @method('PUT')

        @include('automations._form')

        <div class="flex flex-wrap gap-2">
            <x-button tipo="submit">salvar regra</x-button>
            <x-button variante="fantasma" href="{{ route('automations.show', $regra) }}">cancelar</x-button>
        </div>
    </form>
</x-layouts.app>
