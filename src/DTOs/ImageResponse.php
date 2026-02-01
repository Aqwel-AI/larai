<?php

namespace AqwelAI\LarAI\DTOs;

/**
 * Image generation response DTO.
 */
class ImageResponse extends BaseResponse
{
    /**
     * @param array<int, mixed> $images
     */
    public function __construct(
        public array $images = [],
        array $raw = [],
        array $usage = [],
    ) {
        parent::__construct($raw, $usage);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'images' => $this->images,
        ]);
    }
}
