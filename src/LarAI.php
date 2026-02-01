<?php

namespace AqwelAI\LarAI;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use AqwelAI\LarAI\Contracts\AudioProvider;
use AqwelAI\LarAI\Contracts\Provider;
use AqwelAI\LarAI\Contracts\StreamingProvider;
use AqwelAI\LarAI\Contracts\VisionProvider;
use AqwelAI\LarAI\DTOs\ResponseFactory;
use AqwelAI\LarAI\Events\LarAIAfterRequest;
use AqwelAI\LarAI\Events\LarAIBeforeRequest;
use AqwelAI\LarAI\Events\LarAIRequestTimed;
use AqwelAI\LarAI\Events\LarAIUsageReported;
use AqwelAI\LarAI\Exceptions\LarAIException;
use AqwelAI\LarAI\Exceptions\UnsupportedFeatureException;
use AqwelAI\LarAI\Jobs\LarAIJob;
use AqwelAI\LarAI\Middleware\Middleware;
use AqwelAI\LarAI\Providers\ClaudeProvider;
use AqwelAI\LarAI\Providers\LlamaProvider;
use AqwelAI\LarAI\Providers\OpenAIProvider;
use AqwelAI\LarAI\Routing\HealthStore;
use AqwelAI\LarAI\Routing\ProviderRouter;
use AqwelAI\LarAI\Schema\SchemaValidator;

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
     * Run a vision prompt against one or more images.
     *
     * @param string|array<int, string> $images
     */
    public function vision(string $prompt, string|array $images, array $options = []): array
    {
        return $this->callProvider('vision', [$prompt, $images, $options], $options);
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
     * Transcribe an audio file from a local path.
     */
    public function transcribe(string $path, array $options = []): array
    {
        return $this->callProvider('transcribe', [$path, $options], $options);
    }

    /**
     * Generate speech audio for the given text.
     */
    public function speak(string $text, array $options = []): array
    {
        return $this->callProvider('speak', [$text, $options], $options);
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
     * Queue a vision request.
     *
     * @param string|array<int, string> $images
     */
    public function queueVision(string $prompt, string|array $images, array $options = []): mixed
    {
        return $this->queue('vision', [$prompt, $images, $options], $options);
    }

    /**
     * Queue an embeddings job.
     */
    public function queueEmbeddings(string|array $input, array $options = []): mixed
    {
        return $this->queue('embeddings', [$input, $options], $options);
    }

    /**
     * Queue an audio transcription job.
     */
    public function queueTranscribe(string $path, array $options = []): mixed
    {
        return $this->queue('transcribe', [$path, $options], $options);
    }

    /**
     * Queue a speech generation job.
     */
    public function queueSpeak(string $text, array $options = []): mixed
    {
        return $this->queue('speak', [$text, $options], $options);
    }

    /**
     * Dispatch a provider call or queue it when async is requested.
     */
    protected function callProvider(string $method, array $args, array $options = []): array
    {
        if (Arr::get($options, 'async')) {
            return ['queued' => true, 'job' => $this->queue($method, $args, $options)];
        }

        $cacheEnabled = $this->cacheEnabled($options);
        $providers = $this->resolveProviders($options);
        $lastException = null;
        $supported = false;

        foreach ($providers as $providerName) {
            try {
                $provider = $this->provider($providerName);
                $providerType = $this->resolveProviderType($provider, $method);

                if ($providerType === null) {
                    continue;
                }

                $supported = true;
                $cacheKey = null;
                $context = [
                    'trace_id' => $options['trace_id'] ?? null,
                    'started_at' => microtime(true),
                ];

                [$args, $options, $context] = $this->applyMiddlewaresBefore(
                    $provider->name(),
                    $method,
                    $args,
                    $options,
                    $context
                );

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

                if ($cacheEnabled && $cacheKey !== null) {
                    $cacheTtl = $this->cacheTtl($options);
                    $this->cacheStore()->put($cacheKey, $response, $cacheTtl);
                }

                [$response, $context] = $this->applyMiddlewaresAfter(
                    $provider->name(),
                    $method,
                    $response,
                    $context
                );

                $this->dispatchAfterRequest($provider->name(), $method, $args, $options, $response);
                $this->recordUsage($provider->name(), $method, $options, $response);
                $this->dispatchTiming($provider->name(), $method, $context);

                $response = $this->validateStructuredOutput($method, $response, $options);

                if ($this->dtoEnabled($options)) {
                    return ResponseFactory::make($method, $response);
                }

                return $response;
            } catch (\Throwable $exception) {
                $this->healthStore()->markFailure($providerName);
                $lastException = $exception;
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        if (!$supported && in_array($method, ['vision', 'transcribe', 'speak'], true)) {
            throw new UnsupportedFeatureException('LarAI provider does not support this feature.');
        }

        throw new LarAIException('LarAI provider resolution failed.');
    }

    /**
     * Dispatch a streaming provider call.
     *
     * @return iterable<int, string>
     */
    protected function streamProvider(string $method, array $args, array $options = [], ?callable $onChunk = null): iterable
    {
        $providers = $this->resolveProviders($options);

        foreach ($providers as $providerName) {
            try {
                $provider = $this->provider($providerName);

                if (!$provider instanceof StreamingProvider) {
                    continue;
                }

        return (function () use ($provider, $method, $args, $onChunk) {
                    foreach ($provider->{$method}(...$args) as $chunk) {
                        if ($onChunk) {
                            $onChunk($chunk);
                        }

                        yield $chunk;
                    }
                })();
            } catch (\Throwable $exception) {
                continue;
            }
        }

        throw new UnsupportedFeatureException('LarAI streaming is not supported by the configured providers.');
    }

    /**
     * Push a LarAI job onto the queue.
     */
    protected function queue(string $action, array $args, array $options = []): mixed
    {
        if (!Config::get('larai.queue.enabled')) {
            throw new UnsupportedFeatureException('LarAI queue support is disabled.');
        }

        $provider = Arr::get($options, 'provider');

        if ($provider) {
            $this->enforceQueueRateLimit($provider);
        }

        $job = new LarAIJob($action, $args, $provider);
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
     * Enforce per-provider queue budgets.
     */
    protected function enforceQueueRateLimit(string $provider): void
    {
        if (!Config::get('larai.queue.rate_limits.enabled', false)) {
            return;
        }

        $limits = Config::get("larai.queue.rate_limits.providers.$provider", null);

        if (!$limits || !is_array($limits)) {
            return;
        }

        $maxPerMinute = (int) ($limits['per_minute'] ?? 0);

        if ($maxPerMinute <= 0) {
            return;
        }

        $key = 'larai:queue:rate:' . $provider . ':' . now()->format('YmdHi');
        $count = Cache::increment($key);

        if ($count === 1) {
            Cache::put($key, $count, 60);
        }

        if ($count > $maxPerMinute) {
            throw new LarAIException("Queue rate limit exceeded for provider [$provider].");
        }
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
        return Arr::except($options, [
            'async',
            'cache',
            'cache_ttl',
            'provider',
            'fallback',
            'routing',
            'response_schema',
            'dto',
            'trace_id',
        ]);
    }

    /**
     * Resolve provider candidates for a request.
     *
     * @return array<int, string>
     */
    protected function resolveProviders(array $options): array
    {
        if (Config::get('larai.routing.enabled', false)) {
            return $this->router()->resolve($options);
        }

        $override = Arr::get($options, 'provider');

        if (is_array($override)) {
            return array_values(array_unique(array_filter($override)));
        }

        $primary = $override ?: Config::get('larai.default', 'openai');
        $fallbackEnabled = Arr::get($options, 'fallback', Config::get('larai.fallback.enabled', true));
        $fallbacks = $fallbackEnabled ? (Config::get('larai.fallback.providers', []) ?: []) : [];

        if (!is_array($fallbacks)) {
            $fallbacks = [];
        }

        return array_values(array_unique(array_filter(array_merge([$primary], $fallbacks))));
    }

    /**
     * Load configured request/response middlewares.
     *
     * @return array<int, Middleware>
     */
    protected function loadMiddlewares(): array
    {
        $middlewares = Config::get('larai.middlewares', []);
        $instances = [];

        foreach ($middlewares as $middleware) {
            if (is_string($middleware) && class_exists($middleware)) {
                $instances[] = App::make($middleware);
            }
        }

        return $instances;
    }

    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     * @return array{0: array<int, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    protected function applyMiddlewaresBefore(
        string $provider,
        string $method,
        array $args,
        array $options,
        array $context
    ): array {
        foreach ($this->loadMiddlewares() as $middleware) {
            $result = $middleware->before($provider, $method, $args, $options, $context);
            $args = $result['args'];
            $options = $result['options'];
            $context = $result['context'];
        }

        return [$args, $options, $context];
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $context
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function applyMiddlewaresAfter(
        string $provider,
        string $method,
        array $response,
        array $context
    ): array {
        foreach ($this->loadMiddlewares() as $middleware) {
            $result = $middleware->after($provider, $method, $response, $context);
            $response = $result['response'];
            $context = $result['context'];
        }

        return [$response, $context];
    }

    /**
     * Dispatch a timing event for observability.
     *
     * @param array<string, mixed> $context
     */
    protected function dispatchTiming(string $provider, string $method, array $context): void
    {
        if (!Config::get('larai.observability.enabled', true)) {
            return;
        }

        $start = $context['started_at'] ?? null;
        if (!is_float($start)) {
            return;
        }

        $duration = (microtime(true) - $start) * 1000;

        Event::dispatch(new LarAIRequestTimed(
            provider: $provider,
            method: $method,
            durationMs: $duration,
            context: $context
        ));
    }

    /**
     * Validate structured outputs with a JSON schema.
     *
     * @param array<string, mixed> $response
     */
    protected function validateStructuredOutput(string $method, array $response, array $options): array
    {
        $schema = $options['response_schema'] ?? null;

        if (!$schema || !is_array($schema)) {
            return $response;
        }

        $payload = $response['content'] ?? $response['raw'] ?? null;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }

        if ($payload !== null) {
            App::make(SchemaValidator::class)->validate($schema, $payload);
        }

        return $response;
    }

    /**
     * Determine if DTO responses should be returned.
     */
    protected function dtoEnabled(array $options): bool
    {
        return (bool) Arr::get($options, 'dto', Config::get('larai.dto.enabled', false));
    }

    protected function router(): ProviderRouter
    {
        return App::make(ProviderRouter::class);
    }

    protected function healthStore(): HealthStore
    {
        return App::make(HealthStore::class);
    }

    /**
     * Ensure the provider supports the requested method.
     */
    protected function resolveProviderType(Provider $provider, string $method): ?Provider
    {
        return match ($method) {
            'vision' => $provider instanceof VisionProvider ? $provider : null,
            'transcribe', 'speak' => $provider instanceof AudioProvider ? $provider : null,
            default => $provider,
        };
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
