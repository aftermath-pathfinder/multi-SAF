<?php

return [

    /*
    |--------------------------------------------------------------------
    | Default transport
    |--------------------------------------------------------------------
    |
    | Used for any channel that doesn't declare its own driver below.
    | Ships with "sync" (inline, tests/local), "log" (dev visibility),
    | and "queue" (forwards onto Laravel's own queue — no extra broker).
    | Install a driver package (e.g. store-forward-kafka) to add more.
    |
    */
    'default' => env('STORE_FORWARD_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------
    | Outbox store connection / table
    |--------------------------------------------------------------------
    */
    'connection' => env('STORE_FORWARD_DB_CONNECTION'),
    'table' => 'store_forward_messages',

    /*
    |--------------------------------------------------------------------
    | Per-channel routing
    |--------------------------------------------------------------------
    |
    | Map a logical channel name (what your app calls when publishing) to
    | a driver + its options. "immediate" sends synchronously in addition
    | to writing the outbox row (still store-and-forward: the row is your
    | safety net if the immediate send throws or the process dies first).
    |
    */
    'channels' => [
        // Zero infrastructure: writes to the log. Good first thing to try.
        'demo.log' => ['driver' => 'log'],

        // Real self-hosted broker: Redis Streams, via the app's own Redis
        // connection — see PlaygroundServiceProvider for why this is the
        // one cloud/self-hosted driver wired into the playground by default.
        'demo.redis' => ['driver' => 'redis-streams'],

        // Registered by PlaygroundServiceProvider (app/Providers), not a
        // real broker — fails a fixed percentage of the time so you can
        // watch attempts climb and (eventually) hit sent or dead-letter.
        'demo.flaky' => ['driver' => 'flaky'],

        // Whatever you pick on the /settings page. Nothing writes to this
        // array at runtime — Settings only ever writes STORE_FORWARD_*
        // env vars, and this line just reads them back, same as any other
        // channel. See app/Http/Controllers/SettingsController.php.
        'demo.custom' => ['driver' => env('STORE_FORWARD_CUSTOM_DRIVER', 'log')],
    ],

    /*
    |--------------------------------------------------------------------
    | Driver configuration
    |--------------------------------------------------------------------
    |
    | Config passed to each driver's factory. Extension packages document
    | their own keys; core drivers need very little.
    |
    */
    'drivers' => [
        'log' => [
            'log_channel' => null, // null = default log channel
        ],
        'queue' => [
            'connection' => null, // null = default queue connection
            'queue' => null,
        ],
        'redis-streams' => [
            'connection' => null, // null = default Redis connection
            'stream' => env('REDIS_ORDERS_STREAM', 'store-forward-playground'),
        ],
        'flaky' => [
            // Chance (0-100) that a send() throws instead of succeeding.
            // Try 100 to watch a message ride out every retry to dead
            // (max_attempts below), or something like 60 to see it
            // eventually get through.
            'failure_percent' => env('STORE_FORWARD_FLAKY_PERCENT', 60),
        ],

        // The six blocks below always have a config shape, whether or not
        // the corresponding package is installed — that's harmless (it's
        // just an array), and it's what lets /settings write plain env
        // vars without ever needing to touch this file. Each one only
        // actually resolves if you `composer require
        // aftermath-pathfinder/store-forward-<name>` and select it.
        'sqs' => [
            'queue_url' => env('SQS_ORDERS_QUEUE_URL'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
        'pubsub' => [
            'topic_name' => env('PUBSUB_ORDERS_TOPIC'),
            'project_id' => env('GOOGLE_CLOUD_PROJECT'),
            'key_file_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        ],
        'mns' => [
            'endpoint' => env('MNS_ENDPOINT'),
            'access_id' => env('ALIBABA_ACCESS_KEY_ID'),
            'access_key' => env('ALIBABA_ACCESS_KEY_SECRET'),
            'topic_name' => env('MNS_ORDERS_TOPIC'),
        ],
        'kafka' => [
            'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
            'topic' => env('KAFKA_ORDERS_TOPIC'),
        ],
        'amqp' => [
            'host' => env('RABBITMQ_HOST', 'localhost'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'queue' => env('RABBITMQ_ORDERS_QUEUE', 'orders'),
        ],
    ],

    /*
    |--------------------------------------------------------------------
    | Retry / dead-letter
    |--------------------------------------------------------------------
    */
    'max_attempts' => env('STORE_FORWARD_MAX_ATTEMPTS', 10),

];
