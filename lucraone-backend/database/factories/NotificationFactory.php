<?php

namespace Database\Factories;

use App\Modules\Automation\Domain\Models\Notification;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'title' => $this->faker->sentence(3),
            'message' => $this->faker->sentence(),
            'level' => Notification::NIVEL_INFO,
            'read_at' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (array $attributes) => ['tenant_id' => $tenantId]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => now()]);
    }

    public function level(string $level): static
    {
        return $this->state(fn (array $attributes) => ['level' => $level]);
    }
}
