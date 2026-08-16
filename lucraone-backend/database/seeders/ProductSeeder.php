<?php

namespace Database\Seeders;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::withoutGlobalScopes()->get() as $tenant) {
            $company = $this->activeCompanyFor($tenant);
            $this->seedTenantProducts($tenant, $company);
        }
    }

    private function seedTenantProducts(Tenant $tenant, Company $company): void
    {
        $products = [
            [
                'sku' => 'ALM-ARZ-001',
                'name' => 'arroz tipo 1 5kg',
                'description' => 'Pacote de arroz tipo 1 para mercearia.',
                'categories' => ['alimentos', 'mercearia'],
                'cost' => 18.90,
                'sale' => 24.90,
                'suggested' => 26.90,
            ],
            [
                'sku' => 'ALM-FEJ-001',
                'name' => 'feijão carioca 1kg',
                'description' => 'Feijão carioca selecionado, pacote de 1kg.',
                'categories' => ['alimentos', 'mercearia'],
                'cost' => 5.90,
                'sale' => 8.49,
                'suggested' => 8.99,
            ],
            [
                'sku' => 'ALM-CAF-001',
                'name' => 'café tradicional 500g',
                'description' => 'Café torrado e moído para venda recorrente.',
                'categories' => ['alimentos', 'mercearia', 'bebidas'],
                'cost' => 12.40,
                'sale' => 17.90,
                'suggested' => 18.90,
            ],
            [
                'sku' => 'BEB-AGU-001',
                'name' => 'água mineral 500ml',
                'description' => 'Garrafa de água mineral sem gás.',
                'categories' => ['alimentos', 'bebidas'],
                'cost' => 1.10,
                'sale' => 2.49,
                'suggested' => 2.99,
            ],
            [
                'sku' => 'BEB-SUC-001',
                'name' => 'suco de uva 1l',
                'description' => 'Suco integral de uva em embalagem de 1 litro.',
                'categories' => ['alimentos', 'bebidas'],
                'cost' => 9.80,
                'sale' => 14.90,
                'suggested' => 15.90,
            ],
            [
                'sku' => 'HOR-BAN-001',
                'name' => 'banana prata kg',
                'description' => 'Produto de hortifruti vendido por quilo.',
                'categories' => ['alimentos', 'hortifruti'],
                'cost' => 3.50,
                'sale' => 5.99,
                'suggested' => 6.49,
            ],
            [
                'sku' => 'LIM-DET-001',
                'name' => 'detergente neutro 500ml',
                'description' => 'Detergente líquido neutro para cozinha.',
                'categories' => ['limpeza', 'higiene-da-casa', 'cozinha'],
                'cost' => 1.75,
                'sale' => 2.99,
                'suggested' => 3.49,
            ],
            [
                'sku' => 'LIM-DES-001',
                'name' => 'desinfetante lavanda 2l',
                'description' => 'Desinfetante perfumado para uso doméstico.',
                'categories' => ['limpeza', 'higiene-da-casa'],
                'cost' => 5.20,
                'sale' => 8.99,
                'suggested' => 9.49,
            ],
            [
                'sku' => 'LAV-SAB-001',
                'name' => 'sabão em pó 1kg',
                'description' => 'Sabão em pó para roupas, embalagem de 1kg.',
                'categories' => ['limpeza', 'lavanderia'],
                'cost' => 6.80,
                'sale' => 10.90,
                'suggested' => 11.90,
            ],
            [
                'sku' => 'HIG-SAB-001',
                'name' => 'sabonete hidratante 85g',
                'description' => 'Sabonete em barra para banho diário.',
                'categories' => ['higiene-pessoal', 'banho'],
                'cost' => 1.45,
                'sale' => 2.79,
                'suggested' => 2.99,
            ],
            [
                'sku' => 'HIG-CRE-001',
                'name' => 'creme dental 90g',
                'description' => 'Creme dental de uso familiar.',
                'categories' => ['higiene-pessoal', 'cuidados-bucais'],
                'cost' => 3.20,
                'sale' => 5.99,
                'suggested' => 6.49,
            ],
            [
                'sku' => 'UTI-PAP-001',
                'name' => 'papel toalha 2 rolos',
                'description' => 'Papel toalha para cozinha em pacote com 2 rolos.',
                'categories' => ['utilidades', 'cozinha'],
                'cost' => 4.60,
                'sale' => 7.99,
                'suggested' => 8.49,
            ],
        ];

        foreach ($products as $data) {
            $product = Product::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'sku' => $data['sku'],
                ],
                [
                    'company_id' => $company->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'status' => 'active',
                ]
            );

            $product->forceFill([
                'company_id' => $company->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'status' => 'active',
            ])->save();

            $product->categories()->syncWithoutDetaching(
                $this->categoryIds($tenant, $data['categories'])
            );

            $this->upsertPrice($tenant, $product, Price::TYPE_COST, $data['cost']);
            $this->upsertPrice($tenant, $product, Price::TYPE_SALE, $data['sale']);
            $this->upsertPrice($tenant, $product, Price::TYPE_SUGGESTED_RETAIL, $data['suggested']);
        }
    }

    private function activeCompanyFor(Tenant $tenant): Company
    {
        $company = Company::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'ACTIVE')
            ->orderBy('trade_name')
            ->first();

        if ($company) {
            return $company;
        }

        return Company::withoutGlobalScopes()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'document' => $this->documentFor($tenant),
            ],
            [
                'id' => (string) Str::ulid(),
                'legal_name' => "Empresa Demo {$tenant->name}",
                'trade_name' => 'Empresa Demo',
                'email' => "demo+{$tenant->slug}@lucraone.local",
                'phone' => '(11) 4000-0000',
                'status' => 'ACTIVE',
            ]
        );
    }

    private function categoryIds(Tenant $tenant, array $slugs): array
    {
        return Category::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->all();
    }

    private function upsertPrice(Tenant $tenant, Product $product, string $type, float $amount): void
    {
        Price::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'currency' => 'BRL',
                'type' => $type,
            ],
            ['amount' => $amount]
        );
    }

    private function documentFor(Tenant $tenant): string
    {
        $number = str_pad((string) (abs(crc32($tenant->id)) % 99999999), 8, '0', STR_PAD_LEFT);

        return "99.{$number[0]}{$number[1]}{$number[2]}.{$number[3]}{$number[4]}{$number[5]}/{$number[6]}{$number[7]}00-01";
    }
}
