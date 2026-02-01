<?php

namespace AqwelAI\LarAI\Listeners;

use AqwelAI\LarAI\Events\LarAIUsageReported;
use AqwelAI\LarAI\Models\UsageLog;

/**
 * Store usage logs for dashboard reporting.
 */
class StoreUsageLog
{
    public function handle(LarAIUsageReported $event): void
    {
        UsageLog::create([
            'provider' => $event->provider,
            'method' => $event->method,
            'usage' => $event->usage,
            'meta' => [
                'options' => $event->options,
            ],
        ]);
    }
}
