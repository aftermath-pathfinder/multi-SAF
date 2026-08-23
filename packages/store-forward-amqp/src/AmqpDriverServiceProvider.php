<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardAmqp;

use AftermathPathfinder\StoreForward\StoreForwardManager;
use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Registers the 'amqp' transport driver with the core package's manager.
 *
 * Like the Kafka driver, the connection is opened once when this driver is
 * first resolved and reused for the life of the process — ideal for
 * `store-forward:work`, less so for synchronous (`immediate: true`) sends
 * from short-lived web requests.
 */
class AmqpDriverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(StoreForwardManager::class)->extend('amqp', function (array $config) {
            $connection = new AMQPStreamConnection(
                $config['host'] ?? 'localhost',
                $config['port'] ?? 5672,
                $config['user'] ?? 'guest',
                $config['password'] ?? 'guest',
                $config['vhost'] ?? '/',
            );

            $channel = $connection->channel();

            $queue = $config['queue'] ?? throw new \InvalidArgumentException(
                "store-forward.drivers.amqp.queue is required to use the 'amqp' driver."
            );

            // Declaring here (durable, not auto-deleted) makes the driver
            // work out of the box against a fresh broker; it's a no-op
            // against a queue that's already declared identically.
            $channel->queue_declare($queue, false, true, false, false);

            return new AmqpTransport($channel, $config['exchange'] ?? '', $config['routing_key'] ?? $queue);
        });
    }
}
