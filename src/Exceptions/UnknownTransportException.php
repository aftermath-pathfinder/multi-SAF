<?php

namespace AftermathPathfinder\StoreForward\Exceptions;

class UnknownTransportException extends \RuntimeException
{
    public static function forDriver(string $driver): self
    {
        return new self(
            "Store-forward transport [{$driver}] is not registered. Either add a ".
            "\"driver\" mapping in config/store-forward.php or register it at ".
            "runtime with StoreForward::extend('{$driver}', fn (array \$config) => ...)."
        );
    }
}
