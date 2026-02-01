<?php

namespace AqwelAI\LarAI\Routing;

use Illuminate\Support\Facades\Config;

/**
 * Resolve provider order based on routing strategy.
 */
class ProviderRouter
{
    public function __construct(protected HealthStore $healthStore)
    {
    }

    /**
     * @return array<int, string>
     */
    public function resolve(array $options = []): array
    {
        $override = $options['provider'] ?? null;

        if (is_array($override)) {
            return array_values(array_unique(array_filter($override)));
        }

        $providers = Config::get('larai.routing.providers', []);

        if (!is_array($providers) || $providers === []) {
            $default = $override ?: Config::get('larai.default', 'openai');
            return [$default];
        }

        $strategy = $options['routing'] ?? Config::get('larai.routing.strategy', 'cost');
        $candidates = $this->sortProviders($providers, $strategy);
        $result = [];

        foreach ($candidates as $provider) {
            if ($this->healthStore->isHealthy($provider)) {
                $result[] = $provider;
            }
        }

        return $result === [] ? $candidates : $result;
    }

    /**
     * @param array<string, array<string, mixed>> $providers
     * @return array<int, string>
     */
    protected function sortProviders(array $providers, string $strategy): array
    {
        $keys = array_keys($providers);

        if ($strategy === 'latency') {
            usort($keys, function ($a, $b) use ($providers) {
                return ($providers[$a]['latency'] ?? 999) <=> ($providers[$b]['latency'] ?? 999);
            });
        } else {
            usort($keys, function ($a, $b) use ($providers) {
                return ($providers[$a]['cost'] ?? 999) <=> ($providers[$b]['cost'] ?? 999);
            });
        }

        return $keys;
    }
}
