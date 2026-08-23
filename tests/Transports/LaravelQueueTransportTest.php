<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests\Transports;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForward\Jobs\DeliverEnvelopeJob;
use AftermathPathfinder\StoreForward\Tests\TestCase;
use AftermathPathfinder\StoreForward\Transports\LaravelQueueTransport;
use Illuminate\Support\Facades\Queue;

class LaravelQueueTransportTest extends TestCase
{
    public function test_send_pushes_a_deliver_envelope_job_onto_the_queue(): void
    {
        Queue::fake();

        $transport = new LaravelQueueTransport($this->app['queue']->connection());
        $envelope = Envelope::make('orders', ['order_id' => 1]);

        $transport->send($envelope);

        Queue::assertPushed(DeliverEnvelopeJob::class, fn ($job) => $job->envelope->id === $envelope->id);
    }

    public function test_send_batch_pushes_one_job_per_envelope(): void
    {
        Queue::fake();

        $transport = new LaravelQueueTransport($this->app['queue']->connection());

        $transport->sendBatch([
            Envelope::make('orders', ['i' => 1]),
            Envelope::make('orders', ['i' => 2]),
        ]);

        Queue::assertPushed(DeliverEnvelopeJob::class, 2);
    }
}
