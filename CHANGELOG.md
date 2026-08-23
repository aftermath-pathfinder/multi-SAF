# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project intends to follow [Semantic Versioning](https://semver.org/)
once it reaches a tagged `1.0.0`.

## [Unreleased]

### Added
- Six transport driver packages under `packages/` (a monorepo), each
  independently installable: `store-forward-sqs` (AWS), `store-forward-pubsub`
  (GCP), `store-forward-mns` (Alibaba Cloud), `store-forward-kafka`,
  `store-forward-amqp`, and `store-forward-redis-streams` (self-hosted).
  Each ships its own tests (mocking the real SDK client) and README.
- A `playground/version1` branch: a minimal runnable Laravel app
  demonstrating the package end to end (a live dashboard showing the
  outbox lifecycle), kept off this branch so the library repo stays a
  library. See that branch's `playground/README.md` and `CLAUDE.md`.

### Changed
- Widened `illuminate/support`, `illuminate/database`, `illuminate/queue`
  (core) and `illuminate/redis` (store-forward-redis-streams) version
  constraints to include `^13.0`, found necessary when
  `composer create-project laravel/laravel` (used for the playground app)
  scaffolded a Laravel 13 project.

### Fixed
- `StoreForwardManager` now reads `store-forward.*` config live from the
  container's config repository instead of snapshotting it once at
  construction, so a `config()->set(...)` made after the manager was first
  resolved is honored (affects `default`, `channels.*`, and `drivers.*`).
- **Channel names containing a literal dot** (e.g. `orders.created`) no
  longer silently resolve to the default driver instead of their
  configured one. `transportForChannel()`/`publish()` were building a
  config lookup by string-interpolating the channel name into a dotted
  path (`"channels.{$channel}.driver"`); Laravel's config repository
  treats every dot as a nesting separator, so a channel literally named
  `orders.created` was read as `channels→orders→created` (nonexistent)
  rather than the array key `"orders.created"`, silently falling back to
  `default` with no error. Found via the playground app's `demo.redis`
  channel appearing to succeed (`status: sent`) while never actually
  reaching Redis — it was silently using the `log` driver instead.
  `channelConfig()`/`driverConfig()` now index into the resolved array
  directly instead of building dotted paths from user-supplied names.
  Added regression tests.
- `StoreForwardServiceProvider` now registers `store-forward:work` and
  `store-forward:retry` unconditionally in `boot()`, rather than only
  `if ($this->app->runningInConsole())`. That check reflects how the
  *current* request was invoked, not whether Artisan commands will ever
  be needed — a web request calling `Artisan::call('store-forward:work',
  ...)` (exactly what the playground app's "process pending now" button
  does) needs those commands registered during this same boot() call, or
  they never exist for the rest of the request. `publishes()` calls stay
  console-gated (that's genuinely a `vendor:publish`-only concern).

### Added
- Initial package scaffold: `Envelope`, `StoreInterface`/`DatabaseStore`
  (outbox pattern), `TransportInterface`, built-in `sync`/`log`/`queue`
  transports, `StoreForwardManager` driver registry, `Dispatcher`,
  `store-forward:work` / `store-forward:retry` Artisan commands,
  `MessagePublished` / `MessageFailed` events, facade, service provider,
  config, and migration.
- `declare(strict_types=1)` across the codebase.
- Unit test suite covering the outbox lifecycle, driver resolution/`extend()`,
  retry/dead-letter backoff, and both Artisan commands.
- Project scaffolding for external contribution and CI: `CONTRIBUTING.md`,
  `SECURITY.md`, `.editorconfig`, `.gitattributes`, and a GitHub Actions
  test matrix across supported PHP/Laravel versions.

[Unreleased]: https://github.com/aftermath-pathfinder/multi-SAF/compare/main...HEAD
