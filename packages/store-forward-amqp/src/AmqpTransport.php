<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardAmqp;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Delivers envelopes to a RabbitMQ exchange/queue via php-amqplib.
 *
 * `exchange` in config/store-forward.php under `drivers.amqp` may be left
 * as the default empty string to publish directly to a named queue (the
 * default exchange routes by queue name), or set to a real exchange with
 * `routing_key` set to whatever that exchange expects.
 */
class AmqpTransport implements TransportInterface
{
    public function __construct(
        protected AMQPChannel $channel,
        protected string $exchange,
        protected string $routingKey,
    ) {
    }

    public function send(Envelope $envelope): void
    {
        $this->channel->basic_publish($this->messageFor($envelope), $this->exchange, $this->routingKey);
    }

    public function sendBatch(array $envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $this->send($envelope);
        }
    }

    protected function messageFor(Envelope $envelope): AMQPMessage
    {
        return new AMQPMessage(
            json_encode($envelope->toArray(), JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable(['channel' => $envelope->channel]),
            ],
        );
    }
}
