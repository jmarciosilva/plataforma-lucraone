<?php

namespace Database\Seeders;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            $this->seedRolesAndPermissions($tenant);
        }
    }

    private function seedRolesAndPermissions(Tenant $tenant): void
    {
        $permissions = $this->createPermissions($tenant);
        $this->createRoles($tenant, $permissions);
    }

    private function createPermissions(Tenant $tenant): array
    {
        $permissionNames = [
            'create-role',
            'update-role',
            'delete-role',
            'view-roles',
            'create-permission',
            'update-permission',
            'delete-permission',
            'view-permissions',
            'manage-companies',
            'view-companies',
            'manage-users',
            'view-users',
            'manage-branches',
            'view-branches',
            'view-all-branches',
            'manage-assigned-branches',
            'view-assigned-branches',
        ];

        $permissions = [];
        foreach ($permissionNames as $name) {
            $permissions[$name] = Permission::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['id' => (string) Str::ulid(), 'description' => $name]
            );
        }

        return $permissions;
    }

    private function createRoles(Tenant $tenant, array $permissions): void
    {
        $admin = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'admin'],
            ['id' => (string) Str::ulid(), 'description' => 'Administrator role with full access']
        );

        $manager = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'manager'],
            ['id' => (string) Str::ulid(), 'description' => 'Manager role with limited administrative access']
        );

        $user = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'user'],
            ['id' => (string) Str::ulid(), 'description' => 'Regular user role']
        );

        $viewer = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'viewer'],
            ['id' => (string) Str::ulid(), 'description' => 'Viewer role with read-only access']
        );

        $adminPermissions = [
            'create-role', 'update-role', 'delete-role', 'view-roles',
            'create-permission', 'update-permission', 'delete-permission', 'view-permissions',
            'manage-companies', 'view-companies',
            'manage-users', 'view-users',
            'manage-branches', 'view-branches', 'view-all-branches',
        ];

        foreach ($adminPermissions as $permName) {
            if (isset($permissions[$permName])) {
                $admin->grantPermission($permissions[$permName]);
            }
        }

        $managerPermissions = [
            'manage-companies', 'view-companies',
            'manage-users', 'view-users',
            'manage-assigned-branches', 'view-branches',
        ];

        foreach ($managerPermissions as $permName) {
            if (isset($permissions[$permName])) {
                $manager->grantPermission($permissions[$permName]);
            }
        }

        $userPermissions = [
            'view-companies',
            'view-users',
            'view-assigned-branches',
        ];

        foreach ($userPermissions as $permName) {
            if (isset($permissions[$permName])) {
                $user->grantPermission($permissions[$permName]);
            }
        }

        $viewerPermissions = [
            'view-companies',
            'view-branches',
        ];

        foreach ($viewerPermissions as $permName) {
            if (isset($permissions[$permName])) {
                $viewer->grantPermission($permissions[$permName]);
            }
        }
    }
}
