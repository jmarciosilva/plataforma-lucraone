<?php

namespace Tests\Feature\Admin;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrecoPorMargemTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->active()->create();
        $company = Company::factory()->forCurrentTenant($tenant->id)->create();
        $admin = User::factory()->forTenant($tenant)->create();
        $p = app(ProvisionarEstabelecimento::class);
        $p->provisionarMatriz($tenant);
        $p->atribuirAdministrador($tenant, $admin);
        $this->product = Product::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id]);
        $this->actingAs($admin);
    }

    public function test_formulario_ensina_centavos_e_mostra_custo_real(): void
    {
        $this->custo();
        $this->get(route('catalog.products.show', $this->product))->assertOk()
            ->assertSee('12,90')->assertSee('Use vírgula para informar os centavos')
            ->assertSee('Preço de custo atual')->assertSee('2,19')
            ->assertSee('Margem desejada')->assertSee('Usar preço sugerido')->assertSee('Margem efetiva');
    }

    public static function valores(): array
    {
        return [['2', '2.00'], ['2,19', '2.19'], ['10,50', '10.50'], ['100,00', '100.00'], ['2.79', '2.79']];
    }

    #[DataProvider('valores')]
    public function test_venda_sem_custo_e_sem_margem_salva_decimal(string $entrada, string $esperado): void
    {
        $this->get(route('catalog.products.show', $this->product))->assertOk()->assertSee('Cadastre um preço de custo');
        $this->salvar(['amount' => $entrada])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($esperado, Price::where('product_id', $this->product->id)->firstOrFail()->amount);
    }

    public function test_valor_manual_e_autoritativo_e_historico_e_preservado(): void
    {
        $this->custo();
        $this->salvar(['amount' => '2,85', 'margem_desejada' => '30'])->assertSessionHasNoErrors();
        $this->salvar(['amount' => '2,79', 'margem_desejada' => '30', 'reason' => 'preço escolhido'])->assertSessionHasNoErrors();
        $sale = Price::where('product_id', $this->product->id)->sale()->firstOrFail();
        $this->assertSame('2.79', $sale->amount);
        $this->assertArrayNotHasKey('margem_desejada', $sale->getAttributes());
        $initial = $sale->histories()->where('event_type', 'initial')->sole();
        $this->assertSame('sale', $initial->price_type);
        $this->assertNull($initial->old_amount);
        $this->assertSame('2.85', $initial->new_amount);
        $this->assertNull($initial->old_reference_cost_amount);
        $this->assertSame('2.19', $initial->new_reference_cost_amount);
        $this->assertNull($initial->old_effective_margin_percentage);
        $this->assertSame('30.1370', $initial->new_effective_margin_percentage);
        $history = $sale->histories()->where('event_type', 'amount_changed')->sole();
        $this->assertSame('sale', $history->price_type);
        $this->assertSame('2.19', $history->old_reference_cost_amount);
        $this->assertSame('2.19', $history->new_reference_cost_amount);
        $this->assertSame('30.1370', $history->old_effective_margin_percentage);
        $this->assertSame('27.3973', $history->new_effective_margin_percentage);
        $this->assertSame('2.85', $history->old_amount);
        $this->assertSame('2.79', $history->new_amount);
        $this->assertSame('preço escolhido', $history->reason);
        $this->assertSame('27.3973', $sale->effective_margin_percentage);
        $this->salvar(['amount' => '2,79'])->assertSessionHasNoErrors();
        $this->assertSame(2, $sale->histories()->count());
    }

    public static function invalidos(): array
    {
        return [['0'], ['-1'], ['1e3'], ['NaN'], ['INF'], ['2,123'], ['10000000000,00'], ['1.234,56']];
    }

    #[DataProvider('invalidos')]
    public function test_valor_invalido_nao_grava(string $valor): void
    {
        $this->salvar(['amount' => $valor])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('prices', 0);
        $this->assertDatabaseCount('price_histories', 0);
    }

    public static function margensInvalidas(): array
    {
        return [['-1'], ['1e2'], ['NaN'], ['INF'], ['1000.01'], ['2,333']];
    }

    #[DataProvider('margensInvalidas')]
    public function test_margem_opcional_mas_validada_no_backend(string $margem): void
    {
        $this->salvar(['type' => 'cost', 'margem_desejada' => $margem])->assertSessionHasErrors('margem_desejada');
        $this->assertDatabaseCount('prices', 0);
    }

    public function test_custo_e_salvo_sem_margem_e_limite_decimal_e_aceito(): void
    {
        $this->salvar(['type' => 'cost', 'amount' => '9999999999,99'])->assertSessionHasNoErrors();
        $this->assertSame('9999999999.99', Price::where('product_id', $this->product->id)->cost()->sole()->amount);
    }

    public function test_margem_zero_e_decimal_brasileiro_sao_opcionais_e_aceitos(): void
    {
        foreach (['0', '12,50'] as $margem) {
            $this->salvar(['type' => 'cost', 'margem_desejada' => $margem])->assertSessionHasNoErrors();
        }
        $this->assertSame('2.79', Price::where('product_id', $this->product->id)->cost()->sole()->amount);
    }

    public function test_formulario_conecta_sugestao_e_valor_manual_sem_exigir_margem(): void
    {
        $this->custo();
        $html = $this->get(route('catalog.products.show', $this->product))->assertOk()->getContent();
        $dom = new \DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form[contains(@x-data, "precoProduto")]')->item(0);
        $this->assertNotNull($form);
        $amount = $xpath->query('.//input[@name="amount"]', $form)->item(0);
        $this->assertSame('text', $amount->getAttribute('type'));
        $this->assertSame('decimal', $amount->getAttribute('inputmode'));
        $this->assertSame('12,90', $amount->getAttribute('placeholder'));
        $this->assertSame('valor', $amount->getAttribute('x-model'));
        $margin = $xpath->query('.//input[@name="margem_desejada"]', $form)->item(0);
        $this->assertFalse($margin->hasAttribute('required'));
        $this->assertSame("tipo !== 'cost'", $margin->getAttribute('x-bind:disabled'));
        $this->assertSame(1, $xpath->query('.//div[@x-show="tipo === \'cost\'"]', $form)->length);
        $this->assertSame(1, $xpath->query('.//div[@x-show="precoSugerido !== null"]', $form)->length);
        $button = $xpath->query('.//button[contains(normalize-space(.), "Usar preço sugerido")]', $form)->item(0);
        $this->assertNotNull($button);
        $this->assertSame('button', $button->getAttribute('type'));
    }

    public function test_custo_de_outro_produto_ou_estabelecimento_nao_e_usado(): void
    {
        $outro = Product::factory()->create();
        Price::factory()->create(['tenant_id' => $outro->tenant_id, 'product_id' => $outro->id, 'type' => 'cost', 'currency' => 'BRL', 'amount' => '87654.32']);
        $html = $this->get(route('catalog.products.show', $this->product))->assertOk()
            ->assertSee('Cadastre um preço de custo')->assertDontSee('87654')->getContent();
        $this->assertStringContainsString('precoProduto', $html);
    }

    public function test_moeda_e_selecionada_com_nomes_claros_e_real_como_padrao(): void
    {
        $html = $this->get(route('catalog.products.show', $this->product))->assertOk()->getContent();
        $dom = new \DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        $xpath = new \DOMXPath($dom);
        $select = $xpath->query('//select[@name="currency"]')->item(0);
        $this->assertNotNull($select);
        $this->assertSame('moeda', $select->getAttribute('x-model'));
        $this->assertSame(0, $xpath->query('//input[@name="currency"]')->length);
        foreach (['BRL' => 'Real brasileiro', 'EUR' => 'Euro', 'USD' => 'Dólar americano'] as $codigo => $nome) {
            $option = $xpath->query('.//option[@value="'.$codigo.'"]', $select)->item(0);
            $this->assertNotNull($option);
            $this->assertStringContainsString($nome, $option->textContent);
        }
        $this->assertSame('BRL', $xpath->query('.//option[@selected]', $select)->item(0)->getAttribute('value'));
    }

    public function test_moeda_selecionada_e_preservada_apos_erro_e_no_preco_salvo(): void
    {
        $this->from(route('catalog.products.show', $this->product));
        $this->salvar(['currency' => 'EUR', 'amount' => 'inválido'])->assertSessionHasErrors('amount');
        $this->get(route('catalog.products.show', $this->product))->assertOk()
            ->assertSee('value="EUR" selected', false);
        $this->salvar(['currency' => 'USD', 'amount' => '2,79'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('prices', ['product_id' => $this->product->id, 'currency' => 'USD']);
    }

    public function test_custo_com_margem_e_opcional_e_nao_cria_venda(): void
    {
        $this->salvar(['type' => 'cost', 'amount' => '2,19'])->assertSessionHasNoErrors();
        $this->salvar(['type' => 'cost', 'amount' => '2,19', 'margem_desejada' => '30'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('prices', ['product_id' => $this->product->id, 'type' => 'cost', 'amount' => '2.19']);
        $this->assertDatabaseMissing('prices', ['product_id' => $this->product->id, 'type' => 'sale']);
        $this->assertDatabaseCount('price_histories', 1);
        $this->get(route('catalog.products.show', $this->product))->assertOk()
            ->assertSee('Informe a margem que deseja aplicar')->assertSee('Exemplo: 30 representa 30%')
            ->assertSee('Preço de venda sugerido');
    }

    private function salvar(array $mais = [])
    {
        return $this->post(route('catalog.products.prices.store', $this->product), ['type' => 'sale', 'currency' => 'BRL', 'amount' => '2,79', 'reason' => null, ...$mais]);
    }

    private function custo(): void
    {
        Price::factory()->create(['tenant_id' => $this->product->tenant_id, 'product_id' => $this->product->id, 'type' => 'cost', 'currency' => 'BRL', 'amount' => '2.19']);
    }
}
