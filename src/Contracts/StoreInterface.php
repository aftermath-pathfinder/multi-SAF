<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Contracts;

use AftermathPathfinder\StoreForward\Envelope;

/**
 * The durable "store" half of store-and-forward: the local write-ahead log
 * (an outbox table by default) that a message lands in synchronously, in
 * the same transaction as the business change that produced it.
 *
 * The Dispatcher later reads pending rows and hands them to a Transport.
 * This is what makes delivery survive a broker outage, a deploy, or a
 * crashed worker: nothing is lost between "the app decided to publish"
 * and "the broker actually has it".
 */
interface StoreInterface
{
    /**
     * Persist an envelope as pending, returning the store's internal id
     * for it (not necessarily the envelope's own id).
     */
    public function put(Envelope $envelope): string|int;

    /**
     * Fetch up to $limit pending envelopes, oldest first, for a channel
     * (or all channels when null). Implementations should lock/claim rows
     * so concurrent workers don't double-send.
     *
     * @return array<array{record_id: string|int, envelope: Envelope, attempts: int}>
     */
    public function pending(?string $channel = null, int $limit = 100): array;

    public function markSent(string|int $recordId): void;

    public function markFailed(string|int $recordId, string $reason): void;

    /**
     * Move a record to the dead-letter state after exhausting retries.
     */
    public function markDead(string|int $recordId, string $reason): void;
}
