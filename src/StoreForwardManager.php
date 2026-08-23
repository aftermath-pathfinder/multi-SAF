<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForward;

use AftermathPathfinder\StoreForward\Contracts\StoreInterface;
use AftermathPathfinder\StoreForward\Contracts\TransportInterface;
use AftermathPathfinder\StoreForward\Exceptions\UnknownTransportException;
use AftermathPathfinder\StoreForward\Transports\LaravelQueueTransport;
use AftermathPathfinder\StoreForward\Transports\LogTransport;
use AftermathPathfinder\StoreForward\Transports\SyncTransport;
use Illuminate\Contracts\Container\Container;

/**
 * Front door for the package. Mirrors the shape of Laravel's own manager
 * classes (CacheManager, QueueManager): a small set of drivers ship in
 * core, and anything else — Kafka, RabbitMQ, SQS, Google Pub/Sub, a
 * bespoke webhook — is added with extend() from a service provider,
 * either the host app's or a separate composer package's.
 *
 *     StoreForward::extend('kafka', function (array $config, Container $app) {
 *         return new \AftermathPathfinder\StoreForwardKafka\KafkaTransport($config);
 *     });
 */
class StoreForwardManager
{
    /** @var array<string, TransportInterface> */
    protected array $transports = [];

    /** @var array<string, callable(array, Container): TransportInterface> */
    protected array $customCreators = [];

    public function __construct(protected Container $app)
    {
    }

    /**
     * Resolve the transport bound to a channel (or the given driver name
     * directly). Instances are cached per name for the life of the request.
     */
    public function transport(?string $name = null): TransportInterface
    {
        $name ??= $this->config('default', 'sync');

        return $this->transports[$name] ??= $this->resolveTransport($name);
    }

    /**
     * Resolve which transport a channel is configured to use.
     */
    public function transportForChannel(string $channel): TransportInterface
    {
        $driver = $this->channelConfig($channel)['driver']
            ?? $this->config('default')
            ?? 'sync';

        return $this->transport($driver);
    }

    public function store(): StoreInterface
    {
        return $this->app->make(StoreInterface::class);
    }

    /**
     * Store an envelope durably (the "store" half). It is picked up and
     * forwarded by `store-forward:work` (or immediately, for drivers
     * configured as synchronous).
     */
    public function publish(string $channel, array $payload, array $headers = [], ?string $key = null): Envelope
    {
        $envelope = Envelope::make($channel, $payload, $headers, $key);

        $this->store()->put($envelope);

        if (($this->channelConfig($channel)['immediate'] ?? false) === true) {
            $this->transportForChannel($channel)->send($envelope);
        }

        return $envelope;
    }

    /**
     * Register a custom transport driver, e.g. from an extension package's
     * service provider (`store-forward-kafka`, `store-forward-amqp`, ...).
     */
    public function extend(string $driver, callable $creator): static
    {
        $this->customCreators[$driver] = $creator;
        unset($this->transports[$driver]);

        return $this;
    }

    protected function resolveTransport(string $name): TransportInterface
    {
        $config = $this->driverConfig($name);

        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($config, $this->app);
        }

        return match ($name) {
            'sync' => new SyncTransport(),
            'log' => new LogTransport($this->app->make('log')->channel($config['log_channel'] ?? null)),
            'queue' => new LaravelQueueTransport($this->app->make('queue')->connection($config['connection'] ?? null), $config['queue'] ?? null),
            default => throw UnknownTransportException::forDriver($name),
        };
    }

    /**
     * Read a store-forward config value live from the container's config
     * repository (rather than a snapshot taken at construction time), so a
     * `config(['store-forward....' => ...])` made after this manager was
     * first resolved is still honored.
     *
     * $key here must be a *fixed* dot-path this class controls (e.g.
     * 'default', 'channels', 'drivers') — never build it by interpolating
     * a channel or driver name, since Laravel's config repository treats
     * every dot in the path as a nesting separator. A channel literally
     * named "orders.created" would silently look up channels→orders→created
     * instead of the intended array key "orders.created" and return null.
     * That's exactly why channelConfig()/driverConfig() below take the
     * user-supplied name as a separate argument and index into the
     * resolved array directly, rather than appending it into this string.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->app['config']->get("store-forward.{$key}", $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function channelConfig(string $channel): array
    {
        return $this->config('channels', [])[$channel] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function driverConfig(string $name): array
    {
        return $this->config('drivers', [])[$name] ?? [];
    }
}
