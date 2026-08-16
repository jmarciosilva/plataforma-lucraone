<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'street' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:32'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'string', 'max:16'],
            'country' => ['required', 'string', 'size:2'],
            'is_primary' => ['nullable', Rule::in(['1', '0', 1, 0, true, false])],
        ];
    }
}
