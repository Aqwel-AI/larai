<?php

namespace AqwelAI\LarAI\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use AqwelAI\LarAI\Exceptions\LarAIException;

/**
 * Shared provider utilities for HTTP requests and config.
 */
abstract class BaseProvider
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config)
    {
    }

    /**
     * Return the base URL for the provider.
     */
    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    /**
     * Build a preconfigured HTTP client.
     */
    protected function request(): PendingRequest
    {
        $timeout = (int) Config::get('larai.timeout', 60);

        return Http::timeout($timeout);
    }

    /**
     * Ensure an API key exists and return it.
     */
    protected function ensureApiKey(string $keyName = 'api_key'): string
    {
        $apiKey = (string) ($this->config[$keyName] ?? '');

        if ($apiKey === '') {
            throw new LarAIException('LarAI API key is missing.');
        }

        return $apiKey;
    }

    /**
     * Extract usage metadata from API responses.
     */
    protected function extractUsage(array $payload): array
    {
        return $payload['usage'] ?? [];
    }
}
