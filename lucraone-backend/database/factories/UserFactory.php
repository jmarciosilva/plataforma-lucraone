<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Identity\Domain\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'status' => 'ACTIVE',
            'last_login_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
            ];
        });
    }

    public function invited(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'INVITED',
                'email_verified_at' => null,
            ];
        });
    }

    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'INACTIVE',
            ];
        });
    }

    public function suspended(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'SUSPENDED',
            ];
        });
    }

    public function forCurrentTenant(string $tenantId): static
    {
        return $this->state(function (array $attributes) use ($tenantId) {
            return [
                'tenant_id' => $tenantId,
            ];
        });
    }
}
