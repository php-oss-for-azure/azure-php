# Azure Storage Blob Flysystem bundle for Symfony

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-blob-symfony.svg)](https://packagist.org/packages/azure-oss/storage-blob-symfony)
[![Packagist Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob-symfony)](https://packagist.org/packages/azure-oss/storage-blob-symfony)

Community-driven PHP SDKs for Azure, because Microsoft won't.

In November 2023, Microsoft officially archived their [Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php) and stopped maintaining PHP integrations for most Azure services. No migration path, no replacement — just a repository marked read-only.

We picked up where they left off.

<img src="https://azure-oss.github.io/img/logo.svg" width="150" alt="Screenshot">

This package is the Symfony bridge for [`azure-oss/storage-blob-flysystem`](https://packagist.org/packages/azure-oss/storage-blob-flysystem). It registers a `azure_oss` adapter shortcut with [`league/flysystem-bundle`](https://packagist.org/packages/league/flysystem-bundle) so storages can be declared directly in `config/packages/flysystem.yaml`.

## Package ecosystem

- **[azure-oss/storage](https://packagist.org/packages/azure-oss/storage)** — Meta package for the Storage SDKs
  - **[azure-oss/storage-common](https://packagist.org/packages/azure-oss/storage-common)** — Shared authentication, HTTP, and SAS primitives
  - **[azure-oss/storage-blob](https://packagist.org/packages/azure-oss/storage-blob)** — Blob Storage SDK
    - **[azure-oss/storage-blob-flysystem](https://packagist.org/packages/azure-oss/storage-blob-flysystem)** — Flysystem adapter
    - **[azure-oss/storage-blob-laravel](https://packagist.org/packages/azure-oss/storage-blob-laravel)** — Laravel filesystem driver
    - **[azure-oss/storage-blob-symfony](https://packagist.org/packages/azure-oss/storage-blob-symfony)** — Symfony Flysystem bridge
  - **[azure-oss/storage-queue](https://packagist.org/packages/azure-oss/storage-queue)** — Queue Storage SDK
    - **[azure-oss/storage-queue-laravel](https://packagist.org/packages/azure-oss/storage-queue-laravel)** — Laravel queue connector
  - **[azure-oss/storage-file-share](https://packagist.org/packages/azure-oss/storage-file-share)** — File Share SDK (under construction)
- **[azure-oss/identity](https://packagist.org/packages/azure-oss/identity)** — Microsoft Entra ID token authentication

## Install

```shell
composer require azure-oss/storage-blob-symfony
```

## Documentation

You can read the documentation [here](https://azure-oss.github.io/category/storage-blob-symfony).

## License

This project is released under the MIT License. See [LICENSE](https://github.com/Azure-OSS/azure-php/blob/main/LICENSE) for details.
