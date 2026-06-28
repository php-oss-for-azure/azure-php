---
sidebar_position: 1
slug: /
title: Introduction
---

# Introduction

**Community-driven PHP SDKs for Azure, because Microsoft won't.**

In November 2023, Microsoft officially archived their
[Azure SDK for PHP](https://github.com/Azure/azure-sdk-for-php) and stopped
maintaining PHP integrations for most Azure services. No migration path,
no replacement — just a repository marked read-only.

We picked up where they left off.

Azure-OSS provides modern, actively maintained PHP packages for Azure
services — built on PHP 8.1+, designed around clean APIs, and tested
against real Azure infrastructure. Whether you're working with plain PHP,
Flysystem, or Laravel, we have you covered.

## Packages

- **[`azure-oss/storage`](/category/storage-blob-core)** — Full Azure Blob Storage SDK
- **[`azure-oss/storage-queue`](/category/storage-queue-core)** — Azure Storage Queue SDK
- **[`azure-oss/storage-blob-flysystem`](/category/storage-blob-flysystem)** — Flysystem 3.x adapter for Azure Blob Storage
- **[`azure-oss/storage-blob-laravel`](/category/storage-blob-laravel)** — Laravel filesystem driver for Azure Blob Storage
- **[`azure-oss/storage-blob-flysystem-bundle`](/category/storage-blob-flysystem-bundle)** — Symfony bundle for the Azure Blob Storage Flysystem adapter
- **[`azure-oss/storage-queue-laravel`](/category/storage-queue-laravel)** — Laravel Queue connector for Azure Storage Queues
- **[`azure-oss/azure-identity`](https://github.com/Azure-OSS/azure-identity-php)** — Azure Identity client for token-based authentication
  
## Quick Example

```php
use AzureOss\Storage\Blob\BlobServiceClient;

$service = BlobServiceClient::fromConnectionString(
    "DefaultEndpointsProtocol=https;AccountName=...;AccountKey=...;",
);

$container = $service->getContainerClient("photos");
$container->create();

$blob = $container->getBlobClient("hello.txt");
$blob->upload("Hello from Azure-OSS!");
```
