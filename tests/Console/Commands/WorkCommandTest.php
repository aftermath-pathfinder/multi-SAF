<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Tests\Console\Commands;

use AftermathPathfinder\StoreForward\Facades\StoreForward;
use AftermathPathfinder\StoreForward\Tests\TestCase;
use AftermathPathfinder\StoreForward\Transports\SyncTransport;
use Illuminate\Support\Facades\Artisan;

class WorkCommandTest extends TestCase
{
    public function test_once_processes_a_single_batch_and_exits(): void
    {
        $delivered = [];
        $transport = new SyncTransport(function ($envelope) use (&$delivered) {
            $delivered[] = $envelope;
        });
        StoreForward::extend('sync', fn () => $transport);

        StoreForward::publish('orders', ['order_id' => 1]);
        StoreForward::publish('orders', ['order_id' => 2]);

        Artisan::call('store-forward:work', ['--once' => true]);

        $this->assertCount(2, $delivered);
        $this->assertDatabaseHas('store_forward_messages', ['channel' => 'orders', 'status' => 'sent']);
    }

    public function test_channel_option_only_drains_the_requested_channel(): void
    {
        $delivered = [];
        $transport = new SyncTransport(function ($envelope) use (&$delivered) {
            $delivered[] = $envelope->channel;
        });
        StoreForward::extend('sync', fn () => $transport);

        StoreForward::publish('orders', ['order_id' => 1]);
        StoreForward::publish('notifications', ['to' => 'a@example.com']);

        Artisan::call('store-forward:work', ['--once' => true, '--channel' => 'orders']);

        $this->assertSame(['orders'], $delivered);
        $this->assertDatabaseHas('store_forward_messages', ['channel' => 'notifications', 'status' => 'pending']);
    }

    public function test_reports_zero_processed_when_the_outbox_is_empty(): void
    {
        $exitCode = Artisan::call('store-forward:work', ['--once' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString('Forwarded', Artisan::output());
    }
}
