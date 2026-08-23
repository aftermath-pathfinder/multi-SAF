<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests;

use AftermathPathfinder\StoreForward\Envelope;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Envelope is a plain DTO with no framework dependencies, so it's tested
 * in isolation rather than through the full Testbench app.
 */
class EnvelopeTest extends BaseTestCase
{
    public function test_make_generates_a_unique_id_per_envelope(): void
    {
        $a = Envelope::make('orders', ['x' => 1]);
        $b = Envelope::make('orders', ['x' => 1]);

        $this->assertNotSame($a->id, $b->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $a->id
        );
    }

    public function test_make_carries_channel_payload_headers_and_key(): void
    {
        $envelope = Envelope::make('orders.created', ['order_id' => 1], ['trace-id' => 'abc'], 'order-1');

        $this->assertSame('orders.created', $envelope->channel);
        $this->assertSame(['order_id' => 1], $envelope->payload);
        $this->assertSame(['trace-id' => 'abc'], $envelope->headers);
        $this->assertSame('order-1', $envelope->key);
    }

    public function test_defaults_headers_and_key_when_omitted(): void
    {
        $envelope = Envelope::make('orders', ['order_id' => 1]);

        $this->assertSame([], $envelope->headers);
        $this->assertNull($envelope->key);
    }

    public function test_to_array_round_trips_through_from_array(): void
    {
        $original = Envelope::make('orders', ['order_id' => 1], ['trace-id' => 'abc'], 'order-1');

        $restored = Envelope::fromArray($original->toArray());

        $this->assertSame($original->id, $restored->id);
        $this->assertSame($original->channel, $restored->channel);
        $this->assertSame($original->payload, $restored->payload);
        $this->assertSame($original->headers, $restored->headers);
        $this->assertSame($original->key, $restored->key);
        $this->assertSame(
            $original->createdAt->format(DATE_ATOM),
            $restored->createdAt->format(DATE_ATOM)
        );
    }

    public function test_from_array_defaults_missing_optional_fields(): void
    {
        $envelope = Envelope::fromArray([
            'id' => 'test-id',
            'channel' => 'orders',
            'payload' => ['order_id' => 1],
        ]);

        $this->assertSame([], $envelope->headers);
        $this->assertNull($envelope->key);
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $envelope = Envelope::make('orders', ['order_id' => 1]);

        $this->assertSame($envelope->toArray(), $envelope->jsonSerialize());
        $this->assertJson(json_encode($envelope));
    }
}
