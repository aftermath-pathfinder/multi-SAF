<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardRedisStreams;

use AftermathPathfinder\StoreForward\StoreForwardManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the 'redis-streams' transport driver with the core package's
 * manager. Requires no new infrastructure beyond Redis itself — reuses
 * the app's existing Redis connection configuration.
 */
class RedisStreamsDriverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(StoreForwardManager::class)->extend('redis-streams', function (array $config) {
            $connection = $this->app->make('redis')->connection($config['connection'] ?? null);

            $stream = $config['stream'] ?? throw new \InvalidArgumentException(
                "store-forward.drivers.redis-streams.stream is required to use the 'redis-streams' driver."
            );

            return new RedisStreamsTransport($connection, $stream);
        });
    }
}
