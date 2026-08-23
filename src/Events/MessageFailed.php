<?php

namespace AftermathPathfinder\StoreForward\Events;

use AftermathPathfinder\StoreForward\Envelope;

class MessageFailed
{
    public function __construct(
        public Envelope $envelope,
        public string $transport,
        public \Throwable $exception,
        public int $attempts,
        public bool $dead,
    ) {
    }
}
