# Azure Storage PHP Monorepo

Community-driven PHP SDKs for Azure, because Microsoft won't.

In November 2023, Microsoft officially archived their [Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php) and stopped maintaining PHP integrations for most Azure services. No migration path, no replacement — just a repository marked read-only.

We picked up where they left off.


<img src="https://azure-oss.github.io/img/logo.svg" width="150" alt="Screenshot">

## Documentation

You can read the documentation [here](https://azure-oss.github.io).

## Packages

This monorepo contains the following packages:

### [azure-oss/storage](https://packagist.org/packages/azure-oss/storage) ![Version](https://img.shields.io/packagist/v/azure-oss/storage) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/storage)

Meta package that installs the Blob, Queue and File Share SDKs. Use this when you want a single Composer dependency for the core Azure Storage clients.

### [azure-oss/storage-blob](https://packagist.org/packages/azure-oss/storage-blob) ![Version](https://img.shields.io/packagist/v/azure-oss/storage-blob) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob)

The core Azure Blob Storage PHP SDK. Use this when you only need Blob Storage support.

### [azure-oss/storage-queue](https://packagist.org/packages/azure-oss/storage-queue) ![Version](https://img.shields.io/packagist/v/azure-oss/storage-queue) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-queue)

Azure Storage Queue PHP SDK. Provides functionality for interacting with Azure Storage Queues, including queue and message operations.

### [azure-oss/storage-blob-flysystem](https://packagist.org/packages/azure-oss/storage-blob-flysystem) ![Version](https://img.shields.io/packagist/v/azure-oss/storage-blob-flysystem) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob-flysystem)

Flysystem adapter for Azure Storage PHP. Provides integration with the [Flysystem](https://flysystem.thephpleague.com/) filesystem abstraction library.

### [azure-oss/storage-blob-symfony](https://packagist.org/packages/azure-oss/storage-blob-symfony) ![Version](https://img.shields.io/packagist/v/azure-oss/storage-blob-symfony) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob-symfony)

Symfony bridge for the Flysystem adapter.

### [azure-oss/storage-file-share](https://packagist.org/packages/azure-oss/storage-file-share) ![Version](https://img.shields.io/packagist/v/azure-oss/storage-file-share) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-file-share)

Azure Storage File Share PHP SDK. **(Under construction)**

### [azure-oss/storage-blob-laravel](https://packagist.org/packages/azure-oss/storage-blob-laravel) ![Version](https://img.shields.io/packagist/v/azure-oss/storage-blob-laravel) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob-laravel)

Laravel filesystem driver for Azure Storage Blob. Provides seamless integration with Laravel's filesystem abstraction.

### [azure-oss/storage-queue-laravel](https://packagist.org/packages/azure-oss/storage-queue-laravel) ![Version](https://img.shields.io/packagist/v/azure-oss/storage-queue-laravel) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-queue-laravel)

Laravel Queue connector for Azure Storage Queues. Provides integration with Laravel's queue system.

### [azure-oss/storage-common](https://packagist.org/packages/azure-oss/storage-common) ![Version](https://img.shields.io/packagist/v/azure-oss/storage-common) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-common)

Common utilities and shared components for the Azure Storage PHP SDK. This package contains reusable functionality used across the Azure Storage Blob, Queue, and related integrations.

## Other packages by us

### [azure-oss/identity](https://packagist.org/packages/azure-oss/identity) ![Version](https://img.shields.io/packagist/v/azure-oss/identity) ![Total Downloads](https://img.shields.io/packagist/dt/azure-oss/identity)

Azure Active Directory (Entra ID) token authentication.

## License

This project is released under the MIT License. See [LICENSE](./LICENSE) for details.
