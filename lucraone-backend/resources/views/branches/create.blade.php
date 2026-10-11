<x-layouts.app title="unidades" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        cadastrar nova unidade
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('branches.index') }}">voltar</x-button>
    </x-slot:acoes>

    <x-section-label>cadastro</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('branches.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="company_id" rotulo="empresa" obrigatorio class="lg:col-span-2">
                    <x-select
                        nome="company_id"
                        :opcoes="$empresas->mapWithKeys(fn ($e) => [$e->id => $e->legal_name])->all()"
                        :valor="old('company_id')"
                        vazio="selecione"
                    />
                </x-form-group>

                <x-form-group nome="name" rotulo="nome da unidade" obrigatorio class="lg:col-span-2">
                    <x-input nome="name" :valor="old('name')" placeholder="Loja Matriz, Filial Sul, Depósito Central…" autocomplete="off" />
                </x-form-group>

                <x-form-group nome="code" rotulo="código" ajuda="identificador curto. ex.: MAT, F01, DEP.">
                    <x-input nome="code" :valor="old('code')" placeholder="MAT" autocomplete="off" />
                </x-form-group>

                <x-form-group nome="document_override" rotulo="cnpj da filial" ajuda="somente se diferente da empresa-mãe.">
                    <x-input nome="document_override" :valor="old('document_override')" />
                </x-form-group>

                <x-form-group nome="email" rotulo="e-mail">
                    <x-input tipo="email" nome="email" :valor="old('email')" />
                </x-form-group>

                <x-form-group nome="phone" rotulo="telefone">
                    <x-input nome="phone" :valor="old('phone')" />
                </x-form-group>
            </div>

            <div>
                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-linha bg-white px-3 text-sm transition-colors hover:bg-nevoa
                    {{ old('is_headquarters') ? 'border-sol bg-sol/5' : '' }}
                    {{ $temMatriz ? 'opacity-50 cursor-not-allowed' : '' }}"
                    @if($temMatriz) title="já existe uma matriz neste estabelecimento" @endif
                >
                    <input
                        type="checkbox"
                        name="is_headquarters"
                        value="1"
                        class="h-4 w-4 rounded border-linha text-sol focus:ring-sol/30"
                        @checked(old('is_headquarters'))
                        @if($temMatriz) disabled @endif
                    >
                    <span>
                        <span class="block font-semibold lowercase text-grafite">esta é a matriz</span>
                        <span class="block text-xs text-aco">apenas uma unidade pode ser a matriz da empresa</span>
                    </span>
                </label>

                @if ($temMatriz)
                    <p class="mt-1 text-xs text-aco">já existe uma matriz neste estabelecimento. edite a matriz atual para transferir a designação.</p>
                @endif

                @error('is_headquarters')
                    <p class="mt-1 text-xs font-semibold text-brasa" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <p class="text-sm font-semibold lowercase text-grafite">endereço</p>
                <p class="mt-1 text-xs text-aco">opcional. pode ser adicionado depois.</p>
                <div class="mt-4">
                    @include('branches._address_form')
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-button variante="fantasma" href="{{ route('branches.index') }}">cancelar</x-button>
                <x-button tipo="submit">salvar unidade</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
