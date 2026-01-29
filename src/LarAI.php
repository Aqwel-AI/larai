<?php

namespace AqwelAI\LarAI;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use AqwelAI\LarAI\Contracts\Provider;
use AqwelAI\LarAI\Contracts\StreamingProvider;
use AqwelAI\LarAI\Events\LarAIAfterRequest;
use AqwelAI\LarAI\Events\LarAIBeforeRequest;
use AqwelAI\LarAI\Events\LarAIUsageReported;
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
     * Stream text chunks from a prompt.
     *
     * @return iterable<int, string>
     */
    public function streamText(string $prompt, array $options = [], ?callable $onChunk = null): iterable
    {
        return $this->streamProvider('streamText', [$prompt, $options], $options, $onChunk);
    }

    /**
     * Stream chat chunks from role-based messages.
     *
     * @return iterable<int, string>
     */
    public function streamChat(array $messages, array $options = [], ?callable $onChunk = null): iterable
    {
        return $this->streamProvider('streamChat', [$messages, $options], $options, $onChunk);
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
     * Summarize the contents of a local file.
     */
    public function summarizeFile(string $path, array $options = []): array
    {
        $text = $this->extractTextFromFile($path);

        return $this->summarize($text, $options);
    }

    /**
     * Generate embeddings for text or an array of texts.
     */
    public function embeddings(string|array $input, array $options = []): array
    {
        return $this->callProvider('embeddings', [$input, $options], $options);
    }

    /**
     * Generate embeddings for the contents of a local file.
     */
    public function embeddingsFile(string $path, array $options = []): array
    {
        $text = $this->extractTextFromFile($path);

        return $this->embeddings($text, $options);
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
        $cacheEnabled = $this->cacheEnabled($options);

        if ($cacheEnabled) {
            $cache = $this->cacheStore();
            $cacheKey = $this->cacheKey($provider->name(), $method, $args, $options);
            $cached = $cache->get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $this->dispatchBeforeRequest($provider->name(), $method, $args, $options);
        $response = $provider->{$method}(...$args);

        if ($cacheEnabled) {
            $cacheTtl = $this->cacheTtl($options);
            $this->cacheStore()->put($cacheKey, $response, $cacheTtl);
        }

        $this->dispatchAfterRequest($provider->name(), $method, $args, $options, $response);
        $this->recordUsage($provider->name(), $method, $options, $response);

        return $response;
    }

    /**
     * Dispatch a streaming provider call.
     *
     * @return iterable<int, string>
     */
    protected function streamProvider(string $method, array $args, array $options = [], ?callable $onChunk = null): iterable
    {
        $provider = $this->provider(Arr::get($options, 'provider'));

        if (!$provider instanceof StreamingProvider) {
            throw new UnsupportedFeatureException('LarAI streaming is not supported by this provider.');
        }

        return (function () use ($provider, $method, $args, $onChunk) {
            foreach ($provider->{$method}(...$args) as $chunk) {
                if ($onChunk) {
                    $onChunk($chunk);
                }

                yield $chunk;
            }
        })();
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
    protected function recordUsage(string $provider, string $method, array $options, array $response): void
    {
        $usage = $response['usage'] ?? null;

        if (!$usage) {
            return;
        }

        if (!Config::get('larai.logging.enabled')) {
            $this->dispatchUsageEvent($provider, $method, $options, $usage, $response);
            return;
        }

        $channel = Config::get('larai.logging.channel');
        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        if (!$logger) {
            $this->dispatchUsageEvent($provider, $method, $options, $usage, $response);
            return;
        }

        $logger->info('LarAI usage', [
            'provider' => $provider,
            'usage' => $usage,
        ]);

        $this->dispatchUsageEvent($provider, $method, $options, $usage, $response);
    }

    /**
     * Dispatch a usage event for downstream tracking.
     *
     * @param array<string, mixed> $usage
     * @param array<string, mixed> $response
     */
    protected function dispatchUsageEvent(
        string $provider,
        string $method,
        array $options,
        array $usage,
        array $response
    ): void {
        if (!Config::get('larai.usage.events', true)) {
            return;
        }

        $includeResponse = (bool) Config::get('larai.usage.include_response', false);
        $includeOptions = (bool) Config::get('larai.usage.include_options', false);

        event(new LarAIUsageReported(
            provider: $provider,
            method: $method,
            usage: $usage,
            options: $includeOptions ? $options : [],
            response: $includeResponse ? $response : []
        ));
    }

    /**
     * Determine if caching should be used for this call.
     */
    protected function cacheEnabled(array $options): bool
    {
        return (bool) Arr::get($options, 'cache', Config::get('larai.cache.enabled', false));
    }

    /**
     * Resolve the cache store configured for LarAI.
     */
    protected function cacheStore()
    {
        $store = Config::get('larai.cache.store');

        return $store ? Cache::store($store) : Cache::store();
    }

    /**
     * Determine cache TTL for the current request.
     */
    protected function cacheTtl(array $options): int
    {
        return (int) Arr::get($options, 'cache_ttl', Config::get('larai.cache.ttl', 300));
    }

    /**
     * Build a deterministic cache key for a provider call.
     */
    protected function cacheKey(string $provider, string $method, array $args, array $options): string
    {
        $payload = [
            'provider' => $provider,
            'method' => $method,
            'args' => $args,
            'options' => $this->normalizeOptionsForCache($options),
        ];

        $prefix = (string) Config::get('larai.cache.prefix', 'larai:');

        return $prefix . hash('sha256', json_encode($payload));
    }

    /**
     * Normalize options to exclude non-deterministic keys.
     *
     * @return array<string, mixed>
     */
    protected function normalizeOptionsForCache(array $options): array
    {
        return Arr::except($options, ['async', 'cache', 'cache_ttl']);
    }

    /**
     * Dispatch a pre-request hook for moderation and tracing.
     */
    protected function dispatchBeforeRequest(string $provider, string $method, array $args, array $options): void
    {
        if (!Config::get('larai.hooks.enabled', true)) {
            return;
        }

        $result = Event::until(new LarAIBeforeRequest(
            provider: $provider,
            method: $method,
            args: $args,
            options: $options
        ));

        if ($result === false) {
            throw new LarAIException('LarAI request blocked by a moderation hook.');
        }
    }

    /**
     * Dispatch a post-request hook for moderation and tracing.
     */
    protected function dispatchAfterRequest(
        string $provider,
        string $method,
        array $args,
        array $options,
        array $response
    ): void {
        if (!Config::get('larai.hooks.enabled', true)) {
            return;
        }

        Event::dispatch(new LarAIAfterRequest(
            provider: $provider,
            method: $method,
            args: $args,
            options: $options,
            response: $response
        ));
    }

    /**
     * Read text content from a local file path.
     */
    protected function extractTextFromFile(string $path): string
    {
        if (!is_file($path)) {
            throw new LarAIException("File [$path] not found.");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new LarAIException("Unable to read file [$path].");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['html', 'htm'], true)) {
            $content = strip_tags($content);
        }

        if ($extension === 'pdf') {
            throw new UnsupportedFeatureException('PDF parsing is not configured. Convert to text first.');
        }

        return trim($content);
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
