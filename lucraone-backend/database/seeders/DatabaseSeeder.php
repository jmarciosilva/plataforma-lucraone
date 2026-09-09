<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /*
    | Sem WithoutModelEvents de propósito.
    |
    | Aquele trait desliga os eventos de model durante todo o seed — inclusive o
    | `creating` onde o HasUlid gera a chave. O resultado é o banco recusando a
    | linha por "Field 'id' doesn't have a default value", e cada seeder tendo
    | que repetir `'id' => (string) Str::ulid()` para contornar.
    |
    | Os três únicos hooks do projeto são inofensivos aqui: HasUlid::creating
    | (que é justamente o que precisamos), Category::deleting (o seed não apaga
    | nada) e Product::created, que enfileira a avaliação de automações — e num
    | banco recém-populado não há regra cadastrada para avaliar.
    */

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class,
            AuthorizationSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            InventorySeeder::class,
            SalesSeeder::class,
        ]);
    }
}
