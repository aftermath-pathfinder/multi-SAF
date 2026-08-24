<?php

declare(strict_types=1);

namespace App\StoreForwardDemo;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;

/**
 * A transport that isn't backed by any real broker — it exists purely so
 * the playground dashboard can demonstrate store-forward's retry/backoff/
 * dead-letter behavior (the DatabaseStore + Dispatcher machinery) without
 * you needing to actually take a real broker down to see it in action.
 *
 * Not something a real driver package would ever ship; this is playground-
 * only demo code, which is why it lives in app/ rather than packages/.
 */
class FlakyTransport implements TransportInterface
{
    public function __construct(protected int $failurePercent = 60)
    {
    }

    public function send(Envelope $envelope): void
    {
        if (random_int(1, 100) <= $this->failurePercent) {
            throw new \RuntimeException(
                "Simulated failure (this driver fails ~{$this->failurePercent}% of sends on purpose)."
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
