# Azure Storage Queue PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-queue.svg)](https://packagist.org/packages/azure-oss/storage-queue)
[![Packagist Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-queue)](https://packagist.org/packages/azure-oss/storage-queue)

Community-driven PHP SDKs for Azure, because Microsoft won't.

In November 2023, Microsoft officially archived their [Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php) and stopped maintaining PHP integrations for most Azure services. No migration path, no replacement — just a repository marked read-only.

We picked up where they left off.

<img src="https://azure-oss.github.io/img/logo.svg" width="150" alt="Logo">

## Package ecosystem

- **[azure-oss/storage](https://packagist.org/packages/azure-oss/storage)** — Meta package for the Storage SDKs
  - **[azure-oss/storage-common](https://packagist.org/packages/azure-oss/storage-common)** — Shared authentication, HTTP, and SAS primitives
  - **[azure-oss/storage-blob](https://packagist.org/packages/azure-oss/storage-blob)** — Blob Storage SDK
    - **[azure-oss/storage-blob-flysystem](https://packagist.org/packages/azure-oss/storage-blob-flysystem)** — Flysystem adapter
    - **[azure-oss/storage-blob-laravel](https://packagist.org/packages/azure-oss/storage-blob-laravel)** — Laravel filesystem driver
    - **[azure-oss/storage-blob-flysystem-bundle](https://packagist.org/packages/azure-oss/storage-blob-flysystem-bundle)** — Symfony Flysystem bundle
  - **[azure-oss/storage-queue](https://packagist.org/packages/azure-oss/storage-queue)** — Queue Storage SDK
    - **[azure-oss/storage-queue-laravel](https://packagist.org/packages/azure-oss/storage-queue-laravel)** — Laravel queue connector
  - **[azure-oss/storage-file-share](https://packagist.org/packages/azure-oss/storage-file-share)** — File Share SDK (under construction)
- **[azure-oss/identity](https://packagist.org/packages/azure-oss/identity)** — Microsoft Entra ID token authentication

## Features

- Authentication:
  - Connection strings (access keys)
  - Shared key credentials
  - Shared access signatures (SAS) for delegated, time-limited access
  - Microsoft Entra ID (token-based authentication) via azure-oss/azure-identity
- Queues:
  - Create, delete, and check existence
  - Read properties
  - Clear messages
- Messages:
  - Send messages (with optional visibility timeout and TTL)
  - Receive one or multiple messages (with visibility timeout)
  - Delete messages
  - Update messages (including visibility timeout)

## Notes

- SAS generation is not supported yet in this SDK.

## Documentation

You can read the documentation [here](https://azure-oss.github.io).

## Install

```shell
composer require azure-oss/storage-queue
```

## Quickstart

```php
<?php

use AzureOss\Storage\Queue\QueueServiceClient;

$service = QueueServiceClient::fromConnectionString(
    getenv('AZURE_STORAGE_CONNECTION_STRING')
);

$queue = $service->getQueueClient('quickstart');
$queue->createIfNotExists();

$queue->sendMessage('Hello from Azure-OSS');

$message = $queue->receiveMessage(30);
if ($message !== null) {
    echo $message->messageText.PHP_EOL;
    $queue->deleteMessage($message->messageId, $message->popReceipt);
}

// Optional cleanup
$queue->deleteIfExists();
```

## License

This project is released under the MIT License. See [LICENSE](https://github.com/Azure-OSS/azure-php/blob/main/LICENSE) for details.
