<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward;

use Ramsey\Uuid\Uuid;

/**
 * The unit of transport: everything a driver needs to deliver a message,
 * independent of where it ends up (Kafka topic, AMQP exchange, SQS queue, ...).
 */
class Envelope implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $channel,
        public readonly array $payload,
        public readonly array $headers = [],
        public readonly ?string $key = null,
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }

    public static function make(string $channel, array $payload, array $headers = [], ?string $key = null): self
    {
        return new self(
            id: self::uuid(),
            channel: $channel,
            payload: $payload,
            headers: $headers,
            key: $key,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            channel: $data['channel'],
            payload: $data['payload'],
            headers: $data['headers'] ?? [],
            key: $data['key'] ?? null,
            createdAt: isset($data['created_at'])
                ? new \DateTimeImmutable($data['created_at'])
                : new \DateTimeImmutable(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'payload' => $this->payload,
            'headers' => $this->headers,
            'key' => $this->key,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function uuid(): string
    {
        if (class_exists(Uuid::class)) {
            return Uuid::uuid4()->toString();
        }

        // Fallback so the package has no hard dependency on ramsey/uuid.
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}
