# Azure Storage Queue driver for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/azure-oss/storage-queue-laravel.svg)](https://packagist.org/packages/azure-oss/storage-queue-laravel)
[![Packagist Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-queue-laravel)](https://packagist.org/packages/azure-oss/storage-queue-laravel)

Community-driven PHP SDKs for Azure, because Microsoft won't.

In November 2023, Microsoft officially archived their [Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php) and stopped maintaining PHP integrations for most Azure services. No migration path, no replacement — just a repository marked read-only.

We picked up where they left off.

<img src="https://azure-oss.github.io/img/logo.svg" width="150" alt="Screenshot">

Our other packages:

- **[azure-oss/storage-queue](https://packagist.org/packages/azure-oss/storage-queue)** – Azure Storage Queue SDK  
  ![Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-queue)

- **[azure-oss/storage](https://packagist.org/packages/azure-oss/storage)** – Meta package with Blob, Queue + File Share SDKs  
  ![Downloads](https://img.shields.io/packagist/dt/azure-oss/storage)

- **[azure-oss/storage-blob](https://packagist.org/packages/azure-oss/storage-blob)** – Azure Blob Storage SDK  
  ![Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob)

- **[azure-oss/storage-blob-flysystem](https://packagist.org/packages/azure-oss/storage-blob-flysystem)** – Flysystem adapter  
  ![Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob-flysystem)

- **[azure-oss/storage-blob-laravel](https://packagist.org/packages/azure-oss/storage-blob-laravel)** – Laravel filesystem driver  
  ![Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob-laravel)

- **[azure-oss/storage-blob-symfony](https://packagist.org/packages/azure-oss/storage-blob-symfony)** – Symfony bridge for the Flysystem adapter  
  ![Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-blob-symfony)

- **[azure-oss/storage-file-share](https://packagist.org/packages/azure-oss/storage-file-share)** – Azure Storage File Share SDK (Under construction)  
  ![Downloads](https://img.shields.io/packagist/dt/azure-oss/storage-file-share)

## Install

```shell
composer require azure-oss/storage-queue-laravel
```

## Documentation

You can read the documentation [here](https://azure-oss.github.io/category/storage-queue-laravel).

## Configuration

Add a connection to `config/queue.php`:

```php
'connections' => [
    'azure' => [
        'driver' => 'azure-storage-queue',
        'connection_string' => env('AZURE_STORAGE_CONNECTION_STRING'),
        'queue' => env('AZURE_STORAGE_QUEUE', 'default'),
        'retry_after' => 60,
        'time_to_live' => null,
        'create_queue' => false,
    ],
],
```

This connector supports shared key and SAS-based authentication via `connection_string`, or shared key via `account_name` + `account_key`. See the docs for configuration examples: https://azure-oss.github.io/category/storage-queue-laravel/installation

## Per-message options

`pushRaw()` accepts `retry_after` and `time_to_live` options (seconds):

```php
$queue->pushRaw($payload, null, [
    'retry_after' => 10,
    'time_to_live' => 3600,
]);
```

## Job expiration (important)

`retry_after` is the queue message visibility timeout. If your job runs longer than `retry_after`, the message can become visible again and another worker can pick it up, causing **double processing**.

Set `retry_after` higher than your longest-running jobs on this connection (and keep your worker `--timeout` lower than that). See Laravel's docs: https://laravel.com/docs/12.x/queues#job-expiration

## License

This project is released under the MIT License. See [LICENSE](https://github.com/Azure-OSS/azure-storage-monorepo/blob/main/LICENSE) for details.
