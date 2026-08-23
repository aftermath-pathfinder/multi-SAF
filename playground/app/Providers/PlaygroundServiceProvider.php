<?php

declare(strict_types=1);

namespace App\Providers;

use AftermathPathfinder\StoreForward\StoreForwardManager;
use App\StoreForwardDemo\FlakyTransport;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the playground-only 'flaky' demo driver. Every real driver
 * (sqs, pubsub, mns, kafka, amqp, redis-streams) is registered by its own
 * package's service provider — this is the one exception, since 'flaky'
 * isn't a real broker and has no reason to exist outside this demo.
 */
class PlaygroundServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(StoreForwardManager::class)->extend('flaky', function (array $config) {
            return new FlakyTransport((int) ($config['failure_percent'] ?? 60));
        });
    }
}
