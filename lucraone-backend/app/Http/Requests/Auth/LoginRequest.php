<?php

namespace App\Http\Requests\Auth;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Tentativas antes do bloqueio temporário.
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'lembrar' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'informe seu e-mail.',
            'email.email' => 'informe um e-mail válido.',
            'password.required' => 'informe sua senha.',
        ];
    }

    /**
     * Autentica e devolve a pessoa.
     *
     * @throws ValidationException
     */
    public function autenticar(): User
    {
        $this->garantirQueNaoEstaBloqueado();

        $credenciais = $this->only('email', 'password');

        if (! Auth::guard('web')->attempt($credenciais, $this->boolean('lembrar'))) {
            RateLimiter::hit($this->chaveDeBloqueio(), self::BLOQUEIO);

            // Mensagem deliberadamente genérica: dizer "e-mail não encontrado"
            // confirmaria a existência da conta para quem está sondando.
            throw ValidationException::withMessages([
                'email' => 'e-mail ou senha incorretos.',
            ]);
        }

        $usuario = Auth::guard('web')->user();

        // Conta desativada globalmente. A senha estava certa, então aqui a
        // mensagem específica não vaza nada que a pessoa já não saiba.
        if (! $usuario->isActive()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'esta conta está inativa. procure o administrador.',
            ]);
        }

        // Sem vínculo ativo não há onde operar — mesmo com a senha correta.
        if ($usuario->estabelecimentosDisponiveis()->isEmpty()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'você não tem acesso ativo a nenhum estabelecimento.',
            ]);
        }

        RateLimiter::clear($this->chaveDeBloqueio());

        return $usuario;
    }

    /**
     * @throws ValidationException
     */
    private function garantirQueNaoEstaBloqueado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->chaveDeBloqueio(), self::TENTATIVAS)) {
            return;
        }

        Event::dispatch(new Lockout($this));

        $segundos = RateLimiter::availableIn($this->chaveDeBloqueio());

        throw ValidationException::withMessages([
            'email' => "muitas tentativas. tente novamente em {$segundos} segundos.",
        ]);
    }

    /**
     * Freio por e-mail + IP: trava o ataque a uma conta específica sem
     * derrubar quem compartilha a mesma saída de rede.
     */
    private function chaveDeBloqueio(): string
    {
        return Str::transliterate(
            Str::lower($this->input('email')).'|'.$this->ip()
        );
    }
}
