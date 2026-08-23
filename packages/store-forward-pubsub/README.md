# store-forward-pubsub

Google Cloud Pub/Sub transport driver for
[`aftermath-pathfinder/store-forward`](../../README.md).

## Install

```bash
composer require aftermath-pathfinder/store-forward-pubsub
```

`google/cloud-pubsub` is already scoped to just Pub/Sub by Google (the
`google-cloud-php` SDK is split into one Composer package per service), so
there's no extra trimming step needed here, unlike the AWS driver.

## Configure

```php
// config/store-forward.php
'channels' => [
    'orders.created' => ['driver' => 'pubsub'],
],
'drivers' => [
    'pubsub' => [
        'topic_name' => env('PUBSUB_ORDERS_TOPIC'),
        'project_id' => env('GOOGLE_CLOUD_PROJECT'),
        // Optional — omit to use Application Default Credentials (e.g. a
        // GCE/GKE/Cloud Run service account) instead of a key file.
        'key_file_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    ],
],
```

## Ordering keys

If you pass a `$key` to `StoreForward::publish($channel, $payload, $headers, $key)`,
it's sent as the message's [ordering
key](https://cloud.google.com/pubsub/docs/ordering) — messages published
with the same key are delivered to subscribers in the order they were
published. `sendBatch()` groups envelopes by key before calling
`publishBatch()`, since Pub/Sub requires every message in one batch call
to share the same ordering key (or have none).

## Verification

This package's test suite mocks `Google\Cloud\PubSub\Topic` to verify the
adapter calls `publish()`/`publishBatch()` with the right message shape —
it does not talk to a real Pub/Sub topic. Verify end-to-end against a real
topic (or the Pub/Sub emulator) before relying on this in production.
