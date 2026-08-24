<?php

declare(strict_types=1);

namespace App\StoreForwardDemo;

/**
 * The single source of truth for the /settings page: every real driver
 * this library ships, what env vars each one's config reads (see
 * config/store-forward.php), and — the "litmus test" — which class only
 * exists once that driver's Composer package is actually installed.
 *
 * This intentionally mirrors the DRIVERS object in the Dispatch Desk
 * artifact's "Configure a driver" panel; the difference is that page only
 * ever generates text for you to paste, while this one actually writes
 * your .env file and switches demo.custom live.
 */
class DriverCatalog
{
    /**
     * @return array<string, array{
     *     label: string,
     *     platform: string,
     *     package: ?string,
     *     providerClass: ?string,
     *     fields: array<int, array{key: string, label: string, env: string, placeholder: string, secret?: bool}>,
     * }>
     */
    public static function all(): array
    {
        return [
            'log' => [
                'label' => 'Log (built-in)',
                'platform' => 'Core',
                'package' => null, // ships in the core package — nothing to install
                'providerClass' => null,
                'fields' => [],
            ],
            'redis-streams' => [
                'label' => 'Redis Streams',
                'platform' => 'Self-hosted',
                'package' => 'aftermath-pathfinder/store-forward-redis-streams',
                'providerClass' => \AftermathPathfinder\StoreForwardRedisStreams\RedisStreamsDriverServiceProvider::class,
                'fields' => [
                    ['key' => 'stream', 'label' => 'Stream name', 'env' => 'REDIS_ORDERS_STREAM', 'placeholder' => 'orders'],
                ],
            ],
            'kafka' => [
                'label' => 'Kafka',
                'platform' => 'Self-hosted',
                'package' => 'aftermath-pathfinder/store-forward-kafka',
                'providerClass' => \AftermathPathfinder\StoreForwardKafka\KafkaDriverServiceProvider::class,
                'fields' => [
                    ['key' => 'brokers', 'label' => 'Broker(s)', 'env' => 'KAFKA_BROKERS', 'placeholder' => 'localhost:9092'],
                    ['key' => 'topic', 'label' => 'Topic', 'env' => 'KAFKA_ORDERS_TOPIC', 'placeholder' => 'orders'],
                ],
            ],
            'amqp' => [
                'label' => 'RabbitMQ',
                'platform' => 'Self-hosted',
                'package' => 'aftermath-pathfinder/store-forward-amqp',
                'providerClass' => \AftermathPathfinder\StoreForwardAmqp\AmqpDriverServiceProvider::class,
                'fields' => [
                    ['key' => 'host', 'label' => 'Host', 'env' => 'RABBITMQ_HOST', 'placeholder' => 'localhost'],
                    ['key' => 'port', 'label' => 'Port', 'env' => 'RABBITMQ_PORT', 'placeholder' => '5672'],
                    ['key' => 'user', 'label' => 'User', 'env' => 'RABBITMQ_USER', 'placeholder' => 'guest'],
                    ['key' => 'password', 'label' => 'Password', 'env' => 'RABBITMQ_PASSWORD', 'placeholder' => 'guest', 'secret' => true],
                    ['key' => 'queue', 'label' => 'Queue', 'env' => 'RABBITMQ_ORDERS_QUEUE', 'placeholder' => 'orders'],
                ],
            ],
            'sqs' => [
                'label' => 'AWS SQS',
                'platform' => 'AWS',
                'package' => 'aftermath-pathfinder/store-forward-sqs',
                'providerClass' => \AftermathPathfinder\StoreForwardSqs\SqsDriverServiceProvider::class,
                'fields' => [
                    ['key' => 'region', 'label' => 'Region', 'env' => 'AWS_DEFAULT_REGION', 'placeholder' => 'us-east-1'],
                    ['key' => 'queue_url', 'label' => 'Queue URL', 'env' => 'SQS_ORDERS_QUEUE_URL', 'placeholder' => 'https://sqs.us-east-1.amazonaws.com/123456789012/orders'],
                    ['key' => 'key', 'label' => 'Access Key ID', 'env' => 'AWS_ACCESS_KEY_ID', 'placeholder' => 'AKIA...', 'secret' => true],
                    ['key' => 'secret', 'label' => 'Secret Access Key', 'env' => 'AWS_SECRET_ACCESS_KEY', 'placeholder' => '', 'secret' => true],
                ],
            ],
            'pubsub' => [
                'label' => 'GCP Pub/Sub',
                'platform' => 'GCP',
                'package' => 'aftermath-pathfinder/store-forward-pubsub',
                'providerClass' => \AftermathPathfinder\StoreForwardPubSub\PubSubDriverServiceProvider::class,
                'fields' => [
                    ['key' => 'project_id', 'label' => 'Project ID', 'env' => 'GOOGLE_CLOUD_PROJECT', 'placeholder' => 'my-project-123456'],
                    ['key' => 'topic_name', 'label' => 'Topic name', 'env' => 'PUBSUB_ORDERS_TOPIC', 'placeholder' => 'orders-created'],
                    ['key' => 'key_file_path', 'label' => 'Service account key file path', 'env' => 'GOOGLE_APPLICATION_CREDENTIALS', 'placeholder' => '/path/to/service-account.json', 'secret' => true],
                ],
            ],
            'mns' => [
                'label' => 'Alibaba MNS',
                'platform' => 'Alibaba Cloud',
                'package' => 'aftermath-pathfinder/store-forward-mns',
                'providerClass' => \AftermathPathfinder\StoreForwardMns\MnsDriverServiceProvider::class,
                'fields' => [
                    ['key' => 'endpoint', 'label' => 'Endpoint', 'env' => 'MNS_ENDPOINT', 'placeholder' => 'https://1234567890.mns.cn-hangzhou.aliyuncs.com'],
                    ['key' => 'access_id', 'label' => 'Access Key ID', 'env' => 'ALIBABA_ACCESS_KEY_ID', 'placeholder' => 'LTAI...', 'secret' => true],
                    ['key' => 'access_key', 'label' => 'Access Key Secret', 'env' => 'ALIBABA_ACCESS_KEY_SECRET', 'placeholder' => '', 'secret' => true],
                    ['key' => 'topic_name', 'label' => 'Topic name', 'env' => 'MNS_ORDERS_TOPIC', 'placeholder' => 'orders-created'],
                ],
            ],
        ];
    }

    public static function isInstalled(string $driver): bool
    {
        $providerClass = self::all()[$driver]['providerClass'] ?? null;

        // 'log' and any driver with no provider class ships in core, so
        // there's nothing to check — it's always available.
        return $providerClass === null || class_exists($providerClass);
    }
}
