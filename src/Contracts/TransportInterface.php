<?php

namespace AftermathPathfinder\StoreForward\Contracts;

use AftermathPathfinder\StoreForward\Envelope;

/**
 * A Transport is a driver for one wire protocol/broker: Kafka, AMQP, SQS,
 * Redis Streams, a webhook, Laravel's own queue, etc.
 *
 * Transports only need to know how to *send*. Anything about durability,
 * retry, or ordering ahead of the send is the Store's and Dispatcher's job —
 * that's what keeps a Transport implementation small enough to ship as its
 * own tiny composer package (store-forward-kafka, store-forward-amqp, ...).
 */
interface TransportInterface
{
    /**
     * Deliver a single envelope. Throw on failure — the Dispatcher decides
     * whether/when to retry; the transport should not swallow errors.
     */
    public function send(Envelope $envelope): void;

    /**
     * Deliver a batch, when the underlying broker supports it more
     * efficiently than one-by-one (Kafka producer batches, SQS
     * SendMessageBatch, ...). Default implementation may just loop.
     *
     * @param  Envelope[]  $envelopes
     */
    public function sendBatch(array $envelopes): void;
}
