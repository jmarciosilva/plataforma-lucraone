<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Corpo do provisionamento DIRETO de terminal pelo painel.
 *
 * Difere do StoreTerminalRequest (pré-cadastro PENDING + pareamento): aqui o
 * administrador informa o `installation_id` que o PDV gerou, e o terminal nasce
 * ATIVO com credencial. A autoridade sobre unicidade, cota e vínculos segue no
 * TerminalProvisioningService; estas regras só recusam o que nem é requisição
 * bem formada.
 *
 * `installation_id` usa `uuid:4` com versão explícita, como no contrato de API:
 * a regra `uuid` sem parâmetro aceitaria qualquer versão e seria mais frouxa que
 * o `InstallationId::normalize()` do domínio, que exige v4.
 */
class ProvisionTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A guarda real é a policy (create Terminal) aplicada na controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'string', 'uuid:4'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'branch_id' => ['required', 'string', 'size:26'],
        ];
    }

    public function messages(): array
    {
        return [
            'installation_id.required' => 'O ID de instalação é obrigatório.',
            'installation_id.uuid' => 'O ID de instalação deve ser um UUID válido copiado do PDV.',
            'name.required' => 'O nome do terminal é obrigatório.',
            'name.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'branch_id.required' => 'A filial é obrigatória.',
            'branch_id.size' => 'Filial inválida.',
        ];
    }
}
