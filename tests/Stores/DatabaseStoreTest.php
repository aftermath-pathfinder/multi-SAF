<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests\Stores;

use AftermathPathfinder\StoreForward\Contracts\StoreInterface;
use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForward\Tests\TestCase;
use Illuminate\Support\Carbon;

class DatabaseStoreTest extends TestCase
{
    public function test_put_persists_a_pending_row_and_returns_an_id(): void
    {
        $store = $this->app->make(StoreInterface::class);

        $id = $store->put(Envelope::make('orders', ['order_id' => 1]));

        $this->assertNotEmpty($id);
        $this->assertDatabaseHas('store_forward_messages', [
            'id' => $id,
            'channel' => 'orders',
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    public function test_pending_returns_only_due_rows_for_the_requested_channel(): void
    {
        $store = $this->app->make(StoreInterface::class);

        $store->put(Envelope::make('orders', ['order_id' => 1]));
        $store->put(Envelope::make('notifications', ['to' => 'a@example.com']));

        $results = $store->pending('orders');

        $this->assertCount(1, $results);
        $this->assertSame('orders', $results[0]['envelope']->channel);
        $this->assertSame(0, $results[0]['attempts']);
    }

    public function test_pending_excludes_rows_not_yet_available(): void
    {
        $store = $this->app->make(StoreInterface::class);
        $id = $store->put(Envelope::make('orders', ['order_id' => 1]));

        $this->app['db']->connection()->table('store_forward_messages')
            ->where('id', $id)
            ->update(['available_at' => Carbon::now()->addMinutes(5)]);

        $this->assertCount(0, $store->pending());
    }

    public function test_pending_claims_rows_by_marking_them_processing(): void
    {
        $store = $this->app->make(StoreInterface::class);
        $store->put(Envelope::make('orders', ['order_id' => 1]));

        $store->pending();

        $this->assertDatabaseHas('store_forward_messages', ['status' => 'processing']);
        $this->assertCount(0, $store->pending(), 'a second read should not reclaim an already-processing row');
    }

    public function test_pending_respects_the_limit(): void
    {
        $store = $this->app->make(StoreInterface::class);
        for ($i = 0; $i < 5; $i++) {
            $store->put(Envelope::make('orders', ['i' => $i]));
        }

        $this->assertCount(2, $store->pending(null, 2));
    }

    public function test_mark_sent_updates_status_and_timestamp(): void
    {
        $store = $this->app->make(StoreInterface::class);
        $id = $store->put(Envelope::make('orders', ['order_id' => 1]));

        $store->markSent($id);

        $this->assertDatabaseHas('store_forward_messages', ['id' => $id, 'status' => 'sent']);
        $sentAt = $this->app['db']->connection()->table('store_forward_messages')->where('id', $id)->value('sent_at');
        $this->assertNotNull($sentAt);
    }

    public function test_mark_failed_reverts_to_pending_and_increments_attempts_with_backoff(): void
    {
        $store = $this->app->make(StoreInterface::class);
        $id = $store->put(Envelope::make('orders', ['order_id' => 1]));

        $store->markFailed($id, 'broker unreachable');

        $row = $this->app['db']->connection()->table('store_forward_messages')->where('id', $id)->first();

        $this->assertSame('pending', $row->status);
        $this->assertSame(1, $row->attempts);
        $this->assertSame('broker unreachable', $row->last_error);
        $this->assertTrue(Carbon::parse($row->available_at)->isFuture());
    }

    public function test_mark_dead_sets_terminal_status(): void
    {
        $store = $this->app->make(StoreInterface::class);
        $id = $store->put(Envelope::make('orders', ['order_id' => 1]));

        $store->markDead($id, 'exhausted retries');

        $this->assertDatabaseHas('store_forward_messages', [
            'id' => $id,
            'status' => 'dead',
            'last_error' => 'exhausted retries',
        ]);
    }
}
