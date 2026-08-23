<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Console\Commands;

use AftermathPathfinder\StoreForward\Dispatcher;
use Illuminate\Console\Command;

/**
 * Long-running (or single-pass with --once) worker that drains the outbox
 * and forwards to the configured transport(s). Analogous to `queue:work`.
 */
class WorkCommand extends Command
{
    protected $signature = 'store-forward:work
        {--channel= : Only drain this channel}
        {--limit=100 : Max envelopes per batch}
        {--sleep=1 : Seconds to sleep between empty batches}
        {--once : Process a single batch and exit}';

    protected $description = 'Forward pending store-and-forward messages to their transport';

    public function handle(Dispatcher $dispatcher): int
    {
        $channel = $this->option('channel');
        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');

        do {
            $processed = $dispatcher->drain($channel, $limit);

            if ($processed > 0) {
                $this->info("Forwarded {$processed} message(s)".($channel ? " on [{$channel}]" : '').'.');
            }

            if ($this->option('once')) {
                break;
            }

            if ($processed === 0) {
                sleep(max(0, $sleep));
            }
        } while (true);

        return self::SUCCESS;
    }
}
