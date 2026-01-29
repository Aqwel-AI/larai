<?php

namespace AqwelAI\LarAI\Services;

use AqwelAI\LarAI\LarAI;

/**
 * Example service class for embeddings generation.
 */
class EmbeddingsService
{
    /**
     * Create a new embeddings service instance.
     */
    public function __construct(protected LarAI $larai)
    {
    }

    /**
     * Generate embeddings for one or more texts.
     */
    public function generate(string|array $input, array $options = []): array
    {
        return $this->larai->embeddings($input, $options);
    }
}
