<?php

namespace AqwelAI\LarAI\Events;

/**
 * Event fired after a provider response is received.
 */
class LarAIAfterRequest
{
    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     * @param array<string, mixed> $response
     */
    public function __construct(
        public string $provider,
        public string $method,
        public array $args,
        public array $options,
        public array $response,
    ) {
    }
}
