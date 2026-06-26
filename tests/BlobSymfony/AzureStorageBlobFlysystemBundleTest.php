<?php

declare(strict_types=1);

namespace AzureOss\Storage\Tests\BlobSymfony;

use AzureOss\Storage\BlobSymfony\AzureStorageBlobAdapterDefinitionBuilder;
use AzureOss\Storage\BlobSymfony\AzureStorageBlobFlysystemBundle;
use League\FlysystemBundle\Adapter\Builder\AdapterDefinitionBuilderInterface;
use League\FlysystemBundle\DependencyInjection\FlysystemExtension;
use League\FlysystemBundle\FlysystemBundle;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AzureStorageBlobFlysystemBundleTest extends TestCase
{
    public function test_build_registers_adapter_definition_builder(): void
    {
        $container = new ContainerBuilder;

        // Symfony's Kernel normally registers each bundle's extension before
        // calling Bundle::build(). Reproduce that ordering here.
        $flysystemBundle = new FlysystemBundle;
        $flysystemExtension = $flysystemBundle->getContainerExtension();
        self::assertInstanceOf(FlysystemExtension::class, $flysystemExtension);
        $container->registerExtension($flysystemExtension);
        $flysystemBundle->build($container);

        $bundle = new AzureStorageBlobFlysystemBundle;
        $bundle->build($container);

        $extension = $container->getExtension('flysystem');
        self::assertInstanceOf(FlysystemExtension::class, $extension);

        $builders = self::readAdapterDefinitionBuilders($extension);

        $azureOss = null;
        foreach ($builders as $builder) {
            if ($builder->getName() === 'azure_oss') {
                $azureOss = $builder;
                break;
            }
        }

        self::assertInstanceOf(AzureStorageBlobAdapterDefinitionBuilder::class, $azureOss, 'AzureOssFlysystemBundle did not register the azure_oss adapter builder.');
    }

    /**
     * @return list<AdapterDefinitionBuilderInterface>
     */
    private static function readAdapterDefinitionBuilders(FlysystemExtension $extension): array
    {
        $reflection = new ReflectionClass($extension);
        $property = $reflection->getProperty('adapterDefinitionBuilders');

        $value = $property->getValue($extension);
        assert(is_array($value));

        $builders = [];
        foreach ($value as $builder) {
            assert($builder instanceof AdapterDefinitionBuilderInterface);
            $builders[] = $builder;
        }

        return $builders;
    }
}
