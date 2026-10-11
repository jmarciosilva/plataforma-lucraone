<x-layouts.app title="operadores de pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        liberar alguém para abrir o caixa
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-pdv-operators')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('pdv-operators.index') }}">voltar</x-button>
    </x-slot:acoes>

    @include('pdv-operators._help')

    <x-section-label>cadastro</x-section-label>

    {{--
        Dois modos no mesmo formulário, alternados no cliente. O backend não
        confia nessa alternância: ele lê o campo `modo` e aplica autoridades
        diferentes para cada caminho — criar pessoa exige administrar usuários,
        habilitar alguém existente não mexe em identidade alguma.
    --}}
    <x-card x-data="{ modo: '{{ old('modo', $podeCriarIdentidade ? 'novo' : 'existente') }}' }">
        <form method="POST" action="{{ route('pdv-operators.store') }}" class="space-y-6">
            @csrf

            <div>
                <p class="text-sm font-semibold lowercase text-grafite">quem vai operar</p>

                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @if ($podeCriarIdentidade)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border px-3 text-sm transition-colors"
                               :class="modo === 'novo' ? 'border-sol bg-sol/5 text-grafite' : 'border-linha bg-white text-aco hover:bg-nevoa'">
                            <input type="radio" name="modo" value="novo" x-model="modo" class="h-4 w-4 border-linha text-sol focus:ring-sol/30">
                            <span>
                                <span class="block font-semibold lowercase">pessoa nova</span>
                                <span class="block text-xs text-aco">cria a conta e libera o caixa</span>
                            </span>
                        </label>
                    @endif

                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border px-3 text-sm transition-colors"
                           :class="modo === 'existente' ? 'border-sol bg-sol/5 text-grafite' : 'border-linha bg-white text-aco hover:bg-nevoa'">
                        <input type="radio" name="modo" value="existente" x-model="modo" class="h-4 w-4 border-linha text-sol focus:ring-sol/30">
                        <span>
                            <span class="block font-semibold lowercase">pessoa já cadastrada</span>
                            <span class="block text-xs text-aco">libera o caixa sem mexer na conta</span>
                        </span>
                    </label>
                </div>

                @error('modo')
                    <p class="mt-1 text-xs font-semibold text-brasa" role="alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Pessoa nova --}}
            <div x-show="modo === 'novo'" x-cloak class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="name" rotulo="nome completo" obrigatorio>
                    <x-input nome="name" autocomplete="name" placeholder="José Márcio Ferreira da Silva" />
                </x-form-group>

                <x-form-group nome="email" rotulo="e-mail" ajuda="opcional. operador de caixa não precisa de e-mail.">
                    <x-input tipo="email" nome="email" autocomplete="email" />
                </x-form-group>

                <x-form-group nome="password" rotulo="senha inicial" obrigatorio ajuda="pelo menos 8 caracteres.">
                    <x-input tipo="password" nome="password" autocomplete="new-password" />
                </x-form-group>

                <x-form-group nome="password_confirmation" rotulo="confirmar senha" obrigatorio>
                    <x-input tipo="password" nome="password_confirmation" autocomplete="new-password" />
                </x-form-group>
            </div>

            {{-- Pessoa existente --}}
            <div x-show="modo === 'existente'" x-cloak>
                <x-form-group nome="user_id" rotulo="pessoa" obrigatorio ajuda="apenas pessoas com acesso ativo a este estabelecimento e que ainda não operam o caixa.">
                    <x-select
                        nome="user_id"
                        :opcoes="$pessoasDisponiveis->mapWithKeys(fn ($p) => [$p->id => $p->name . ($p->email ? ' · ' . $p->email : '')])->all()"
                        vazio="selecione"
                    />
                </x-form-group>

                @if ($pessoasDisponiveis->isEmpty())
                    <x-alert tipo="info" class="mt-3">
                        todas as pessoas com acesso ativo a este estabelecimento já operam o caixa.
                    </x-alert>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group
                    nome="username"
                    rotulo="usuário do pdv"
                    obrigatorio
                    ajuda="é o que a pessoa digita no caixa. ex.: marcio, joao, caixa01."
                >
                    <x-input nome="username" autocomplete="off" placeholder="marcio" />
                </x-form-group>
            </div>

            @include('pdv-operators._filiais', ['filiais' => $filiais, 'filiaisSelecionadas' => []])

            <div class="flex justify-end gap-2">
                <x-button variante="fantasma" href="{{ route('pdv-operators.index') }}">cancelar</x-button>
                <x-button tipo="submit">salvar operador</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
