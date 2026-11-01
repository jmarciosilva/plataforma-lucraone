<?php

namespace App\Http\Requests;

use App\Modules\Menu\Domain\ModifierGroupType;
use Illuminate\Foundation\Http\FormRequest;

class StoreModifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'price_delta' => ['sometimes', 'numeric', 'decimal:0,2'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'available' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Grupos do tipo REMOVAL: price_delta deve ser 0 (ou negativo sem custo)
        $group = $this->route('modifierGroup') ?? $this->route('grupo');
        if ($group && $group->type === ModifierGroupType::REMOVAL) {
            $this->merge(['price_delta' => '0.00']);
        }
    }
}
