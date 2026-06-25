# Azure Storage Blob Flysystem bundle for Symfony

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-blob-flysystem-symfony.svg)](https://packagist.org/packages/azure-oss/storage-blob-flysystem-symfony)
[![Packagist Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob-flysystem-symfony)](https://packagist.org/packages/azure-oss/storage-blob-flysystem-symfony)

Community-driven PHP SDKs for Azure, because Microsoft won't.

In November 2023, Microsoft officially archived their [Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php) and stopped maintaining PHP integrations for most Azure services. No migration path, no replacement — just a repository marked read-only.

We picked up where they left off.

<img src="https://azure-oss.github.io/img/logo.svg" width="150" alt="Screenshot">

This package is the Symfony bridge for [`azure-oss/storage-blob-flysystem`](https://packagist.org/packages/azure-oss/storage-blob-flysystem). It registers a `azure_oss` adapter shortcut with [`league/flysystem-bundle`](https://packagist.org/packages/league/flysystem-bundle) so storages can be declared directly in `config/packages/flysystem.yaml`.

Our other packages:

- **[azure-oss/storage](https://packagist.org/packages/azure-oss/storage)** – Azure Blob Storage SDK
- **[azure-oss/storage-blob-flysystem](https://packagist.org/packages/azure-oss/storage-blob-flysystem)** – Flysystem adapter
- **[azure-oss/storage-blob-laravel](https://packagist.org/packages/azure-oss/storage-blob-laravel)** – Laravel filesystem driver
- **[azure-oss/storage-queue](https://packagist.org/packages/azure-oss/storage-queue)** – Azure Storage Queue SDK
- **[azure-oss/storage-queue-laravel](https://packagist.org/packages/azure-oss/storage-queue-laravel)** – Laravel Queue connector

## Requirements

- PHP 8.2+
- `league/flysystem-bundle` 3.7 or newer (the version that introduced the pluggable `AdapterDefinitionBuilderInterface`).

## Install

```shell
composer require azure-oss/storage-blob-flysystem-symfony
```

If you have Symfony Flex installed it will register `AzureOss\Storage\BlobFlysystemSymfony\AzureOssFlysystemBundle` for you. Otherwise add it to `config/bundles.php`:

```php
return [
    // ...
    AzureOss\Storage\BlobFlysystemSymfony\AzureOssFlysystemBundle::class => ['all' => true],
];
```

## Quickstart

Declare a `BlobServiceClient` service and reference it from a `flysystem` storage that uses the `azure_oss` adapter:

```yaml
# config/services.yaml
services:
    azure_blob_service_client:
        class: AzureOss\Storage\Blob\BlobServiceClient
        factory: ['AzureOss\Storage\Blob\BlobServiceClient', 'fromConnectionString']
        arguments:
            - '%env(AZURE_STORAGE_CONNECTION_STRING)%'
```

```yaml
# config/packages/flysystem.yaml
flysystem:
    storages:
        default.storage:
            azure_oss:
                client: azure_blob_service_client
                container: '%env(AZURE_STORAGE_CONTAINER)%'
                # Optional:
                # prefix: 'optional/path/prefix'
                # mime_type_detector: 'my.custom.mime_type_detector'
                # visibility_handling: throw  # or 'ignore'
                # public_container: false
            visibility: public
```

You can now autowire the storage anywhere:

```php
use League\Flysystem\FilesystemOperator;

final class MyService
{
    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
    ) {
    }
}
```

## Configuration reference

| Option | Required | Default | Description |
| --- | --- | --- | --- |
| `client` | yes | – | Service id of a configured `AzureOss\Storage\Blob\BlobServiceClient`. You choose the auth — connection string, SAS token, Entra ID / managed identity. |
| `container` | yes | – | Azure Blob Storage container name. |
| `prefix` | no | `''` | Path prefix prepended to every blob name. |
| `mime_type_detector` | no | `null` | Service id of a `League\MimeTypeDetection\MimeTypeDetector`. Defaults to the adapter's `FinfoMimeTypeDetector`. |
| `visibility_handling` | no | `throw` | What to do when `setVisibility()` is called (Azure has no per-blob ACL). `throw` or `ignore`. |
| `public_container` | no | `false` | Whether the underlying container is set to public access. Affects URL generation. |

## Authentication

Authentication is delegated to whatever `BlobServiceClient` you supply via `client`. Besides connection strings the SDK supports SAS tokens and token-based credentials (Entra ID, managed identity, workload identity). See the [`azure-oss/storage` documentation](https://azure-oss.github.io/category/storage) for the full set of authentication helpers.

## License

This project is released under the MIT License. See [LICENSE](https://github.com/Azure-OSS/azure-storage-monorepo/blob/main/LICENSE) for details.
