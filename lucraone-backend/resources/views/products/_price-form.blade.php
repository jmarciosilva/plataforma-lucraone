@php
    $moedas = [
        'BRL' => 'Real brasileiro (BRL)',
        'EUR' => 'Euro (EUR)',
        'USD' => 'Dólar americano (USD)',
        'GBP' => 'Libra esterlina (GBP)',
        'ARS' => 'Peso argentino (ARS)',
        'CLP' => 'Peso chileno (CLP)',
        'CAD' => 'Dólar canadense (CAD)',
        'AUD' => 'Dólar australiano (AUD)',
        'CHF' => 'Franco suíço (CHF)',
        'JPY' => 'Iene japonês (JPY)',
    ];
    foreach ([...$product->prices->pluck('currency')->all(), old('currency')] as $moeda) {
        if (is_string($moeda) && preg_match('/^[A-Za-z]{3}$/D', $moeda) && !isset($moedas[$moeda])) {
            $moedas[$moeda] = strtoupper($moeda);
        }
    }
    $custos = $product->prices->where('type', 'cost')->mapWithKeys(fn ($price) => [$price->currency => $price->amount])->all();
    $precoInicial = [
        'custos' => $custos,
        'tipo' => old('type', 'sale'),
        'moeda' => old('currency', 'BRL'),
        'valor' => old('amount', ''),
        'margem' => old('margem_desejada', ''),
    ];
@endphp

<form method="POST" action="{{ route('catalog.products.prices.store', $product) }}" class="mt-4 space-y-4" x-data="precoProduto(@js($precoInicial))">
    @csrf
    <x-form-group nome="type" rotulo="tipo" obrigatorio>
        <x-select nome="type" :opcoes="$priceTypes" :valor="old('type', 'sale')" x-model="tipo" />
    </x-form-group>
    <x-form-group nome="currency" rotulo="moeda" obrigatorio>
        <x-select nome="currency" :opcoes="$moedas" :valor="old('currency', 'BRL')" x-model="moeda" required />
    </x-form-group>

    <div x-show="tipo === 'sale'" x-cloak class="rounded-xl bg-nevoa p-3 text-sm text-grafite" aria-live="polite">
        <p x-show="custoAtual !== null">
            Preço de custo atual:
            <span x-text="(moeda.toUpperCase() === 'BRL' ? 'R$' : moeda.toUpperCase()) + ' ' + custoAtual"></span>
        </p>
        <p x-show="custoAtual === null || custoAtual === '0,00'">
            Cadastre um preço de custo para visualizar a margem deste produto.
        </p>
    </div>

    <x-form-group nome="amount" rotulo="valor" obrigatorio ajuda="Use vírgula para informar os centavos. Exemplo: 12,90">
        <x-input nome="amount" x-model="valor" inputmode="decimal" placeholder="12,90" maxlength="13" required />
    </x-form-group>

    <div x-show="tipo === 'cost'" x-cloak>
        <x-form-group nome="margem_desejada" rotulo="Margem desejada (%)" ajuda="Informe a margem que deseja aplicar para sugerir o preço de venda deste produto. Exemplo: 30 representa 30%. Campo opcional, de 0% a 1000%.">
            <x-input nome="margem_desejada" :valor="old('margem_desejada')" x-model="margem" x-bind:disabled="tipo !== 'cost'" inputmode="decimal" placeholder="30" maxlength="7" />
        </x-form-group>
        <div x-show="precoSugerido !== null" class="mt-3 rounded-xl bg-nevoa p-3 text-sm" aria-live="polite">
            <p>Preço de venda sugerido: <strong x-text="(moeda.toUpperCase() === 'BRL' ? 'R$' : moeda.toUpperCase()) + ' ' + precoSugerido"></strong></p>
            <x-button variante="secundario" class="mt-2" @click="aplicar()">Usar preço sugerido</x-button>
        </div>
        <p class="mt-2 text-xs text-aco">Usar a sugestão apenas preenche uma venda para você conferir e salvar. O custo digitado não será salvo por essa ação; salve o custo primeiro para registrar a margem efetiva.</p>
        <p x-show="margem !== '' && precoSugerido === null" class="mt-2 text-xs text-brasa" role="alert">Informe um custo maior que zero e uma margem de 0% a 1000%, com até duas casas decimais. A sugestão precisa caber no campo de valor.</p>
    </div>

    <p x-show="margemEfetiva !== null" x-cloak class="text-xs text-aco" aria-live="polite">
        Margem efetiva sobre o custo: <span x-text="margemEfetiva + '%'"></span>
    </p>
    <x-form-group nome="reason" rotulo="motivo">
        <x-input nome="reason" placeholder="promoção, reajuste, correção..." />
    </x-form-group>
    <x-button tipo="submit" class="w-full">salvar preço</x-button>
</form>
