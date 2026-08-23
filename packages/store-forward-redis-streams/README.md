# store-forward-redis-streams

Self-hosted Redis Streams transport driver for
[`aftermath-pathfinder/store-forward`](../../README.md). No new SDK — it
reuses your Laravel app's existing Redis connection (Predis or the
phpredis extension, whichever `config('database.redis.client')` selects).
If you already run Redis for cache or queues, there's nothing new to
install or operate to use this driver.

## Install

```bash
composer require aftermath-pathfinder/store-forward-redis-streams
```

Your app needs a working Redis connection either way (`predis/predis` or
the `redis` PECL extension) — this package doesn't require either
directly since most Laravel apps already have one configured.

## Configure

```php
// config/store-forward.php
'channels' => [
    'orders.created' => ['driver' => 'redis-streams'],
],
'drivers' => [
    'redis-streams' => [
        'connection' => null, // null = default Redis connection (config/database.php)
        'stream' => env('REDIS_ORDERS_STREAM', 'orders'),
    ],
],
```

## Why this is the lightest self-hosted option

Kafka and RabbitMQ are purpose-built brokers with real operational weight
(clustering, ZooKeeper/KRaft, disk-backed logs). Redis Streams gets you
durable, ordered, at-least-once delivery with consumer groups, running on
infrastructure most Laravel apps already have. The tradeoff: Redis
Streams' durability depends on your Redis persistence config (AOF/RDB) —
it's not a purpose-built log the way Kafka is, so for very high-durability
requirements prefer Kafka or a managed cloud service instead.

## Verification

This package's test suite mocks `Illuminate\Contracts\Redis\Connection`
to verify the adapter calls `command('xAdd', ...)` with the right stream
and field shape — it does not talk to a real Redis server. Verify
end-to-end against a real (or local Docker) Redis before relying on this
in production; the playground app's `docker-compose.yml` includes one for
exactly that.
