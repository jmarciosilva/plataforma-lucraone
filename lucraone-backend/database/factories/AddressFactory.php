<?php

namespace Database\Factories;

use App\Modules\Companies\Domain\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => null, // Deve ser definido pela factory do modelo associado
            'addressable_type' => null, // Deve ser definido explicitamente
            'addressable_id' => null, // Deve ser definido explicitamente
            'street' => $this->faker->streetName(),
            'number' => $this->faker->numerify('####'),
            'complement' => $this->faker->optional()->secondaryAddress(),
            'district' => $this->faker->citySuffix(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'postal_code' => $this->generateCEP(),
            'country' => 'BR',
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Gerar CEP válido para testes.
     */
    private function generateCEP(): string
    {
        return sprintf('%05d-%03d', random_int(10000, 99999), random_int(100, 999));
    }
}
