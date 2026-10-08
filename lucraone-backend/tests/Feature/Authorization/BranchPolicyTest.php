<?php

namespace Tests\Feature\Authorization;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Authorization\Http\Policies\BranchPolicy;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class BranchPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private Branch $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->active()->create();
        $this->otherTenant = Tenant::factory()->active()->create();
        foreach ([$this->tenant, $this->otherTenant] as $tenant) {
            app(ProvisionarEstabelecimento::class)->provisionarMatriz($tenant);
        }
        $company = Company::factory()->forCurrentTenant($this->tenant->id)->create();
        $this->branch = Branch::factory()->forCompany($company)->create();
        $this->admin = $this->userWithRole('admin');
        app(TenantContext::class)->set($this->tenant->id);
    }

    public function test_real_branch_policy_is_registered(): void
    {
        $this->assertInstanceOf(BranchPolicy::class, Gate::getPolicyFor(Branch::class));
    }

    public function test_admin_can_read_and_manage_branch_in_current_tenant(): void
    {
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertTrue(Gate::forUser($this->admin)->allows($ability, Branch::class));
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($this->admin)->allows($ability, $this->branch));
        }
    }

    public function test_viewer_can_read_but_cannot_manage(): void
    {
        $viewer = $this->userWithRole('viewer');
        $this->assertTrue(Gate::forUser($viewer)->allows('viewAny', Branch::class));
        $this->assertTrue(Gate::forUser($viewer)->allows('view', $this->branch));
        $this->assertFalse(Gate::forUser($viewer)->allows('create', Branch::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('update', $this->branch));
        $this->assertFalse(Gate::forUser($viewer)->allows('delete', $this->branch));
    }

    public function test_no_permission_denies_all_actions(): void
    {
        $user = User::factory()->forTenant($this->tenant)->create();
        $policy = new BranchPolicy;
        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->create($user));
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse($policy->$ability($user, $this->branch));
        }
    }

    public function test_direct_branch_from_another_tenant_is_denied(): void
    {
        $company = Company::factory()->forCurrentTenant($this->otherTenant->id)->create();
        $otherBranch = Branch::factory()->forCompany($company)->create();
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse((new BranchPolicy)->$ability($this->admin, $otherBranch));
        }
    }

    public function test_inactive_account_is_denied_even_with_permissions(): void
    {
        $this->admin->update(['status' => User::STATUS_INACTIVE]);
        $policy = new BranchPolicy;
        $this->assertFalse($policy->viewAny($this->admin));
        $this->assertFalse($policy->create($this->admin));
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse($policy->$ability($this->admin, $this->branch));
        }
    }

    public function test_permissions_from_another_tenant_do_not_grant_access(): void
    {
        $this->admin->joinTenant($this->otherTenant->id);
        app(TenantContext::class)->set($this->otherTenant->id);
        $company = Company::factory()->forCurrentTenant($this->otherTenant->id)->create();
        $branch = Branch::factory()->forCompany($company)->create();
        $this->assertFalse(Gate::forUser($this->admin)->allows('viewAny', Branch::class));
        $this->assertFalse(Gate::forUser($this->admin)->allows('create', Branch::class));
        $this->assertFalse(Gate::forUser($this->admin)->allows('view', $branch));
        $this->assertFalse(Gate::forUser($this->admin)->allows('update', $branch));
    }

    public function test_entity_must_match_current_context_even_with_membership_in_both(): void
    {
        $this->admin->joinTenant($this->otherTenant->id);
        $this->admin->assignRole('admin', $this->otherTenant->id);
        app(TenantContext::class)->set($this->otherTenant->id);
        $this->assertFalse(Gate::forUser($this->admin)->allows('view', $this->branch));
        $this->assertFalse(Gate::forUser($this->admin)->allows('update', $this->branch));
    }

    public function test_missing_context_is_denied(): void
    {
        app(TenantContext::class)->clear();
        $policy = new BranchPolicy;
        $this->assertFalse($policy->viewAny($this->admin));
        $this->assertFalse($policy->create($this->admin));
        $this->assertFalse($policy->view($this->admin, $this->branch));
    }

    public function test_inactive_membership_is_denied(): void
    {
        $this->admin->joinTenant($this->tenant->id, 'INACTIVE');
        $policy = new BranchPolicy;
        $this->assertFalse($policy->viewAny($this->admin));
        $this->assertFalse($policy->create($this->admin));
        $this->assertFalse($policy->update($this->admin, $this->branch));
    }

    public function test_company_relationship_matches_branch_tenant(): void
    {
        $this->assertSame($this->tenant->id, $this->branch->company->tenant_id);
        $this->assertSame($this->branch->company_id, $this->branch->company->id);
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $this->branch));
        $otherCompany = Company::factory()->forCurrentTenant($this->otherTenant->id)->create();
        $this->branch->update(['company_id' => $otherCompany->id]);
        $this->assertNull($this->branch->company);
        $this->assertFalse(Gate::forUser($this->admin)->allows('view', $this->branch));
        $this->assertFalse(Gate::forUser($this->admin)->allows('update', $this->branch));
    }

    public function test_legacy_assigned_permissions_do_not_grant_access(): void
    {
        $role = Role::factory()->forTenant($this->tenant->id)->create();
        foreach (['view-assigned-branches', 'manage-assigned-branches'] as $name) {
            $role->grantPermission(Permission::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', $name)->firstOrFail());
        }
        $user = User::factory()->forTenant($this->tenant)->create();
        $user->assignRole($role, $this->tenant->id);
        $policy = new BranchPolicy;
        $this->assertFalse($policy->view($user, $this->branch));
        $this->assertFalse($policy->update($user, $this->branch));
    }

    public function test_company_management_does_not_grant_branch_management(): void
    {
        $manager = $this->userWithRole('manager');
        $this->assertTrue($manager->hasPermission('manage-companies', $this->tenant->id));
        $this->assertTrue(Gate::forUser($manager)->allows('view', $this->branch));
        $this->assertFalse(Gate::forUser($manager)->allows('create', Branch::class));
        $this->assertFalse(Gate::forUser($manager)->allows('update', $this->branch));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->forTenant($this->tenant)->create();
        $user->assignRole($role, $this->tenant->id);

        return $user;
    }
}
