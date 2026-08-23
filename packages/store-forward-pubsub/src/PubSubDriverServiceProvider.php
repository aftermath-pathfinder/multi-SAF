<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardPubSub;

use AftermathPathfinder\StoreForward\StoreForwardManager;
use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the 'pubsub' transport driver with the core package's manager.
 */
class PubSubDriverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(StoreForwardManager::class)->extend('pubsub', function (array $config) {
            $client = new PubSubClient(array_filter([
                'projectId' => $config['project_id'] ?? null,
                'keyFilePath' => $config['key_file_path'] ?? null,
            ]));

            $topicName = $config['topic_name'] ?? throw new \InvalidArgumentException(
                "store-forward.drivers.pubsub.topic_name is required to use the 'pubsub' driver."
            );

            return new PubSubTransport($client->topic($topicName));
        });
    }
}
