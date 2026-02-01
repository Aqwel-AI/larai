<?php

namespace AqwelAI\LarAI\Middleware;

use AqwelAI\LarAI\Policies\PolicyEngine;

/**
 * Redact sensitive data via the policy engine.
 */
class RedactMiddleware implements Middleware
{
    public function __construct(protected PolicyEngine $policyEngine)
    {
    }

    public function before(string $provider, string $method, array $args, array $options, array $context): array
    {
        $result = $this->policyEngine->sanitize($method, $args, $options);

        return [
            'args' => $result['args'],
            'options' => $result['options'],
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
