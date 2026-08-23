<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardKafka\Tests;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForwardKafka\KafkaTransport;
use longlang\phpkafka\Producer\ProduceMessage;
use longlang\phpkafka\Producer\Producer;
use PHPUnit\Framework\TestCase;

class KafkaTransportTest extends TestCase
{
    public function test_send_calls_producer_send_with_topic_and_json_payload(): void
    {
        $producer = $this->createMock(Producer::class);
        $producer->expects($this->once())
            ->method('send')
            ->with(
                'orders-topic',
                $this->callback(fn (string $value) => json_decode($value, true)['payload'] === ['order_id' => 1]),
                'order-1',
            );

        $transport = new KafkaTransport($producer, 'orders-topic');
        $transport->send(Envelope::make('orders', ['order_id' => 1], key: 'order-1'));
    }

    public function test_send_batch_builds_one_produce_message_per_envelope(): void
    {
        $producer = $this->createMock(Producer::class);
        $producer->expects($this->once())
            ->method('sendBatch')
            ->with($this->callback(function (array $messages) {
                return count($messages) === 2
                    && $messages[0] instanceof ProduceMessage;
            }));

        $transport = new KafkaTransport($producer, 'orders-topic');
        $transport->sendBatch([
            Envelope::make('orders', ['i' => 1]),
            Envelope::make('orders', ['i' => 2]),
        ]);
    }

    public function test_a_producer_exception_propagates(): void
    {
        $producer = $this->createMock(Producer::class);
        $producer->method('send')->willThrowException(new \RuntimeException('broker unreachable'));

        $transport = new KafkaTransport($producer, 'orders-topic');

        $this->expectException(\RuntimeException::class);
        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }
}
