<?php

namespace App\Modules\Terminals\Http\Controllers;

use App\Modules\Terminals\Application\TerminalManagementService;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController
{
    public function __invoke(Request $request, TerminalManagementService $service): JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->user();

        $service->registrarHeartbeat($terminal);

        return response()->json(['status' => 'ok', 'terminal_id' => $terminal->id]);
    }
}
