<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests;

use AftermathPathfinder\StoreForward\Dispatcher;
use AftermathPathfinder\StoreForward\Facades\StoreForward;
use AftermathPathfinder\StoreForward\Transports\SyncTransport;

class PublishAndDispatchTest extends TestCase
{
    public function test_publish_writes_a_pending_outbox_row(): void
    {
        StoreForward::publish('orders', ['order_id' => 1]);

        $this->assertDatabaseHas('store_forward_messages', [
            'channel' => 'orders',
            'status' => 'pending',
        ]);
    }

    public function test_dispatcher_forwards_pending_rows_to_the_configured_transport(): void
    {
        $captured = [];
        $sync = new SyncTransport(function ($envelope) use (&$captured) {
            $captured[] = $envelope;
        });

        StoreForward::extend('sync', fn () => $sync);

        StoreForward::publish('orders', ['order_id' => 42]);

        $processed = $this->app->make(Dispatcher::class)->drain();

        $this->assertSame(1, $processed);
        $this->assertCount(1, $captured);
        $this->assertSame(42, $captured[0]->payload['order_id']);
        $this->assertDatabaseHas('store_forward_messages', [
            'channel' => 'orders',
            'status' => 'sent',
        ]);
    }

    public function test_a_failing_transport_is_retried_then_eventually_dead_lettered(): void
    {
        $this->app['config']->set('store-forward.max_attempts', 1);

        StoreForward::extend('sync', fn () => new SyncTransport(function () {
            throw new \RuntimeException('broker unreachable');
        }));

        StoreForward::publish('orders', ['order_id' => 7]);

        $this->app->make(Dispatcher::class)->drain();

        $this->assertDatabaseHas('store_forward_messages', [
            'channel' => 'orders',
            'status' => 'dead',
        ]);
    }
}
