<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage\BlobFlysystemBundle;

use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter;
use AzureOss\Storage\BlobFlysystemBundle\AzureStorageBlobAdapterDefinitionBuilder;
use League\FlysystemBundle\Test\AbstractAdapterDefinitionBuilderTest;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class AzureStorageBlobAdapterDefinitionBuilderTest extends AbstractAdapterDefinitionBuilderTest
{
    protected function createBuilder(): AzureStorageBlobAdapterDefinitionBuilder
    {
        return new AzureStorageBlobAdapterDefinitionBuilder;
    }

    /**
     * @return \Generator<string, array{0: array<string, mixed>}>
     */
    public static function provideValidOptions(): \Generator
    {
        yield 'minimal' => [
            [
                'client' => 'my_client',
                'container' => 'my-container',
            ],
        ];

        yield 'full' => [
            [
                'client' => 'my_client',
                'container' => 'my-container',
                'prefix' => 'some/prefix',
                'mime_type_detector' => 'my.mime_type_detector',
                'visibility_handling' => AzureBlobStorageAdapter::ON_VISIBILITY_IGNORE,
                'public_container' => true,
            ],
        ];
    }

    protected function assertDefinition(Definition $definition): void
    {
        self::assertSame(
            AzureBlobStorageAdapter::class,
            $definition->getClass(),
        );

        // arg 0: reference to the per-storage BlobContainerClient definition
        $containerClientRef = $definition->getArgument(0);
        self::assertInstanceOf(Reference::class, $containerClientRef);
        self::assertSame(
            'flysystem.adapter.full.azure_oss_container_client',
            (string) $containerClientRef,
        );

        // arg 1: prefix
        self::assertSame('some/prefix', $definition->getArgument(1));

        // arg 2: mime type detector reference (the 'full' fixture supplies one)
        $mimeTypeDetectorRef = $definition->getArgument(2);
        self::assertInstanceOf(Reference::class, $mimeTypeDetectorRef);
        self::assertSame(
            'my.mime_type_detector',
            (string) $mimeTypeDetectorRef,
        );

        // arg 3: visibility handling
        self::assertSame(
            AzureBlobStorageAdapter::ON_VISIBILITY_IGNORE,
            $definition->getArgument(3),
        );

        // arg 4: public_container
        self::assertTrue($definition->getArgument(4));

        // Also verify the auxiliary container-client service is wired correctly.
        $container = $this->getContainer();
        self::assertTrue(
            $container->hasDefinition(
                'flysystem.adapter.full.azure_oss_container_client',
            ),
        );
        $containerClient = $container->getDefinition(
            'flysystem.adapter.full.azure_oss_container_client',
        );
        self::assertSame(
            BlobContainerClient::class,
            $containerClient->getClass(),
        );
        self::assertSame('my-container', $containerClient->getArgument(0));

        $factory = $containerClient->getFactory();
        self::assertIsArray($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('my_client', (string) $factory[0]);
        self::assertSame('getContainerClient', $factory[1]);
    }

    public function test_adapter_name_is_azure_oss(): void
    {
        self::assertSame('azure_oss', $this->createBuilder()->getName());
    }

    public function test_required_packages_points_at_the_flysystem_adapter(): void
    {
        self::assertSame(
            [
                AzureBlobStorageAdapter::class => 'azure-oss/storage-blob-flysystem',
            ],
            $this->createBuilder()->getRequiredPackages(),
        );
    }

    public function test_mime_type_detector_defaults_to_null_reference(): void
    {
        $builder = $this->createBuilder();
        $container = $this->getContainer();

        $adapterId = $builder->createAdapter(
            $container,
            'default',
            [
                'client' => 'my_client',
                'container' => 'my-container',
                'prefix' => '',
                'mime_type_detector' => null,
                'visibility_handling' => AzureBlobStorageAdapter::ON_VISIBILITY_THROW_ERROR,
                'public_container' => false,
            ],
            null,
        );

        self::assertNull($container->getDefinition($adapterId)->getArgument(2));
    }
}
