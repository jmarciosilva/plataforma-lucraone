<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Pdv\Http\Requests\CredentialLifecycleRequest;
use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Application\CredentialOperationResult;
use App\Modules\Terminals\Application\TerminalContext;
use App\Modules\Terminals\Application\TerminalCredentialLifecycle;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Http\JsonResponse;

final class CredentialLifecycleController
{
    public function __construct(private TerminalCredentialLifecycle $lifecycle, private TerminalContext $context) {}

    public function prepare(CredentialLifecycleRequest $request): JsonResponse
    {
        return $this->response($this->lifecycle->prepare($this->context->terminal(), $this->context->terminal()->currentAccessToken(), strtolower($request->validated('operation_id')), $request->validated('delivery_secret')));
    }

    public function confirm(CredentialLifecycleRequest $request): JsonResponse
    {
        return $this->response($this->lifecycle->confirm($this->context->terminal(), $this->context->terminal()->currentAccessToken(), strtolower($request->validated('operation_id'))));
    }

    public function recover(CredentialLifecycleRequest $request): JsonResponse
    {
        return $this->response($this->lifecycle->recover($request->validated('recovery_code'), $request->validated('installation_id'), $request->validated('delivery_secret')));
    }

    public function authorizeRecovery(CredentialLifecycleRequest $request, string $terminal): JsonResponse
    {
        $subject = Terminal::withoutGlobalScopes()->whereKey($terminal)->where('tenant_id', app(TenantContext::class)->id())->first();
        if (! $subject) {
            return PdvErrorResponse::make($request, 'forbidden', 'Operação administrativa não autorizada.', 403);
        }

        return $this->sensitive(['data' => $this->lifecycle->authorizeRecovery($request->user(), $subject, $request->validated('reason'))]);
    }

    private function response(CredentialOperationResult $result): JsonResponse
    {
        $data = ['rotation' => ['id' => $result->operation->id, 'status' => $result->operation->status,
            'expires_at' => $result->operation->expires_at->toIso8601String()],
            'credential' => ['token_type' => 'Bearer', 'expires_at' => $result->credentialExpiresAt]];
        if ($result->credential !== null) {
            $data['credential']['access_token'] = $result->credential;
        }

        return $this->sensitive(['data' => $data]);
    }

    private function sensitive(array $payload): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }
}
