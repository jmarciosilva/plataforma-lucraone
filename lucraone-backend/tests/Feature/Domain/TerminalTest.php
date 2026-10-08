<?php

namespace Tests\Feature\Domain;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TerminalTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
    }

    private function attributes(array $overrides = []): array
    {
        return array_replace([
            'tenant_id' => $this->branch->tenant_id,
            'company_id' => $this->branch->company_id,
            'branch_id' => $this->branch->id,
            'name' => 'Caixa 01',
        ], $overrides);
    }

    public function test_administrative_creation_defaults_to_pending_with_ulid_and_no_installation(): void
    {
        $terminal = Terminal::create($this->attributes());
        $this->assertTrue(Str::isUlid($terminal->id));
        $this->assertSame('PENDING', $terminal->status);
        $this->assertSame('PENDING', $terminal->fresh()->status);
        $this->assertNull($terminal->installation_id);
        $this->assertSame($this->branch->tenant_id, $terminal->tenant_id);
    }

    public function test_company_from_another_tenant_is_rejected(): void
    {
        $company = Company::factory()->create();
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes(['company_id' => $company->id]));
    }

    public function test_branch_from_another_tenant_is_rejected(): void
    {
        $branch = Branch::factory()->create();
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes(['branch_id' => $branch->id]));
    }

    public function test_branch_from_another_company_in_same_tenant_is_rejected(): void
    {
        $company = Company::factory()->forCurrentTenant($this->branch->tenant_id)->create();
        $branch = Branch::factory()->forCompany($company)->create();
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes(['branch_id' => $branch->id]));
    }

    public function test_update_cannot_break_assignment(): void
    {
        $terminal = Terminal::create($this->attributes());
        $other = Branch::factory()->create();
        try {
            $terminal->update(['branch_id' => $other->id]);
            $this->fail('An inconsistent update was persisted.');
        } catch (InvalidTerminalAssignment) {
            $this->assertSame($this->branch->id, $terminal->fresh()->branch_id);
        }
    }

    public function test_multiple_null_installations_are_allowed(): void
    {
        Terminal::create($this->attributes());
        Terminal::create($this->attributes());
        $this->assertSame(2, Terminal::whereNull('installation_id')->count());
    }

    public function test_installation_is_globally_unique_across_tenants(): void
    {
        $id = (string) Str::uuid();
        Terminal::create($this->attributes(['installation_id' => $id]));
        $other = Branch::factory()->create();
        $this->expectException(QueryException::class);
        Terminal::factory()->forBranch($other)->create(['installation_id' => $id]);
    }

    public function test_uuid_v4_installation_is_accepted_and_canonicalized(): void
    {
        $id = (string) Str::uuid();
        $terminal = Terminal::create($this->attributes(['installation_id' => strtoupper($id)]));
        $this->assertSame($id, $terminal->installation_id);
    }

    public function test_invalid_installation_is_rejected(): void
    {
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes(['installation_id' => 'not-a-uuid']));
    }

    public function test_uuid_from_another_version_is_rejected(): void
    {
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes(['installation_id' => '123e4567-e89b-12d3-a456-426614174000']));
    }

    public function test_missing_parent_is_rejected(): void
    {
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes(['branch_id' => (string) Str::ulid()]));
    }

    public function test_all_relationships_and_inverse_relationships_work(): void
    {
        $terminal = Terminal::create($this->attributes());
        $this->assertSame($terminal->tenant_id, $terminal->tenant->id);
        $this->assertSame($terminal->company_id, $terminal->company->id);
        $this->assertSame($terminal->branch_id, $terminal->branch->id);
        $this->assertTrue($terminal->tenant->terminals->contains($terminal));
        $this->assertTrue($terminal->company->terminals->contains($terminal));
        $this->assertTrue($terminal->branch->terminals->contains($terminal));
    }

    public function test_tenant_scope_hides_other_tenant_terminals(): void
    {
        $terminal = Terminal::create($this->attributes());
        $other = Terminal::factory()->create();
        app(TenantContext::class)->set($terminal->tenant_id);
        $this->assertNotNull(Terminal::find($terminal->id));
        $this->assertNull(Terminal::find($other->id));
    }

    public function test_relationships_can_be_eager_loaded(): void
    {
        $terminal = Terminal::create($this->attributes());
        $loaded = Terminal::with(['tenant', 'company', 'branch'])->findOrFail($terminal->id);
        $this->assertSame($loaded->tenant_id, $loaded->tenant->id);
        $this->assertSame($loaded->company_id, $loaded->company->id);
        $this->assertSame($loaded->branch_id, $loaded->branch->id);
    }

    public function test_uuid_case_cannot_bypass_installation_uniqueness(): void
    {
        $id = (string) Str::uuid();
        Terminal::create($this->attributes(['installation_id' => $id]));
        $this->expectException(QueryException::class);
        Terminal::create($this->attributes(['installation_id' => strtoupper($id)]));
    }

    public function test_factory_is_coherent_and_pending_by_default(): void
    {
        $terminal = Terminal::factory()->create();
        $this->assertSame($terminal->tenant_id, $terminal->company->tenant_id);
        $this->assertSame($terminal->tenant_id, $terminal->branch->tenant_id);
        $this->assertSame($terminal->company_id, $terminal->branch->company_id);
        $this->assertSame('PENDING', $terminal->status);
        $this->assertNull($terminal->installation_id);
    }

    public function test_unknown_status_is_rejected(): void
    {
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes(['status' => 'UNKNOWN']));
    }

    public function test_revoked_terminal_cannot_be_reactivated(): void
    {
        $terminal = Terminal::create($this->attributes(['status' => 'REVOKED']));
        $this->expectException(InvalidTerminalAssignment::class);
        $terminal->update(['status' => 'PENDING']);
    }

    public function test_invalid_installation_update_is_rejected(): void
    {
        $terminal = Terminal::create($this->attributes());
        $this->expectException(InvalidTerminalAssignment::class);
        $terminal->update(['installation_id' => 'invalid']);
    }

    public function test_inconsistent_existing_branch_cannot_be_assigned(): void
    {
        $company = Company::factory()->create();
        $this->branch->update(['company_id' => $company->id]);
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes());
    }

    public function test_active_status_without_installation_is_rejected(): void
    {
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes(['status' => 'ACTIVE']));
    }

    public function test_explicit_statuses_are_domain_states_without_machine_authentication(): void
    {
        foreach (['PENDING', 'ACTIVE', 'BLOCKED', 'REVOKED'] as $status) {
            $terminal = Terminal::create($this->attributes([
                'status' => $status,
                'installation_id' => $status === 'ACTIVE' ? (string) Str::uuid() : null,
            ]));
            $this->assertSame($status, $terminal->fresh()->status);
        }
    }

    public function test_branch_delete_is_restricted(): void
    {
        Terminal::create($this->attributes());
        $this->expectException(QueryException::class);
        $this->branch->delete();
    }

    public function test_company_delete_is_restricted_even_with_existing_branch_cascade(): void
    {
        Terminal::create($this->attributes());
        $this->expectException(QueryException::class);
        Company::findOrFail($this->branch->company_id)->delete();
    }

    public function test_physical_tenant_delete_is_restricted(): void
    {
        Terminal::create($this->attributes());
        $this->expectException(QueryException::class);
        Tenant::findOrFail($this->branch->tenant_id)->forceDelete();
    }

    public function test_blank_administrative_name_is_rejected(): void
    {
        $this->expectException(InvalidTerminalAssignment::class);
        Terminal::create($this->attributes(['name' => '  ']));
    }
}
