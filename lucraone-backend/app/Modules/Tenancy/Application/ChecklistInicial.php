<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;

class ChecklistInicial
{
    public function itens(TenantContext $context): array
    {
        // Exige contexto resolvido, mantém todos os TenantScopes. A consulta de
        // equipe usa a relação explícita do tenant porque User é global.
        $tenant = $context->tenant();
        $estados = [
            ['estabelecimento', 'Estabelecimento criado', true, null, null],
            ['empresa', 'Cadastrar empresa', Company::exists(), 'companies.create', 'manage-companies'],
            ['categoria', 'Criar primeira categoria', Category::exists(), 'catalog.categories.create', 'manage-products'],
            ['produto', 'Cadastrar primeiro produto', Product::exists(), 'catalog.products.create', 'manage-products'],
            ['preco', 'Definir preço', Price::sale()->whereHas('product')->exists(), 'catalog.products.index', 'manage-products'],
            ['estoque', 'Informar estoque inicial', Inventory::whereHas('product')->exists(), 'inventory.index', 'manage-inventory'],
            ['equipe', 'Cadastrar equipe', $tenant->activeUsers()->where('users.status', User::STATUS_ACTIVE)
                ->where('users.is_platform_admin', false)->distinct()->count('users.id') > 1, 'users.create', 'manage-users'],
        ];

        return array_map(fn ($item) => [
            'id' => $item[0], 'rotulo' => $item[1], 'concluido' => $item[2],
            'rota' => $item[3], 'permissao' => $item[4],
        ], $estados);
    }
}
