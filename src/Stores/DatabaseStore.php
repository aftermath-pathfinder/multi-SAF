<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Stores;

use AftermathPathfinder\StoreForward\Contracts\StoreInterface;
use AftermathPathfinder\StoreForward\Envelope;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Default outbox implementation: one table, one connection, works with
 * whatever database the host app already has. No broker required to be
 * "durable" — that's the whole point of store-and-forward.
 */
class DatabaseStore implements StoreInterface
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected string $table = 'store_forward_messages',
    ) {
    }

    public function put(Envelope $envelope): string|int
    {
        return $this->connection->table($this->table)->insertGetId([
            'message_id' => $envelope->id,
            'channel' => $envelope->channel,
            'key' => $envelope->key,
            'payload' => json_encode($envelope->payload),
            'headers' => json_encode($envelope->headers),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function pending(?string $channel = null, int $limit = 100): array
    {
        return $this->connection->transaction(function () use ($channel, $limit) {
            $query = $this->connection->table($this->table)
                ->where('status', 'pending')
                ->where('available_at', '<=', Carbon::now())
                ->when($channel, fn ($q) => $q->where('channel', $channel))
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate();

            $rows = $query->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $this->connection->table($this->table)
                ->whereIn('id', $rows->pluck('id'))
                ->update(['status' => 'processing', 'updated_at' => Carbon::now()]);

            return $rows->map(fn ($row) => [
                'record_id' => $row->id,
                'attempts' => $row->attempts,
                'envelope' => Envelope::fromArray([
                    'id' => $row->message_id,
                    'channel' => $row->channel,
                    'payload' => json_decode($row->payload, true),
                    'headers' => json_decode($row->headers ?? '[]', true),
                    'key' => $row->key,
                    'created_at' => $row->created_at,
                ]),
            ])->all();
        });
    }

    public function markSent(string|int $recordId): void
    {
        $this->connection->table($this->table)->where('id', $recordId)->update([
            'status' => 'sent',
            'sent_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markFailed(string|int $recordId, string $reason): void
    {
        $this->connection->table($this->table)->where('id', $recordId)->update([
            'status' => 'pending',
            'attempts' => $this->connection->raw('attempts + 1'),
            'last_error' => $reason,
            'available_at' => $this->nextAttemptAt($recordId),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markDead(string|int $recordId, string $reason): void
    {
        $this->connection->table($this->table)->where('id', $recordId)->update([
            'status' => 'dead',
            'last_error' => $reason,
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Simple exponential backoff based on attempts already made.
     */
    protected function nextAttemptAt(string|int $recordId): Carbon
    {
        $attempts = (int) $this->connection->table($this->table)->where('id', $recordId)->value('attempts');
        $seconds = min(900, (2 ** max(0, $attempts)) * 5);

        return Carbon::now()->addSeconds($seconds);
    }
}
