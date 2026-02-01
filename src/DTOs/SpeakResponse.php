<?php

namespace AqwelAI\LarAI\DTOs;

/**
 * Speech generation response DTO.
 */
class SpeakResponse extends BaseResponse
{
    public function __construct(
        public string $audio = '',
        public string $format = 'mp3',
        array $raw = [],
        array $usage = [],
    ) {
        parent::__construct($raw, $usage);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'audio' => $this->audio,
            'format' => $this->format,
        ]);
    }
}
