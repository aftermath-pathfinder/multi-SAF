<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardPubSub;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;
use Google\Cloud\PubSub\Topic;

/**
 * Delivers envelopes to a Google Cloud Pub/Sub topic. One topic per
 * transport instance (`topic_name` in config/store-forward.php under
 * `drivers.pubsub`) — see this package's README for multi-topic setups.
 */
class PubSubTransport implements TransportInterface
{
    public function __construct(protected Topic $topic)
    {
    }

    public function send(Envelope $envelope): void
    {
        $this->topic->publish($this->messageFor($envelope));
    }

    /**
     * Pub/Sub's publishBatch() requires every message in one call to share
     * the same ordering key (or have none at all), so a mixed-key batch is
     * split into one publishBatch() call per distinct key before sending.
     */
    public function sendBatch(array $envelopes): void
    {
        $groups = [];
        foreach ($envelopes as $envelope) {
            $groups[$envelope->key ?? ''][] = $envelope;
        }

        foreach ($groups as $group) {
            $this->topic->publishBatch(array_map(
                fn (Envelope $envelope) => $this->messageFor($envelope),
                $group
            ));
        }
    }

    /**
     * @return array{data: string, attributes: array<string, string>, orderingKey?: string}
     */
    protected function messageFor(Envelope $envelope): array
    {
        $message = [
            'data' => json_encode($envelope->toArray(), JSON_THROW_ON_ERROR),
            'attributes' => ['channel' => $envelope->channel],
        ];

        if ($envelope->key !== null) {
            $message['orderingKey'] = $envelope->key;
        }

        return $message;
    }
}
