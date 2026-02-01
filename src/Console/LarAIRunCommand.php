<?php

namespace AqwelAI\LarAI\Console;

use AqwelAI\LarAI\LarAI;
use Illuminate\Console\Command;

/**
 * Simple CLI runner for LarAI.
 */
class LarAIRunCommand extends Command
{
    protected $signature = 'larai:run {method=text} {input} {--provider=}';

    protected $description = 'Run a LarAI request from the CLI';

    public function handle(LarAI $larai): int
    {
        $method = $this->argument('method');
        $input = $this->argument('input');
        $provider = $this->option('provider');

        $options = [];
        if ($provider) {
            $options['provider'] = $provider;
        }

        $response = match ($method) {
            'chat' => $larai->chat(json_decode($input, true) ?? [], $options),
            'vision' => $larai->vision($input, $this->ask('Image URL'), $options),
            default => $larai->text($input, $options),
        };

        $this->line(json_encode($response, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
