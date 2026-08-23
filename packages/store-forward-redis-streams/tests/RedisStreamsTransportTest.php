<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardRedisStreams\Tests;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForwardRedisStreams\RedisStreamsTransport;
use Illuminate\Contracts\Redis\Connection;
use PHPUnit\Framework\TestCase;

class RedisStreamsTransportTest extends TestCase
{
    public function test_send_calls_xadd_with_the_configured_stream_and_json_fields(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('command')
            ->with('xAdd', $this->callback(function (array $args) {
                [$stream, $id, $fields] = $args;

                return $stream === 'orders-stream'
                    && $id === '*'
                    && json_decode($fields['payload'], true) === ['order_id' => 1]
                    && $fields['channel'] === 'orders';
            }));

        $transport = new RedisStreamsTransport($connection, 'orders-stream');
        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }

    public function test_send_batch_calls_xadd_once_per_envelope(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))->method('command');

        $transport = new RedisStreamsTransport($connection, 'orders-stream');
        $transport->sendBatch([
            Envelope::make('orders', ['i' => 1]),
            Envelope::make('orders', ['i' => 2]),
            Envelope::make('orders', ['i' => 3]),
        ]);
    }

    public function test_a_connection_exception_propagates(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('command')->willThrowException(new \RuntimeException('connection refused'));

        $transport = new RedisStreamsTransport($connection, 'orders-stream');

        $this->expectException(\RuntimeException::class);
        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }
}
