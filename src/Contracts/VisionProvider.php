<?php

namespace AqwelAI\LarAI\Contracts;

/**
 * Optional vision provider contract for image + text prompts.
 */
interface VisionProvider
{
    /**
     * Run a vision prompt against one or more images.
     *
     * @param string|array<int, string> $images
     */
    public function vision(string $prompt, string|array $images, array $options = []): array;
}
