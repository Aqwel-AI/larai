<?php

namespace AqwelAI\LarAI\Providers;

use AqwelAI\LarAI\Contracts\Provider;
use AqwelAI\LarAI\Exceptions\LarAIException;
use AqwelAI\LarAI\Exceptions\UnsupportedFeatureException;
use Illuminate\Support\Facades\App;

/**
 * Claude (Anthropic) provider implementation.
 */
class ClaudeProvider extends BaseProvider implements Provider
{
    /**
     * Return provider identifier.
     */
    public function name(): string
    {
        return 'claude';
    }

    /**
     * Generate text via Claude messages API.
     */
    public function text(string $prompt, array $options = []): array
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        return $this->chat($messages, $options);
    }

    /**
     * Run a Claude chat request.
     */
    public function chat(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->config['model'] ?? null,
            'max_tokens' => (int) ($options['max_tokens'] ?? 1024),
            'messages' => $messages,
        ];

        $response = $this->request()
            ->withHeaders([
                'x-api-key' => $this->ensureApiKey(),
                'anthropic-version' => $this->config['anthropic_version'] ?? '2023-06-01',
            ])
            ->post($this->baseUrl() . '/messages', $payload);

        if (!$response->successful()) {
            throw new LarAIException('Claude request failed: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['content'][0]['text'] ?? '';

        return [
            'content' => $content,
            'raw' => $data,
            'usage' => $this->extractUsage($data),
        ];
    }

    /**
     * Claude does not support image generation.
     */
    public function image(string $prompt, array $options = []): array
    {
        throw new UnsupportedFeatureException('Claude does not support image generation.');
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
     * Claude embeddings are not configured in this sample.
     */
    public function embeddings(string|array $input, array $options = []): array
    {
        throw new UnsupportedFeatureException('Claude embeddings are not configured.');
    }
}
