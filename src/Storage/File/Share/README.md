# Azure Storage File Share PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-file-share.svg)](https://packagist.org/packages/azure-oss/storage-file-share)
[![Packagist Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-file-share)](https://packagist.org/packages/azure-oss/storage-file-share)

A PHP SDK for Azure Files service operations and SAS generation.

> [!IMPORTANT]
> This package is community-maintained and is not affiliated with, endorsed by, or supported by Microsoft.

## Install

```bash
composer require azure-oss/storage-file-share
```

## Documentation

Read the File Share docs at [php-oss-for-azure.github.io/storage-file-share/core](https://php-oss-for-azure.github.io/storage-file-share/core).

## Quickstart

```php
<?php

use AzureOss\Storage\File\Share\Sas\ShareFileSasPermissions;
use AzureOss\Storage\File\Share\Sas\ShareSasBuilder;
use AzureOss\Storage\File\Share\ShareServiceClient;

$service = ShareServiceClient::fromConnectionString(
    getenv('AZURE_STORAGE_CONNECTION_STRING')
);

$file = $service
    ->getShareClient('documents')
    ->getDirectoryClient('reports')
    ->getFileClient('summary.txt');

$sasUri = $file->generateSasUri(
    ShareSasBuilder::new()
        ->setPermissions(new ShareFileSasPermissions(read: true))
        ->setExpiresOn(new DateTimeImmutable('+15 minutes')),
);

echo $sasUri.PHP_EOL;
```

## Related packages

- **[azure-oss/storage](https://packagist.org/packages/azure-oss/storage)** — Meta package for the Storage SDKs
- **[azure-oss/storage-common](https://packagist.org/packages/azure-oss/storage-common)** — Shared authentication, HTTP, and SAS primitives
- **[azure-oss/storage-blob](https://packagist.org/packages/azure-oss/storage-blob)** — Blob Storage SDK
- **[azure-oss/storage-blob-flysystem](https://packagist.org/packages/azure-oss/storage-blob-flysystem)** — Flysystem adapter
- **[azure-oss/storage-blob-flysystem-bundle](https://packagist.org/packages/azure-oss/storage-blob-flysystem-bundle)** — Symfony Flysystem bundle
- **[azure-oss/storage-blob-laravel](https://packagist.org/packages/azure-oss/storage-blob-laravel)** — Laravel filesystem driver
- **[azure-oss/storage-queue](https://packagist.org/packages/azure-oss/storage-queue)** — Queue Storage SDK
- **[azure-oss/storage-queue-laravel](https://packagist.org/packages/azure-oss/storage-queue-laravel)** — Laravel queue connector
- **[azure-oss/identity](https://packagist.org/packages/azure-oss/identity)** — Microsoft Entra ID token authentication

## Maintenance

This package is part of the community-maintained PHP OSS for Azure project. It is an independent project and is not affiliated with or endorsed by Microsoft.

## License

This project is released under the MIT License. See [LICENSE](./LICENSE) for details.
