<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardSqs;

use AftermathPathfinder\StoreForward\StoreForwardManager;
use Aws\Sqs\SqsClient;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the 'sqs' transport driver with the core package's manager.
 * Installing this package and adding it to your app's providers is enough
 * to make `driver: sqs` valid in config/store-forward.php.
 */
class SqsDriverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(StoreForwardManager::class)->extend('sqs', function (array $config) {
            $clientConfig = [
                'region' => $config['region'] ?? env('AWS_DEFAULT_REGION', 'us-east-1'),
                'version' => $config['version'] ?? '2012-11-05',
            ];

            // Omit 'credentials' entirely (rather than passing null) so the
            // SDK falls through to its default provider chain when explicit
            // keys aren't configured: env vars, ~/.aws/credentials, an
            // instance/task role, etc.
            if ($credentials = $this->credentialsFor($config)) {
                $clientConfig['credentials'] = $credentials;
            }

            $client = new SqsAdapter(new SqsClient($clientConfig));

            return new SqsTransport($client, $config['queue_url'] ?? throw new \InvalidArgumentException(
                "store-forward.drivers.sqs.queue_url is required to use the 'sqs' driver."
            ));
        });
    }

    /**
     * Returns an explicit credentials array when key/secret are configured,
     * or lets the SDK fall through to its default provider chain (env vars,
     * shared ~/.aws/credentials file, an instance/task role, ...) otherwise.
     *
     * @return array{key: string, secret: string}|null
     */
    protected function credentialsFor(array $config): ?array
    {
        if (! empty($config['key']) && ! empty($config['secret'])) {
            return ['key' => $config['key'], 'secret' => $config['secret']];
        }

        return null;
    }
}
