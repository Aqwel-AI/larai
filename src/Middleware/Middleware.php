<?php

namespace AqwelAI\LarAI\Middleware;

/**
 * Request/response middleware contract.
 */
interface Middleware
{
    /**
     * @param array<string, mixed> $context
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     * @return array{args: array<int, mixed>, options: array<string, mixed>, context: array<string, mixed>}
     */
    public function before(string $provider, string $method, array $args, array $options, array $context): array;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $response
     * @return array{response: array<string, mixed>, context: array<string, mixed>}
     */
    public function after(string $provider, string $method, array $response, array $context): array;
}
