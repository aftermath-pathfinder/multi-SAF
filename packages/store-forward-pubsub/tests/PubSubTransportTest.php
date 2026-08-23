<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardPubSub\Tests;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForwardPubSub\PubSubTransport;
use Google\Cloud\PubSub\Topic;
use PHPUnit\Framework\TestCase;

class PubSubTransportTest extends TestCase
{
    public function test_send_publishes_data_and_channel_attribute(): void
    {
        $topic = $this->createMock(Topic::class);
        $topic->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (array $message) {
                return json_decode($message['data'], true)['payload'] === ['order_id' => 1]
                    && $message['attributes']['channel'] === 'orders'
                    && ! isset($message['orderingKey']);
            }));

        $transport = new PubSubTransport($topic);
        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }

    public function test_send_includes_ordering_key_when_the_envelope_has_one(): void
    {
        $topic = $this->createMock(Topic::class);
        $topic->expects($this->once())
            ->method('publish')
            ->with($this->callback(fn (array $message) => $message['orderingKey'] === 'order-1'));

        $transport = new PubSubTransport($topic);
        $transport->send(Envelope::make('orders', ['order_id' => 1], key: 'order-1'));
    }

    public function test_send_batch_groups_by_ordering_key(): void
    {
        $topic = $this->createMock(Topic::class);
        $topic->expects($this->exactly(2))->method('publishBatch');

        $transport = new PubSubTransport($topic);
        $transport->sendBatch([
            Envelope::make('orders', ['i' => 1], key: 'a'),
            Envelope::make('orders', ['i' => 2], key: 'a'),
            Envelope::make('orders', ['i' => 3], key: 'b'),
        ]);
    }

    public function test_send_batch_with_no_keys_is_a_single_call(): void
    {
        $topic = $this->createMock(Topic::class);
        $topic->expects($this->once())
            ->method('publishBatch')
            ->with($this->callback(fn (array $messages) => count($messages) === 3));

        $transport = new PubSubTransport($topic);
        $transport->sendBatch([
            Envelope::make('orders', ['i' => 1]),
            Envelope::make('orders', ['i' => 2]),
            Envelope::make('orders', ['i' => 3]),
        ]);
    }
}
