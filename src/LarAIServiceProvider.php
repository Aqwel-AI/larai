<?php

namespace AqwelAI\LarAI;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use AqwelAI\LarAI\Services\EmbeddingsService;
use AqwelAI\LarAI\Services\ImageService;
use AqwelAI\LarAI\Services\TextService;

/**
 * Registers LarAI bindings and publishable config.
 */
class LarAIServiceProvider extends ServiceProvider
{
    /**
     * Register the LarAI singleton and merge defaults.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/larai.php', 'larai');

        $this->app->singleton(LarAI::class, function () {
            return new LarAI();
        });

        $this->app->singleton(TextService::class, function ($app) {
            return new TextService($app->make(LarAI::class));
        });

        $this->app->singleton(ImageService::class, function ($app) {
            return new ImageService($app->make(LarAI::class));
        });

        $this->app->singleton(EmbeddingsService::class, function ($app) {
            return new EmbeddingsService($app->make(LarAI::class));
        });
    }

    /**
     * Publish config for host applications.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/larai.php' => App::configPath('larai.php'),
        ], 'larai-config');
    }
}
