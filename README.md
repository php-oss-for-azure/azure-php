# PHP OSS for Azure

Community-driven PHP SDKs for Azure, because Microsoft won't.

In November 2023, Microsoft officially archived their [Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php) and stopped maintaining PHP integrations for most Azure services. No migration path, no replacement — just a repository marked read-only.

We picked up where they left off.

PHP OSS for Azure is the new project name. Composer package names, namespaces,
and other technical identifiers still use `azure-oss` for backwards
compatibility.

<img src="https://php-oss-for-azure.github.io/img/logo.svg" width="150" alt="Screenshot">

## Documentation

You can read the documentation [here](https://php-oss-for-azure.github.io).

## Package ecosystem

- **[azure-oss/storage](https://packagist.org/packages/azure-oss/storage)** — Meta package for the Storage SDKs
- **[azure-oss/storage-common](https://packagist.org/packages/azure-oss/storage-common)** — Shared authentication, HTTP, and SAS primitives
- **[azure-oss/storage-blob](https://packagist.org/packages/azure-oss/storage-blob)** — Blob Storage SDK
- **[azure-oss/storage-blob-flysystem](https://packagist.org/packages/azure-oss/storage-blob-flysystem)** — Flysystem adapter
- **[azure-oss/storage-blob-flysystem-bundle](https://packagist.org/packages/azure-oss/storage-blob-flysystem-bundle)** — Symfony Flysystem bundle
- **[azure-oss/storage-blob-laravel](https://packagist.org/packages/azure-oss/storage-blob-laravel)** — Laravel filesystem driver
- **[azure-oss/storage-queue](https://packagist.org/packages/azure-oss/storage-queue)** — Queue Storage SDK
- **[azure-oss/storage-queue-laravel](https://packagist.org/packages/azure-oss/storage-queue-laravel)** — Laravel queue connector
- **[azure-oss/storage-file-share](https://packagist.org/packages/azure-oss/storage-file-share)** — File Share SDK
- **[azure-oss/identity](https://packagist.org/packages/azure-oss/identity)** — Microsoft Entra ID token authentication

## Acknowledgements

This PHP library takes inspiration from the Azure Storage .NET SDK, particularly in its API design, naming conventions, and overall developer experience.

This project is an independent implementation and is not affiliated with, endorsed by, or maintained by Microsoft.

## License

This project is released under the MIT License. See [LICENSE](./LICENSE) for details.
