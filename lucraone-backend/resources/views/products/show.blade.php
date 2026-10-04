<x-layouts.app title="produtos" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $product->trashed() ? 'bg-brasa' : ($product->status === 'active' ? 'bg-ok' : 'bg-alerta') }}"></span>
        detalhe do produto
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-products')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('catalog.products.index') }}">voltar</x-button>
        @if (! $product->trashed())
            <x-button href="{{ route('catalog.products.edit', $product) }}">editar</x-button>
        @endif
    </x-slot:acoes>

    @include('products._help')

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="sku" :valor="$product->sku" nota="código interno" />
        <x-kpi rotulo="status" :valor="$product->trashed() ? 'arquivado' : $product->status" nota="catálogo" />
        <x-kpi rotulo="categorias" :valor="$product->categories->count()" nota="associadas" />
        <x-kpi rotulo="preços" :valor="$product->prices->count()" nota="vigentes" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">nome</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $product->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">empresa</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $product->company?->trade_name ?: $product->company?->legal_name ?: 'sem empresa' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">código de barras</dt>
                    <dd class="mt-1 font-comanda text-sm text-grafite">{{ $product->barcode ?: 'sem código de barras' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">unidade de venda/estoque</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $unitOptions[$product->unit] ?? $product->unit }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs lowercase text-aco">descrição</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $product->description ?: 'não informada' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs lowercase text-aco">categorias</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $product->categories->pluck('name')->implode(', ') ?: 'sem categoria' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">ações</p>

            @if ($product->trashed())
                <form method="POST" action="{{ route('catalog.products.restore', $product->id) }}" class="mt-4">
                    @csrf
                    <x-button tipo="submit" class="w-full">restaurar produto</x-button>
                </form>
            @else
                <x-button variante="secundario" href="{{ route('catalog.products.edit', $product) }}" class="mt-4 w-full">editar dados</x-button>

                <button type="button" x-data @click="$dispatch('abrir-modal', 'arquivar-product')" class="mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brasa px-4 text-sm font-semibold lowercase text-white transition-opacity hover:opacity-90">
                    arquivar produto
                </button>

                <x-modal nome="arquivar-product" titulo="arquivar produto">
                    <p class="text-sm text-aco">O produto sairá das listagens ativas, mas poderá ser restaurado depois.</p>

                    <x-slot:acoes>
                        <form method="POST" action="{{ route('catalog.products.destroy', $product) }}">
                            @csrf
                            @method('DELETE')
                            <x-button variante="perigo" tipo="submit">confirmar arquivo</x-button>
                        </form>
                    </x-slot:acoes>
                </x-modal>
            @endif
        </x-card>
    </div>

    <x-section-label class="mt-8">embalagens</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @if (! $product->trashed())
            <x-card>
                <p class="text-sm font-semibold lowercase text-grafite">nova embalagem</p>

                @if ($product->unit === 'UN')
                    <p class="mt-1 text-xs text-aco">caixa, fardo ou multipack deste produto. o estoque continua contado em {{ $product->unit }}.</p>

                    <form method="POST" action="{{ route('catalog.products.packages.store', $product) }}" class="mt-4 space-y-4">
                        @csrf
                        <x-form-group nome="name" rotulo="nome" obrigatorio>
                            <x-input nome="name" placeholder="caixa 24, fardo 6..." maxlength="100" />
                        </x-form-group>
                        <x-form-group nome="barcode" rotulo="código de barras" ajuda="Opcional. Código impresso na caixa ou no fardo, diferente do código da unidade.">
                            <x-input nome="barcode" inputmode="numeric" maxlength="14" autocomplete="off" />
                        </x-form-group>
                        <x-form-group nome="factor" rotulo="fator" obrigatorio ajuda="Quantas unidades do produto a embalagem contém.">
                            <x-input tipo="number" nome="factor" step="1" min="2" />
                        </x-form-group>
                        <x-button tipo="submit" class="w-full">salvar embalagem</x-button>
                    </form>
                @else
                    <p class="mt-4 text-sm text-aco">Embalagens comerciais estão disponíveis inicialmente apenas para produtos com unidade UN.</p>
                @endif
            </x-card>
        @endif

        <div class="{{ $product->trashed() ? 'lg:col-span-3' : 'lg:col-span-2' }}">
            <x-table :cabecalhos="['embalagem', 'código de barras', 'contém', '']">
                @forelse ($product->packages as $package)
                    <tr class="border-b border-linha last:border-0">
                        <td class="px-5 py-4 font-semibold lowercase text-grafite">{{ $package->name }}</td>
                        <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $package->barcode ?: 'sem código' }}</td>
                        <td class="px-5 py-4 font-comanda text-sm text-grafite">{{ $package->factor }} {{ $product->unit }}</td>
                        <td class="px-5 py-4 text-right">
                            @if (! $product->trashed())
                                <form method="POST" action="{{ route('catalog.products.packages.destroy', [$product, $package]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variante="fantasma" tipo="submit">remover</x-button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-aco">nenhuma embalagem cadastrada.</td>
                    </tr>
                @endforelse
            </x-table>
        </div>
    </div>

    <x-section-label class="mt-8">preços</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @if (! $product->trashed())
            <x-card>
                <p class="text-sm font-semibold lowercase text-grafite">novo preço</p>
                @include('products._price-form')
            </x-card>
        @endif

        <div class="{{ $product->trashed() ? 'lg:col-span-3' : 'lg:col-span-2' }}">
            <x-table :cabecalhos="['tipo', 'moeda', 'valor', 'margem', '']">
                @forelse ($product->prices as $price)
                    <tr class="border-b border-linha last:border-0">
                        <td class="px-5 py-4 font-semibold lowercase text-grafite">{{ $priceTypes[$price->type] ?? $price->type }}</td>
                        <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $price->currency }}</td>
                        <td class="px-5 py-4 font-comanda text-sm text-grafite">R$ {{ number_format((float) $price->amount, 2, ',', '.') }}</td>
                        <td class="px-5 py-4 text-sm text-aco">
                            {{ $price->effective_margin_percentage !== null ? \App\Modules\Products\Domain\Services\CalculoMargem::exibir($price->effective_margin_percentage).'%' : 'n/a' }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            <form method="POST" action="{{ route('catalog.products.prices.destroy', [$product, $price]) }}">
                                @csrf
                                @method('DELETE')
                                <x-button variante="fantasma" tipo="submit">remover</x-button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-aco">nenhum preço cadastrado.</td>
                    </tr>
                @endforelse
            </x-table>
        </div>
    </div>

    <x-section-label class="mt-8">histórico de preços</x-section-label>

    <x-table :cabecalhos="['tipo', 'evento', 'moeda', 'alteração', 'custo de referência', 'margem', 'motivo', 'usuário', 'quando']">
        @forelse ($priceHistory as $history)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4 text-sm text-aco">{{ $priceTypes[$history->price_type] ?? 'não informado' }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ \App\Modules\Products\Domain\Models\PriceHistory::EVENT_LABELS[$history->event_type] ?? 'evento antigo' }}</td>
                <td class="px-5 py-4 font-comanda text-sm text-aco">{{ $history->currency }}</td>
                <td class="px-5 py-4 text-sm text-grafite">
                    {{ \App\Modules\Products\Domain\Services\CalculoMargem::exibir($history->old_amount) }}
                    &rarr;
                    {{ \App\Modules\Products\Domain\Services\CalculoMargem::exibir($history->new_amount) }}
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ \App\Modules\Products\Domain\Services\CalculoMargem::exibir($history->old_reference_cost_amount) }} → {{ \App\Modules\Products\Domain\Services\CalculoMargem::exibir($history->new_reference_cost_amount) }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $history->old_effective_margin_percentage !== null ? \App\Modules\Products\Domain\Services\CalculoMargem::exibir($history->old_effective_margin_percentage).'%' : '—' }} → {{ $history->new_effective_margin_percentage !== null ? \App\Modules\Products\Domain\Services\CalculoMargem::exibir($history->new_effective_margin_percentage).'%' : '—' }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $history->reason ?: 'sem motivo' }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $history->changedBy?->name ?: 'sistema' }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $history->changed_at->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="px-5 py-10 text-center text-sm text-aco">nenhuma alteração de preço registrada.</td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
