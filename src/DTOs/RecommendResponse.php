<?php

namespace AqwelAI\LarAI\DTOs;

/**
 * Recommendations response DTO.
 */
class RecommendResponse extends BaseResponse
{
    /**
     * @param array<int, mixed> $recommendations
     */
    public function __construct(
        public array $recommendations = [],
        array $raw = [],
        array $usage = [],
    ) {
        parent::__construct($raw, $usage);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'recommendations' => $this->recommendations,
        ]);
    }
}
