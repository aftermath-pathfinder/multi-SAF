<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardRedisStreams;

use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Envelope;
use Illuminate\Contracts\Redis\Connection;

/**
 * Delivers envelopes to a Redis stream via `XADD`. No new SDK dependency —
 * this uses the Redis connection your Laravel app already has configured
 * (Predis or the phpredis extension, whichever `config('database.redis.client')`
 * selects), which is what makes this the lowest-friction self-hosted
 * option: if you already run Redis for cache/queues, there's nothing new
 * to install or operate.
 */
class RedisStreamsTransport implements TransportInterface
{
    public function __construct(
        protected Connection $connection,
        protected string $stream,
    ) {
    }

    public function send(Envelope $envelope): void
    {
        // Calling command('xAdd', ...) rather than $this->connection->xAdd(...)
        // deliberately avoids relying on the underlying client's magic
        // __call forwarding: Illuminate\Contracts\Redis\Connection only
        // declares command() itself, so this is the one call guaranteed to
        // be understood by static analysis and by both Predis and phpredis.
        $this->connection->command('xAdd', [$this->stream, '*', $this->fieldsFor($envelope)]);
    }

    /**
     * XADD has no native multi-entry form (each call appends one entry),
     * so this is a loop — the same documented default as every other
     * driver without a real batch API.
     */
    public function sendBatch(array $envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $this->send($envelope);
        }
    }

    /**
     * Redis streams store flat field => value string pairs, so the
     * envelope's structured payload/headers are JSON-encoded into single
     * fields rather than flattened (which would be lossy for nested data).
     *
     * @return array<string, string>
     */
    protected function fieldsFor(Envelope $envelope): array
    {
        return [
            'id' => $envelope->id,
            'channel' => $envelope->channel,
            'payload' => json_encode($envelope->payload, JSON_THROW_ON_ERROR),
            'headers' => json_encode($envelope->headers, JSON_THROW_ON_ERROR),
            'key' => $envelope->key ?? '',
            'created_at' => $envelope->createdAt->format(DATE_ATOM),
        ];
    }
}
