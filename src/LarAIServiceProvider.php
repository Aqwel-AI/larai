<?php

namespace AqwelAI\LarAI;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use AqwelAI\LarAI\Console\LarAIRunCommand;
use AqwelAI\LarAI\Http\Controllers\DashboardController;
use AqwelAI\LarAI\Listeners\StoreUsageLog;
use AqwelAI\LarAI\Middleware\RedactMiddleware;
use AqwelAI\LarAI\Middleware\TraceIdMiddleware;
use AqwelAI\LarAI\Policies\DenylistPolicy;
use AqwelAI\LarAI\Policies\PolicyEngine;
use AqwelAI\LarAI\Routing\HealthStore;
use AqwelAI\LarAI\Routing\ProviderRouter;
use AqwelAI\LarAI\Schema\SchemaValidator;
use AqwelAI\LarAI\Services\EmbeddingsService;
use AqwelAI\LarAI\Services\ImageService;
use AqwelAI\LarAI\Services\PromptRegistry;
use AqwelAI\LarAI\Services\RagService;
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

        $this->app->singleton(HealthStore::class, function () {
            return new HealthStore();
        });

        $this->app->singleton(ProviderRouter::class, function ($app) {
            return new ProviderRouter($app->make(HealthStore::class));
        });

        $this->app->singleton(SchemaValidator::class, function () {
            return new SchemaValidator();
        });

        $this->app->singleton(PolicyEngine::class, function () {
            $policies = config('larai.policies', []);
            $instances = [];

            foreach ($policies as $policy) {
                if (is_string($policy) && class_exists($policy)) {
                    $instances[] = new $policy();
                }
            }

            $instances[] = new DenylistPolicy(config('larai.policies_denylist', []));

            return new PolicyEngine($instances);
        });

        $this->app->singleton(TraceIdMiddleware::class, function () {
            return new TraceIdMiddleware();
        });

        $this->app->singleton(RedactMiddleware::class, function ($app) {
            return new RedactMiddleware($app->make(PolicyEngine::class));
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

        $this->app->singleton(RagService::class, function ($app) {
            return new RagService($app->make(LarAI::class));
        });

        $this->app->singleton(PromptRegistry::class, function () {
            return new PromptRegistry();
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

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (config('larai.dashboard.enabled', false)) {
            Route::middleware(config('larai.dashboard.middleware', ['web']))
                ->prefix(config('larai.dashboard.path', 'larai'))
                ->group(function () {
                    Route::get('/', [DashboardController::class, 'index']);
                });
        }

        if (config('larai.dashboard.store_usage', false)) {
            Event::listen(\AqwelAI\LarAI\Events\LarAIUsageReported::class, StoreUsageLog::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                LarAIRunCommand::class,
            ]);
        }
    }
}
