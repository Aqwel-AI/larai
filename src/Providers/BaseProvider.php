<?php

namespace AqwelAI\LarAI\Providers;

use Illuminate\Http\Client\Response;
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

        $request = Http::timeout($timeout);
        $retryConfig = Config::get('larai.retry', []);

        $enabled = (bool) ($retryConfig['enabled'] ?? true);

        if ($enabled) {
            $times = (int) ($retryConfig['times'] ?? 3);
            $baseDelay = (int) ($retryConfig['sleep'] ?? 200);
            $maxDelay = (int) ($retryConfig['max_sleep'] ?? 2000);
            $useJitter = (bool) ($retryConfig['jitter'] ?? true);
            $statuses = $retryConfig['statuses'] ?? [429, 500, 502, 503, 504];

            $delay = function (int $attempt) use ($baseDelay, $maxDelay, $useJitter): int {
                $delay = min($maxDelay, (int) ($baseDelay * (2 ** max(0, $attempt - 1))));

                if ($useJitter) {
                    $minDelay = (int) max(0, $delay * 0.5);
                    $delay = random_int($minDelay, $delay);
                }

                return $delay;
            };

            $when = function ($exception) use ($statuses): bool {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $status = $exception->response?->status();

                    if ($status === null) {
                        return true;
                    }

                    return in_array($status, $statuses, true);
                }

                return true;
            };

            $request = $request->retry($times, $delay, $when);
        }

        return $request;
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

    /**
     * Stream non-empty lines from a response body.
     *
     * @return \Generator<int, string>
     */
    protected function streamLines(Response $response): \Generator
    {
        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line !== '') {
                    yield $line;
                }
            }
        }

        $buffer = trim($buffer);

        if ($buffer !== '') {
            yield $buffer;
        }
    }

    /**
     * Stream Server-Sent Event data payloads.
     *
     * @return \Generator<int, string>
     */
    protected function streamSse(Response $response): \Generator
    {
        foreach ($this->streamLines($response) as $line) {
            if (str_starts_with($line, 'data:')) {
                $payload = trim(substr($line, 5));

                if ($payload !== '') {
                    yield $payload;
                }
            }
        }
    }
}
