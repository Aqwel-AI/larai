<?php

namespace AqwelAI\LarAI\Providers;

use AqwelAI\LarAI\Contracts\Provider;
use AqwelAI\LarAI\Exceptions\LarAIException;
use Illuminate\Support\Facades\App;

/**
 * OpenAI provider implementation.
 */
class OpenAIProvider extends BaseProvider implements Provider
{
    /**
     * Return provider identifier.
     */
    public function name(): string
    {
        return 'openai';
    }

    /**
     * Generate text via chat completions.
     */
    public function text(string $prompt, array $options = []): array
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        return $this->chat($messages, $options);
    }

    /**
     * Run a chat completion request.
     */
    public function chat(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->config['model'] ?? null,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        $response = $this->request()
            ->withToken($this->ensureApiKey())
            ->post($this->baseUrl() . '/chat/completions', $payload);

        if (!$response->successful()) {
            throw new LarAIException('OpenAI request failed: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        return [
            'content' => $content,
            'raw' => $data,
            'usage' => $this->extractUsage($data),
        ];
    }

    /**
     * Generate images from a prompt.
     */
    public function image(string $prompt, array $options = []): array
    {
        $payload = [
            'prompt' => $prompt,
            'model' => $options['model'] ?? $this->config['image_model'] ?? 'gpt-image-1',
            'size' => $options['size'] ?? '1024x1024',
        ];

        $response = $this->request()
            ->withToken($this->ensureApiKey())
            ->post($this->baseUrl() . '/images/generations', $payload);

        if (!$response->successful()) {
            throw new LarAIException('OpenAI image request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'images' => $data['data'] ?? [],
            'raw' => $data,
            'usage' => $this->extractUsage($data),
        ];
    }

    /**
     * Summarize text by reusing the text endpoint.
     */
    public function summarize(string $text, array $options = []): array
    {
        $prompt = $options['prompt'] ?? App::make(\AqwelAI\LarAI\LarAI::class)->prompt('summarize', [
            'text' => $text,
        ]);

        return $this->text($prompt, $options);
    }

    /**
     * Generate vector embeddings.
     */
    public function embeddings(string|array $input, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->config['embedding_model'] ?? 'text-embedding-3-small',
            'input' => $input,
        ];

        $response = $this->request()
            ->withToken($this->ensureApiKey())
            ->post($this->baseUrl() . '/embeddings', $payload);

        if (!$response->successful()) {
            throw new LarAIException('OpenAI embeddings request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'embeddings' => $data['data'] ?? [],
            'raw' => $data,
            'usage' => $this->extractUsage($data),
        ];
    }
}
