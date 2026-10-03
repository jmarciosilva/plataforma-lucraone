<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\Models\User;

/** Um único payload: os passos visuais não gravam no banco. */
class StoreOnboardingRequest extends StoreTenantRequest
{
    public function rules(): array
    {
        $existente = User::withTrashed()->where('email', $this->input('administrator_email'))->exists();
        $dados = [
            ...parent::rules(),
            'administrator_email' => ['required', 'email', 'max:255'],
            'administrator_name' => [$existente ? 'nullable' : 'required', 'string', 'max:255'],
            'password' => $existente ? ['exclude'] : ['required', 'string', 'min:8', 'confirmed'],
            'configure_company' => ['sometimes', 'boolean'],
            'keep_platform_access' => ['sometimes', 'boolean'],
        ];

        if ($this->boolean('configure_company')) {
            // Um estabelecimento novo não tem documentos duplicados. As demais
            // regras são exatamente as do cadastro atual de empresa.
            foreach (StoreCompanyRequest::rulesForTenant(null) as $campo => $regras) {
                $dados['company.'.$campo] = $regras;
            }
        }

        return $dados;
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome do estabelecimento', 'slug' => 'identificador no endereço',
            'status' => 'situação', 'plan' => 'plano', 'timezone' => 'fuso horário',
            'locale' => 'idioma', 'currency' => 'moeda',
            'administrator_name' => 'nome do administrador', 'administrator_email' => 'e-mail do administrador',
            'password' => 'senha inicial', 'company.legal_name' => 'razão social',
            'company.email' => 'e-mail da empresa', 'company.status' => 'situação da empresa',
        ];
    }
}
