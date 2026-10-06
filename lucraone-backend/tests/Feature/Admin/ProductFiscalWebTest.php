<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductFiscalWebTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $company;

    private User $admin;

    private const FISCAL = ['ncm_code' => '00123456', 'cest_code' => '0012345', 'default_origin_code' => '0'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $role = Role::factory()->admin()->forTenant($this->tenant->id)->create();
        $role->grantPermission(Permission::factory()->forTenant($this->tenant->id)->create(['name' => 'manage-products']));
        $this->admin->assignRole($role, $this->tenant->id);
        $this->actingAs($this->admin);
    }

    private function data(array $data = []): array
    {
        return ['company_id' => $this->company->id, 'sku' => 'PM05-'.Str::random(8), 'name' => 'Produto', 'status' => 'active', 'unit' => 'UN', ...$data];
    }

    private function product(array $data = []): Product
    {
        return Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, ...$data]);
    }

    public function test_form_and_detail_show_optional_fields_technical_origins_and_unknown_values(): void
    {
        $this->get(route('catalog.products.create'))->assertOk()
            ->assertSee('Dados fiscais')->assertSee('name="ncm_code"', false)->assertSee('name="cest_code"', false)
            ->assertSee('name="default_origin_code"', false)->assertSee('Não informada')
            ->assertSee('Código 0')->assertSee('contexto da operação');
        $product = $this->product();
        $this->get(route('catalog.products.show', $product))->assertOk()->assertSee('Dados fiscais')->assertSee('Não informado');
    }

    public function test_web_create_preserves_codes_and_zero_and_edit_selects_zero(): void
    {
        $data = $this->data(self::FISCAL);
        $response = $this->post(route('catalog.products.store'), $data)->assertSessionHasNoErrors();
        $product = Product::withoutGlobalScopes()->where('sku', $data['sku'])->firstOrFail();
        $response->assertRedirect(route('catalog.products.show', $product));
        $this->assertSame(self::FISCAL, $product->only(array_keys(self::FISCAL)));
        $this->get(route('catalog.products.show', $product))->assertOk()->assertSee('00123456')->assertSee('0012345')->assertSee('Código 0');
        $response = $this->get(route('catalog.products.edit', $product))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//select[@name="default_origin_code"]/option[@value="0"][@selected]')->length);
    }

    public function test_web_create_without_fiscal_fields_or_with_empty_fields_succeeds(): void
    {
        foreach ([[], array_fill_keys(array_keys(self::FISCAL), '')] as $fiscal) {
            $data = $this->data($fiscal);
            $this->post(route('catalog.products.store'), $data)->assertSessionHasNoErrors();
            $product = Product::withoutGlobalScopes()->where('sku', $data['sku'])->firstOrFail();
            $this->assertSame(array_fill_keys(array_keys(self::FISCAL), null), $product->only(array_keys(self::FISCAL)));
        }
    }

    public function test_web_update_changes_preserves_and_clears_only_explicit_fields(): void
    {
        $product = $this->product(self::FISCAL);
        $data = $this->data(['sku' => $product->sku]);
        $this->put(route('catalog.products.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame(self::FISCAL, $product->fresh()->only(array_keys(self::FISCAL)));
        $this->put(route('catalog.products.update', $product), [...$data, 'ncm_code' => '12345678', 'cest_code' => '1234567', 'default_origin_code' => '8'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['ncm_code' => '12345678', 'cest_code' => '1234567', 'default_origin_code' => '8'], $product->fresh()->only(array_keys(self::FISCAL)));
        foreach (self::FISCAL as $field => $value) {
            $product->refresh()->update(self::FISCAL);
            $this->put(route('catalog.products.update', $product), [...$data, $field => null])->assertSessionHasNoErrors();
            $expected = self::FISCAL;
            $expected[$field] = null;
            $this->assertSame($expected, $product->fresh()->only(array_keys(self::FISCAL)));
        }
    }

    public function test_invalid_web_create_and_update_show_business_errors_and_preserve_old_input(): void
    {
        $invalid = ['ncm_code' => '12.34.56.78', 'cest_code' => 'ABC4567', 'default_origin_code' => '9'];
        $this->from(route('catalog.products.create'))->post(route('catalog.products.store'), $this->data($invalid))
            ->assertSessionHasErrors([
                'ncm_code' => 'Informe o NCM com exatamente 8 dígitos, sem pontuação.',
                'cest_code' => 'Informe o CEST com exatamente 7 dígitos, sem pontuação.',
                'default_origin_code' => 'Selecione um código de origem padrão disponível.',
            ])->assertSessionHasInput('ncm_code', '12.34.56.78');
        $response = $this->get(route('catalog.products.create'))->assertOk()
            ->assertSee('value="12.34.56.78"', false)
            ->assertDontSee('regex')->assertDontSee('SQLSTATE');
        $product = $this->product(self::FISCAL);
        $this->put(route('catalog.products.update', $product), $this->data(['sku' => $product->sku, ...$invalid]))->assertSessionHasErrors(array_keys(self::FISCAL));
        $this->assertSame(self::FISCAL, $product->fresh()->only(array_keys(self::FISCAL)));
    }

    public function test_form_submission_does_not_silently_strip_spaces_from_codes(): void
    {
        foreach (['ncm_code' => ' 12345678 ', 'cest_code' => ' 1234567 ', 'default_origin_code' => ' 0 '] as $field => $value) {
            $this->post(route('catalog.products.store'), $this->data([$field => $value]))->assertSessionHasErrors($field);
        }
    }

    public function test_web_cannot_edit_foreign_product_and_existing_permission_remains_required(): void
    {
        $other = Tenant::factory()->active()->create();
        $company = Company::factory()->create(['tenant_id' => $other->id]);
        $foreign = Product::factory()->create(['tenant_id' => $other->id, 'company_id' => $company->id, ...self::FISCAL]);
        $this->get(route('catalog.products.show', $foreign))->assertNotFound();
        $this->put(route('catalog.products.update', $foreign), $this->data(['sku' => $foreign->sku, 'ncm_code' => null]))->assertNotFound();
        $this->assertSame(self::FISCAL, $foreign->fresh()->only(array_keys(self::FISCAL)));
        $viewer = User::factory()->forTenant($this->tenant)->create();
        $this->actingAs($viewer)->post(route('catalog.products.store'), $this->data(self::FISCAL))->assertForbidden();
    }
}
