# store-forward-sqs

AWS SQS transport driver for [`aftermath-pathfinder/store-forward`](../../README.md).

## Install

```bash
composer require aftermath-pathfinder/store-forward-sqs
```

Add the provider (auto-discovered on real Laravel apps via the package's
`extra.laravel.providers`; list it manually in `config/app.php` only if
your app has package discovery disabled).

## Configure

```php
// config/store-forward.php
'channels' => [
    'orders.created' => ['driver' => 'sqs'],
],
'drivers' => [
    'sqs' => [
        'queue_url' => env('SQS_ORDERS_QUEUE_URL'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        // Optional — omit to use the SDK's default credential provider
        // chain (env vars, ~/.aws/credentials, an instance/task role, ...).
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],
],
```

## Reducing the install footprint

`aws/aws-sdk-php` bundles API/waiter/paginator definitions for every AWS
service, not just SQS — that's most of its size on disk. The SDK ships an
official Composer script for trimming it down to just the services your
**application** actually uses. This has to be configured in your Laravel
app's own root `composer.json` (Composer only runs scripts from the root
package, never from a dependency's), for example:

```json
{
    "scripts": {
        "pre-autoload-dump": "Aws\\Script\\Composer\\Composer::removeUnusedServices"
    },
    "extra": {
        "aws/aws-sdk-php": [
            "Sqs"
        ]
    }
}
```

List every AWS service your app touches anywhere (not just through this
package) — `S3`, `Kms`, `Sso`, and `Sts` are always kept regardless, since
core SDK functionality depends on them. Re-run `composer install` (or
`update`) after adding this; it re-applies on every install, so it stays
in sync as your `extra` list changes.

**Caveat:** because Composer's `vendor/` is shared across your whole app,
trimming to `["Sqs"]` will break any other part of the app that uses a
different AWS service (e.g. S3 uploads) unless that service is also
listed. This is a whole-application decision, not something this package
can safely do on your behalf.

## Design note: why there's an `SqsAdapter` class

AWS SDK PHP service clients (`Aws\Sqs\SqsClient` included) don't declare
real methods for operations like `sendMessage()` — they're dispatched
through `__call()` against a generated API description at runtime.
PHPUnit can't stub a method that doesn't really exist on the class
without `MockBuilder::addMethods()`, which is deprecated with no
replacement as of PHPUnit 11. So `SqsTransport` depends on
`SqsClientInterface` (two real, declared methods) instead of the
concrete SDK class directly; `SqsAdapter` is the one place that actually
calls into `SqsClient`, wiring the two together.

## Verification

`tests/SqsTransportTest.php` mocks `SqsClientInterface` to verify the
adapter calls `sendMessage`/`sendMessageBatch` with the right shape — it
does not talk to real AWS. `SqsAdapter` itself is two one-line pass-
through methods and isn't independently unit tested (verifying it would
hit the same magic-method mocking problem it exists to route around);
verify it, and this driver end-to-end, against a real queue (or
LocalStack) before relying on this in production.
