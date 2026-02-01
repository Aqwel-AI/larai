<?php

namespace AqwelAI\LarAI\DTOs;

/**
 * Base response DTO.
 */
class BaseResponse
{
    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $usage
     */
    public function __construct(
        public array $raw = [],
        public array $usage = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw' => $this->raw,
            'usage' => $this->usage,
        ];
    }
}
