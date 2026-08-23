<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardAmqp\Tests;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForwardAmqp\AmqpTransport;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

class AmqpTransportTest extends TestCase
{
    public function test_send_publishes_a_persistent_message_to_the_configured_exchange_and_routing_key(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->callback(function (AMQPMessage $msg) {
                    return json_decode($msg->getBody(), true)['payload'] === ['order_id' => 1]
                        && $msg->get('delivery_mode') === AMQPMessage::DELIVERY_MODE_PERSISTENT;
                }),
                'orders-exchange',
                'orders-routing-key',
            );

        $transport = new AmqpTransport($channel, 'orders-exchange', 'orders-routing-key');
        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }

    public function test_send_batch_publishes_each_envelope_individually(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->exactly(3))->method('basic_publish');

        $transport = new AmqpTransport($channel, '', 'orders-queue');
        $transport->sendBatch([
            Envelope::make('orders', ['i' => 1]),
            Envelope::make('orders', ['i' => 2]),
            Envelope::make('orders', ['i' => 3]),
        ]);
    }

    public function test_a_channel_exception_propagates(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willThrowException(new \RuntimeException('channel closed'));

        $transport = new AmqpTransport($channel, '', 'orders-queue');

        $this->expectException(\RuntimeException::class);
        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }
}
