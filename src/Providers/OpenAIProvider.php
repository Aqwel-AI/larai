<?php

namespace AqwelAI\LarAI\Providers;

use AqwelAI\LarAI\Contracts\AudioProvider;
use AqwelAI\LarAI\Contracts\Provider;
use AqwelAI\LarAI\Contracts\StreamingProvider;
use AqwelAI\LarAI\Contracts\VisionProvider;
use AqwelAI\LarAI\Exceptions\LarAIException;
use Illuminate\Support\Facades\App;

/**
 * OpenAI provider implementation.
 */
class OpenAIProvider extends BaseProvider implements Provider, StreamingProvider, VisionProvider, AudioProvider
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

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if (isset($options['tool_choice'])) {
            $payload['tool_choice'] = $options['tool_choice'];
        }

        $response = $this->request()
            ->withToken($this->ensureApiKey())
            ->post($this->baseUrl() . '/chat/completions', $payload);

        if (!$response->successful()) {
            throw new LarAIException('OpenAI request failed: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';
        $toolCalls = $data['choices'][0]['message']['tool_calls'] ?? [];

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
            'raw' => $data,
            'usage' => $this->extractUsage($data),
        ];
    }

    /**
     * Stream a chat completion response.
     *
     * @return iterable<int, string>
     */
    public function streamChat(array $messages, array $options = []): iterable
    {
        $payload = [
            'model' => $options['model'] ?? $this->config['model'] ?? null,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'stream' => true,
        ];

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if (isset($options['tool_choice'])) {
            $payload['tool_choice'] = $options['tool_choice'];
        }

        $response = $this->request()
            ->withOptions(['stream' => true])
            ->withToken($this->ensureApiKey())
            ->post($this->baseUrl() . '/chat/completions', $payload);

        if (!$response->successful()) {
            throw new LarAIException('OpenAI streaming request failed: ' . $response->body());
        }

        return (function () use ($response) {
            foreach ($this->streamSse($response) as $data) {
                if ($data === '[DONE]') {
                    break;
                }

                $payload = json_decode($data, true);

                if (!is_array($payload)) {
                    continue;
                }

                $chunk = $payload['choices'][0]['delta']['content'] ?? '';

                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        })();
    }

    /**
     * Stream text via chat completions.
     *
     * @return iterable<int, string>
     */
    public function streamText(string $prompt, array $options = []): iterable
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        return $this->streamChat($messages, $options);
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

    /**
     * Run a vision prompt against one or more images.
     *
     * @param string|array<int, string> $images
     */
    public function vision(string $prompt, string|array $images, array $options = []): array
    {
        $imageList = is_array($images) ? $images : [$images];
        $content = [
            ['type' => 'text', 'text' => $prompt],
        ];

        foreach ($imageList as $image) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $image],
            ];
        }

        $payload = [
            'model' => $options['model'] ?? $this->config['vision_model'] ?? $this->config['model'] ?? null,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'temperature' => $options['temperature'] ?? 0.2,
        ];

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        $response = $this->request()
            ->withToken($this->ensureApiKey())
            ->post($this->baseUrl() . '/chat/completions', $payload);

        if (!$response->successful()) {
            throw new LarAIException('OpenAI vision request failed: ' . $response->body());
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
     * Transcribe an audio file using OpenAI.
     */
    public function transcribe(string $path, array $options = []): array
    {
        if (!is_file($path)) {
            throw new LarAIException("Audio file [$path] not found.");
        }

        $payload = [
            'model' => $options['model'] ?? $this->config['transcribe_model'] ?? 'whisper-1',
        ];

        if (isset($options['language'])) {
            $payload['language'] = $options['language'];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new LarAIException("Unable to read audio file [$path].");
        }

        $response = $this->request()
            ->withToken($this->ensureApiKey())
            ->attach('file', $contents, basename($path))
            ->post($this->baseUrl() . '/audio/transcriptions', $payload);

        if (!$response->successful()) {
            throw new LarAIException('OpenAI transcription request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'text' => $data['text'] ?? '',
            'raw' => $data,
            'usage' => $this->extractUsage($data),
        ];
    }

    /**
     * Generate speech audio for the given text.
     */
    public function speak(string $text, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->config['speech_model'] ?? 'gpt-4o-mini-tts',
            'input' => $text,
            'voice' => $options['voice'] ?? $this->config['voice'] ?? 'alloy',
        ];

        $format = $options['format'] ?? $this->config['speech_format'] ?? 'mp3';
        $payload['format'] = $format;

        $response = $this->request()
            ->withToken($this->ensureApiKey())
            ->post($this->baseUrl() . '/audio/speech', $payload);

        if (!$response->successful()) {
            throw new LarAIException('OpenAI speech request failed: ' . $response->body());
        }

        $audio = base64_encode($response->body());

        return [
            'audio' => $audio,
            'format' => $format,
            'raw' => $response->body(),
        ];
    }
}
