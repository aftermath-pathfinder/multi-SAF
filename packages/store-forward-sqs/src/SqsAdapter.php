<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardSqs;

use Aws\Sqs\SqsClient;

/**
 * The only class in this package that touches the real AWS SDK. Kept to
 * two one-line methods on purpose — see SqsClientInterface's docblock for
 * why this seam exists.
 */
class SqsAdapter implements SqsClientInterface
{
    public function __construct(protected SqsClient $client)
    {
    }

    public function sendMessage(array $params): void
    {
        $this->client->sendMessage($params);
    }

    public function sendMessageBatch(array $params): void
    {
        $this->client->sendMessageBatch($params);
    }
}
