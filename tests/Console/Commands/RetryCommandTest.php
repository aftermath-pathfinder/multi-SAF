<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests\Console\Commands;

use AftermathPathfinder\StoreForward\Contracts\StoreInterface;
use AftermathPathfinder\StoreForward\Envelope;
use AftermathPathfinder\StoreForward\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class RetryCommandTest extends TestCase
{
    public function test_retry_requeues_all_dead_messages_by_default(): void
    {
        $store = $this->app->make(StoreInterface::class);
        $id = $store->put(Envelope::make('orders', ['order_id' => 1]));
        $store->markDead($id, 'exhausted retries');

        Artisan::call('store-forward:retry');

        $this->assertDatabaseHas('store_forward_messages', [
            'id' => $id,
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
        ]);
    }

    public function test_retry_scoped_to_a_channel_leaves_other_channels_dead(): void
    {
        $store = $this->app->make(StoreInterface::class);
        $ordersId = $store->put(Envelope::make('orders', ['order_id' => 1]));
        $notificationsId = $store->put(Envelope::make('notifications', ['to' => 'a@example.com']));
        $store->markDead($ordersId, 'boom');
        $store->markDead($notificationsId, 'boom');

        Artisan::call('store-forward:retry', ['--channel' => 'orders']);

        $this->assertDatabaseHas('store_forward_messages', ['id' => $ordersId, 'status' => 'pending']);
        $this->assertDatabaseHas('store_forward_messages', ['id' => $notificationsId, 'status' => 'dead']);
    }

    public function test_retry_scoped_to_specific_message_ids(): void
    {
        $store = $this->app->make(StoreInterface::class);
        $envelopeA = Envelope::make('orders', ['order_id' => 1]);
        $envelopeB = Envelope::make('orders', ['order_id' => 2]);
        $idA = $store->put($envelopeA);
        $idB = $store->put($envelopeB);
        $store->markDead($idA, 'boom');
        $store->markDead($idB, 'boom');

        Artisan::call('store-forward:retry', ['id' => [$envelopeA->id]]);

        $this->assertDatabaseHas('store_forward_messages', ['id' => $idA, 'status' => 'pending']);
        $this->assertDatabaseHas('store_forward_messages', ['id' => $idB, 'status' => 'dead']);
    }

    public function test_retry_does_not_touch_messages_that_are_not_dead(): void
    {
        $store = $this->app->make(StoreInterface::class);
        $id = $store->put(Envelope::make('orders', ['order_id' => 1]));

        Artisan::call('store-forward:retry');

        $this->assertDatabaseHas('store_forward_messages', ['id' => $id, 'status' => 'pending', 'attempts' => 0]);
    }
}
