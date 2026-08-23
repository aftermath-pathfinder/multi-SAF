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

## Multi-cloud drivers

Six driver packages ship in this repo's [`packages/`](packages/) directory
(a monorepo — see [`packages/README.md`](packages/README.md) for why),
each independently installable and each proven against a real,
verified SDK API surface with unit tests that mock the SDK client:

| Package | Platform | Backing service |
|---|---|---|
| `store-forward-sqs` | AWS | SQS |
| `store-forward-pubsub` | GCP | Pub/Sub |
| `store-forward-mns` | Alibaba Cloud | MNS |
| `store-forward-kafka` | Self-hosted | Kafka |
| `store-forward-amqp` | Self-hosted | RabbitMQ |
| `store-forward-redis-streams` | Self-hosted | Redis Streams |

Switching platforms is a one-line config change
(`'channels' => ['orders' => ['driver' => 'sqs']]` → `'pubsub'`, etc.) —
that's the whole point of the `TransportInterface`/`extend()` design above.

## Roadmap / open questions for the driver packages

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

The suite (47 tests) covers the outbox lifecycle end to end: `Envelope`
construction/(de)serialization, `DatabaseStore`'s claim/backoff/dead-letter
transitions, `StoreForwardManager` driver resolution and `extend()`,
`Dispatcher` retry/dead-letter/event-dispatch behavior and per-channel
routing, both built-in Artisan commands, and each shipped transport. Every
class in `src/` has at least one dedicated test file under `tests/`.

## Project quality & maintainability

This package follows the checklist that makes a Composer library both
worth adopting and safe for another contributor (human or AI) to extend
without re-deriving intent from scratch:

- **`declare(strict_types=1)`** on every file — type errors surface at the
  call site, not three functions downstream.
- **A docblock on every public class explaining *why* it exists**, not
  just what its methods do — see `CONTRIBUTING.md` rule 4. This is the
  single highest-leverage thing for AI-assisted maintenance: an assistant
  (or a new contributor) reading `TransportInterface.php` in isolation
  should understand *why* it's only two methods without reading the rest
  of the package.
- **No hidden config snapshots.** Earlier drafts captured
  `config('store-forward.*')` once in the manager's constructor; a
  `config()->set(...)` after first resolution was silently ignored. It now
  reads live from the container's config repository on every call, so
  behavior matches what's actually configured, including in tests that
  change config mid-test.
- **A CI matrix** (`.github/workflows/tests.yml`) across supported
  PHP (8.1–8.4) and Laravel (10–12) versions — a package with no CI badge
  and no version matrix is a package you find out is broken after you've
  already installed it.
- **`CONTRIBUTING.md`** codifies the architectural invariants (interface
  stays small, no new required dependencies, durability logic never lives
  in a transport) so a contribution doesn't quietly erode the reason the
  package is split the way it is.
- **`CHANGELOG.md`** (Keep a Changelog) and **`SECURITY.md`** (private
  disclosure process) — table stakes for anyone deciding whether to depend
  on this in production.
- **`LICENSE.md`**, `.editorconfig`, `.gitattributes` (dev-only files
  excluded from `composer create-project`/dist archives) — small things
  that are nonetheless checked by tools like Packagist's quality score and
  by cautious teams before adding a dependency.
