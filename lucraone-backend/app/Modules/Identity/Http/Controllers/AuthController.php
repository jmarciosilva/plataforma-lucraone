<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\PasswordVerification;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\TokenAbility;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\LogoutRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AuthController
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected PasswordVerification $senha,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $request->garantirQueNaoEstaBloqueado();

        $email = $request->validated()['email'];
        $password = $request->validated()['password'];

        $user = User::where('email', $email)->first();

        // Os dois caminhos passam por uma verificação de hash. A regra vive no
        // PasswordVerification, compartilhado com o login do operador de PDV.
        if (! $this->senha->confere($user, $password)) {
            $request->registrarTentativaFalha();

            return response()->json([
                'message' => 'Credenciais inválidas',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'message' => 'Conta inativa',
            ], 403);
        }

        $estabelecimentos = $user->estabelecimentosDisponiveis();

        if ($estabelecimentos->isEmpty()) {
            return response()->json([
                'message' => 'Usuário sem vínculo ativo com nenhum estabelecimento',
            ], 403);
        }

        $request->limparTentativas();

        $user->update(['last_login_at' => now()]);

        $abilities = TokenAbility::paraSessaoHumana();
        $token = $user->createToken('auth_token', $abilities);

        return response()->json([
            'message' => 'Login realizado com sucesso',
            'token' => $token->plainTextToken,
            // Quando o token deixa de valer, para o cliente saber a hora de
            // pedir outro em vez de descobrir no meio de uma operação.
            'expires_at' => $this->expiraEm($token->accessToken->created_at),
            'abilities' => $abilities,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ],
            // O cliente escolhe um destes e o envia em X-Tenant-ID
            'tenants' => $estabelecimentos->map(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ])->values(),
        ], 200);
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        // Só o token desta requisição. Os outros dispositivos da pessoa
        // seguem conectados — encerrar todos sem ela pedir seria surpresa.
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso',
        ], 200);
    }

    /**
     * Instante em que o token expira, em ISO-8601 UTC, ou null se a
     * configuração não define expiração.
     */
    private function expiraEm(?\DateTimeInterface $emitidoEm): ?string
    {
        $minutos = config('sanctum.expiration');

        if ($minutos === null || $emitidoEm === null) {
            return null;
        }

        return Carbon::instance($emitidoEm)
            ->addMinutes((int) $minutos)
            ->utc()
            ->toIso8601ZuluString();
    }
}
