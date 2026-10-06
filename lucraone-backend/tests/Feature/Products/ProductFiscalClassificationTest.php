<?php

namespace Tests\Feature\Products;

use App\Modules\Automation\Domain\Events\AutomationTriggered;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductFiscalClassificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $company;

    private const FISCAL = ['ncm_code' => '00123456', 'cest_code' => '0012345', 'default_origin_code' => '0'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->create(['tenant_id' => $this->tenant->id]);
        $user = User::factory()->forTenant($this->tenant)->create();
        $this->withToken($user->createToken('pm05')->plainTextToken)->withHeader('X-Tenant-ID', $this->tenant->id);
    }

    private function createProduct(array $data = [])
    {
        return $this->postJson('/api/v1/products', [
            'company_id' => $this->company->id, 'sku' => 'PM05-'.Str::random(8),
            'name' => 'Produto', 'status' => 'active', ...$data,
        ]);
    }

    private function product(array $data = []): Product
    {
        return Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, ...$data]);
    }

    public function test_store_and_get_preserve_string_codes_zero_and_base_unit(): void
    {
        foreach (['UN', 'KG'] as $unit) {
            $response = $this->createProduct(['unit' => $unit, ...self::FISCAL])->assertCreated();
            $id = $response->json('data.id');
            $get = $this->getJson("/api/v1/products/{$id}")->assertOk();
            foreach (self::FISCAL as $field => $value) {
                $response->assertJsonPath("data.{$field}", $value);
                $get->assertJsonPath("data.{$field}", $value);
                $this->assertSame($value, Product::withoutGlobalScopes()->findOrFail($id)->$field);
            }
            $get->assertJsonPath('data.unit', $unit);
        }
    }

    public function test_absent_null_and_empty_fields_are_optional_without_derived_cest(): void
    {
        foreach ([[], array_fill_keys(array_keys(self::FISCAL), null), array_fill_keys(array_keys(self::FISCAL), '')] as $data) {
            $response = $this->createProduct($data)->assertCreated();
            foreach (self::FISCAL as $field => $value) {
                $response->assertJsonPath("data.{$field}", null);
            }
        }
        $this->createProduct(['ncm_code' => '12345678'])->assertCreated()->assertJsonPath('data.cest_code', null);
        $legacy = $this->product();
        $response = $this->getJson("/api/v1/products/{$legacy->id}")->assertOk();
        foreach (self::FISCAL as $field => $value) {
            $response->assertJsonPath("data.{$field}", null);
        }
    }

    public function test_partial_update_preserves_codes_and_explicit_null_clears_only_selected_field(): void
    {
        $product = $this->product(self::FISCAL);
        $response = $this->putJson("/api/v1/products/{$product->id}", ['name' => 'Outro nome'])->assertOk();
        foreach (self::FISCAL as $field => $value) {
            $response->assertJsonPath("data.{$field}", $value);
        }
        foreach (self::FISCAL as $field => $value) {
            $product->refresh()->update(self::FISCAL);
            $response = $this->putJson("/api/v1/products/{$product->id}", [$field => null])->assertOk();
            foreach (self::FISCAL as $other => $expected) {
                $response->assertJsonPath("data.{$other}", $other === $field ? null : $expected);
            }
            $this->assertNull($product->fresh()->$field);
        }
        $this->putJson("/api/v1/products/{$product->id}", ['ncm_code' => '12345678', 'cest_code' => '1234567', 'default_origin_code' => '8'])
            ->assertOk()->assertJsonPath('data.ncm_code', '12345678')->assertJsonPath('data.cest_code', '1234567')->assertJsonPath('data.default_origin_code', '8');
    }

    public function test_all_approved_origin_codes_are_accepted_as_strings(): void
    {
        foreach (range(0, 8) as $code) {
            $this->createProduct(['default_origin_code' => (string) $code])->assertCreated()->assertJsonPath('data.default_origin_code', (string) $code);
        }
    }

    public static function invalidCodes(): array
    {
        $cases = [];
        foreach (['ncm_code' => ['1234567', '123456789', 'ABC45678', '1234567A', '12.34.56.78', '1e7', '12 34567', ' 12345678 ', '１２３４５６７８', 12345678, ['12345678']],
            'cest_code' => ['123456', '12345678', 'ABC4567', '12.345.67', '12 3456', ' 1234567 ', '1e6', '１２３４５６７', 1234567],
            'default_origin_code' => ['9', '00', 'A', ' 0 ', 0, false, ' ']] as $field => $values) {
            foreach ($values as $index => $value) {
                $cases["{$field}-{$index}"] = [$field, $value];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidCodes')]
    public function test_invalid_codes_are_rejected_without_partial_write(string $field, mixed $value): void
    {
        $before = Product::withoutGlobalScopes()->count();
        $this->createProduct([$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, Product::withoutGlobalScopes()->count());
        $product = $this->product(self::FISCAL);
        $this->putJson("/api/v1/products/{$product->id}", [$field => $value, 'name' => 'Não salvar'])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(self::FISCAL, $product->fresh()->only(array_keys(self::FISCAL)));
        $this->assertNotSame('NÃO SALVAR', $product->fresh()->name);
    }

    public function test_fiscal_fields_do_not_expand_the_product_created_automation_payload(): void
    {
        Event::fake([AutomationTriggered::class]);
        $product = $this->product(self::FISCAL);
        Event::assertDispatched(AutomationTriggered::class, fn ($event) => $event->tenantId === $this->tenant->id
            && $event->trigger === TriggerCatalog::PRODUTO_CRIADO
            && $event->payload === [
                'product_id' => $product->id,
                'nome' => $product->name,
                'sku' => $product->sku,
                'status' => $product->status,
                'company_id' => $product->company_id,
            ]);
    }

    public function test_codes_are_not_unique_and_other_tenant_remains_inaccessible(): void
    {
        $other = Tenant::factory()->active()->create();
        $company = Company::factory()->create(['tenant_id' => $other->id]);
        $foreign = Product::factory()->create(['tenant_id' => $other->id, 'company_id' => $company->id, ...self::FISCAL]);
        $this->createProduct(self::FISCAL)->assertCreated();
        $this->createProduct(self::FISCAL)->assertCreated();
        $this->getJson("/api/v1/products/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/products/{$foreign->id}", ['ncm_code' => null])->assertNotFound();
        $this->assertSame(self::FISCAL, $foreign->fresh()->only(array_keys(self::FISCAL)));
    }
}
