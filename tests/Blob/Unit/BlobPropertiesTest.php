<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

use AzureOss\Storage\Blob\Models\BlobProperties;
use AzureOss\Storage\Blob\Models\CopyStatus;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlobPropertiesTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_response_headers(): void
    {
        $properties = BlobProperties::fromResponseHeaders(new Response(200, [
            'Last-Modified' => 'Sun, 27 Sep 2009 18:41:57 GMT',
            'Content-Length' => '1024',
            'x-encoded-content-length' => '512',
            'Content-Type' => 'text/plain',
            'Content-MD5' => 'sQqNsWTgdUEFt6mb5y4/5Q==',
            'Cache-Control' => 'max-age=3600',
            'Content-Disposition' => 'attachment; filename="readme.txt"',
            'Content-Language' => 'en-US',
            'x-encoded-content-encoding' => 'gzip',
            'x-ms-copy-id' => 'copy-id',
            'x-ms-copy-source' => 'https://account.blob.core.windows.net/container/source.txt',
            'x-ms-copy-status' => 'success',
            'x-ms-copy-status-description' => 'The copy completed.',
            'x-ms-copy-completion-time' => 'Sun, 27 Sep 2009 18:45:00 GMT',
            'x-ms-meta-owner' => 'storage-team',
        ]));

        self::assertSame('2009-09-27T18:41:57+00:00', $properties->lastModified->format(\DateTimeInterface::ATOM));
        self::assertSame(512, $properties->contentLength);
        self::assertSame('text/plain', $properties->contentType);
        self::assertSame('b10a8db164e0754105b7a99be72e3fe5', $properties->contentMD5);
        self::assertSame(['owner' => 'storage-team'], $properties->metadata);
        self::assertSame('copy-id', $properties->copyId);
        self::assertSame('https://account.blob.core.windows.net/container/source.txt', (string) $properties->copySource);
        self::assertSame(CopyStatus::SUCCESS, $properties->copyStatus);
        self::assertSame('The copy completed.', $properties->copyStatusDescription);
        self::assertSame('2009-09-27T18:45:00+00:00', $properties->copyCompletionTime?->format(\DateTimeInterface::ATOM));
        self::assertSame('max-age=3600', $properties->cacheControl);
        self::assertSame('attachment; filename="readme.txt"', $properties->contentDisposition);
        self::assertSame('en-US', $properties->contentLanguage);
        self::assertSame('gzip', $properties->contentEncoding);
    }

    #[Test]
    public function it_deserializes_from_xml(): void
    {
        $properties = BlobProperties::fromXml(new \SimpleXMLElement(<<<'XML'
            <Properties>
                <Last-Modified>Sun, 27 Sep 2009 18:41:57 GMT</Last-Modified>
                <Content-Length>1024</Content-Length>
                <Content-Type>text/plain</Content-Type>
                <Content-MD5>sQqNsWTgdUEFt6mb5y4/5Q==</Content-MD5>
                <Cache-Control>max-age=3600</Cache-Control>
                <Content-Disposition>attachment; filename="readme.txt"</Content-Disposition>
                <Content-Language>en-US</Content-Language>
                <Content-Encoding>gzip</Content-Encoding>
                <CopyId>copy-id</CopyId>
                <CopySource>https://account.blob.core.windows.net/container/source.txt</CopySource>
                <CopyStatus>success</CopyStatus>
                <CopyStatusDescription>The copy completed.</CopyStatusDescription>
                <CopyCompletionTime>Sun, 27 Sep 2009 18:45:00 GMT</CopyCompletionTime>
            </Properties>
            XML));

        self::assertSame('2009-09-27T18:41:57+00:00', $properties->lastModified->format(\DateTimeInterface::ATOM));
        self::assertSame(1024, $properties->contentLength);
        self::assertSame('text/plain', $properties->contentType);
        self::assertSame('b10a8db164e0754105b7a99be72e3fe5', $properties->contentMD5);
        self::assertSame([], $properties->metadata);
        self::assertSame('copy-id', $properties->copyId);
        self::assertSame('https://account.blob.core.windows.net/container/source.txt', (string) $properties->copySource);
        self::assertSame(CopyStatus::SUCCESS, $properties->copyStatus);
        self::assertSame('The copy completed.', $properties->copyStatusDescription);
        self::assertSame('2009-09-27T18:45:00+00:00', $properties->copyCompletionTime?->format(\DateTimeInterface::ATOM));
        self::assertSame('max-age=3600', $properties->cacheControl);
        self::assertSame('attachment; filename="readme.txt"', $properties->contentDisposition);
        self::assertSame('en-US', $properties->contentLanguage);
        self::assertSame('gzip', $properties->contentEncoding);
    }
}
