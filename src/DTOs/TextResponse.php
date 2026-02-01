<?php

namespace AqwelAI\LarAI\DTOs;

/**
 * Text/chat response DTO.
 */
class TextResponse extends BaseResponse
{
    public function __construct(
        public string $content = '',
        array $raw = [],
        array $usage = [],
        public array $toolCalls = [],
    ) {
        parent::__construct($raw, $usage);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'content' => $this->content,
            'tool_calls' => $this->toolCalls,
        ]);
    }
}
