<?php

namespace Database\Factories;

use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        $slug = Str::slug($name) . '-' . Str::random(4);

        return [
            'id' => (string) Str::ulid(),
            'name' => $name,
            'slug' => $slug,
            'status' => $this->faker->randomElement(['TRIAL', 'ACTIVE']),
            'plan' => 'free',
            'timezone' => 'America/Sao_Paulo',
            'locale' => 'pt-BR',
            'currency' => 'BRL',
            'active' => true,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'TRIAL',
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ACTIVE',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'SUSPENDED',
            'active' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
