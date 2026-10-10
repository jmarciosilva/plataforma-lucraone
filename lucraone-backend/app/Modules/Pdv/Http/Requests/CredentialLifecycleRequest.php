<?php

namespace App\Modules\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CredentialLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'pdv.credential.prepare' => ['operation_id' => ['required', 'uuid:4'], 'delivery_secret' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']],
            'pdv.credential.confirm' => ['operation_id' => ['required', 'uuid:4']],
            'pdv.credential.recover' => ['recovery_code' => ['required', 'string', 'size:19'], 'installation_id' => ['required', 'uuid:4'], 'delivery_secret' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']],
            'pdv.credential.authorize' => ['reason' => ['required', 'in:expired,lost,compromised,keyring-unavailable,rotation-failed']],
            default => throw new \LogicException('Unknown credential operation.'),
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (array_diff(array_keys($this->all()), array_keys($this->rules())) !== []
                || ($this->route()->getName() === 'pdv.credential.recover' && $this->hasHeader('X-Tenant-ID'))) {
                $validator->errors()->add('request', 'Dados da requisição inválidos.');
            }
        });
    }

    public function messages(): array
    {
        // Never echo identifiers, proof, code or supplied values.
        return ['*.required' => 'Campo obrigatório.', '*.uuid' => 'Identificador inválido.',
            '*.string' => 'Campo inválido.', '*.size' => 'Campo inválido.', '*.regex' => 'Campo inválido.', '*.in' => 'Campo inválido.'];
    }
}
