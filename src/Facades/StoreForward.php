<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward\Facades;

use AftermathPathfinder\StoreForward\Envelope;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Envelope publish(string $channel, array $payload, array $headers = [], ?string $key = null)
 * @method static \AftermathPathfinder\StoreForward\Contracts\TransportInterface transport(?string $name = null)
 * @method static \AftermathPathfinder\StoreForward\Contracts\StoreInterface store()
 * @method static \AftermathPathfinder\StoreForward\StoreForwardManager extend(string $driver, callable $creator)
 *
 * @see \AftermathPathfinder\StoreForward\StoreForwardManager
 */
class StoreForward extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'store-forward';
    }
}
