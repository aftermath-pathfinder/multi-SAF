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
    'default' => env('STORE_FORWARD_DRIVER', 'sync'),

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
        // 'orders' => [
        //     'driver' => 'kafka',
        //     'immediate' => false,
        // ],
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

        // 'kafka' => [
        //     'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
        //     'topic_prefix' => env('KAFKA_TOPIC_PREFIX'),
        // ],
        // 'amqp' => [
        //     'host' => env('RABBITMQ_HOST', 'localhost'),
        //     'exchange' => env('RABBITMQ_EXCHANGE', 'amq.topic'),
        // ],
        // 'sqs' => [
        //     'queue_url' => env('SQS_QUEUE_URL'),
        //     'region' => env('AWS_DEFAULT_REGION'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------
    | Retry / dead-letter
    |--------------------------------------------------------------------
    */
    'max_attempts' => env('STORE_FORWARD_MAX_ATTEMPTS', 10),

];
