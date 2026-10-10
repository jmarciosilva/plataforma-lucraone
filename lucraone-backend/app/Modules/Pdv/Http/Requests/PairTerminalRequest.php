<?php

namespace App\Modules\Pdv\Http\Requests;

use App\Modules\Terminals\Domain\PairingCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação ESTRUTURAL do pairing. Nada além disso.
 *
 * A autoridade sobre o pareamento continua sendo o domínio: selector, hash do
 * segredo, prazo, attempts, uso único, estrutura operacional e vínculo da
 * instalação são decididos por `ConsumeTerminalPairingCode`, e seguem sendo
 * decididos lá mesmo que esta validação passe. O papel daqui é só recusar o que
 * nem chega a ser uma requisição bem formada, para não gastar transação e lock
 * com lixo.
 *
 * `installation_id` usa `uuid:4`, com versão explícita: a regra `uuid` sem
 * parâmetro aceita qualquer versão e seria mais frouxa que o
 * `InstallationId::normalize()` do domínio, que exige v4. Validação de HTTP
 * nunca pode ser mais permissiva que a do domínio — isso transformaria o 422
 * honesto num 500 ou, pior, numa divergência silenciosa entre as duas camadas.
 */
class PairTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint público: antes do pairing o Terminal não tem credencial e
        // não há sujeito a autorizar. Quem protege é prazo, uso único,
        // attempts persistentes e rate limiting.
        return true;
    }

    public function rules(): array
    {
        return [
            // O tamanho exato do código é conhecido e fixo (selector + '.' +
            // segredo). Um max generoso aqui só deixaria entrar payload grande
            // para ser rejeitado mais adiante.
            'pairing_code' => ['required', 'string', 'size:'.PairingCode::LENGTH],
            'installation_id' => ['required', 'string', 'uuid:4'],
        ];
    }

    public function messages(): array
    {
        return [
            // Mensagens sem eco do valor enviado: o código é segredo de curta
            // duração e não pode aparecer em corpo de erro, log ou tela.
            'pairing_code.required' => 'Código de pareamento é obrigatório',
            'pairing_code.string' => 'Código de pareamento inválido',
            'pairing_code.size' => 'Código de pareamento inválido',
            'installation_id.required' => 'Identificador da instalação é obrigatório',
            'installation_id.string' => 'Identificador da instalação inválido',
            'installation_id.uuid' => 'Identificador da instalação inválido',
        ];
    }
}
