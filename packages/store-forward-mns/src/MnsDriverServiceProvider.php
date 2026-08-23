<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardMns;

use AftermathPathfinder\StoreForward\StoreForwardManager;
use AliyunMNS\Client;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the 'mns' transport driver with the core package's manager.
 */
class MnsDriverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(StoreForwardManager::class)->extend('mns', function (array $config) {
            $required = fn (string $key) => $config[$key] ?? throw new \InvalidArgumentException(
                "store-forward.drivers.mns.{$key} is required to use the 'mns' driver."
            );

            $client = new Client(
                $required('endpoint'),
                $required('access_id'),
                $required('access_key'),
            );

            return new MnsTransport($client->getTopicRef($required('topic_name')));
        });
    }
}
