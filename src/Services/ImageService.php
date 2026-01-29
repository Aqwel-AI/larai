<?php

namespace AqwelAI\LarAI\Services;

use AqwelAI\LarAI\LarAI;

/**
 * Example service class for image generation.
 */
class ImageService
{
    /**
     * Create a new image service instance.
     */
    public function __construct(protected LarAI $larai)
    {
    }

    /**
     * Generate images from a prompt with optional overrides.
     */
    public function generate(string $prompt, array $options = []): array
    {
        return $this->larai->image($prompt, $options);
    }
}
