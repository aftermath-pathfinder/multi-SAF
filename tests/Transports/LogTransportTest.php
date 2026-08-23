<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests\Transports;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForward\Transports\LogTransport;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LogTransportTest extends TestCase
{
    public function test_send_logs_the_envelope_payload(): void
    {
        $logger = new class extends NullLogger {
            public array $records = [];

            public function info(string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [$message, $context];
            }
        };

        $transport = new LogTransport($logger, 'store-forward-test');
        $envelope = Envelope::make('orders', ['order_id' => 1]);

        $transport->send($envelope);

        $this->assertCount(1, $logger->records);
        [$message, $context] = $logger->records[0];
        $this->assertStringContainsString('store-forward-test', $message);
        $this->assertSame($envelope->toArray(), $context);
    }

    public function test_send_batch_logs_each_envelope(): void
    {
        $logger = new class extends NullLogger {
            public int $count = 0;

            public function info(string|\Stringable $message, array $context = []): void
            {
                $this->count++;
            }
        };

        $transport = new LogTransport($logger);

        $transport->sendBatch([
            Envelope::make('orders', ['i' => 1]),
            Envelope::make('orders', ['i' => 2]),
        ]);

        $this->assertSame(2, $logger->count);
    }
}
