<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\QueueLaravel\Integration;

use AzureOss\Storage\QueueLaravel\AzureStorageQueue;
use AzureOss\Storage\QueueLaravel\AzureStorageQueueServiceProvider;
use AzureOss\Tests\Storage\CreatesTempQueues;
use AzureOss\Tests\Storage\RetryableAssertions;
use Illuminate\Queue\QueueManager;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class AzureStorageQueueTest extends TestCase
{
    use CreatesTempQueues, RetryableAssertions;

    private function makeQueue(string $queueName, int $visibilityTimeout = 1): AzureStorageQueue
    {
        $connectionString = getenv('AZURE_STORAGE_CONNECTION_STRING');
        $connectionName = 'azure_'.bin2hex(random_bytes(6));

        config([
            'queue.default' => $connectionName,
            'queue.connections.'.$connectionName => [
                'driver' => 'azure-storage-queue',
                'connection_string' => $connectionString !== false ? $connectionString : '',
                'queue' => $queueName,
                'retry_after' => $visibilityTimeout,
                'create_queue' => true,
            ],
        ]);

        $app = $this->app;
        self::assertNotNull($app);

        /** @var QueueManager $manager */
        $manager = $app->make('queue');

        /** @var AzureStorageQueue $queue */
        $queue = $manager->connection($connectionName);

        return $queue;
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [AzureStorageQueueServiceProvider::class];
    }

    #[Test]
    public function push_pop_and_clear_work(): void
    {
        $queueClient = $this->tempQueue();
        $queueName = $queueClient->queueName;
        $queue = $this->makeQueue($queueName, visibilityTimeout: 1);

        self::assertSame($queueName, $queue->getQueue($queueName));

        $payload = json_encode(['hello' => 'world'], JSON_THROW_ON_ERROR);
        $queue->pushRaw($payload);

        $job = $queue->pop();
        self::assertNotNull($job);
        self::assertSame($payload, $job->getRawBody());

        $job->delete();

        $queue->pushRaw($payload, null, ['retry_after' => 1]);
        $job = $queue->pop();
        self::assertNull($job);

        self::assertEventually(function () use ($queue, $payload) {
            $job = $queue->pop();
            if ($job === null) {
                return false;
            }

            self::assertSame($payload, $job->getRawBody());
            $job->delete();

            return true;
        }, maxAttempts: 10, delayMs: 200);

        $queue->pushRaw($payload);

        self::assertEventually(fn () => $queue->size($queueName) >= 1, maxAttempts: 10, delayMs: 100);

        $cleared = $queue->clear($queueName);
        self::assertGreaterThanOrEqual(1, $cleared);
        self::assertNull($queue->pop());
    }

    #[Test]
    public function later_delays_the_job(): void
    {
        $queueName = $this->tempQueue()->queueName;
        $queue = $this->makeQueue($queueName, visibilityTimeout: 1);

        $queue->later(1, 'MyJob', ['hello' => 'world'], $queueName);

        $job = $queue->pop($queueName);
        self::assertNull($job);

        self::assertEventually(function () use ($queue, $queueName) {
            $job = $queue->pop($queueName);
            if ($job === null) {
                return false;
            }

            $job->delete();

            return true;
        }, maxAttempts: 10, delayMs: 200);
    }

    #[Test]
    public function size_and_clear_work(): void
    {
        $queueName = $this->tempQueue()->queueName;
        $queue = $this->makeQueue($queueName, visibilityTimeout: 1);

        $queue->pushRaw('a', $queueName);
        $queue->pushRaw('b', $queueName);

        self::assertEventually(fn () => $queue->size($queueName) === 2, maxAttempts: 10, delayMs: 100);

        self::assertSame(2, $queue->pendingSize($queueName));
        self::assertSame(0, $queue->delayedSize($queueName));
        self::assertSame(0, $queue->reservedSize($queueName));
        self::assertNull($queue->creationTimeOfOldestPendingJob($queueName));

        self::assertSame(2, $queue->clear($queueName));
        self::assertNull($queue->pop($queueName));
    }

    #[Test]
    public function push_raw_throws_on_invalid_options_types(): void
    {
        $queueName = $this->tempQueue()->queueName;
        $queue = $this->makeQueue($queueName, visibilityTimeout: 1);

        $this->expectException(\InvalidArgumentException::class);
        $queue->pushRaw('payload', $queueName, ['retry_after' => '1']);
    }

    #[Test]
    public function bulk_throws_on_invalid_job_type(): void
    {
        $queueName = $this->tempQueue()->queueName;
        $queue = $this->makeQueue($queueName, visibilityTimeout: 1);

        $this->expectException(\InvalidArgumentException::class);
        $queue->bulk([123], queue: $queueName);
    }
}
