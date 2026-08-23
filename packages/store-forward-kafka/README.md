# store-forward-kafka

Self-hosted Kafka transport driver for
[`aftermath-pathfinder/store-forward`](../../README.md), using
[`longlang/phpkafka`](https://github.com/swoole/phpkafka) — a pure-PHP
client, not `rdkafka` (a PECL extension). That tradeoff is deliberate: no
extension to compile, no custom Docker image just to install this driver.

## Install

```bash
composer require aftermath-pathfinder/store-forward-kafka
```

## Configure

```php
// config/store-forward.php
'channels' => [
    'orders.created' => ['driver' => 'kafka'],
],
'drivers' => [
    'kafka' => [
        'brokers' => env('KAFKA_BROKERS', 'localhost:9092'), // comma-separated for multiple
        'topic' => env('KAFKA_ORDERS_TOPIC'),
        'acks' => -1, // -1 = all in-sync replicas, 1 = leader only, 0 = fire-and-forget
    ],
],
```

## Connection lifecycle

The producer connects once, when this driver is first resolved, and that
connection is reused for the life of the process. That's ideal for the
`store-forward:work` worker (long-running, one connection for many
messages) but means a short-lived web request using `immediate: true`
reconnects on every request — one more reason to prefer the default
outbox+worker flow for this driver rather than synchronous sends.

## Verification

This package's test suite mocks `longlang\phpkafka\Producer\Producer` to
verify the adapter calls `send()`/`sendBatch()` with the right topic and
payload shape — it does not talk to a real Kafka broker. Verify
end-to-end against a real (or local Docker) broker before relying on this
in production; the playground app's `docker-compose.yml` includes one for
exactly that.
