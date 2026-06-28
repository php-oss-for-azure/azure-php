<?php

declare(strict_types=1);

namespace AzureOss\Storage\BlobFlysystemBundle;

use League\FlysystemBundle\DependencyInjection\FlysystemExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Registers the `azure_oss` adapter shortcut with league/flysystem-bundle's
 * pluggable AdapterDefinitionBuilder system (introduced in flysystem-bundle 3.7
 * via thephpleague/flysystem-bundle#186).
 *
 * @internal
 */
final class AzureStorageBlobFlysystemBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $extension = $container->getExtension('flysystem');

        if (! $extension instanceof FlysystemExtension) {
            return;
        }

        $extension->addAdapterDefinitionBuilder(new AzureStorageBlobAdapterDefinitionBuilder);
    }
}
