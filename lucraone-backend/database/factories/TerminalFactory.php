<?php

namespace Database\Factories;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

class TerminalFactory extends Factory
{
    protected $model = Terminal::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()->findOrFail($attributes['branch_id'])->company_id,
            'tenant_id' => fn (array $attributes) => Branch::withoutGlobalScopes()->findOrFail($attributes['branch_id'])->tenant_id,
            'name' => 'Caixa '.$this->faker->numerify('###'),
            'installation_id' => null,
            'status' => Terminal::STATUS_PENDING,
        ];
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branch->id,
            'company_id' => $branch->company_id,
            'tenant_id' => $branch->tenant_id,
        ]);
    }
}
