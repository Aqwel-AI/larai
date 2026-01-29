<?php

namespace AqwelAI\LarAI\Events;

/**
 * Event fired before a provider request is made.
 */
class LarAIBeforeRequest
{
    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $provider,
        public string $method,
        public array $args,
        public array $options,
    ) {
    }
}
