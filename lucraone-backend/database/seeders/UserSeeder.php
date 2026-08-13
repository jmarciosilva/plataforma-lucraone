<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            User::factory()
                ->forCurrentTenant($tenant->id)
                ->active()
                ->create([
                    'name' => "Admin - {$tenant->name}",
                    'email' => "admin@{$tenant->slug}.local",
                ]);

            User::factory(3)
                ->forCurrentTenant($tenant->id)
                ->active()
                ->create();

            User::factory(2)
                ->forCurrentTenant($tenant->id)
                ->invited()
                ->create();
        }
    }
}
