<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Queue\Unit;

use AzureOss\Storage\Queue\Models\QueueMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueMessageTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_xml(): void
    {
        $message = QueueMessage::fromXml(new \SimpleXMLElement(<<<'XML'
            <QueueMessage>
                <MessageId>message-id</MessageId>
                <InsertionTime>Sun, 27 Sep 2009 18:41:57 GMT</InsertionTime>
                <ExpirationTime>Sun, 04 Oct 2009 18:41:57 GMT</ExpirationTime>
                <PopReceipt>pop-receipt</PopReceipt>
                <TimeNextVisible>Sun, 27 Sep 2009 18:42:27 GMT</TimeNextVisible>
                <DequeueCount>3</DequeueCount>
                <MessageText>PHNhbXBsZT5tZXNzYWdlPC9zYW1wbGU+</MessageText>
            </QueueMessage>
            XML));

        self::assertSame('message-id', $message->messageId);
        self::assertSame('pop-receipt', $message->popReceipt);
        self::assertSame('PHNhbXBsZT5tZXNzYWdlPC9zYW1wbGU+', $message->messageText);
        self::assertSame('2009-09-27T18:41:57+00:00', $message->insertionTime->format(\DateTimeInterface::ATOM));
        self::assertSame('2009-10-04T18:41:57+00:00', $message->expirationTime->format(\DateTimeInterface::ATOM));
        self::assertSame(3, $message->dequeueCount);
        self::assertSame('2009-09-27T18:42:27+00:00', $message->timeNextVisible->format(\DateTimeInterface::ATOM));
    }
}
