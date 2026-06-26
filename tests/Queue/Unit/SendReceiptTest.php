<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Queue\Unit;

use AzureOss\Storage\Queue\Models\SendReceipt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SendReceiptTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_xml(): void
    {
        $receipt = SendReceipt::fromXml(new \SimpleXMLElement(<<<'XML'
            <QueueMessage>
                <MessageId>message-id</MessageId>
                <InsertionTime>Sun, 27 Sep 2009 18:41:57 GMT</InsertionTime>
                <ExpirationTime>Sun, 04 Oct 2009 18:41:57 GMT</ExpirationTime>
                <PopReceipt>pop-receipt</PopReceipt>
                <TimeNextVisible>Sun, 27 Sep 2009 18:42:27 GMT</TimeNextVisible>
            </QueueMessage>
            XML));

        self::assertSame('message-id', $receipt->messageId);
        self::assertSame('2009-09-27T18:41:57+00:00', $receipt->insertionTime->format(\DateTimeInterface::ATOM));
        self::assertSame('2009-10-04T18:41:57+00:00', $receipt->expirationTime->format(\DateTimeInterface::ATOM));
        self::assertSame('pop-receipt', $receipt->popReceipt);
        self::assertSame('2009-09-27T18:42:27+00:00', $receipt->timeNextVisible->format(\DateTimeInterface::ATOM));
    }
}
