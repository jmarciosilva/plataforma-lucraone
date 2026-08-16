<x-layouts.app title="empresas" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $company->status === 'ACTIVE' ? 'bg-ok' : ($company->status === 'SUSPENDED' ? 'bg-alerta' : 'bg-brasa') }}"></span>
        detalhe da empresa
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-companies')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('companies.index') }}">voltar</x-button>
        <x-button href="{{ route('companies.edit', $company) }}">editar</x-button>
    </x-slot:acoes>

    @include('companies._help')

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="status" :valor="strtolower($company->status)" nota="operacional" />
        <x-kpi rotulo="endereços" :valor="$company->addresses->count()" nota="vinculados" />
        <x-kpi rotulo="filiais" :valor="$company->branches()->count()" nota="neste tenant" />
        <x-kpi rotulo="atualizada" :valor="$company->updated_at->format('d/m')" nota="última alteração" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">razão social</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $company->legal_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">nome fantasia</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $company->trade_name ?: 'não informado' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">cnpj</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $company->document ?: 'não informado' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">email</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $company->email ?: 'não informado' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">telefone</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $company->phone ?: 'não informado' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">inscrições</dt>
                    <dd class="mt-1 text-sm text-grafite">
                        estadual {{ $company->state_registration ?: 'n/i' }} · municipal {{ $company->municipal_registration ?: 'n/i' }}
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">ações</p>
            <x-button variante="secundario" href="{{ route('companies.edit', $company) }}" class="mt-4 w-full">editar dados</x-button>

            @if ($company->status === 'INACTIVE')
                <form method="POST" action="{{ route('companies.restore', $company) }}" class="mt-2">
                    @csrf
                    <x-button tipo="submit" class="w-full">reativar empresa</x-button>
                </form>
            @else
                <button type="button" x-data @click="$dispatch('abrir-modal', 'desativar-company')" class="mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brasa px-4 text-sm font-semibold lowercase text-white transition-opacity hover:opacity-90">
                    desativar empresa
                </button>

                <x-modal nome="desativar-company" titulo="desativar empresa">
                    <p class="text-sm text-aco">
                        A empresa ficará inativa, mas o cadastro e os vínculos históricos continuam preservados.
                    </p>

                    <x-slot:acoes>
                        <form method="POST" action="{{ route('companies.destroy', $company) }}">
                            @csrf
                            @method('DELETE')
                            <x-button variante="perigo" tipo="submit">confirmar desativação</x-button>
                        </form>
                    </x-slot:acoes>
                </x-modal>
            @endif
        </x-card>
    </div>

    <x-section-label class="mt-8">endereços</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">novo endereço</p>
            <form method="POST" action="{{ route('companies.addresses.store', $company) }}" class="mt-4">
                @csrf
                @include('companies._address_form')

                <div class="mt-6 flex justify-end">
                    <x-button tipo="submit">salvar endereço</x-button>
                </div>
            </form>
        </x-card>

        <div class="space-y-4">
            @forelse ($company->addresses as $address)
                <x-card>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold lowercase text-grafite">{{ $address->formatted() }}</p>
                            <p class="mt-1 text-xs text-aco">{{ $address->country }} · {{ $address->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        @if ($address->is_primary)
                            <x-badge tipo="destaque">principal</x-badge>
                        @endif
                    </div>

                    <button type="button" x-data @click="$dispatch('abrir-modal', 'editar-address-{{ $address->id }}')" class="mt-4 text-sm font-semibold lowercase text-sol">
                        editar endereço
                    </button>

                    <x-modal nome="editar-address-{{ $address->id }}" titulo="editar endereço">
                        <form method="POST" action="{{ route('companies.addresses.update', [$company, $address]) }}">
                            @csrf
                            @method('PUT')
                            @include('companies._address_form', ['address' => $address, 'prefix' => 'edit_'.$address->id.'_'])

                            <div class="mt-6 flex justify-end">
                                <x-button tipo="submit">salvar endereço</x-button>
                            </div>
                        </form>
                    </x-modal>
                </x-card>
            @empty
                <x-card>
                    <p class="text-sm text-aco">nenhum endereço cadastrado.</p>
                </x-card>
            @endforelse
        </div>
    </div>
</x-layouts.app>
