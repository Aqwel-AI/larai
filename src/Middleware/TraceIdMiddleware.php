<?php

namespace AqwelAI\LarAI\Middleware;

use Illuminate\Support\Str;

/**
 * Attach a trace ID to the request context and options.
 */
class TraceIdMiddleware implements Middleware
{
    public function before(string $provider, string $method, array $args, array $options, array $context): array
    {
        $traceId = $options['trace_id'] ?? $context['trace_id'] ?? (string) Str::uuid();
        $options['trace_id'] = $traceId;
        $context['trace_id'] = $traceId;

        return [
            'args' => $args,
            'options' => $options,
            'context' => $context,
        ];
    }

    public function after(string $provider, string $method, array $response, array $context): array
    {
        return [
            'response' => $response,
            'context' => $context,
        ];
    }
}
