# Contributing

Thanks for considering a contribution. This package is small on purpose —
please read this before opening a PR so review stays fast.

## Setup

```bash
composer install
```

## Running checks locally

```bash
vendor/bin/phpunit          # unit tests
php -l src/**/*.php         # syntax check (CI also runs this per-file)
```

If `phpstan/larastan` or `laravel/pint` are present in your checkout
(`composer require --dev` them if you want static analysis / formatting
locally — they're deliberately not required just to run the test suite):

```bash
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

## Design rules to keep in mind

These aren't arbitrary style preferences — they're what keeps this package
easy for both humans and AI assistants to safely extend:

1. **`TransportInterface` stays at two methods.** `send()` and
   `sendBatch()`. Anything broker-specific (auth, serialization, batching
   strategy) belongs inside a transport implementation, not in the
   interface. This is what lets a new broker driver ship as a tiny,
   independent package.
2. **Durability logic lives in `StoreInterface`/`Dispatcher`, never in a
   transport.** A transport should be able to throw on any failure and
   trust the dispatcher to retry/backoff/dead-letter correctly.
3. **No new required dependencies in the core package.** `composer.json`'s
   `require` block should only ever need `illuminate/*`. A broker client
   (rdkafka, php-amqplib, aws-sdk-php, predis, ...) belongs in a separate
   `store-forward-<broker>` package that calls `StoreForward::extend()`.
4. **Every public class gets a one-paragraph docblock explaining *why* it
   exists**, not just what its methods do — see the existing classes for
   the expected style. Prefer that over inline comments explaining *what*
   a line does (the code should already say that).
5. **Add a test with every behavior change.** A change to `Dispatcher`,
   `DatabaseStore`, or `StoreForwardManager` without an accompanying test
   in `tests/` will be asked to add one before merge.
6. **Keep `declare(strict_types=1)` at the top of every PHP file.**

## Commit messages

Plain, descriptive, imperative mood ("Add retry backoff cap", not "Added"
or "Adds"). No need for conventional-commits prefixes.

## Reporting bugs / requesting features

Open an issue with a minimal reproduction where possible — for this kind
of package that's usually a short PHPUnit test showing the expected vs.
actual `store_forward_messages` row state.

## Security issues

Do not open a public issue — see [SECURITY.md](SECURITY.md).
