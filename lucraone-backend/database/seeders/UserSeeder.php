<?php

namespace Database\Seeders;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $admin = User::firstOrCreate(
                ['email' => "admin@{$tenant->slug}.local"],
                [
                    'id' => (string) Str::ulid(),
                    'name' => "Admin - {$tenant->name}",
                    'status' => User::STATUS_ACTIVE,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );

            $admin->joinTenant($tenant->id, TenantUser::STATUS_ACTIVE);

            // Completa até 3 pessoas com vínculo ativo neste estabelecimento
            $ativos = $tenant->memberships()
                ->where('status', TenantUser::STATUS_ACTIVE)
                ->count();

            if ($ativos < 3) {
                User::factory(3 - $ativos)
                    ->forTenant($tenant)
                    ->create();
            }

            // E 2 convidados que ainda não aceitaram
            $convidados = $tenant->memberships()
                ->where('status', TenantUser::STATUS_INVITED)
                ->count();

            if ($convidados < 2) {
                User::factory(2 - $convidados)
                    ->forTenant($tenant, TenantUser::STATUS_INVITED)
                    ->create();
            }
        }

        $this->seedContadorMultiEstabelecimento($tenants);
    }

    /**
     * Uma pessoa associada a mais de um estabelecimento — o caso que motivou
     * o modelo de identidade. Uma conta, uma senha, dois vínculos.
     */
    private function seedContadorMultiEstabelecimento($tenants): void
    {
        if ($tenants->count() < 2) {
            return;
        }

        $contador = User::firstOrCreate(
            ['email' => 'contador@escritorio.local'],
            [
                'id' => (string) Str::ulid(),
                'name' => 'Contador Externo',
                'status' => User::STATUS_ACTIVE,
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        foreach ($tenants->take(2) as $tenant) {
            $contador->joinTenant($tenant->id, TenantUser::STATUS_ACTIVE);
        }
    }
}
