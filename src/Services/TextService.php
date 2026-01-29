<?php

namespace AqwelAI\LarAI\Services;

use AqwelAI\LarAI\LarAI;

/**
 * Example service class for text generation.
 */
class TextService
{
    /**
     * Create a new text service instance.
     */
    public function __construct(protected LarAI $larai)
    {
    }

    /**
     * Generate text from a prompt with optional overrides.
     */
    public function generate(string $prompt, array $options = []): array
    {
        return $this->larai->text($prompt, $options);
    }
}
