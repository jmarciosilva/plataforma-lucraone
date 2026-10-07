<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    /**
     * Tentativas antes do bloqueio temporário.
     *
     * Mesmos números do login web (`App\Http\Requests\Auth\LoginRequest`): o
     * freio não deveria ser mais frouxo só porque a porta é a API.
     */
    private const TENTATIVAS = 5;

    /**
     * Segundos de bloqueio após estourar as tentativas.
     */
    private const BLOQUEIO = 60;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email é obrigatório',
            'email.email' => 'Email deve ser um endereço válido',
            'password.required' => 'Senha é obrigatória',
            'password.min' => 'Senha deve ter pelo menos 6 caracteres',
        ];
    }

    /**
     * Recusa a tentativa se a conta já estourou o limite.
     *
     * Vale também para a senha correta: um freio que liberasse o acerto
     * seguinte não freia nada — bastaria ao atacante continuar tentando.
     *
     * @throws ThrottleRequestsException
     */
    public function garantirQueNaoEstaBloqueado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->chaveDeBloqueio(), self::TENTATIVAS)) {
            return;
        }

        Event::dispatch(new Lockout($this));

        $segundos = RateLimiter::availableIn($this->chaveDeBloqueio());

        // ThrottleRequestsException em vez de ValidationException: o status
        // correto aqui é 429, e o Laravel já devolve JSON com Retry-After.
        throw new ThrottleRequestsException(
            "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            null,
            ['Retry-After' => $segundos, 'X-RateLimit-Reset' => now()->addSeconds($segundos)->getTimestamp()]
        );
    }

    public function registrarTentativaFalha(): void
    {
        RateLimiter::hit($this->chaveDeBloqueio(), self::BLOQUEIO);
    }

    public function limparTentativas(): void
    {
        RateLimiter::clear($this->chaveDeBloqueio());
    }

    /**
     * Freio por e-mail + IP: trava o ataque a uma conta específica sem
     * derrubar quem compartilha a mesma saída de rede. A senha nunca entra na
     * chave.
     */
    private function chaveDeBloqueio(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('email')).'|'.$this->ip()
        );
    }
}
