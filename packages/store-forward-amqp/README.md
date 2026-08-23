# store-forward-amqp

Self-hosted RabbitMQ transport driver for
[`aftermath-pathfinder/store-forward`](../../README.md), using the
standard [`php-amqplib/php-amqplib`](https://github.com/php-amqplib/php-amqplib)
client.

## Install

```bash
composer require aftermath-pathfinder/store-forward-amqp
```

## Configure

```php
// config/store-forward.php
'channels' => [
    'orders.created' => ['driver' => 'amqp'],
],
'drivers' => [
    'amqp' => [
        'host' => env('RABBITMQ_HOST', 'localhost'),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'queue' => env('RABBITMQ_ORDERS_QUEUE', 'orders'),
        // Leave 'exchange' as '' (the default exchange) to publish
        // directly to 'queue' by name — the common case. Set both
        // 'exchange' and 'routing_key' to publish through a real exchange.
        'exchange' => '',
    ],
],
```

The driver declares the configured queue as durable (not auto-deleted) on
first use, so it works against a fresh broker with no manual setup.

## Connection lifecycle

Same consideration as the Kafka driver: the connection and channel open
once, when this driver is first resolved, and are reused for the life of
the process. Best suited to the `store-forward:work` worker.

## Verification

This package's test suite mocks `PhpAmqpLib\Channel\AMQPChannel` to verify
the adapter calls `basic_publish()` with a persistent `AMQPMessage` and the
right exchange/routing key — it does not talk to a real broker. Verify
end-to-end against a real (or local Docker) RabbitMQ before relying on
this in production; the playground app's `docker-compose.yml` includes
one for exactly that.
