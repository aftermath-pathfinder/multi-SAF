# Driver packages (monorepo)

Each subdirectory here is a **separate, independently installable Composer
package** — its own `composer.json`, `src/`, `tests/`, `README.md` — that
adds one transport driver to the core
[`aftermath-pathfinder/store-forward`](../README.md) package. This is
what makes switching between AWS, GCP, Alibaba Cloud, or a self-hosted
broker a one-line config change (`'driver' => 'sqs'` → `'driver' =>
'pubsub'`, etc.) instead of a rewrite: every driver implements the same
two-method `TransportInterface` and registers itself with
`StoreForwardManager::extend()`.

| Package | Platform | Backing service | New dependency |
|---|---|---|---|
| `store-forward-sqs` | AWS | SQS | `aws/aws-sdk-php` |
| `store-forward-pubsub` | GCP | Pub/Sub | `google/cloud-pubsub` |
| `store-forward-mns` | Alibaba Cloud | MNS (topics) | `aliyun/aliyun-mns-php-sdk` |
| `store-forward-kafka` | Self-hosted | Kafka | `longlang/phpkafka` (pure PHP, no PECL ext) |
| `store-forward-amqp` | Self-hosted | RabbitMQ | `php-amqplib/php-amqplib` |
| `store-forward-redis-streams` | Self-hosted | Redis Streams | none — reuses your app's existing Redis connection |

## Why one repo during development, but separate packages

Right now each package's `composer.json` points back at the core package
via a **path repository** (`"repositories": [{"type": "path", "url":
"../../"}]`) with a `*@dev` constraint — that's what lets you develop and
test the core package and every driver together, in one place, without
publishing anything. It's the same tradeoff Laravel itself makes:
`laravel/framework` is developed as one repo and *split* into the
`illuminate/*` packages you actually `composer require`.

**Before publishing a driver package to Packagist**, it needs to become
its own real Git repository (via a subtree split — `git subtree split
--prefix=packages/store-forward-sqs -b split-sqs`, or a tool like
[`symplify/monorepo-split-github-action`](https://github.com/symplify/monorepo-split-github-action)
to automate it on every push), and its `composer.json`'s
`aftermath-pathfinder/store-forward` constraint needs to change from
`*@dev` to a real semver range (e.g. `^1.0`) once the core package has a
tagged release — the path repository and `*@dev` only make sense for
local, same-machine development.

## Adding a new driver

1. Copy the shape of an existing package here (composer.json, one
   `<X>Transport` class implementing `TransportInterface`, one
   `<X>DriverServiceProvider` that calls `StoreForwardManager::extend()`
   in `boot()`, tests that mock the SDK client — never a real connection).
2. Keep the transport class itself dumb: constructor takes the already-
   configured SDK client, `send()`/`sendBatch()` call it and let exceptions
   propagate. All retry/backoff/dead-letter logic lives in the core
   package's `Dispatcher` — see `CONTRIBUTING.md` at the repo root.
3. Document the driver's config keys and any platform-specific caveats
   (batching limits, ordering guarantees, connection lifecycle) in its own
   README, following the existing ones as a template.
