<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\Queue\Integration;

use AzureOss\Storage\Queue\QueueServiceClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueServiceClientTest extends TestCase
{
    #[Test]
    public function get_queue_client_works(): void
    {
        $connectionString = 'UseDevelopmentStorage=true';

        $service = QueueServiceClient::fromConnectionString($connectionString);

        $queue = $service->getQueueClient('testing');

        self::assertEquals($queue->credential, $service->credential);
        self::assertEquals('http://127.0.0.1:10001/devstoreaccount1/testing', (string) $queue->uri);
    }
}
