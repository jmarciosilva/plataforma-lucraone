<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\LogoutRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->validated()['email'];
        $password = $request->validated()['password'];

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
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

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login realizado com sucesso',
            'token' => $token,
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

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso',
        ], 200);
    }
}
