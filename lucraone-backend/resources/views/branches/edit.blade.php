<x-layouts.app title="unidades" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $filial->isActive() ? 'bg-ok' : 'bg-brasa' }}"></span>
        editar unidade
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('branches.show', $filial) }}">voltar</x-button>
    </x-slot:acoes>

    <x-section-label>dados</x-section-label>

    <x-card>
        <form method="POST" action="{{ route('branches.update', $filial) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="name" rotulo="nome da unidade" obrigatorio class="lg:col-span-2">
                    <x-input nome="name" :valor="old('name', $filial->name)" autocomplete="off" />
                </x-form-group>

                <x-form-group nome="code" rotulo="código" ajuda="identificador curto.">
                    <x-input nome="code" :valor="old('code', $filial->code)" autocomplete="off" />
                </x-form-group>

                <x-form-group nome="document_override" rotulo="cnpj da filial" ajuda="somente se diferente da empresa-mãe.">
                    <x-input nome="document_override" :valor="old('document_override', $filial->document_override)" />
                </x-form-group>

                <x-form-group nome="email" rotulo="e-mail">
                    <x-input tipo="email" nome="email" :valor="old('email', $filial->email)" />
                </x-form-group>

                <x-form-group nome="phone" rotulo="telefone">
                    <x-input nome="phone" :valor="old('phone', $filial->phone)" />
                </x-form-group>
            </div>

            <div>
                @php $jaMatriz = old('is_headquarters', $filial->is_headquarters); @endphp
                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border px-3 text-sm transition-colors hover:bg-nevoa
                    {{ $jaMatriz ? 'border-sol bg-sol/5' : 'border-linha bg-white' }}
                    {{ ($temMatriz && ! $filial->is_headquarters) ? 'opacity-50 cursor-not-allowed' : '' }}"
                    @if($temMatriz && ! $filial->is_headquarters) title="já existe outra matriz neste estabelecimento" @endif
                >
                    <input
                        type="checkbox"
                        name="is_headquarters"
                        value="1"
                        class="h-4 w-4 rounded border-linha text-sol focus:ring-sol/30"
                        @checked($jaMatriz)
                        @if($temMatriz && ! $filial->is_headquarters) disabled @endif
                    >
                    <span>
                        <span class="block font-semibold lowercase text-grafite">esta é a matriz</span>
                        <span class="block text-xs text-aco">apenas uma unidade pode ser a matriz</span>
                    </span>
                </label>

                @if ($temMatriz && ! $filial->is_headquarters)
                    <p class="mt-1 text-xs text-aco">já existe outra matriz neste estabelecimento.</p>
                @endif

                @error('is_headquarters')
                    <p class="mt-1 text-xs font-semibold text-brasa" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <p class="text-sm font-semibold lowercase text-grafite">endereço principal</p>
                <div class="mt-4">
                    @include('branches._address_form', ['address' => $filial->primaryAddress])
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-button variante="fantasma" href="{{ route('branches.show', $filial) }}">cancelar</x-button>
                <x-button tipo="submit">salvar alterações</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
