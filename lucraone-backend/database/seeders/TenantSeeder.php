<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Tenancy\Domain\Models\Tenant;

class TenantSeeder extends Seeder
{
    /**
     * Seed initial tenants for testing.
     */
    public function run(): void
    {
        // Tenant de desenvolvimento para testes
        Tenant::factory()->active()->create([
            'name' => 'LUCRAONE Development',
            'slug' => 'lucraone-dev',
            'plan' => 'enterprise',
        ]);

        // Tenant de teste
        Tenant::factory()->trial()->create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'plan' => 'free',
        ]);

        // Exemplos adicionais
        for ($i = 1; $i <= 3; $i++) {
            Tenant::factory()->active()->create();
        }
    }
}
