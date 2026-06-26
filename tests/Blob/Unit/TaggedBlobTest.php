<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\Blob\Unit;

use AzureOss\Storage\Blob\Models\TaggedBlob;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TaggedBlobTest extends TestCase
{
    #[Test]
    public function it_deserializes_from_xml(): void
    {
        $blob = TaggedBlob::fromXml(new \SimpleXMLElement(<<<'XML'
            <Blob>
                <Name>docs/readme.txt</Name>
                <ContainerName>documents</ContainerName>
                <Tags>
                    <TagSet>
                        <Tag>
                            <Key>project</Key>
                            <Value>blue</Value>
                        </Tag>
                        <Tag>
                            <Key>env</Key>
                            <Value>test</Value>
                        </Tag>
                    </TagSet>
                </Tags>
            </Blob>
            XML));

        self::assertSame('docs/readme.txt', $blob->name);
        self::assertSame('documents', $blob->containerName);
        self::assertSame(['project' => 'blue', 'env' => 'test'], $blob->tags);
    }
}
