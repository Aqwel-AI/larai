<?php

namespace AqwelAI\LarAI\Contracts;

/**
 * Optional audio provider contract for transcription and speech.
 */
interface AudioProvider
{
    /**
     * Transcribe an audio file from a local path.
     */
    public function transcribe(string $path, array $options = []): array;

    /**
     * Generate speech audio for the given text.
     */
    public function speak(string $text, array $options = []): array;
}
