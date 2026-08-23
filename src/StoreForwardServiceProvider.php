<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward;

use AftermathPathfinder\StoreForward\Console\Commands\RetryCommand;
use AftermathPathfinder\StoreForward\Console\Commands\WorkCommand;
use AftermathPathfinder\StoreForward\Contracts\StoreInterface;
use AftermathPathfinder\StoreForward\Stores\DatabaseStore;
use Illuminate\Support\ServiceProvider;

class StoreForwardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/store-forward.php', 'store-forward');

        $this->app->singleton('store-forward', function ($app) {
            return new StoreForwardManager($app);
        });

        $this->app->alias('store-forward', StoreForwardManager::class);

        $this->app->singleton(StoreInterface::class, function ($app) {
            $config = $app['config']['store-forward'];

            return new DatabaseStore(
                $app['db']->connection($config['connection'] ?? null),
                $config['table'] ?? 'store_forward_messages',
            );
        });

        $this->app->singleton(Dispatcher::class, function ($app) {
            return new Dispatcher(
                $app['store-forward'],
                $app['events'],
                (int) $app['config']['store-forward']['max_attempts'] ?? 10,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/store-forward.php' => config_path('store-forward.php'),
            ], 'store-forward-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_store_forward_messages_table.php.stub' => database_path(
                    'migrations/'.date('Y_m_d_His').'_create_store_forward_messages_table.php'
                ),
            ], 'store-forward-migrations');
        }

        // Deliberately NOT gated behind runningInConsole(): that check
        // reflects how the *current* request was invoked, not whether
        // Artisan commands will ever be needed. A web request that calls
        // Artisan::call('store-forward:work', ...) — exactly what the
        // playground app's "process pending now" button does — triggers
        // Laravel's console kernel lazily, using commands registered
        // during THIS boot() call; if registration were skipped here,
        // that command would never exist for the rest of the request.
        $this->commands([
            WorkCommand::class,
            RetryCommand::class,
        ]);
    }
}
