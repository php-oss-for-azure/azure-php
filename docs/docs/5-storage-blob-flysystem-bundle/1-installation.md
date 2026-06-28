---
sidebar_position: 1
title: Installation
---

`azure-oss/storage-blob-flysystem-bundle` connects the Azure Blob Storage Flysystem adapter to [`league/flysystem-bundle`](https://github.com/thephpleague/flysystem-bundle). It registers an `azure_oss` adapter so that Azure Blob Storage filesystems can be declared in `config/packages/flysystem.yaml`.

## Requirements

- PHP 8.2+
- `league/flysystem-bundle` 3.7 or newer

Version 3.7 introduced the pluggable adapter definition builder used by this bundle.

## Install With Composer

```bash
composer require azure-oss/storage-blob-flysystem-bundle
```

Symfony Flex normally registers the bundle automatically. Without Flex, add it to `config/bundles.php`:

```php
<?php

return [
    // ...
    AzureOss\Storage\BlobFlysystemBundle\AzureStorageBlobFlysystemBundle::class => ['all' => true],
];
```

## Authentication

Authentication is provided by the `BlobServiceClient` service referenced in the Flysystem configuration. You can construct that client with a connection string, SAS token, Microsoft Entra ID, managed identity, or workload identity.

See the Blob SDK guides for [Microsoft Entra ID](../2-storage-blob/3-authorize/1-entra.md), [SAS tokens](../2-storage-blob/3-authorize/2-sas-tokens.md), and [access keys](../2-storage-blob/3-authorize/3-access-key.md).

## Next Step

Continue to [Quickstart](./quickstart) to configure a Blob service client and a Flysystem storage.
