<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardSqs\Tests;

use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForwardSqs\SqsClientInterface;
use AftermathPathfinder\StoreForwardSqs\SqsTransport;
use PHPUnit\Framework\TestCase;

class SqsTransportTest extends TestCase
{
    public function test_send_calls_send_message_with_the_configured_queue_url(): void
    {
        $client = $this->createMock(SqsClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(function (array $args) {
                return $args['QueueUrl'] === 'https://sqs.example/queue'
                    && json_decode($args['MessageBody'], true)['payload'] === ['order_id' => 1]
                    && $args['MessageAttributes']['channel']['StringValue'] === 'orders';
            }));

        $transport = new SqsTransport($client, 'https://sqs.example/queue');
        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }

    public function test_send_batch_chunks_into_groups_of_ten(): void
    {
        $client = $this->createMock(SqsClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('sendMessageBatch')
            ->with($this->callback(function (array $args) {
                return $args['QueueUrl'] === 'https://sqs.example/queue'
                    && count($args['Entries']) <= 10
                    && isset($args['Entries'][0]['Id'], $args['Entries'][0]['MessageBody']);
            }));

        $envelopes = array_map(
            fn ($i) => Envelope::make('orders', ['i' => $i]),
            range(1, 15)
        );

        $transport = new SqsTransport($client, 'https://sqs.example/queue');
        $transport->sendBatch($envelopes);
    }

    public function test_a_client_exception_propagates(): void
    {
        $client = $this->createMock(SqsClientInterface::class);
        $client->method('sendMessage')->willThrowException(new \RuntimeException('throttled'));

        $transport = new SqsTransport($client, 'https://sqs.example/queue');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('throttled');

        $transport->send(Envelope::make('orders', ['order_id' => 1]));
    }
}
