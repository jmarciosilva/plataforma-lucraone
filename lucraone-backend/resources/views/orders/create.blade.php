@php
    $companyOptions = $companies->mapWithKeys(fn ($company) => [
        $company->id => $company->trade_name ?: $company->legal_name,
    ])->all();

    $customerOptions = $customers->mapWithKeys(fn ($customer) => [
        $customer->id => $customer->name . ($customer->email ? " · {$customer->email}" : ''),
    ])->all();
@endphp

<x-layouts.app title="novo pedido" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        passo 1 de 2 — dados do pedido
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-sales')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('sales.orders.index') }}">voltar</x-button>
    </x-slot:acoes>

    @include('orders._help')

    <x-card>
        <p class="text-sm text-aco">
            crie o pedido primeiro e depois adicione os itens na tela de detalhe. o pedido nasce em <strong class="text-grafite">rascunho</strong> e não movimenta estoque.
        </p>

        <form method="POST" action="{{ route('sales.orders.store') }}" class="mt-6 space-y-6" x-data="{ clienteExistente: '{{ old('customer_id') }}' }">
            @csrf

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-form-group nome="company_id" rotulo="empresa" obrigatorio>
                    <x-select nome="company_id" :opcoes="$companyOptions" :valor="old('company_id')" vazio="selecione" />
                </x-form-group>

                <x-form-group nome="discount" rotulo="desconto" ajuda="valor absoluto abatido do subtotal">
                    <x-input tipo="number" nome="discount" step="0.01" min="0" :valor="old('discount', '0')" />
                </x-form-group>
            </div>

            <div>
                <x-section-label>cliente</x-section-label>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <x-form-group nome="customer_id" rotulo="cliente cadastrado" ajuda="deixe em branco para cadastrar um cliente novo">
                        <x-select nome="customer_id" :opcoes="$customerOptions" :valor="old('customer_id')" vazio="cadastrar novo cliente" x-model="clienteExistente" />
                    </x-form-group>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3" x-show="! clienteExistente">
                    <x-form-group nome="customer_name" rotulo="nome do novo cliente">
                        <x-input nome="customer_name" :valor="old('customer_name')" placeholder="nome de quem está comprando" />
                    </x-form-group>

                    <x-form-group nome="customer_email" rotulo="e-mail">
                        <x-input tipo="email" nome="customer_email" :valor="old('customer_email')" />
                    </x-form-group>

                    <x-form-group nome="customer_phone" rotulo="telefone">
                        <x-input nome="customer_phone" :valor="old('customer_phone')" />
                    </x-form-group>
                </div>
            </div>

            <x-form-group nome="notes" rotulo="observações">
                <x-input tipo="textarea" nome="notes" :valor="old('notes')" placeholder="entrega, condição de pagamento, combinados..." />
            </x-form-group>

            <div class="flex flex-wrap gap-2">
                <x-button tipo="submit">criar pedido</x-button>
                <x-button variante="fantasma" href="{{ route('sales.orders.index') }}">cancelar</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
