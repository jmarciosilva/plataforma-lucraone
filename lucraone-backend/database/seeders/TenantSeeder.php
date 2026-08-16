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
        Tenant::firstOrCreate(
            ['slug' => 'lucraone-dev'],
            [
                'name' => 'LUCRAONE Development',
                'plan' => 'enterprise',
                'status' => 'ACTIVE',
                'timezone' => 'America/Sao_Paulo',
                'locale' => 'pt-BR',
                'currency' => 'BRL',
                'active' => true,
            ]
        );

        // Tenant de teste
        Tenant::firstOrCreate(
            ['slug' => 'test-store'],
            [
                'name' => 'Test Store',
                'plan' => 'free',
                'status' => 'ACTIVE',
                'timezone' => 'America/Sao_Paulo',
                'locale' => 'pt-BR',
                'currency' => 'BRL',
                'active' => true,
            ]
        );

        // Exemplos adicionais (só cria se houver menos de 5 tenants)
        if (Tenant::count() < 5) {
            for ($i = 1; $i <= 3; $i++) {
                Tenant::factory()->active()->create();
            }
        }
    }
}
