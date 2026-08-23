<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward;

use AftermathPathfinder\StoreForward\Events\MessageFailed;
use AftermathPathfinder\StoreForward\Events\MessagePublished;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

/**
 * The "forward" half of store-and-forward: drains pending outbox rows and
 * hands them to the transport configured for their channel, with retry and
 * dead-lettering. Run continuously via `store-forward:work`, or on a
 * schedule for low-volume apps.
 */
class Dispatcher
{
    public function __construct(
        protected StoreForwardManager $manager,
        protected EventDispatcher $events,
        protected int $maxAttempts = 10,
    ) {
    }

    /**
     * Process one batch for a channel (or every channel when null).
     * Returns how many envelopes were attempted.
     */
    public function drain(?string $channel = null, int $limit = 100): int
    {
        $records = $this->manager->store()->pending($channel, $limit);

        foreach ($records as $record) {
            $this->deliver($record['record_id'], $record['envelope'], $record['attempts']);
        }

        return count($records);
    }

    protected function deliver(string|int $recordId, Envelope $envelope, int $attempts): void
    {
        $transport = $this->manager->transportForChannel($envelope->channel);

        try {
            $transport->send($envelope);
            $this->manager->store()->markSent($recordId);
            $this->events->dispatch(new MessagePublished($envelope, get_class($transport)));
        } catch (\Throwable $e) {
            $dead = ($attempts + 1) >= $this->maxAttempts;

            if ($dead) {
                $this->manager->store()->markDead($recordId, $e->getMessage());
            } else {
                $this->manager->store()->markFailed($recordId, $e->getMessage());
            }

            $this->events->dispatch(new MessageFailed($envelope, get_class($transport), $e, $attempts + 1, $dead));
        }
    }
}
