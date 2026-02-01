<?php

namespace AqwelAI\LarAI\Testing;

use AqwelAI\LarAI\Contracts\Provider;

/**
 * Mock provider for tests.
 */
class MockProvider implements Provider
{
    /**
     * @param array<string, array<string, mixed>> $responses
     */
    public function __construct(protected array $responses = [])
    {
    }

    public function name(): string
    {
        return 'mock';
    }

    public function text(string $prompt, array $options = []): array
    {
        return $this->responses['text'] ?? ['content' => 'mock', 'raw' => [], 'usage' => []];
    }

    public function chat(array $messages, array $options = []): array
    {
        return $this->responses['chat'] ?? ['content' => 'mock', 'raw' => [], 'usage' => []];
    }

    public function image(string $prompt, array $options = []): array
    {
        return $this->responses['image'] ?? ['images' => [], 'raw' => [], 'usage' => []];
    }

    public function summarize(string $text, array $options = []): array
    {
        return $this->responses['summarize'] ?? ['content' => 'summary', 'raw' => [], 'usage' => []];
    }

    public function embeddings(string|array $input, array $options = []): array
    {
        return $this->responses['embeddings'] ?? ['embeddings' => [], 'raw' => [], 'usage' => []];
    }
}
