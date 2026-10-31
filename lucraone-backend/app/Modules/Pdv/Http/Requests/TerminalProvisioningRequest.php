<?php

namespace App\Modules\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Corpo do provisionamento direto de Terminal.
 *
 * Validação ESTRUTURAL apenas. A autoridade sobre o provisionamento continua no
 * serviço: unicidade da instalação, pertencimento da filial ao estabelecimento,
 * cota e coerência de vínculos são decididos por `TerminalProvisioningService`,
 * e seguem sendo decididos lá mesmo que esta validação passe. O papel daqui é
 * recusar o que nem chega a ser requisição bem formada, para não gastar
 * transação e lock com lixo.
 *
 * `installation_id` usa `uuid:4`, com versão explícita, pelo mesmo motivo do
 * PairTerminalRequest: a regra `uuid` sem parâmetro aceitaria qualquer versão e
 * seria mais frouxa que o `InstallationId::normalize()` do domínio, que exige
 * v4. Validação de HTTP nunca pode ser mais permissiva que a do domínio.
 *
 * `branch_id` é o ULID da filial (char(26)), não um inteiro: filiais usam ULID
 * em todo o projeto. Daí `size:26`, o mesmo do `user_id` da concessão de
 * operador.
 */
final class TerminalProvisioningRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Porta humana e contexto de estabelecimento são do middleware
        // HumanAdministrationContext; aqui não há o que autorizar.
        return true;
    }

    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'string', 'uuid:4'],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'string', 'size:26'],
        ];
    }

    public function withValidator($validator): void
    {
        // Contrato fechado: um campo a mais no corpo é erro, não é ignorado em
        // silêncio. Mesmo padrão da concessão de operador.
        $validator->after(function ($validator) {
            if (array_diff(array_keys($this->all()), array_keys($this->rules())) !== []) {
                $validator->errors()->add('request', 'Dados da requisição inválidos.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'installation_id.required' => 'Identificador da instalação é obrigatório.',
            'installation_id.string' => 'Identificador da instalação inválido.',
            'installation_id.uuid' => 'Identificador da instalação inválido.',
            'name.required' => 'Nome do terminal é obrigatório.',
            'name.string' => 'Nome do terminal inválido.',
            'name.max' => 'Nome do terminal inválido.',
            'branch_id.required' => 'Filial é obrigatória.',
            'branch_id.string' => 'Filial inválida.',
            'branch_id.size' => 'Filial inválida.',
        ];
    }
}
