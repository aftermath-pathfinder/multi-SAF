<?php

declare(strict_types=1);

namespace AftermathPathfinder\StoreForwardSqs;

/**
 * A minimal, testable seam around Aws\Sqs\SqsClient.
 *
 * AWS SDK PHP service clients don't declare real methods for operations
 * like sendMessage() — they're dispatched through __call() against a
 * generated API description at runtime. That makes them awkward to mock
 * directly (PHPUnit's MockBuilder::addMethods(), the only way to stub a
 * magic method, is deprecated with no replacement as of PHPUnit 11). This
 * interface exists so SqsTransport depends on two real, declared methods
 * instead — SqsAdapter below is the only class that talks to the real SDK.
 */
interface SqsClientInterface
{
    public function sendMessage(array $params): void;

    public function sendMessageBatch(array $params): void;
}
