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

### Fixed
- `StoreForwardManager` now reads `store-forward.*` config live from the
  container's config repository instead of snapshotting it once at
  construction, so a `config()->set(...)` made after the manager was first
  resolved is honored (affects `default`, `channels.*`, and `drivers.*`).

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
