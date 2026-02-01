<?php

namespace AqwelAI\LarAI\DTOs;

/**
 * Embeddings response DTO.
 */
class EmbeddingsResponse extends BaseResponse
{
    /**
     * @param array<int, mixed> $embeddings
     */
    public function __construct(
        public array $embeddings = [],
        array $raw = [],
        array $usage = [],
    ) {
        parent::__construct($raw, $usage);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'embeddings' => $this->embeddings,
        ]);
    }
}
