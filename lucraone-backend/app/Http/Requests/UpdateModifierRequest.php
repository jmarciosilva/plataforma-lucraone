<?php

namespace App\Http\Requests;

use App\Modules\Menu\Domain\ModifierGroupType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'price_delta' => ['sometimes', 'numeric', 'decimal:0,2'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'available' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $group = $this->route('modifierGroup') ?? $this->route('grupo');
        if ($group && $group->type === ModifierGroupType::REMOVAL) {
            $this->merge(['price_delta' => '0.00']);
        }
    }
}
