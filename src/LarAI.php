<?php

namespace AqwelAI\LarAI;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use AqwelAI\LarAI\Contracts\Provider;
use AqwelAI\LarAI\Exceptions\LarAIException;
use AqwelAI\LarAI\Exceptions\UnsupportedFeatureException;
use AqwelAI\LarAI\Jobs\LarAIJob;
use AqwelAI\LarAI\Providers\ClaudeProvider;
use AqwelAI\LarAI\Providers\LlamaProvider;
use AqwelAI\LarAI\Providers\OpenAIProvider;

/**
 * Core entry point for interacting with AI providers.
 */
class LarAI
{
    /**
     * @var array<string, Provider>
     */
    protected array $providers = [];

    /**
     * Register a custom provider instance at runtime.
     */
    public function registerProvider(string $name, Provider $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Resolve the active provider (or a named provider).
     */
    public function provider(?string $name = null): Provider
    {
        $name = $name ?: Config::get('larai.default', 'openai');

        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        $providerConfig = Config::get("larai.providers.$name");

        if (!$providerConfig) {
            throw new LarAIException("LarAI provider [$name] is not configured.");
        }

        if (!isset($this->providers[$name])) {
            $this->providers[$name] = match ($name) {
                'openai' => new OpenAIProvider($providerConfig),
                'claude' => new ClaudeProvider($providerConfig),
                'llama' => new LlamaProvider($providerConfig),
                default => throw new LarAIException("LarAI provider [$name] is not supported."),
            };
        }

        return $this->providers[$name];
    }

    /**
     * Generate text from a prompt.
     */
    public function text(string $prompt, array $options = []): array
    {
        return $this->callProvider('text', [$prompt, $options], $options);
    }

    /**
     * Run a chat completion with role-based messages.
     */
    public function chat(array $messages, array $options = []): array
    {
        return $this->callProvider('chat', [$messages, $options], $options);
    }

    /**
     * Generate images from a prompt.
     */
    public function image(string $prompt, array $options = []): array
    {
        return $this->callProvider('image', [$prompt, $options], $options);
    }

    /**
     * Summarize a long text input.
     */
    public function summarize(string $text, array $options = []): array
    {
        return $this->callProvider('summarize', [$text, $options], $options);
    }

    /**
     * Generate embeddings for text or an array of texts.
     */
    public function embeddings(string|array $input, array $options = []): array
    {
        return $this->callProvider('embeddings', [$input, $options], $options);
    }

    /**
     * Recommend items by embedding similarity.
     *
     * @param array<int, string> $candidates
     */
    public function recommend(string $query, array $candidates, array $options = []): array
    {
        if ($candidates === []) {
            return ['recommendations' => [], 'usage' => [], 'raw' => []];
        }

        $inputs = array_merge([$query], array_values($candidates));
        $response = $this->embeddings($inputs, $options);
        $embeddings = $response['embeddings'] ?? [];

        $vectors = $this->normalizeEmbeddings($embeddings);

        if (!isset($vectors[0])) {
            throw new LarAIException('Unable to compute embeddings for the query.');
        }

        $queryVector = $vectors[0];
        $recommendations = [];

        foreach (array_values($candidates) as $index => $candidate) {
            $vectorIndex = $index + 1;

            if (!isset($vectors[$vectorIndex])) {
                continue;
            }

            $recommendations[] = [
                'item' => $candidate,
                'score' => $this->cosineSimilarity($queryVector, $vectors[$vectorIndex]),
            ];
        }

        usort($recommendations, function (array $a, array $b) {
            return $b['score'] <=> $a['score'];
        });

        return [
            'recommendations' => $recommendations,
            'usage' => $response['usage'] ?? [],
            'raw' => $response,
        ];
    }

    /**
     * Render a prompt template from config.
     */
    public function prompt(string $name, array $vars = []): string
    {
        $template = Config::get("larai.prompts.$name");

        if (!$template) {
            throw new LarAIException("Prompt template [$name] is not defined.");
        }

        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * Queue a text generation job.
     */
    public function queueText(string $prompt, array $options = []): mixed
    {
        return $this->queue('text', [$prompt, $options], $options);
    }

    /**
     * Queue a chat generation job.
     */
    public function queueChat(array $messages, array $options = []): mixed
    {
        return $this->queue('chat', [$messages, $options], $options);
    }

    /**
     * Queue an image generation job.
     */
    public function queueImage(string $prompt, array $options = []): mixed
    {
        return $this->queue('image', [$prompt, $options], $options);
    }

    /**
     * Queue a summarization job.
     */
    public function queueSummarize(string $text, array $options = []): mixed
    {
        return $this->queue('summarize', [$text, $options], $options);
    }

    /**
     * Queue an embeddings job.
     */
    public function queueEmbeddings(string|array $input, array $options = []): mixed
    {
        return $this->queue('embeddings', [$input, $options], $options);
    }

    /**
     * Dispatch a provider call or queue it when async is requested.
     */
    protected function callProvider(string $method, array $args, array $options = []): array
    {
        if (Arr::get($options, 'async')) {
            return ['queued' => true, 'job' => $this->queue($method, $args, $options)];
        }

        $provider = $this->provider(Arr::get($options, 'provider'));
        $response = $provider->{$method}(...$args);

        $this->logUsage($provider->name(), $response);

        return $response;
    }

    /**
     * Push a LarAI job onto the queue.
     */
    protected function queue(string $action, array $args, array $options = []): mixed
    {
        if (!Config::get('larai.queue.enabled')) {
            throw new UnsupportedFeatureException('LarAI queue support is disabled.');
        }

        $job = new LarAIJob($action, $args, Arr::get($options, 'provider'));
        $connection = Config::get('larai.queue.connection');
        $queue = Config::get('larai.queue.queue');

        if ($connection) {
            $job->onConnection($connection);
        }

        if ($queue) {
            $job->onQueue($queue);
        }

        return Bus::dispatch($job);
    }

    /**
     * Log usage metadata when provided by the API.
     */
    protected function logUsage(string $provider, array $response): void
    {
        if (!Config::get('larai.logging.enabled')) {
            return;
        }

        $usage = $response['usage'] ?? null;

        if (!$usage) {
            return;
        }

        $channel = Config::get('larai.logging.channel');
        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        if (!$logger) {
            return;
        }

        $logger->info('LarAI usage', [
            'provider' => $provider,
            'usage' => $usage,
        ]);
    }

    /**
     * Normalize embedding payloads into raw vector arrays.
     *
     * @param array<int, mixed> $embeddings
     * @return array<int, array<int, float>>
     */
    protected function normalizeEmbeddings(array $embeddings): array
    {
        $vectors = [];

        foreach ($embeddings as $index => $item) {
            if (is_array($item) && array_key_exists('embedding', $item)) {
                $vectors[$index] = array_map('floatval', (array) $item['embedding']);
                continue;
            }

            if (is_array($item)) {
                $vectors[$index] = array_map('floatval', $item);
            }
        }

        return $vectors;
    }

    /**
     * Compute cosine similarity between two vectors.
     *
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
