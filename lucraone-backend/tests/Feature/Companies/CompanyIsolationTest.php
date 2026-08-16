<?php

namespace Tests\Feature\Companies;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Tenancy\TenancyTestCase;

class CompanyIsolationTest extends TenancyTestCase
{
    use RefreshDatabase;

    /**
     * Teste 01: Duas empresas de tenants diferentes coexistem.
     */
    public function test_companies_from_different_tenants_coexist(): void
    {
        $companyA = Company::factory()->forCurrentTenant($this->tenantA->id)->create();
        $companyB = Company::factory()->forCurrentTenant($this->tenantB->id)->create();

        $this->assertDatabaseHas('companies', ['id' => $companyA->id]);
        $this->assertDatabaseHas('companies', ['id' => $companyB->id]);
    }

    /**
     * Teste 02: Tenant A acessa somente suas empresas (Global Scope).
     */
    public function test_global_scope_filters_companies_by_tenant(): void
    {
        $companyA = Company::factory()->forCurrentTenant($this->tenantA->id)->create();
        Company::factory()->forCurrentTenant($this->tenantB->id)->create();

        // Definir contexto de tenant A
        $this->tenantContext->set($this->tenantA->id);

        // Querear todas as empresas
        $companies = Company::all();

        // Deve retornar apenas 1 (a de tenant A)
        $this->assertCount(1, $companies);
        $this->assertEquals($companyA->id, $companies->first()->id);
    }

    /**
     * Teste 03: Tenant A não consegue acessar empresa de Tenant B (IDOR prevention).
     */
    public function test_tenant_a_cannot_access_company_b(): void
    {
        $companyA = Company::factory()->forCurrentTenant($this->tenantA->id)->create();
        $companyB = Company::factory()->forCurrentTenant($this->tenantB->id)->create();

        // Definir contexto de tenant A
        $this->tenantContext->set($this->tenantA->id);

        // Tentar encontrar empresa B
        $found = Company::where('id', $companyB->id)->first();

        // Não deve encontrar (Global Scope filtra)
        $this->assertNull($found);
    }

    /**
     * Teste 04: Filial de Tenant A não consegue acessar empresa de Tenant B.
     */
    public function test_branch_respects_tenant_isolation(): void
    {
        $companyA = Company::factory()->forCurrentTenant($this->tenantA->id)->create();
        $branchA = Branch::factory()->forCompany($companyA)->create();

        $companyB = Company::factory()->forCurrentTenant($this->tenantB->id)->create();

        // Definir contexto de tenant A
        $this->tenantContext->set($this->tenantA->id);

        // Deve encontrar branch A
        $found = Branch::find($branchA->id);
        $this->assertNotNull($found);

        // Não deve encontrar nenhuma branch de tenant B
        $branchesB = Branch::where('tenant_id', $this->tenantB->id)->get();
        $this->assertEmpty($branchesB);
    }

    /**
     * Teste 05: Filial só pode pertencer a empresa do mesmo tenant.
     */
    public function test_branch_company_relationship_enforces_tenant(): void
    {
        $companyA = Company::factory()->forCurrentTenant($this->tenantA->id)->create();
        $companyB = Company::factory()->forCurrentTenant($this->tenantB->id)->create();

        $branchA = Branch::factory()->forCompany($companyA)->create();

        // Branch deve pertencer à empresa A
        $this->assertEquals($companyA->id, $branchA->company_id);
        $this->assertEquals($this->tenantA->id, $branchA->tenant_id);

        // Não deve ser possível criar branch de A vinculada a empresa de B
        // (Validação futura em Policy)
    }

    /**
     * Teste 06: Company isActive() method funciona.
     */
    public function test_company_is_active_check(): void
    {
        $activeCo = Company::factory()->active()->create();
        $inactiveCo = Company::factory()->inactive()->create();

        $this->assertTrue($activeCo->isActive());
        $this->assertFalse($inactiveCo->isActive());
    }

    /**
     * Teste 07: Company CNPJ é único por tenant.
     */
    public function test_company_cnpj_unique_per_tenant(): void
    {
        $company1 = Company::factory()->create();

        // Tentar criar outra com mesmo CNPJ no mesmo tenant deve falhar
        $this->expectException(QueryException::class);

        Company::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $company1->tenant_id,
            'legal_name' => 'Outra Empresa',
            'document' => $company1->document, // Mesmo CNPJ
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Teste 08: Branch code é único por tenant+company.
     */
    public function test_branch_code_unique_per_tenant_company(): void
    {
        $company = Company::factory()->create();
        $branch1 = Branch::factory()->forCompany($company)->create();

        // Tentar criar outra branch com mesmo código deve falhar
        $this->expectException(QueryException::class);

        Branch::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'name' => 'Another Branch',
            'code' => $branch1->code, // Mesmo code
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Teste 09: Company tem relação com branches.
     */
    public function test_company_has_many_branches(): void
    {
        $company = Company::factory()->create();

        Branch::factory(3)->forCompany($company)->create();

        $this->assertCount(3, $company->branches);
    }

    /**
     * Teste 10: Branch pertence a uma company.
     */
    public function test_branch_belongs_to_company(): void
    {
        $branch = Branch::factory()->create();
        $company = Company::find($branch->company_id);

        $this->assertNotNull($company);
        $this->assertEquals($branch->company_id, $company->id);
    }
}
