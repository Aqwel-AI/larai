<?php

namespace AqwelAI\LarAI\Testing;

/**
 * Common fixtures for testing.
 */
class Fixtures
{
    public static function text(string $content = 'Hello'): array
    {
        return ['content' => $content, 'raw' => [], 'usage' => []];
    }

    public static function embeddings(array $vectors = []): array
    {
        return ['embeddings' => $vectors, 'raw' => [], 'usage' => []];
    }
}
