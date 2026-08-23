<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardMns;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;
use AliyunMNS\Requests\PublishMessageRequest;
use AliyunMNS\Topic;

/**
 * Delivers envelopes to an Alibaba Cloud MNS topic. One topic per transport
 * instance (`topic_name` in config/store-forward.php under `drivers.mns`).
 *
 * The MNS PHP SDK's topic API only exposes a single-message publish
 * request (no batch-publish-to-topic call, unlike its queue API), so
 * sendBatch() is a plain loop — see TransportInterface's docblock, which
 * explicitly allows that as the default when a broker has no native batch
 * operation for this use case.
 */
class MnsTransport implements TransportInterface
{
    public function __construct(protected Topic $topic)
    {
    }

    public function send(Envelope $envelope): void
    {
        $response = $this->topic->publishMessage(new PublishMessageRequest(
            json_encode($envelope->toArray(), JSON_THROW_ON_ERROR),
            $envelope->channel, // used as the message tag for subscriber-side filtering
        ));

        if (! $response->isSucceed()) {
            throw new \RuntimeException(
                "Failed to publish envelope [{$envelope->id}] to MNS topic: HTTP {$response->getStatusCode()}"
            );
        }
    }

    public function sendBatch(array $envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $this->send($envelope);
        }
    }
}
