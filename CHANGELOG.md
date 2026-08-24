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
- CI's PHP 8.1 + Laravel 11.* matrix leg was failing dependency
  resolution (`your php version (8.1.34) does not satisfy that
  requirement`) — Laravel 11 requires PHP `^8.2`, same as Laravel 12, but
  `.github/workflows/tests.yml`'s `exclude` list only dropped PHP 8.1 for
  Laravel 12, not Laravel 11. Added the missing exclude entry.
- CI's Laravel 10.*/11.* matrix legs were failing composer's dependency
  resolution entirely (`Your requirements could not be resolved to an
  installable set of packages`) — not a real conflict, but Composer 2.9's
  security-advisory blocking (`policy.advisories.block`, default `true`)
  refusing every `laravel/framework` v10.x/v11.x release because each one
  has since accumulated at least one published advisory, as any
  no-longer-latest major version eventually will. Set
  `config.policy.advisories.block` to `false` in `composer.json` — this
  only affects which versions composer's *solver* is willing to select
  for this dev/test install, not a runtime guarantee; consuming apps make
  their own audit decisions via their own `composer.json`. Verified by
  reproducing both matrix legs' `composer update` locally after the fix:
  dependency resolution completes and package installation proceeds (no
  more solver conflict).
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
