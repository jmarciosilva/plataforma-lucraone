<?php

namespace Database\Seeders;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Clientes de exemplo para que a tela de vendas já abra com opções reais.
 *
 * Pedidos não são semeados de propósito: o fluxo de status mexe em estoque e
 * deve ser exercitado pela tela durante a validação manual.
 */
class SalesSeeder extends Seeder
{
    private const CLIENTES = [
        ['name' => 'Bar do Zé', 'email' => 'bar.doze@exemplo.test', 'phone' => '(11) 98888-1010'],
        ['name' => 'Padaria Aurora', 'email' => 'contato@aurora.test', 'phone' => '(11) 97777-2020'],
        ['name' => 'Restaurante Sol Nascente', 'email' => 'compras@solnascente.test', 'phone' => '(11) 96666-3030'],
        ['name' => 'Mercearia Vila Nova', 'email' => 'vilanova@exemplo.test', 'phone' => '(11) 95555-4040'],
    ];

    public function run(): void
    {
        foreach (Tenant::withoutGlobalScopes()->get() as $tenant) {
            $company = Company::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'ACTIVE')
                ->orderBy('created_at')
                ->first();

            if (! $company) {
                continue;
            }

            foreach (self::CLIENTES as $cliente) {
                Customer::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'company_id' => $company->id,
                        'name' => $cliente['name'],
                    ],
                    [
                        'email' => $cliente['email'],
                        'phone' => $cliente['phone'],
                        'status' => Customer::STATUS_ACTIVE,
                    ]
                );
            }
        }
    }
}
