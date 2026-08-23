<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardKafka;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;
use longlang\phpkafka\Producer\ProduceMessage;
use longlang\phpkafka\Producer\Producer;

/**
 * Delivers envelopes to a Kafka topic via longlang/phpkafka — a pure-PHP
 * client, chosen over `rdkafka` (the other common option) specifically
 * because it's a plain Composer dependency: no PECL extension to compile
 * and no Docker-image customization required just to install this driver.
 */
class KafkaTransport implements TransportInterface
{
    public function __construct(
        protected Producer $producer,
        protected string $topic,
    ) {
    }

    public function send(Envelope $envelope): void
    {
        $this->producer->send(
            $this->topic,
            json_encode($envelope->toArray(), JSON_THROW_ON_ERROR),
            $envelope->key,
        );
    }

    public function sendBatch(array $envelopes): void
    {
        $this->producer->sendBatch(array_map(
            fn (Envelope $envelope) => new ProduceMessage(
                $this->topic,
                json_encode($envelope->toArray(), JSON_THROW_ON_ERROR),
                $envelope->key,
            ),
            $envelopes
        ));
    }
}
