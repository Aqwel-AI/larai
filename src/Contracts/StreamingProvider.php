<?php

namespace AqwelAI\LarAI\Contracts;

/**
 * Optional streaming provider contract for incremental responses.
 */
interface StreamingProvider
{
    /**
     * Stream text chunks from a prompt.
     *
     * @return iterable<int, string>
     */
    public function streamText(string $prompt, array $options = []): iterable;

    /**
     * Stream chat chunks from role-based messages.
     *
     * @return iterable<int, string>
     */
    public function streamChat(array $messages, array $options = []): iterable;
}
