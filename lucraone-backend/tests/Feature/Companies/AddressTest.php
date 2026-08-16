<?php

namespace Tests\Feature\Companies;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Address;
use App\Modules\Companies\Domain\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Tenancy\TenancyTestCase;

class AddressTest extends TenancyTestCase
{
    use RefreshDatabase;

    /**
     * Teste 01: Criar endereço para uma empresa.
     */
    public function test_create_address_for_company(): void
    {
        $company = Company::factory()->create();

        $address = Address::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $company->tenant_id,
            'addressable_type' => Company::class,
            'addressable_id' => $company->id,
            'street' => 'Rua das Flores',
            'number' => '123',
            'district' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postal_code' => '01310-100',
            'country' => 'BR',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'addressable_type' => Company::class,
            'addressable_id' => $company->id,
        ]);
    }

    /**
     * Teste 02: Empresa tem muitos endereços.
     */
    public function test_company_has_many_addresses(): void
    {
        $company = Company::factory()->create();

        $address1 = Address::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $company->tenant_id,
            'addressable_type' => Company::class,
            'addressable_id' => $company->id,
            'street' => 'Rua A',
            'number' => '123',
            'district' => 'Centro',
            'city' => 'SP',
            'state' => 'SP',
            'postal_code' => '01310-100',
            'is_primary' => true,
        ]);

        $address2 = Address::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $company->tenant_id,
            'addressable_type' => Company::class,
            'addressable_id' => $company->id,
            'street' => 'Rua B',
            'number' => '456',
            'district' => 'Vila',
            'city' => 'RJ',
            'state' => 'RJ',
            'postal_code' => '20000-000',
            'is_primary' => false,
        ]);

        $this->assertCount(2, $company->addresses);
    }

    /**
     * Teste 03: Obter endereço primário de uma empresa.
     */
    public function test_get_primary_address_from_company(): void
    {
        $company = Company::factory()->create();

        Address::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $company->tenant_id,
            'addressable_type' => Company::class,
            'addressable_id' => $company->id,
            'street' => 'Rua Principal',
            'number' => '100',
            'district' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postal_code' => '01310-100',
            'is_primary' => true,
        ]);

        $primary = $company->primaryAddress;

        $this->assertNotNull($primary);
        $this->assertTrue($primary->is_primary);
        $this->assertEquals('Rua Principal', $primary->street);
    }

    /**
     * Teste 04: Filial tem endereços (polymorphic).
     */
    public function test_branch_has_addresses(): void
    {
        $branch = Branch::factory()->create();

        $address = Address::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $branch->tenant_id,
            'addressable_type' => Branch::class,
            'addressable_id' => $branch->id,
            'street' => 'Rua da Filial',
            'number' => '999',
            'district' => 'Loja',
            'city' => 'Campinas',
            'state' => 'SP',
            'postal_code' => '13000-000',
            'is_primary' => true,
        ]);

        $this->assertCount(1, $branch->addresses);
        $this->assertEquals(Branch::class, $address->addressable_type);
    }

    /**
     * Teste 05: Formatar endereço para exibição.
     */
    public function test_format_address_for_display(): void
    {
        $company = Company::factory()->create();

        $address = Address::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $company->tenant_id,
            'addressable_type' => Company::class,
            'addressable_id' => $company->id,
            'street' => 'Rua das Flores',
            'number' => '123',
            'complement' => 'Apto 45',
            'district' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postal_code' => '01310-100',
        ]);

        $formatted = $address->formatted();

        $this->assertStringContainsString('Rua das Flores', $formatted);
        $this->assertStringContainsString('123', $formatted);
        $this->assertStringContainsString('Apto 45', $formatted);
        $this->assertStringContainsString('01310-100', $formatted);
    }

    /**
     * Teste 06: Endereço respeita isolamento de tenant.
     */
    public function test_address_respects_tenant_isolation(): void
    {
        $companyA = Company::factory()->forCurrentTenant($this->tenantA->id)->create();
        $addressA = Address::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantA->id,
            'addressable_type' => Company::class,
            'addressable_id' => $companyA->id,
            'street' => 'Rua A',
            'number' => '100',
            'district' => 'Centro',
            'city' => 'SP',
            'state' => 'SP',
            'postal_code' => '01310-100',
        ]);

        $companyB = Company::factory()->forCurrentTenant($this->tenantB->id)->create();
        Address::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenantB->id,
            'addressable_type' => Company::class,
            'addressable_id' => $companyB->id,
            'street' => 'Rua B',
            'number' => '200',
            'district' => 'Vila',
            'city' => 'RJ',
            'state' => 'RJ',
            'postal_code' => '20000-000',
        ]);

        // Definir contexto de tenant A
        $this->tenantContext->set($this->tenantA->id);

        // Deve ver apenas endereço A
        $addresses = Address::all();
        $this->assertCount(1, $addresses);
    }
}
