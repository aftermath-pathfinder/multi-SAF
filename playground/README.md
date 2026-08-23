# store-forward playground

A clickable demo of [`aftermath-pathfinder/store-forward`](../README.md):
publish a message, watch it move through the outbox lifecycle
(`pending → processing → sent`, or `pending → … → dead` on repeated
failure) in a live-updating dashboard. See [`CLAUDE.md`](CLAUDE.md) for a
tour of how this app is put together.

This app lives on the `playground/version1` branch only — it's kept off
the library's own development branch so the library repo stays a library,
not an example app.

## Setup

Pick one of the three ways to run this; they all end up the same place.

### Option A — plain PHP (fastest, what this was built/tested with)

```bash
composer install
cp .env.example .env   # already done if you're reading a committed .env
php artisan key:generate
php artisan migrate

# Redis Streams demo needs a Redis server. If you don't have one:
redis-server --daemonize yes

php artisan serve
```

Visit http://127.0.0.1:8000.

### Option B — ddev

```bash
ddev start
ddev composer install
ddev artisan migrate
ddev get ddev/ddev-redis   # official add-on, needed for the demo.redis channel
ddev restart
```

`ddev launch` opens the dashboard. `.ddev/config.yaml` is committed but
**not verified in this repo's own build environment** (no Docker daemon
there) — if something's off, it's most likely a version drift in that
file; `ddev config --project-type=laravel --docroot=public` regenerates
a known-good baseline to diff against.

### Option C — docker-compose, for real self-hosted brokers

`docker-compose.yml` (also unverified here, same reason) brings up Redis,
RabbitMQ, and Kafka so you can try the `kafka`/`amqp` drivers for real,
not just `redis-streams`:

```bash
docker compose up -d
composer require aftermath-pathfinder/store-forward-kafka aftermath-pathfinder/store-forward-amqp
```

Then add path-repo entries for those packages to `composer.json`
(copy the `store-forward-redis-streams` one) and uncomment/fill in their
config blocks in `config/store-forward.php`.

## Using it

1. Pick a channel, write a JSON payload, hit **Publish**. It lands in the
   outbox as `pending` — nothing has tried to deliver it yet.
2. Hit **Process pending now**. Watch the table update (polls every
   1.5s): `demo.log` and `demo.redis` should go straight to `sent`.
3. Try `demo.flaky` a few times — it's a fake driver that fails a
   configurable percentage of sends (`STORE_FORWARD_FLAKY_PERCENT` in
   `.env`, default 60) purely so you can watch `attempts` climb and
   `last_error` populate. Keep clicking **Process pending now**; if it's
   past its backoff window it'll retry, otherwise wait a few seconds.
4. **Retry dead messages** requeues anything that hit `max_attempts`
   (default 10 — lower `STORE_FORWARD_MAX_ATTEMPTS` in `.env` to see a
   dead-letter faster).

For `demo.redis`, confirm it's real:

```bash
redis-cli -n 0 XRANGE "laravel-database-store-forward-playground" - +
```

(the `laravel-database-` prefix is Laravel's own Redis key-prefixing, not
this package's doing.)

## What's demo-only vs. real

- `demo.log`, `demo.redis` → real driver code, same as any consuming app
  would use (`log` ships in core; `redis-streams` is
  `packages/store-forward-redis-streams`, wired in via a path repo — see
  `CLAUDE.md`).
- `demo.flaky` → fake, playground-only, lives in `app/`, not `packages/`.
- The "Process pending now" button running `store-forward:work --once`
  synchronously inside a web request → fine for a demo, wrong for a real
  app. Run `php artisan store-forward:work` as a long-lived worker
  process instead (a second terminal, Supervisor, a systemd unit, ...).
