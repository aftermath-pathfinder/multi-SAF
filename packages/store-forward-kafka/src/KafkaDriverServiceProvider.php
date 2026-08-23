<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardKafka;

use AftermathPathfinder\StoreForward\StoreForwardManager;
use Illuminate\Support\ServiceProvider;
use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;

/**
 * Registers the 'kafka' transport driver with the core package's manager.
 *
 * The Producer connects to the configured broker(s) once, when this
 * driver is first resolved, and that connection is reused for every
 * envelope for the life of the process — this matters most for the
 * long-running `store-forward:work` worker; a short-lived web request
 * reconnects on every request if `immediate: true` is used, which is one
 * more reason to prefer the default outbox+worker flow for this driver.
 */
class KafkaDriverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(StoreForwardManager::class)->extend('kafka', function (array $config) {
            $producerConfig = new ProducerConfig();
            $producerConfig->setBootstrapServer($config['brokers'] ?? throw new \InvalidArgumentException(
                "store-forward.drivers.kafka.brokers is required to use the 'kafka' driver."
            ));
            $producerConfig->setUpdateBrokers(true);
            $producerConfig->setAcks($config['acks'] ?? -1);

            $topic = $config['topic'] ?? throw new \InvalidArgumentException(
                "store-forward.drivers.kafka.topic is required to use the 'kafka' driver."
            );

            return new KafkaTransport(new Producer($producerConfig), $topic);
        });
    }
}
