# store-forward-mns

Alibaba Cloud MNS (Message Service) transport driver for
[`aftermath-pathfinder/store-forward`](../../README.md).

## Install

```bash
composer require aftermath-pathfinder/store-forward-mns
```

## Configure

```php
// config/store-forward.php
'channels' => [
    'orders.created' => ['driver' => 'mns'],
],
'drivers' => [
    'mns' => [
        'endpoint' => env('MNS_ENDPOINT'), // e.g. https://<account-id>.mns.<region>.aliyuncs.com
        'access_id' => env('ALIBABA_ACCESS_KEY_ID'),
        'access_key' => env('ALIBABA_ACCESS_KEY_SECRET'),
        'topic_name' => env('MNS_ORDERS_TOPIC'),
    ],
],
```

## Why `sendBatch()` is a loop here

Unlike SQS's `SendMessageBatch` or Pub/Sub's `publishBatch()`, the MNS PHP
SDK's **topic** API (`AliyunMNS\Topic::publishMessage()`) only accepts one
message per call — there's no batch-publish-to-topic request in the SDK.
(MNS **queues**, a different API in the same SDK, do have a batch send —
if you need that instead of pub/sub-style topics, it would be worth a
separate `MnsQueueTransport` in a future version of this package.) So
`sendBatch()` here is a plain loop, which is explicitly the documented
fallback behavior in `TransportInterface`.

## Verification

This package's test suite mocks `AliyunMNS\Topic` and
`AliyunMNS\Responses\PublishMessageResponse` to verify the adapter builds
the right `PublishMessageRequest` and correctly turns a failed response
into a thrown exception — it does not talk to a real MNS topic. Verify
end-to-end against a real topic before relying on this in production.
