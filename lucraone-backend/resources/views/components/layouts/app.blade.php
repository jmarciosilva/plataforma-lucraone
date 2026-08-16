<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'painel' }} · LUCRAONE</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white">
    <div class="flex min-h-dvh" x-data="{ menuAberto: false }">

        {{-- Sidebar desktop --}}
        <x-sidebar class="sticky top-0 hidden h-dvh lg:flex" />

        {{-- Sidebar mobile (drawer) --}}
        <div
            x-show="menuAberto"
            x-cloak
            class="fixed inset-0 z-40 lg:hidden"
            @keydown.escape.window="menuAberto = false"
        >
            <div
                class="absolute inset-0 bg-grafite/40"
                @click="menuAberto = false"
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-end="opacity-0"
            ></div>

            <x-sidebar
                class="relative h-dvh"
                x-transition:enter="transition-transform ease-out duration-200"
                x-transition:enter-start="-translate-x-full"
                x-transition:leave="transition-transform ease-in duration-150"
                x-transition:leave-end="-translate-x-full"
            />
        </div>

        {{-- Área de conteúdo --}}
        <div class="flex min-w-0 flex-1 flex-col">

            {{-- Barra superior mobile --}}
            <div class="flex items-center gap-3 border-b border-linha px-4 py-3 lg:hidden">
                <button
                    type="button"
                    @click="menuAberto = true"
                    class="flex h-10 w-10 items-center justify-center rounded-xl text-aco transition-colors hover:bg-nevoa hover:text-grafite"
                    aria-label="abrir menu"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5">
                        <path d="M3 6h18M3 12h18M3 18h18" />
                    </svg>
                </button>
                <p class="font-letreiro text-base leading-none text-grafite">
                    LUCRA<span class="texto-sol">ONE</span>
                </p>
            </div>

            <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                {{-- Cabeçalho da página --}}
                @if (isset($cabecalho) || isset($title))
                    <header class="mb-8">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h1 class="text-3xl font-bold lowercase text-grafite lg:text-4xl">
                                    {{ $title ?? '' }}
                                    @if (isset($tenantNome))
                                        <span class="text-aco">·</span>
                                        <span class="texto-sol">{{ $tenantNome }}</span>
                                    @endif
                                </h1>
                                @isset($subtitulo)
                                    <p class="mt-1 flex items-center gap-2 text-sm text-aco">
                                        {{ $subtitulo }}
                                    </p>
                                @endisset
                            </div>

                            @isset($acoes)
                                <div class="flex items-center gap-2">
                                    {{ $acoes }}
                                </div>
                            @endisset
                        </div>
                    </header>
                @endif

                {{-- Mensagens de sessão --}}
                @if (session('sucesso'))
                    <x-alert tipo="sucesso" class="mb-6">{{ session('sucesso') }}</x-alert>
                @endif

                @if (session('erro'))
                    <x-alert tipo="erro" class="mb-6">{{ session('erro') }}</x-alert>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
