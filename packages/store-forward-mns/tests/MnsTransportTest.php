<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardMns\Tests;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForwardMns\MnsTransport;
use AliyunMNS\Responses\PublishMessageResponse;
use AliyunMNS\Topic;
use PHPUnit\Framework\TestCase;

class MnsTransportTest extends TestCase
{
    public function test_send_publishes_with_the_channel_as_the_message_tag(): void
    {
        $response = $this->createMock(PublishMessageResponse::class);
        $response->method('isSucceed')->willReturn(true);

        $topic = $this->createMock(Topic::class);
        $topic->expects($this->once())
            ->method('publishMessage')
            ->with($this->callback(function ($request) {
                return json_decode($request->getMessageBody(), true)['payload'] === ['order_id' => 1]
                    && $request->getMessageTag() === 'orders';
            }))
            ->willReturn($response);

        $transport = new MnsTransport($topic);
        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }

    public function test_send_throws_when_the_response_reports_failure(): void
    {
        $response = $this->createMock(PublishMessageResponse::class);
        $response->method('isSucceed')->willReturn(false);
        $response->method('getStatusCode')->willReturn(500);

        $topic = $this->createMock(Topic::class);
        $topic->method('publishMessage')->willReturn($response);

        $transport = new MnsTransport($topic);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }

    public function test_send_batch_publishes_each_envelope_individually(): void
    {
        $response = $this->createMock(PublishMessageResponse::class);
        $response->method('isSucceed')->willReturn(true);

        $topic = $this->createMock(Topic::class);
        $topic->expects($this->exactly(3))->method('publishMessage')->willReturn($response);

        $transport = new MnsTransport($topic);
        $transport->sendBatch([
            Envelope::make('orders', ['i' => 1]),
            Envelope::make('orders', ['i' => 2]),
            Envelope::make('orders', ['i' => 3]),
        ]);
    }
}
