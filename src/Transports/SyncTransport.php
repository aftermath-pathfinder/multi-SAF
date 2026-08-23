<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Transports;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;

/**
 * No-op-ish transport: invokes a closure inline. Useful for local dev and
 * for tests that want to assert on published envelopes without a broker.
 *
 * Also a template for what the smallest possible Transport looks like —
 * copy this file when wiring up a real driver.
 */
class SyncTransport implements TransportInterface
{
    /** @var array<int, Envelope> */
    protected array $sent = [];

    /** @var (callable(Envelope): void)|null */
    protected $handler;

    public function __construct(?callable $handler = null)
    {
        $this->handler = $handler;
    }

    public function send(Envelope $envelope): void
    {
        $this->sent[] = $envelope;

        if ($this->handler) {
            ($this->handler)($envelope);
        }
    }

    public function sendBatch(array $envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $this->send($envelope);
        }
    }

    /**
     * @return Envelope[]
     */
    public function sentEnvelopes(): array
    {
        return $this->sent;
    }
}
