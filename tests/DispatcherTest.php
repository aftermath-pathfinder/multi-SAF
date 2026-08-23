<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests;

use AftermathPathfinder\StoreForward\Contracts\StoreInterface;
use AftermathPathfinder\StoreForward\Dispatcher;
use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForward\Events\MessageFailed;
use AftermathPathfinder\StoreForward\Events\MessagePublished;
use AftermathPathfinder\StoreForward\Facades\StoreForward;
use AftermathPathfinder\StoreForward\Transports\SyncTransport;
use Illuminate\Support\Facades\Event;

class DispatcherTest extends TestCase
{
    public function test_drain_returns_zero_when_nothing_is_pending(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        $this->assertSame(0, $dispatcher->drain());
    }

    public function test_drain_dispatches_a_message_published_event_on_success(): void
    {
        Event::fake([MessagePublished::class]);
        StoreForward::extend('sync', fn () => new SyncTransport());
        StoreForward::publish('orders', ['order_id' => 1]);

        $this->app->make(Dispatcher::class)->drain();

        Event::assertDispatched(
            MessagePublished::class,
            fn ($event) => $event->envelope->payload['order_id'] === 1
        );
    }

    public function test_drain_dispatches_a_message_failed_event_on_error(): void
    {
        Event::fake([MessageFailed::class]);
        StoreForward::extend('sync', fn () => new SyncTransport(function () {
            throw new \RuntimeException('nope');
        }));
        StoreForward::publish('orders', ['order_id' => 1]);

        $this->app->make(Dispatcher::class)->drain();

        Event::assertDispatched(MessageFailed::class, function ($event) {
            return $event->attempts === 1 && $event->exception->getMessage() === 'nope';
        });
    }

    public function test_a_message_is_dead_lettered_only_after_max_attempts_is_reached(): void
    {
        $this->app['config']->set('store-forward.max_attempts', 3);
        StoreForward::extend('sync', fn () => new SyncTransport(function () {
            throw new \RuntimeException('nope');
        }));

        $store = $this->app->make(StoreInterface::class);
        $id = $store->put(Envelope::make('orders', ['order_id' => 1]));

        $dispatcher = $this->app->make(Dispatcher::class);

        // Attempts 1 and 2 stay pending for retry.
        $dispatcher->drain();
        $this->assertDatabaseHas('store_forward_messages', ['id' => $id, 'status' => 'pending', 'attempts' => 1]);

        $this->makeRowImmediatelyAvailable($id);
        $dispatcher->drain();
        $this->assertDatabaseHas('store_forward_messages', ['id' => $id, 'status' => 'pending', 'attempts' => 2]);

        // Third attempt exhausts max_attempts and dead-letters the row.
        $this->makeRowImmediatelyAvailable($id);
        $dispatcher->drain();
        $this->assertDatabaseHas('store_forward_messages', ['id' => $id, 'status' => 'dead']);
    }

    public function test_drain_routes_each_channel_to_its_own_configured_transport(): void
    {
        $delivered = ['a' => [], 'b' => []];
        $transportA = new SyncTransport(function ($e) use (&$delivered) {
            $delivered['a'][] = $e;
        });
        $transportB = new SyncTransport(function ($e) use (&$delivered) {
            $delivered['b'][] = $e;
        });
        StoreForward::extend('transport-a', fn () => $transportA);
        StoreForward::extend('transport-b', fn () => $transportB);
        $this->app['config']->set('store-forward.channels.orders.driver', 'transport-a');
        $this->app['config']->set('store-forward.channels.notifications.driver', 'transport-b');

        StoreForward::publish('orders', ['order_id' => 1]);
        StoreForward::publish('notifications', ['to' => 'a@example.com']);

        $this->app->make(Dispatcher::class)->drain();

        $this->assertCount(1, $delivered['a']);
        $this->assertCount(1, $delivered['b']);
    }

    /**
     * markFailed() schedules the next attempt in the future (backoff), so a
     * test that wants to immediately re-drain has to fast-forward it.
     */
    private function makeRowImmediatelyAvailable(int|string $id): void
    {
        $this->app['db']->connection()->table('store_forward_messages')
            ->where('id', $id)
            ->update(['available_at' => now()->subMinute()]);
    }
}
