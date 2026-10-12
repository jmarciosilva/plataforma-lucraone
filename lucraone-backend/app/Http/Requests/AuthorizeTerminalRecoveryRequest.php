<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AuthorizeTerminalRecoveryRequest extends FormRequest
{
    private const MOTIVOS = ['expired', 'lost', 'compromised', 'keyring-unavailable', 'rotation-failed'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'in:'.implode(',', self::MOTIVOS)]];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (array_diff(array_keys($this->all()), array_keys($this->rules())) !== []) {
                $validator->errors()->add('request', 'Dados da requisição inválidos.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Selecione o motivo da recuperação.',
            'reason.string' => 'Motivo inválido.',
            'reason.in' => 'Selecione um motivo permitido.',
        ];
    }
}
