<x-layouts.app title="terminais pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        editar terminal
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('terminals.show', $terminal) }}">voltar</x-button>
    </x-slot:acoes>

    <x-section-label>dados do terminal</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('terminals.update', $terminal) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <p class="text-xs lowercase text-aco">filial</p>
                <p class="mt-1 text-sm font-semibold text-grafite">{{ $terminal->branch?->name ?? '—' }}</p>
                <p class="mt-0.5 text-xs text-aco">A filial não pode ser alterada após o cadastro.</p>
            </div>

            <x-form-group nome="name" rotulo="nome administrativo" obrigatorio>
                <x-input nome="name" :valor="old('name', $terminal->name)" autocomplete="off" />
            </x-form-group>

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button variante="secundario" href="{{ route('terminals.show', $terminal) }}">cancelar</x-button>
                <x-button tipo="submit">salvar</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
