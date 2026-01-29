<?php

namespace AqwelAI\LarAI\Contracts;

/**
 * Provider contract for AI model backends.
 */
interface Provider
{
    /**
     * Return the provider name.
     */
    public function name(): string;

    /**
     * Generate text from a prompt.
     */
    public function text(string $prompt, array $options = []): array;

    /**
     * Run a chat completion with role-based messages.
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Generate images from a prompt.
     */
    public function image(string $prompt, array $options = []): array;

    /**
     * Summarize a long text input.
     */
    public function summarize(string $text, array $options = []): array;

    /**
     * Generate embeddings for text or an array of texts.
     */
    public function embeddings(string|array $input, array $options = []): array;
}
