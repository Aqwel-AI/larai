<?php

namespace AqwelAI\LarAI\DTOs;

/**
 * Vision response DTO.
 */
class VisionResponse extends BaseResponse
{
    public function __construct(
        public string $content = '',
        array $raw = [],
        array $usage = [],
    ) {
        parent::__construct($raw, $usage);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'content' => $this->content,
        ]);
    }
}
