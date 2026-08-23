<?php

namespace AftermathPathfinder\StoreForward\Events;

use AftermathPathfinder\StoreForward\Envelope;

class MessagePublished
{
    public function __construct(public Envelope $envelope, public string $transport)
    {
    }
}
