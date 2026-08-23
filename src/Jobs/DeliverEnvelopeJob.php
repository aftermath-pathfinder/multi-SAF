<?php

namespace AftermathPathfinder\StoreForward\Jobs;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForward\Events\MessagePublished;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The job body used by LaravelQueueTransport. Kept deliberately dumb: by
 * the time an envelope reaches here it's already durable in the outbox, so
 * this job's only responsibility is to hand it to whatever actually
 * consumes it downstream (an event listener, another queue, etc.).
 */
class DeliverEnvelopeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public Envelope $envelope)
    {
    }

    public function handle(): void
    {
        event(new MessagePublished($this->envelope, 'laravel-queue'));
    }
}
