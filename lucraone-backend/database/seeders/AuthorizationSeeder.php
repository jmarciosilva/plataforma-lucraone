<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Tenancy\Domain\Models\Tenant;

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
            $permissions[$name] = Permission::factory()
                ->forTenant($tenant->id)
                ->create(['name' => $name]);
        }

        return $permissions;
    }

    private function createRoles(Tenant $tenant, array $permissions): void
    {
        $admin = Role::factory()
            ->admin()
            ->forTenant($tenant->id)
            ->create();

        $manager = Role::factory()
            ->manager()
            ->forTenant($tenant->id)
            ->create();

        $user = Role::factory()
            ->user()
            ->forTenant($tenant->id)
            ->create();

        $viewer = Role::factory()
            ->viewer()
            ->forTenant($tenant->id)
            ->create();

        $adminPermissions = [
            'create-role', 'update-role', 'delete-role', 'view-roles',
            'create-permission', 'update-permission', 'delete-permission', 'view-permissions',
            'manage-users', 'view-users',
            'manage-branches', 'view-branches', 'view-all-branches',
        ];

        foreach ($adminPermissions as $permName) {
            if (isset($permissions[$permName])) {
                $admin->grantPermission($permissions[$permName]);
            }
        }

        $managerPermissions = [
            'manage-users', 'view-users',
            'manage-assigned-branches', 'view-branches',
        ];

        foreach ($managerPermissions as $permName) {
            if (isset($permissions[$permName])) {
                $manager->grantPermission($permissions[$permName]);
            }
        }

        $userPermissions = [
            'view-users',
            'view-assigned-branches',
        ];

        foreach ($userPermissions as $permName) {
            if (isset($permissions[$permName])) {
                $user->grantPermission($permissions[$permName]);
            }
        }

        $viewerPermissions = [
            'view-branches',
        ];

        foreach ($viewerPermissions as $permName) {
            if (isset($permissions[$permName])) {
                $viewer->grantPermission($permissions[$permName]);
            }
        }
    }
}
