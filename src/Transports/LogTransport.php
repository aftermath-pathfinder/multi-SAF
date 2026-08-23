<?php

namespace AftermathPathfinder\StoreForward\Transports;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;
use Psr\Log\LoggerInterface;

/**
 * Writes every envelope to a logger instead of a real broker. Handy as the
 * default driver in local/testing environments, and as a fallback transport
 * to pair with a real one during a migration between brokers.
 */
class LogTransport implements TransportInterface
{
    public function __construct(protected LoggerInterface $logger, protected string $channel = 'store-forward')
    {
    }

    public function send(Envelope $envelope): void
    {
        $this->logger->info("[{$this->channel}] publish", $envelope->toArray());
    }

    public function sendBatch(array $envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $this->send($envelope);
        }
    }
}
