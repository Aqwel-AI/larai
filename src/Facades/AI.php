<?php

namespace AqwelAI\LarAI\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Convenience facade alias for LarAI.
 *
 * @method static array text(string $prompt, array $options = [])
 * @method static array chat(array $messages, array $options = [])
 * @method static iterable streamText(string $prompt, array $options = [], ?callable $onChunk = null)
 * @method static iterable streamChat(array $messages, array $options = [], ?callable $onChunk = null)
 * @method static array image(string $prompt, array $options = [])
 * @method static array summarize(string $text, array $options = [])
 * @method static array vision(string $prompt, string|array $images, array $options = [])
 * @method static array summarizeFile(string $path, array $options = [])
 * @method static array embeddings(string|array $input, array $options = [])
 * @method static array transcribe(string $path, array $options = [])
 * @method static array speak(string $text, array $options = [])
 * @method static array embeddingsFile(string $path, array $options = [])
 * @method static array recommend(string $query, array $candidates, array $options = [])
 * @method static string prompt(string $name, array $vars = [])
 * @method static void registerProvider(string $name, \AqwelAI\LarAI\Contracts\Provider $provider)
 * @method static mixed queueText(string $prompt, array $options = [])
 * @method static mixed queueChat(array $messages, array $options = [])
 * @method static mixed queueImage(string $prompt, array $options = [])
 * @method static mixed queueSummarize(string $text, array $options = [])
 * @method static mixed queueEmbeddings(string|array $input, array $options = [])
 * @method static mixed queueVision(string $prompt, string|array $images, array $options = [])
 * @method static mixed queueTranscribe(string $path, array $options = [])
 * @method static mixed queueSpeak(string $text, array $options = [])
 */
class AI extends Facade
{
    /**
     * Resolve the underlying LarAI instance.
     */
    protected static function getFacadeAccessor(): string
    {
        return \AqwelAI\LarAI\LarAI::class;
    }
}
