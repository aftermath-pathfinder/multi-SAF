<?php

namespace AftermathPathfinder\StoreForward\Transports;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForward\Jobs\DeliverEnvelopeJob;
use Illuminate\Contracts\Queue\Queue;

/**
 * Forwards onto Laravel's own queue connection (database, redis, sqs, ...).
 * Ships in core because every Laravel app already has a queue configured —
 * this is the "no extra infrastructure" option, and a reasonable default
 * before reaching for Kafka/AMQP.
 */
class LaravelQueueTransport implements TransportInterface
{
    public function __construct(protected Queue $queue, protected ?string $queueName = null)
    {
    }

    public function send(Envelope $envelope): void
    {
        $this->queue->pushOn($this->queueName, new DeliverEnvelopeJob($envelope));
    }

    public function sendBatch(array $envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $this->send($envelope);
        }
    }
}
