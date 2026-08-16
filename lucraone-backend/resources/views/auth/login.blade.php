<x-layouts.auth title="entrar">
    <x-slot:subtitulo>PAINEL ADMINISTRATIVO</x-slot:subtitulo>

    @if ($errors->any() && ! $errors->has('email') && ! $errors->has('password'))
        <x-alert tipo="erro" class="mb-5">{{ $errors->first() }}</x-alert>
    @endif

    <form
        method="POST"
        action="{{ route('login') }}"
        class="space-y-4"
        x-data="{
            email: '{{ old('email') }}',
            senha: '',
            enviando: false,
            get valido() { return this.email.includes('@') && this.senha.length > 0 },
        }"
        @submit="enviando = true"
    >
        @csrf

        <x-form-group nome="email" rotulo="e-mail" obrigatorio>
            <x-input
                tipo="email"
                nome="email"
                x-model="email"
                placeholder="voce@empresa.com.br"
                autocomplete="username"
                autofocus
                required
            />
        </x-form-group>

        <x-form-group nome="password" rotulo="senha" obrigatorio>
            <x-input
                tipo="password"
                nome="password"
                x-model="senha"
                placeholder="••••••••"
                autocomplete="current-password"
                required
            />
        </x-form-group>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-aco">
            <input
                type="checkbox"
                name="lembrar"
                value="1"
                @checked(old('lembrar'))
                class="h-4 w-4 rounded border-linha text-sol focus:ring-2 focus:ring-sol/30"
            >
            lembrar de mim neste computador
        </label>

        <x-button
            tipo="submit"
            class="w-full"
            x-bind:disabled="! valido || enviando"
        >
            <span x-show="! enviando">entrar</span>
            <span x-show="enviando" x-cloak>entrando…</span>
        </x-button>
    </form>
</x-layouts.auth>
