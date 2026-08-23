<?php

namespace AftermathPathfinder\StoreForward\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Requeues dead-lettered messages back to pending so they're picked up by
 * `store-forward:work` again. Mirrors `queue:retry`.
 */
class RetryCommand extends Command
{
    protected $signature = 'store-forward:retry
        {id?* : Specific message_id(s) to retry; omit to retry all dead messages}
        {--channel= : Only retry dead messages on this channel}';

    protected $description = 'Retry dead-lettered store-and-forward messages';

    public function handle(): int
    {
        $table = config('store-forward.table', 'store_forward_messages');
        $ids = $this->argument('id');
        $channel = $this->option('channel');

        $query = DB::table($table)->where('status', 'dead');

        if (! empty($ids)) {
            $query->whereIn('message_id', $ids);
        }

        if ($channel) {
            $query->where('channel', $channel);
        }

        $count = $query->update([
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now(),
            'last_error' => null,
            'updated_at' => now(),
        ]);

        $this->info("Requeued {$count} message(s).");

        return self::SUCCESS;
    }
}
