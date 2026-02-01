<?php

namespace AqwelAI\LarAI\DTOs;

/**
 * Transcription response DTO.
 */
class TranscribeResponse extends BaseResponse
{
    public function __construct(
        public string $text = '',
        array $raw = [],
        array $usage = [],
    ) {
        parent::__construct($raw, $usage);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'text' => $this->text,
        ]);
    }
}
