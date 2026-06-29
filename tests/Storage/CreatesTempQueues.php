<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage;

use AzureOss\Storage\Queue\QueueClient;
use AzureOss\Storage\Queue\QueueServiceClient;
use AzureOss\Tests\RequiresEnvironmentVariables;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;

/** @mixin TestCase */
trait CreatesTempQueues
{
    use RequiresEnvironmentVariables;

    /** @var list<QueueClient> */
    private array $tempQueues = [];

    protected function service(): QueueServiceClient
    {
        return QueueServiceClient::fromConnectionString($this->resolveConnectionString());
    }

    protected function tempQueue(string $prefix = 'test-'): QueueClient
    {
        $queueClient = $this->service()->getQueueClient($prefix.bin2hex(random_bytes(12)));
        $queueClient->create();

        $this->tempQueues[] = $queueClient;

        return $queueClient;
    }

    #[After]
    protected function cleanupTempQueues(): void
    {
        foreach ($this->tempQueues as $queueClient) {
            try {
                $queueClient->deleteIfExists();
            } catch (\Throwable) {
            }
        }

        $this->tempQueues = [];
    }

    private function resolveConnectionString(): string
    {
        return self::getRequiredEnvironmentVariable('AZURE_STORAGE_CONNECTION_STRING');
    }
}
