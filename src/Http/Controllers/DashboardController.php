<?php

namespace AqwelAI\LarAI\Http\Controllers;

use AqwelAI\LarAI\Models\UsageLog;
use Illuminate\Http\JsonResponse;

/**
 * Minimal dashboard controller for usage stats.
 */
class DashboardController
{
    public function index(): JsonResponse
    {
        $total = UsageLog::count();
        $byProvider = UsageLog::query()
            ->selectRaw('provider, COUNT(*) as total')
            ->groupBy('provider')
            ->pluck('total', 'provider');

        return response()->json([
            'total' => $total,
            'by_provider' => $byProvider,
        ]);
    }
}
