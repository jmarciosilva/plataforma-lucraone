@props(['codigo', 'titulo'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $titulo }} · LUCRAONE</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-nevoa">
    <div class="flex min-h-dvh items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm text-center">

            <p class="font-letreiro text-2xl leading-none tracking-tight text-grafite">
                LUCRA<span class="texto-sol">ONE</span>
            </p>

            <div class="cartao mt-8 p-8">
                <p class="font-comanda text-5xl font-medium text-aco">{{ $codigo }}</p>

                <h1 class="mt-4 text-lg font-bold lowercase text-grafite">{{ $titulo }}</h1>

                <p class="mt-2 text-sm text-aco">{{ $slot }}</p>

                {{--
                    Aponta para a raiz em vez de decidir aqui entre painel e
                    login: numa rota inexistente a sessão nem chega a iniciar,
                    então @auth daria sempre "visitante". A raiz redireciona
                    conforme o estado real.
                --}}
                <div class="mt-6">
                    <x-button href="{{ url('/') }}" class="w-full">voltar ao início</x-button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
