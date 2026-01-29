<?php

namespace AqwelAI\LarAI\Events;

/**
 * Event fired when a provider reports usage metadata.
 */
class LarAIUsageReported
{
    /**
     * @param array<string, mixed> $usage
     * @param array<string, mixed> $options
     * @param array<string, mixed> $response
     */
    public function __construct(
        public string $provider,
        public string $method,
        public array $usage,
        public array $options = [],
        public array $response = [],
    ) {
    }
}
