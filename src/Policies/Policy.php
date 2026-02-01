<?php

namespace AqwelAI\LarAI\Policies;

/**
 * Policy contract for request sanitization and guardrails.
 */
interface Policy
{
    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     * @return array{args: array<int, mixed>, options: array<string, mixed>}
     */
    public function apply(string $method, array $args, array $options): array;
}
