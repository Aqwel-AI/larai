<?php

namespace AqwelAI\LarAI\Policies;

use AqwelAI\LarAI\Exceptions\LarAIException;

/**
 * Blocks requests that contain denylisted terms.
 */
class DenylistPolicy implements Policy
{
    /**
     * @param array<int, string> $terms
     */
    public function __construct(protected array $terms = [])
    {
    }

    public function apply(string $method, array $args, array $options): array
    {
        if ($this->terms === []) {
            return [
                'args' => $args,
                'options' => $options,
            ];
        }

        $flat = json_encode($args);

        if ($flat && $this->matches($flat)) {
            throw new LarAIException('Request blocked by denylist policy.');
        }

        return [
            'args' => $args,
            'options' => $options,
        ];
    }

    protected function matches(string $text): bool
    {
        foreach ($this->terms as $term) {
            if ($term !== '' && stripos($text, $term) !== false) {
                return true;
            }
        }

        return false;
    }
}
