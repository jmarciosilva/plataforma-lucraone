<?php

namespace App\Modules\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'checks' => [],
        ];

        $health['checks']['database'] = $this->checkDatabase();
        $health['checks']['cache'] = $this->checkCache();

        $allHealthy = collect($health['checks'])->every(fn ($check) => $check['status'] === 'healthy');
        $health['status'] = $allHealthy ? 'healthy' : 'degraded';

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'status' => 'healthy',
                'message' => 'Database connection OK',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: '.$e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            cache()->put('health_check', true, 1);
            $cached = cache()->get('health_check');

            if ($cached === true) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache connection OK',
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Cache read/write failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache connection failed: '.$e->getMessage(),
            ];
        }
    }
}
