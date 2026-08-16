<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $adminEmail = "admin@{$tenant->slug}.local";

            // Create or find admin user
            User::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'email' => $adminEmail,
                ],
                [
                    'id' => (string) Str::ulid(),
                    'name' => "Admin - {$tenant->name}",
                    'status' => 'ACTIVE',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Create regular active users (only if less than 4 total per tenant)
            if (User::where('tenant_id', $tenant->id)->count() < 4) {
                User::factory(3)
                    ->forCurrentTenant($tenant->id)
                    ->active()
                    ->create();
            }

            // Create invited users (only if less than 6 total per tenant)
            if (User::where('tenant_id', $tenant->id)->count() < 6) {
                User::factory(2)
                    ->forCurrentTenant($tenant->id)
                    ->invited()
                    ->create();
            }
        }
    }
}
