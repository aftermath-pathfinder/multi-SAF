<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests;

use AftermathPathfinder\StoreForward\Exceptions\UnknownTransportException;
use AftermathPathfinder\StoreForward\StoreForwardManager;
use AftermathPathfinder\StoreForward\Transports\LogTransport;
use AftermathPathfinder\StoreForward\Transports\SyncTransport;

class StoreForwardManagerTest extends TestCase
{
    public function test_default_driver_is_sync_when_unconfigured(): void
    {
        $manager = $this->app->make(StoreForwardManager::class);

        $this->assertInstanceOf(SyncTransport::class, $manager->transport());
    }

    public function test_transport_instances_are_cached_per_name(): void
    {
        $manager = $this->app->make(StoreForwardManager::class);

        $this->assertSame($manager->transport('log'), $manager->transport('log'));
    }

    public function test_resolves_built_in_log_and_queue_drivers_by_name(): void
    {
        $manager = $this->app->make(StoreForwardManager::class);

        $this->assertInstanceOf(LogTransport::class, $manager->transport('log'));
    }

    public function test_unknown_driver_throws_a_descriptive_exception(): void
    {
        $manager = $this->app->make(StoreForwardManager::class);

        $this->expectException(UnknownTransportException::class);
        $this->expectExceptionMessage('kafka');

        $manager->transport('kafka');
    }

    public function test_extend_registers_a_custom_driver_creator(): void
    {
        $manager = $this->app->make(StoreForwardManager::class);
        $custom = new SyncTransport();

        $manager->extend('kafka', fn () => $custom);

        $this->assertSame($custom, $manager->transport('kafka'));
    }

    public function test_extend_overrides_a_previously_cached_instance(): void
    {
        $manager = $this->app->make(StoreForwardManager::class);
        $manager->transport('sync'); // resolve and cache the built-in one first

        $custom = new SyncTransport();
        $manager->extend('sync', fn () => $custom);

        $this->assertSame($custom, $manager->transport('sync'));
    }

    public function test_transport_for_channel_falls_back_to_default_when_unrouted(): void
    {
        $manager = $this->app->make(StoreForwardManager::class);

        $this->assertInstanceOf(SyncTransport::class, $manager->transportForChannel('anything'));
    }

    public function test_transport_for_channel_uses_the_configured_driver(): void
    {
        $this->app['config']->set('store-forward.channels.orders.driver', 'log');

        $manager = $this->app->make(StoreForwardManager::class);

        $this->assertInstanceOf(LogTransport::class, $manager->transportForChannel('orders'));
    }

    /**
     * Regression test: a channel name containing a literal dot must not be
     * misread as a config nesting path. See StoreForwardManager::config()'s
     * docblock for why channelConfig()/driverConfig() exist.
     */
    public function test_transport_for_channel_resolves_correctly_when_the_channel_name_contains_a_dot(): void
    {
        $this->app['config']->set('store-forward.channels', [
            'orders.created' => ['driver' => 'log'],
        ]);

        $manager = $this->app->make(StoreForwardManager::class);

        $this->assertInstanceOf(LogTransport::class, $manager->transportForChannel('orders.created'));
    }

    public function test_publish_honors_immediate_when_the_channel_name_contains_a_dot(): void
    {
        $sent = [];
        $transport = new SyncTransport(function ($envelope) use (&$sent) {
            $sent[] = $envelope;
        });
        $manager = $this->app->make(StoreForwardManager::class);
        $manager->extend('sync', fn () => $transport);
        $this->app['config']->set('store-forward.channels', [
            'orders.created' => ['driver' => 'sync', 'immediate' => true],
        ]);

        $manager->publish('orders.created', ['order_id' => 1]);

        $this->assertCount(1, $sent);
    }

    public function test_publish_sends_immediately_when_the_channel_opts_in(): void
    {
        $sent = [];
        $manager = $this->app->make(StoreForwardManager::class);
        $transport = new SyncTransport(function ($envelope) use (&$sent) {
            $sent[] = $envelope;
        });
        $manager->extend('sync', fn () => $transport);
        $this->app['config']->set('store-forward.channels.orders.immediate', true);

        $manager->publish('orders', ['order_id' => 1]);

        $this->assertCount(1, $sent);
    }

    public function test_publish_does_not_send_immediately_by_default(): void
    {
        $sent = [];
        $manager = $this->app->make(StoreForwardManager::class);
        $transport = new SyncTransport(function ($envelope) use (&$sent) {
            $sent[] = $envelope;
        });
        $manager->extend('sync', fn () => $transport);

        $manager->publish('orders', ['order_id' => 1]);

        $this->assertCount(0, $sent);
        $this->assertDatabaseHas('store_forward_messages', ['channel' => 'orders', 'status' => 'pending']);
    }
}
