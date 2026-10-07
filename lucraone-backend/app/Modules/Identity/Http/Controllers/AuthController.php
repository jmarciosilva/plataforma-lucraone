<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\TokenAbility;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\LogoutRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class AuthController
{
    /**
     * Hash descartável usado quando o e-mail não existe.
     *
     * Sem isto o `Hash::check` só rodava quando a conta existia, e a resposta
     * do e-mail inexistente voltava mais rápido — diferença suficiente para
     * mapear quais e-mails têm conta, mesmo com status e mensagem idênticos.
     * Comparar contra este hash iguala os dois caminhos.
     *
     * É bcrypt de uma string aleatória descartada na geração: não é a senha de
     * ninguém e não corresponde a conta nenhuma. Fica constante de propósito —
     * gerar um hash por requisição custaria o mesmo que o ataque que se quer
     * evitar.
     */
    private const HASH_INEXISTENTE = '$2y$12$8X.RJxrqQRCDcbW8d6blS.nf7gji5zMmS1dSHANCxEe8TNmzKpna6';

    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $request->garantirQueNaoEstaBloqueado();

        $email = $request->validated()['email'];
        $password = $request->validated()['password'];

        $user = User::where('email', $email)->first();

        // Os dois caminhos passam por uma verificação de hash.
        $senhaConfere = $user
            ? Hash::check($password, $user->password)
            : Hash::check($password, self::HASH_INEXISTENTE);

        if (! $user || ! $senhaConfere) {
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
