<?php

namespace AqwelAI\LarAI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use AqwelAI\LarAI\LarAI;

/**
 * Queueable job wrapper for LarAI actions.
 */
class LarAIJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<int, mixed> $args Method arguments for the action.
     */
    public function __construct(
        public string $action,
        public array $args,
        public ?string $provider = null
    ) {
    }

    /**
     * Execute the queued action.
     */
    public function handle(LarAI $larai): void
    {
        $allowed = [
            'text',
            'chat',
            'image',
            'summarize',
            'embeddings',
        ];

        if (!in_array($this->action, $allowed, true)) {
            return;
        }

        $options = $this->args[1] ?? [];
        $options['provider'] = $this->provider ?? ($options['provider'] ?? null);
        $this->args[1] = $options;

        $larai->{$this->action}(...$this->args);
    }
}
