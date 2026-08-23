# store-forward

A transport-agnostic **store-and-forward** library for Laravel. One publish
API; the actual delivery mechanism — Kafka, RabbitMQ, SQS, Redis Streams,
a webhook, or Laravel's own queue — is a swappable driver, installable as
its own small Composer package.

> Kafka, RabbitMQ, SQS, Redis Streams — underneath, they're all the same
> idea: durably record that a message needs to go out, then produce it.
> "Store, then forward" is the primitive; the broker is a delivery detail.

## The core idea (brainstorm notes)

**Store-and-forward, not "publish and pray".** `publish()` never talks to
a broker directly. It writes an `Envelope` to a durable local outbox
(a DB table by default) in the same request/transaction as the business
change that produced it. A separate worker (`store-forward:work`) drains
that outbox and hands each envelope to the transport configured for its
channel. If the broker is down, mid-deploy, or the process dies right
after the DB commit, nothing is lost — the row is still there waiting.
This is the transactional outbox pattern, generalized across brokers
instead of tied to one.

**Transports are the only broker-specific code, and they're tiny.**
A `TransportInterface` has exactly two methods: `send()` and `sendBatch()`.
Everything else — durability, retry/backoff, dead-lettering, ordering
ahead of the send — is the outbox's and dispatcher's job, not the
transport's. That's deliberately small enough that a Kafka or AMQP driver
is a few dozen lines wrapping an existing PHP client, which is what makes
"ship it as a separate, optional Composer package" realistic.

**Why split into multiple Composer packages instead of one big one:**

| Package | Depends on | Ships |
|---|---|---|
| `aftermath-pathfinder/store-forward` (this repo) | nothing but Laravel | core: outbox, dispatcher, manager, `sync`/`log`/`queue` drivers |
| `aftermath-pathfinder/store-forward-kafka` | `rdkafka` ext or a pure-PHP client | `KafkaTransport` |
| `aftermath-pathfinder/store-forward-amqp` | `php-amqplib` | `AmqpTransport` (RabbitMQ, etc.) |
| `aftermath-pathfinder/store-forward-sqs` | `aws/aws-sdk-php` | `SqsTransport` |

Nobody who just wants "reliable delivery over the queue I already have"
is forced to install `rdkafka` or the AWS SDK. Each driver package's
service provider calls `StoreForward::extend('kafka', fn ($config) => new
KafkaTransport($config))` in its `boot()` — no changes to core needed to
add a broker.

**Producing and consuming are separate concerns.** This library owns
*producing* reliably. For consuming, brokers that Laravel already has
first-class queue connectors for (SQS, Redis) can be consumed with
`queue:work` as normal; a Kafka/AMQP consumer is a thin bridge that reads
from the broker and dispatches into Laravel's queue, so the rest of the
app (jobs, retries, failed_jobs table) doesn't need to know or care what
produced the message.

**Everything is at-least-once.** Retries with backoff happen naturally
when store-and-forward is in play (a send can be retried without
re-doing the business logic that queued it). Consumers should be
idempotent — every `Envelope` carries a stable UUID for that purpose.

## Install

```bash
composer require aftermath-pathfinder/store-forward
php artisan vendor:publish --tag=store-forward-config
php artisan vendor:publish --tag=store-forward-migrations
php artisan migrate
```

## Usage

```php
use AftermathPathfinder\StoreForward\Facades\StoreForward;

StoreForward::publish('orders.created', [
    'order_id' => $order->id,
    'total' => $order->total,
]);
```

Run the worker (a supervisor-managed long-running process, same shape as
`queue:work`):

```bash
php artisan store-forward:work
```

Route a channel to a driver in `config/store-forward.php`:

```php
'channels' => [
    'orders.created' => ['driver' => 'kafka'],
    'notifications.sms' => ['driver' => 'sqs'],
    // anything unlisted falls back to 'default'
],
```

Register a custom/third-party driver from any service provider:

```php
StoreForward::extend('kafka', function (array $config) {
    return new \AftermathPathfinder\StoreForwardKafka\KafkaTransport($config);
});
```

Retry dead-lettered messages after fixing whatever broke:

```bash
php artisan store-forward:retry
php artisan store-forward:retry --channel=orders.created
```

## What ships in core

- `Envelope` — the message DTO (id, channel, payload, headers, key, timestamp).
- `StoreInterface` / `DatabaseStore` — the durable outbox (one migration, one table).
- `TransportInterface` — the two-method contract every driver implements.
- Built-in transports: `sync` (inline, tests/local), `log` (dev visibility),
  `queue` (forwards onto Laravel's own queue — zero extra infrastructure).
- `StoreForwardManager` — driver registry (`extend()`), same pattern as
  Laravel's `CacheManager`/`QueueManager`.
- `Dispatcher` + `store-forward:work` / `store-forward:retry` Artisan commands.
- `MessagePublished` / `MessageFailed` events for observability/alerting.

## Roadmap / open questions for the driver packages

- Kafka: `rdkafka` extension vs. a pure-PHP client (portability vs.
  performance) — likely support both behind the same `KafkaTransport`.
- Batching: transports that benefit from batch sends (Kafka producer
  flush, SQS `SendMessageBatch`) — `sendBatch()` exists for this; the
  dispatcher could group by channel before calling it.
- Ordering guarantees per channel (Kafka partition key = `Envelope::$key`).
- A `store-forward:status` command / simple dashboard for outbox depth
  and dead-letter counts, in the spirit of Horizon.

## Testing

```bash
composer install
vendor/bin/phpunit
```
