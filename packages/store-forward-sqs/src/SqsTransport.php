<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardSqs;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;

/**
 * Delivers envelopes to an AWS SQS queue via the official SDK's SqsClient
 * (wrapped behind SqsClientInterface — see its docblock for why).
 *
 * One queue per transport instance (`queue_url` in config/store-forward.php
 * under `drivers.sqs`) — route different channels to different queues by
 * registering multiple driver names via `extend()` if you need that;
 * see this package's README.
 */
class SqsTransport implements TransportInterface
{
    public function __construct(
        protected SqsClientInterface $client,
        protected string $queueUrl,
    ) {
    }

    public function send(Envelope $envelope): void
    {
        $this->client->sendMessage([
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => json_encode($envelope->toArray(), JSON_THROW_ON_ERROR),
            'MessageAttributes' => $this->attributesFor($envelope),
        ]);
    }

    /**
     * SQS's SendMessageBatch caps at 10 messages per request, so batches
     * larger than that are chunked.
     */
    public function sendBatch(array $envelopes): void
    {
        foreach (array_chunk($envelopes, 10) as $chunk) {
            $this->client->sendMessageBatch([
                'QueueUrl' => $this->queueUrl,
                'Entries' => array_map(fn (Envelope $envelope) => [
                    'Id' => $envelope->id,
                    'MessageBody' => json_encode($envelope->toArray(), JSON_THROW_ON_ERROR),
                    'MessageAttributes' => $this->attributesFor($envelope),
                ], $chunk),
            ]);
        }
    }

    /**
     * @return array<string, array{DataType: string, StringValue: string}>
     */
    protected function attributesFor(Envelope $envelope): array
    {
        return [
            'channel' => ['DataType' => 'String', 'StringValue' => $envelope->channel],
        ];
    }
}
