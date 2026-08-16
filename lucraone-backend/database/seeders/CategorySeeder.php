<?php

namespace Database\Seeders;

use App\Modules\Products\Domain\Models\Category;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::withoutGlobalScopes()->get() as $tenant) {
            $this->seedTenantCategories($tenant);
        }
    }

    private function seedTenantCategories(Tenant $tenant): void
    {
        $categories = [
            [
                'name' => 'alimentos',
                'slug' => 'alimentos',
                'description' => 'Itens alimentícios de giro no varejo.',
                'children' => [
                    ['name' => 'mercearia', 'slug' => 'mercearia', 'description' => 'Produtos secos, grãos e embalados.'],
                    ['name' => 'bebidas', 'slug' => 'bebidas', 'description' => 'Bebidas frias, quentes e não alcoólicas.'],
                    ['name' => 'hortifruti', 'slug' => 'hortifruti', 'description' => 'Frutas, verduras e legumes.'],
                ],
            ],
            [
                'name' => 'limpeza',
                'slug' => 'limpeza',
                'description' => 'Produtos para limpeza doméstica e profissional.',
                'children' => [
                    ['name' => 'lavanderia', 'slug' => 'lavanderia', 'description' => 'Sabões, amaciantes e cuidados com roupas.'],
                    ['name' => 'higiene da casa', 'slug' => 'higiene-da-casa', 'description' => 'Desinfetantes, detergentes e multiuso.'],
                ],
            ],
            [
                'name' => 'higiene pessoal',
                'slug' => 'higiene-pessoal',
                'description' => 'Itens de cuidado pessoal e banho.',
                'children' => [
                    ['name' => 'banho', 'slug' => 'banho', 'description' => 'Sabonetes, shampoos e condicionadores.'],
                    ['name' => 'cuidados bucais', 'slug' => 'cuidados-bucais', 'description' => 'Creme dental, escovas e enxaguantes.'],
                ],
            ],
            [
                'name' => 'utilidades',
                'slug' => 'utilidades',
                'description' => 'Produtos de apoio para casa e cozinha.',
                'children' => [
                    ['name' => 'cozinha', 'slug' => 'cozinha', 'description' => 'Descartáveis, panos e acessórios de cozinha.'],
                ],
            ],
        ];

        foreach ($categories as $category) {
            $parent = $this->firstOrCreateCategory($tenant, $category);

            foreach ($category['children'] as $child) {
                $this->firstOrCreateCategory($tenant, [
                    ...$child,
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }

    private function firstOrCreateCategory(Tenant $tenant, array $data): Category
    {
        return Category::withoutGlobalScopes()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'slug' => $data['slug'],
            ],
            [
                'id' => (string) Str::ulid(),
                'name' => $data['name'],
                'description' => $data['description'],
                'parent_id' => $data['parent_id'] ?? null,
            ]
        );
    }
}
