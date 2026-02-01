<?php

namespace AqwelAI\LarAI\Routing;

use Illuminate\Support\Facades\Cache;

/**
 * Track provider health in cache.
 */
class HealthStore
{
    public function markFailure(string $provider, int $ttl = 300): void
    {
        Cache::put($this->key($provider), false, $ttl);
    }

    public function markHealthy(string $provider, int $ttl = 300): void
    {
        Cache::put($this->key($provider), true, $ttl);
    }

    public function isHealthy(string $provider): bool
    {
        return Cache::get($this->key($provider), true) === true;
    }

    protected function key(string $provider): string
    {
        return 'larai:health:' . $provider;
    }
}
