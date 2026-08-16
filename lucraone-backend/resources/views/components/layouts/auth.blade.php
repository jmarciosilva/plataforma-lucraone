<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'entrar' }} · LUCRAONE</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-nevoa">
    <div class="flex min-h-dvh items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">

            {{-- Marca --}}
            <div class="mb-8 text-center">
                <p class="font-letreiro text-2xl leading-none tracking-tight text-grafite">
                    LUCRA<span class="texto-sol">ONE</span>
                </p>
                @isset($subtitulo)
                    <p class="mt-2 font-comanda text-[0.6rem] uppercase tracking-[0.18em] text-aco">
                        {{ $subtitulo }}
                    </p>
                @endisset
            </div>

            <div class="cartao p-6">
                @if (session('erro'))
                    <x-alert tipo="erro" class="mb-5">{{ session('erro') }}</x-alert>
                @endif

                @if (session('sucesso'))
                    <x-alert tipo="sucesso" class="mb-5">{{ session('sucesso') }}</x-alert>
                @endif

                {{ $slot }}
            </div>

            @isset($rodape)
                <p class="mt-6 text-center text-xs text-aco">
                    {{ $rodape }}
                </p>
            @endisset
        </div>
    </div>
</body>
</html>
