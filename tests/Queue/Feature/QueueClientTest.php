<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Queue\Feature;

use AzureOss\Storage\Queue\Exceptions\QueueStorageException;
use AzureOss\Storage\Tests\CreatesTempQueues;
use AzureOss\Storage\Tests\RetryableAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueClientTest extends TestCase
{
    use CreatesTempQueues, RetryableAssertions;

    #[Test]
    public function create_works(): void
    {
        $queue = $this->service()->getQueueClient('test-'.bin2hex(random_bytes(12)));

        self::assertFalse($queue->exists());

        $queue->create();

        self::assertTrue($queue->exists());

        $queue->delete();
    }

    #[Test]
    public function create_throws_when_queue_already_exists(): void
    {
        $this->expectNotToPerformAssertions();

        $queue = $this->tempQueue();
        $queue->create();
    }

    #[Test]
    public function create_if_not_exists_works(): void
    {
        $queue = $this->service()->getQueueClient('test-'.bin2hex(random_bytes(12)));

        self::assertFalse($queue->exists());

        $queue->createIfNotExists();

        self::assertTrue($queue->exists());

        $queue->delete();
    }

    #[Test]
    public function create_if_not_exists_doesnt_throw_when_queue_already_exists(): void
    {
        $this->expectNotToPerformAssertions();

        $queue = $this->tempQueue();
        $queue->createIfNotExists();
    }

    #[Test]
    public function delete_works(): void
    {
        $queue = $this->tempQueue();

        self::assertTrue($queue->exists());

        $queue->delete();

        self::assertFalse($queue->exists());
    }

    #[Test]
    public function delete_throws_when_queue_doesnt_exists(): void
    {
        $this->expectException(QueueStorageException::class);

        $this->service()->getQueueClient('test-'.bin2hex(random_bytes(12)))->delete();
    }

    #[Test]
    public function delete_if_exists_works(): void
    {
        $queue = $this->tempQueue();

        self::assertTrue($queue->exists());

        $queue->deleteIfExists();

        self::assertFalse($queue->exists());
    }

    #[Test]
    public function delete_if_exists_doesnt_throw_when_queue_doesnt_exists(): void
    {
        $this->expectNotToPerformAssertions();

        $this->service()->getQueueClient('test-'.bin2hex(random_bytes(12)))->deleteIfExists();
    }

    #[Test]
    public function exists_works(): void
    {
        $queue = $this->service()->getQueueClient('test-'.bin2hex(random_bytes(12)));

        self::assertFalse($queue->exists());

        $queue->create();

        self::assertTrue($queue->exists());

        $queue->delete();

        self::assertFalse($queue->exists());
    }

    #[Test]
    public function clear_messages_works(): void
    {
        $queue = $this->tempQueue();

        $queue->sendMessage('test-1');
        $queue->sendMessage('test-2');

        $queue->clearMessages();

        self::assertCount(0, $queue->receiveMessages());
    }

    #[Test]
    public function get_approximate_message_count_works(): void
    {
        $queue = $this->tempQueue();

        self::assertGreaterThanOrEqual(0, $queue->getProperties()->approximateMessagesCount);

        $queue->sendMessage('test-1');
        $queue->sendMessage('test-2');

        self::assertEventually(fn () => $queue->getProperties()->approximateMessagesCount === 2, maxAttempts: 10, delayMs: 100);
    }

    #[Test]
    public function send_message_works(): void
    {
        $queue = $this->tempQueue();

        $receipt = $queue->sendMessage('hello world');

        self::assertNotSame('', $receipt->messageId);
        self::assertNotSame('', $receipt->popReceipt);
    }

    #[Test]
    public function send_message_visibility_timeout_works(): void
    {
        $queue = $this->tempQueue();

        $queue->sendMessage('test-1', visibilityTimeout: 1);

        $message = $queue->receiveMessage();
        self::assertNull($message);

        self::assertEventually(function () use ($queue) {
            return $queue->receiveMessage()?->messageText === 'test-1';
        }, maxAttempts: 10, delayMs: 200);
    }

    #[Test]
    public function receive_message_works(): void
    {
        $queue = $this->tempQueue();
        $queue->sendMessage('hello world');

        $message = $queue->receiveMessage();

        self::assertNotNull($message);
        self::assertSame('hello world', $message->messageText);

        $queue->deleteMessage($message->messageId, $message->popReceipt);
    }

    #[Test]
    public function receive_messages_works(): void
    {
        $queue = $this->tempQueue();
        $queue->sendMessage('test-1');
        $queue->sendMessage('test-2');

        $messages = $queue->receiveMessages(maxMessages: 2);

        self::assertCount(2, $messages);
    }

    #[Test]
    public function receive_message_returns_null_when_queue_is_empty(): void
    {
        $queue = $this->tempQueue();

        self::assertNull($queue->receiveMessage());
    }

    #[Test]
    public function receive_message_visibility_timeout_works(): void
    {
        $queue = $this->tempQueue();
        $queue->sendMessage('test-1');

        $message = $queue->receiveMessage(visibilityTimeout: 1);
        self::assertNotNull($message);

        $message = $queue->receiveMessage();
        self::assertNull($message);

        self::assertEventually(function () use ($queue) {
            return $queue->receiveMessage()?->messageText === 'test-1';
        }, maxAttempts: 10, delayMs: 200);
    }

    #[Test]
    public function update_message_works(): void
    {
        $queue = $this->tempQueue();
        $queue->sendMessage('test-1');

        $message = $queue->receiveMessage();
        self::assertNotNull($message);

        $updateReceipt = $queue->updateMessage($message->messageId, $message->popReceipt, 0, 'test-2');

        self::assertNotSame('', $updateReceipt->popReceipt);

        self::assertEventually(function () use ($queue) {
            try {
                return $queue->receiveMessage()?->messageText === 'test-2';
            } catch (\Throwable) {
                return false;
            }
        }, delayMs: 100);
    }

    #[Test]
    public function delete_message_works(): void
    {
        $queue = $this->tempQueue();
        $queue->sendMessage('test-1');

        $message = $queue->receiveMessage();
        self::assertNotNull($message);

        $queue->deleteMessage($message->messageId, $message->popReceipt);

        $this->expectException(QueueStorageException::class);
        $queue->deleteMessage($message->messageId, $message->popReceipt);
    }
}
