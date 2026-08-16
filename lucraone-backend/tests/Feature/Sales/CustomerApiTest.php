<?php

namespace Tests\Feature\Sales;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2.3 — Sales & Orders (clientes via API)
 */
class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $outroTenant;

    private Company $company;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->outroTenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
        $this->user = User::factory()->forTenant($this->tenant)->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_customer_can_be_created(): void
    {
        $this->requisicao()
            ->postJson('/api/v1/customers', [
                'company_id' => $this->company->id,
                'name' => 'Padaria do Bairro',
                'email' => 'contato@padaria.test',
                'document' => '12345678000199',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Padaria do Bairro')
            ->assertJsonPath('data.status', Customer::STATUS_ACTIVE);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Padaria do Bairro',
        ]);
    }

    public function test_customer_requires_name_and_company(): void
    {
        $this->requisicao()
            ->postJson('/api/v1/customers', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id', 'name']);
    }

    public function test_company_from_another_tenant_is_rejected(): void
    {
        $outraCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();

        $this->requisicao()
            ->postJson('/api/v1/customers', [
                'company_id' => $outraCompany->id,
                'name' => 'Cliente Invasor',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_customer_search_and_isolation(): void
    {
        $meu = Customer::factory()->forCompany($this->company)->create(['name' => 'Mercado Central']);

        $outraCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();
        Customer::factory()->forCompany($outraCompany)->create(['name' => 'Mercado de Outro Tenant']);

        $ids = collect(
            $this->requisicao()->getJson('/api/v1/customers?search=Mercado')->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($meu->id));
        $this->assertCount(1, $ids);
    }

    public function test_customers_require_authentication(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }

    private function requisicao()
    {
        return $this->withToken($this->token)->withHeader('X-Tenant-ID', $this->tenant->id);
    }
}
