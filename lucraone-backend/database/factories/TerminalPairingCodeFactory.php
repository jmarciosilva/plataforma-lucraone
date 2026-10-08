<?php

namespace Database\Factories;

use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use App\Modules\Terminals\Domain\PairingCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class TerminalPairingCodeFactory extends Factory
{
    protected $model = TerminalPairingCode::class;

    public function definition(): array
    {
        return [
            'terminal_id' => Terminal::factory(),
            'selector' => PairingCode::random(6),
            'code_hash' => hash('sha256', PairingCode::random(12)),
            'expires_at' => now()->addMinutes((int) config('pdv.pairing.ttl_minutes')),
            'attempts' => 0,
            'consumed_at' => null,
            'invalidated_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => ['consumed_at' => now()]);
    }

    public function invalidated(): static
    {
        return $this->state(fn () => ['invalidated_at' => now()]);
    }
}
