<fieldset class="mt-6">
    <legend class="text-sm font-semibold text-grafite">Preços iniciais</legend>
    <p class="mt-1 text-xs text-aco">Todo produto precisa de preço de custo e de venda para ser cadastrado. Use vírgula para os centavos (ex.: 12,90).</p>
    <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-form-group nome="currency" rotulo="moeda" obrigatorio>
            <x-select nome="currency" :opcoes="$currencyOptions" :valor="old('currency', 'BRL')" />
        </x-form-group>

        <x-form-group nome="cost_price" rotulo="preço de custo" obrigatorio ajuda="Quanto o produto custa para a loja. Base para calcular a margem de venda.">
            <x-input nome="cost_price" :valor="old('cost_price')" inputmode="decimal" placeholder="8,50" maxlength="13" required />
        </x-form-group>

        <x-form-group nome="sale_price" rotulo="preço de venda" obrigatorio ajuda="Por quanto o produto será vendido ao cliente.">
            <x-input nome="sale_price" :valor="old('sale_price')" inputmode="decimal" placeholder="12,90" maxlength="13" required />
        </x-form-group>
    </div>
</fieldset>
