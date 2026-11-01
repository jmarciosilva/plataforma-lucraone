<x-layouts.auth title="escolher filial">
    <x-slot:subtitulo>selecionar unidade</x-slot:subtitulo>

    <p class="mb-5 text-sm text-aco">
        este estabelecimento tem {{ $filiais->count() }} unidades ativas.
        escolha onde quer trabalhar agora.
    </p>

    @if ($errors->has('branch_id'))
        <p class="mb-4 text-sm font-semibold text-brasa">{{ $errors->first('branch_id') }}</p>
    @endif

    <form method="POST" action="{{ route('filiais.definir') }}" class="space-y-2">
        @csrf

        @foreach ($filiais as $filial)
            @php
                $iconeSegmento = $filial->segment === 'RESTAURANT'
                    ? '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/>'
                    : '<path d="M6 2v6"/><path d="M18 2v6"/><rect x="2" y="8" width="20" height="4" rx="1"/><path d="M2 12v8a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-8"/><path d="M7 15h0m5 0h0m5 0h0"/>';
            @endphp
            <button
                type="submit"
                name="branch_id"
                value="{{ $filial->id }}"
                class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition-colors
                    {{ $atual === $filial->id
                        ? 'border-sol/40 gradiente-sol-suave'
                        : 'border-linha hover:bg-nevoa' }}"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                    {{ $atual === $filial->id ? 'gradiente-sol text-white' : 'bg-nevoa text-aco' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                        {!! $iconeSegmento !!}
                    </svg>
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold lowercase text-grafite">
                        {{ $filial->name }}
                    </span>
                    <span class="block truncate font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                        {{ $filial->company?->legal_name ?? '—' }}
                        @if ($filial->code) · {{ $filial->code }} @endif
                    </span>
                </span>

                @if ($atual === $filial->id)
                    <x-badge tipo="destaque">em uso</x-badge>
                @endif
            </button>
        @endforeach
    </form>

    <div class="mt-5 border-t border-linha pt-4">
        <x-button variante="fantasma" href="{{ route('dashboard') }}" class="w-full">
            voltar ao painel
        </x-button>
    </div>
</x-layouts.auth>
