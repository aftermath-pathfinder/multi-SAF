# CLAUDE.md — orientation for AI assistants working in this app

This is a standard `laravel/laravel` skeleton with almost nothing added.
If you already know Laravel, you know 95% of this repo. What's specific
to it:

## What this app is for

A clickable demo of `aftermath-pathfinder/store-forward` (the sibling
package one directory up, at `../`) — publish a message, watch it move
through the outbox table's lifecycle (`pending → processing → sent`, or
`pending → ... → dead` on repeated failure), without needing any real
message broker running. It exists to be read start to finish in one
sitting, not to model good large-application architecture — resist the
urge to "properly" split it into services/actions/DTOs.

## The four files that matter

- `app/Http/Controllers/PlaygroundController.php` — the entire UI's
  server-side logic. `index()` renders the dashboard, `messages()` is the
  JSON endpoint the page polls, `publish()`/`process()`/`retryDead()` back
  the three buttons/forms.
- `resources/views/dashboard.blade.php` — the entire UI. One file, inline
  `<style>`/`<script>`, no build step (no Vite/npm involved in running
  this — `npm run build` was already run once by `laravel/laravel`'s own
  installer for `resources/css/app.css`, but the dashboard doesn't use it).
  The `<script>` polls `/messages` every 1.5s and re-renders the table.
- `app/StoreForwardDemo/FlakyTransport.php` +
  `app/Providers/PlaygroundServiceProvider.php` — a fake transport that
  fails a configurable percentage of the time, registered as the `flaky`
  driver, purely so you can watch retry/backoff/dead-lettering happen
  without taking a real broker down. Not a pattern real driver packages
  follow — see `../packages/README.md` for what those look like.
- `config/store-forward.php` — four demo channels: `demo.log` (zero
  infra), `demo.redis` (a real self-hosted driver — Redis Streams, using
  whatever Redis your `.env` points at), `demo.flaky` (the fake one above),
  `demo.custom` (whatever driver you picked on `/settings` — see next).

## The Settings page (`/settings`)

`app/Http/Controllers/SettingsController.php` +
`app/StoreForwardDemo/DriverCatalog.php` +
`app/Support/EnvFileWriter.php` + `resources/views/settings.blade.php`.

Lets you pick any of the six real drivers for `demo.custom` and fill in
its credentials, without hand-editing files. Two things worth
understanding if you touch this:

1. **The litmus test.** `DriverCatalog::isInstalled()` does
   `class_exists()` on that driver's service provider — real, not a
   simulation — before letting you switch. Picking an uninstalled driver
   flashes a warning and changes nothing (`.env` untouched, `demo.custom`
   unchanged); it never lets you save a config that would throw on the
   next publish. This is why `providerClass` in `DriverCatalog` uses
   `SomeNamespace\Class::class` even for classes that don't exist yet in
   this checkout — that's valid PHP (`::class` is resolved to a string at
   parse time, no autoload triggered) and exactly what makes the check
   honest.
2. **Driver instances are shared by name, not by channel.** If
   `demo.custom` and `demo.redis` are both set to `redis-streams`, they
   read the *same* `drivers.redis-streams` config block — changing the
   stream name for one changes it for both. That's `StoreForwardManager`'s
   real caching behavior (`$this->transports[$name]`), not a bug in this
   page; the Settings view says so rather than hiding it.

`EnvFileWriter` is a small, deliberately playground-scoped `.env`
line-patcher — single-user local tool, no file lock, don't lift it into a
real app without adding one.

## How the package is wired in

`composer.json`'s `repositories` points two `path` repos at `../` (the
core package) and `../packages/store-forward-redis-streams` (the one
real driver package wired in by default — see below for why only this
one). That's a monorepo dev pattern, not how a real consuming app would
do it — a real app just runs `composer require
aftermath-pathfinder/store-forward` and whichever driver packages it
needs, from Packagist, with normal version constraints.

**Why only `redis-streams` by default:** it needs no SDK beyond what
Laravel already ships (it reuses your app's Redis connection), so it's
the one driver this demo can prove actually delivers a message
end-to-end without you provisioning anything. To try `sqs`/`pubsub`/
`mns`/`kafka`/`amqp`, add the matching path repo entry (copy the
`redis-streams` one) and `composer require` it, then add its config block
to `config/store-forward.php` (commented-out examples already there).

## Running it

See `README.md` for setup (composer/ddev/docker-compose). Once running:
`/` is the dashboard. Publish a message, click "Process pending now"
(this runs `store-forward:work --once` synchronously in the request —
real apps run that as a long-lived worker instead, see the root
`README.md`). Try `demo.flaky` and click "Process pending now" a few
times to watch attempts climb.

## Things NOT to "fix" here

- The controller doing everything in one class — deliberate, see above.
- `process()` calling `Artisan::call()` synchronously inside a web
  request — deliberate, it's what makes this a clickable demo instead of
  needing a worker process in a second terminal. A real app should not do
  this; the root README says so and this file just did too.
- No tests in this app — the package being demonstrated has its own
  (extensive) test suite one directory up; this app's only job is to be
  poked at by a human.
