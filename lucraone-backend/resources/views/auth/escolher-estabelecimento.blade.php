<x-layouts.auth title="escolher estabelecimento">
    <x-slot:subtitulo>{{ auth()->user()->name }}</x-slot:subtitulo>

    <p class="mb-5 text-sm text-aco">
        você tem acesso a {{ $estabelecimentos->count() }} estabelecimentos.
        escolha onde quer trabalhar agora.
    </p>

    <form method="POST" action="{{ route('estabelecimentos.definir') }}" class="space-y-2">
        @csrf

        @foreach ($estabelecimentos as $estabelecimento)
            <button
                type="submit"
                name="tenant_id"
                value="{{ $estabelecimento->id }}"
                class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition-colors
                    {{ $atual === $estabelecimento->id
                        ? 'border-sol/40 gradiente-sol-suave'
                        : 'border-linha hover:bg-nevoa' }}"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                    {{ $atual === $estabelecimento->id ? 'gradiente-sol text-white' : 'bg-nevoa text-aco' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                        <path d="M3 21h18" />
                        <path d="M5 21V7l7-4 7 4v14" />
                        <path d="M10 21v-5h4v5" />
                    </svg>
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold lowercase text-grafite">
                        {{ $estabelecimento->name }}
                    </span>
                    <span class="block truncate font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                        {{ $estabelecimento->slug }}
                    </span>
                </span>

                @if ($atual === $estabelecimento->id)
                    <x-badge tipo="destaque">em uso</x-badge>
                @endif
            </button>
        @endforeach
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-5 border-t border-linha pt-4">
        @csrf
        <x-button variante="fantasma" tipo="submit" class="w-full">
            sair
        </x-button>
    </form>
</x-layouts.auth>
