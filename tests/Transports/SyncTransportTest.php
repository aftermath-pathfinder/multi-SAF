<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests\Transports;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForward\Transports\SyncTransport;
use PHPUnit\Framework\TestCase;

class SyncTransportTest extends TestCase
{
    public function test_send_invokes_the_handler_and_records_the_envelope(): void
    {
        $received = null;
        $transport = new SyncTransport(function (Envelope $envelope) use (&$received) {
            $received = $envelope;
        });

        $envelope = Envelope::make('orders', ['order_id' => 1]);
        $transport->send($envelope);

        $this->assertSame($envelope, $received);
        $this->assertSame([$envelope], $transport->sentEnvelopes());
    }

    public function test_send_works_without_a_handler(): void
    {
        $transport = new SyncTransport();

        $transport->send(Envelope::make('orders', ['order_id' => 1]));

        $this->assertCount(1, $transport->sentEnvelopes());
    }

    public function test_send_batch_delivers_every_envelope_in_order(): void
    {
        $received = [];
        $transport = new SyncTransport(function (Envelope $envelope) use (&$received) {
            $received[] = $envelope->payload['i'];
        });

        $transport->sendBatch([
            Envelope::make('orders', ['i' => 1]),
            Envelope::make('orders', ['i' => 2]),
            Envelope::make('orders', ['i' => 3]),
        ]);

        $this->assertSame([1, 2, 3], $received);
    }

    public function test_a_throwing_handler_propagates_the_exception(): void
    {
        $transport = new SyncTransport(function () {
            throw new \RuntimeException('broker unreachable');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('broker unreachable');

        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }
}
