<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Queue\Unit;

use AzureOss\Storage\Queue\Models\UpdateReceipt;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdateReceiptTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_xml(): void
    {
        $receipt = UpdateReceipt::fromXml(new \SimpleXMLElement(<<<'XML'
            <QueueMessage>
                <PopReceipt>updated-pop-receipt</PopReceipt>
                <TimeNextVisible>Sun, 27 Sep 2009 18:43:57 GMT</TimeNextVisible>
            </QueueMessage>
            XML));

        self::assertSame('updated-pop-receipt', $receipt->popReceipt);
        self::assertSame('2009-09-27T18:43:57+00:00', $receipt->timeNextVisible->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function it_deserializes_from_response_headers(): void
    {
        $receipt = UpdateReceipt::fromResponseHeaders(new Response(204, [
            'x-ms-popreceipt' => 'updated-pop-receipt',
            'x-ms-time-next-visible' => 'Sun, 27 Sep 2009 18:43:57 GMT',
        ]));

        self::assertSame('updated-pop-receipt', $receipt->popReceipt);
        self::assertSame('2009-09-27T18:43:57+00:00', $receipt->timeNextVisible->format(\DateTimeInterface::ATOM));
    }
}
