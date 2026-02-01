<?php

namespace AqwelAI\LarAI\Events;

/**
 * Event fired with request timing metrics.
 */
class LarAIRequestTimed
{
    public function __construct(
        public string $provider,
        public string $method,
        public float $durationMs,
        public array $context = [],
    ) {
    }
}
