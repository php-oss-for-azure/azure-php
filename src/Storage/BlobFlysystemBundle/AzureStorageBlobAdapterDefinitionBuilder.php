<?php

declare(strict_types=1);

namespace AzureOss\Storage\BlobFlysystemBundle;

use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\BlobFlysystem\AzureBlobStorageAdapter;
use League\FlysystemBundle\Adapter\Builder\AdapterDefinitionBuilderInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class AzureStorageBlobAdapterDefinitionBuilder implements AdapterDefinitionBuilderInterface
{
    public function getName(): string
    {
        return 'azure_oss';
    }

    /**
     * @return array<class-string, string>
     */
    public function getRequiredPackages(): array
    {
        return [
            AzureBlobStorageAdapter::class => 'azure-oss/storage-blob-flysystem',
        ];
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        // flysystem-bundle invokes this with the per-adapter ArrayNodeDefinition;
        // the interface's NodeDefinition type is just the common base.
        if (! $node instanceof ArrayNodeDefinition) {
            throw new \LogicException(
                sprintf(
                    'Expected ArrayNodeDefinition, got %s. Did flysystem-bundle change its configuration shape?',
                    $node::class,
                ),
            );
        }

        $children = $node->children();

        $children
            ->scalarNode('client')
            ->isRequired()
            ->info(
                'Service id of a configured AzureOss\\Storage\\Blob\\BlobServiceClient.',
            );

        $children
            ->scalarNode('container')
            ->isRequired()
            ->info('Name of the Azure Blob Storage container.');

        $children
            ->scalarNode('prefix')
            ->defaultValue('')
            ->info('Optional path prefix prepended to every blob name.');

        $children
            ->scalarNode('mime_type_detector')
            ->defaultNull()
            ->info(
                'Optional service id of a League\\MimeTypeDetection\\MimeTypeDetector (defaults to FinfoMimeTypeDetector).',
            );

        $children
            ->enumNode('visibility_handling')
            ->values([
                AzureBlobStorageAdapter::ON_VISIBILITY_THROW_ERROR,
                AzureBlobStorageAdapter::ON_VISIBILITY_IGNORE,
            ])
            ->defaultValue(AzureBlobStorageAdapter::ON_VISIBILITY_THROW_ERROR)
            ->info(
                'How setVisibility() calls are handled (Azure has no per-blob ACL): "throw" or "ignore".',
            );

        $children
            ->booleanNode('public_container')
            ->defaultFalse()
            ->info(
                'Whether the underlying container is set to public access (affects URL generation).',
            );
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    public function createAdapter(
        ContainerBuilder $container,
        string $storageName,
        array $options,
        ?string $defaultVisibilityForDirectories,
    ): string {
        $client = self::requireString($options, 'client');
        $containerName = self::requireString($options, 'container');
        $prefix = self::optionalString($options, 'prefix') ?? '';
        $mimeTypeDetector = self::optionalString(
            $options,
            'mime_type_detector',
        );
        $visibilityHandling =
            self::optionalString($options, 'visibility_handling') ??
            AzureBlobStorageAdapter::ON_VISIBILITY_THROW_ERROR;
        $publicContainer =
            self::optionalBool($options, 'public_container') ?? false;

        $containerClientId =
            'flysystem.adapter.'.$storageName.'.azure_oss_container_client';
        $container
            ->setDefinition(
                $containerClientId,
                new Definition(BlobContainerClient::class),
            )
            ->setFactory([new Reference($client), 'getContainerClient'])
            ->setArgument(0, $containerName)
            ->setPublic(false);

        $adapterId = 'flysystem.adapter.'.$storageName;
        $container
            ->setDefinition(
                $adapterId,
                new Definition(AzureBlobStorageAdapter::class),
            )
            ->setArgument(0, new Reference($containerClientId))
            ->setArgument(1, $prefix)
            ->setArgument(
                2,
                $mimeTypeDetector !== null
                    ? new Reference($mimeTypeDetector)
                    : null,
            )
            ->setArgument(3, $visibilityHandling)
            ->setArgument(4, $publicContainer)
            ->setPublic(false);

        return $adapterId;
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    private static function requireString(array $options, string $key): string
    {
        if (! array_key_exists($key, $options)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Missing required "%s" option for azure_oss adapter.',
                    $key,
                ),
            );
        }

        $value = $options[$key];

        if (! is_string($value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Option "%s" for azure_oss adapter must be a string.',
                    $key,
                ),
            );
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    private static function optionalString(array $options, string $key): ?string
    {
        if (! array_key_exists($key, $options) || $options[$key] === null) {
            return null;
        }

        $value = $options[$key];

        if (! is_string($value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Option "%s" for azure_oss adapter must be a string or null.',
                    $key,
                ),
            );
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    private static function optionalBool(array $options, string $key): ?bool
    {
        if (! array_key_exists($key, $options) || $options[$key] === null) {
            return null;
        }

        $value = $options[$key];

        if (! is_bool($value)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Option "%s" for azure_oss adapter must be a boolean.',
                    $key,
                ),
            );
        }

        return $value;
    }
}
