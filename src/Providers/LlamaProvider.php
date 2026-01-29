<?php

namespace AqwelAI\LarAI\Providers;

use AqwelAI\LarAI\Contracts\Provider;
use AqwelAI\LarAI\Exceptions\LarAIException;
use AqwelAI\LarAI\Exceptions\UnsupportedFeatureException;
use Illuminate\Support\Facades\App;

/**
 * LLaMA provider implementation (compatible with OpenAI-style APIs).
 */
class LlamaProvider extends BaseProvider implements Provider
{
    /**
     * Return provider identifier.
     */
    public function name(): string
    {
        return 'llama';
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

        $response = $this->request()
            ->withToken($this->ensureApiKey())
            ->post($this->baseUrl() . '/chat/completions', $payload);

        if (!$response->successful()) {
            throw new LarAIException('LLaMA request failed: ' . $response->body());
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
     * LLaMA image generation is not configured in this sample.
     */
    public function image(string $prompt, array $options = []): array
    {
        throw new UnsupportedFeatureException('LLaMA image generation is not configured.');
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
     * Generate vector embeddings (provider-dependent support).
     */
    public function embeddings(string|array $input, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->config['embedding_model'] ?? $this->config['model'] ?? null,
            'input' => $input,
        ];

        $response = $this->request()
            ->withToken($this->ensureApiKey())
            ->post($this->baseUrl() . '/embeddings', $payload);

        if (!$response->successful()) {
            throw new LarAIException('LLaMA embeddings request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'embeddings' => $data['data'] ?? [],
            'raw' => $data,
            'usage' => $this->extractUsage($data),
        ];
    }
}
