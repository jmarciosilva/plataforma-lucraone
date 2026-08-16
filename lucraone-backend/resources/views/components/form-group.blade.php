@props([
    'nome',
    'rotulo',
    'ajuda' => null,
    'obrigatorio' => false,
])

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $nome }}" class="block text-sm font-semibold lowercase text-grafite">
        {{ $rotulo }}
        @if ($obrigatorio)
            <span class="text-brasa" aria-hidden="true">*</span>
        @endif
    </label>

    {{ $slot }}

    @error($nome)
        <p class="flex items-center gap-1 text-xs font-semibold text-brasa" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="h-3.5 w-3.5 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 8v4M12 16h.01" />
            </svg>
            {{ $message }}
        </p>
    @else
        @isset($ajuda)
            <p class="text-xs text-aco">{{ $ajuda }}</p>
        @endisset
    @enderror
</div>
