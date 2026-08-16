{{-- Stub do F3.1. O formulário funcional chega no F3.2. --}}
<x-layouts.auth title="entrar">
    <x-slot:subtitulo>PAINEL ADMINISTRATIVO</x-slot:subtitulo>

    <form method="POST" action="#" class="space-y-4">
        @csrf

        <x-form-group nome="email" rotulo="e-mail" obrigatorio>
            <x-input tipo="email" nome="email" placeholder="voce@empresa.com.br" autocomplete="username" />
        </x-form-group>

        <x-form-group nome="password" rotulo="senha" obrigatorio>
            <x-input tipo="password" nome="password" placeholder="••••••••" autocomplete="current-password" />
        </x-form-group>

        <x-button tipo="submit" class="w-full" disabled>entrar</x-button>
    </form>

    <x-slot:rodape>
        formulário em construção — autenticação chega no sprint F3.2
    </x-slot:rodape>
</x-layouts.auth>
