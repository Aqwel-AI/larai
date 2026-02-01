<?php

namespace AqwelAI\LarAI\DTOs;

/**
 * Build response DTOs from provider arrays.
 */
class ResponseFactory
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function make(string $method, array $payload): BaseResponse
    {
        return match ($method) {
            'text', 'chat', 'summarize' => new TextResponse(
                content: (string) ($payload['content'] ?? ''),
                raw: (array) ($payload['raw'] ?? []),
                usage: (array) ($payload['usage'] ?? []),
                toolCalls: (array) ($payload['tool_calls'] ?? [])
            ),
            'image' => new ImageResponse(
                images: (array) ($payload['images'] ?? []),
                raw: (array) ($payload['raw'] ?? []),
                usage: (array) ($payload['usage'] ?? [])
            ),
            'embeddings' => new EmbeddingsResponse(
                embeddings: (array) ($payload['embeddings'] ?? []),
                raw: (array) ($payload['raw'] ?? []),
                usage: (array) ($payload['usage'] ?? [])
            ),
            'recommend' => new RecommendResponse(
                recommendations: (array) ($payload['recommendations'] ?? []),
                raw: (array) ($payload['raw'] ?? []),
                usage: (array) ($payload['usage'] ?? [])
            ),
            'vision' => new VisionResponse(
                content: (string) ($payload['content'] ?? ''),
                raw: (array) ($payload['raw'] ?? []),
                usage: (array) ($payload['usage'] ?? [])
            ),
            'transcribe' => new TranscribeResponse(
                text: (string) ($payload['text'] ?? ''),
                raw: (array) ($payload['raw'] ?? []),
                usage: (array) ($payload['usage'] ?? [])
            ),
            'speak' => new SpeakResponse(
                audio: (string) ($payload['audio'] ?? ''),
                format: (string) ($payload['format'] ?? 'mp3'),
                raw: (array) ($payload['raw'] ?? []),
                usage: (array) ($payload['usage'] ?? [])
            ),
            default => new BaseResponse(
                raw: (array) ($payload['raw'] ?? []),
                usage: (array) ($payload['usage'] ?? [])
            ),
        };
    }
}
