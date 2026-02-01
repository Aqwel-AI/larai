<?php

namespace AqwelAI\LarAI\Policies;

use Illuminate\Support\Facades\Config;

/**
 * Apply policies to requests.
 */
class PolicyEngine
{
    /**
     * @var array<int, Policy>
     */
    protected array $policies = [];

    /**
     * @param array<int, Policy> $policies
     */
    public function __construct(array $policies = [])
    {
        $this->policies = $policies;
    }

    public function loadFromConfig(): void
    {
        $config = Config::get('larai.policies', []);

        if (!is_array($config)) {
            return;
        }

        $policies = [];

        foreach ($config as $policy) {
            if (is_string($policy) && class_exists($policy)) {
                $policies[] = new $policy();
            }
        }

        $this->policies = $policies;
    }

    /**
     * @param array<int, mixed> $args
     * @param array<string, mixed> $options
     * @return array{args: array<int, mixed>, options: array<string, mixed>}
     */
    public function sanitize(string $method, array $args, array $options): array
    {
        foreach ($this->policies as $policy) {
            $result = $policy->apply($method, $args, $options);
            $args = $result['args'];
            $options = $result['options'];
        }

        return [
            'args' => $args,
            'options' => $options,
        ];
    }
}
