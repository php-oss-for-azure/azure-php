---
sidebar_position: 2
title: Quickstart
---

## Configure The Blob Client

Declare a `BlobServiceClient` service. This example creates one from a connection string:

```yaml
# config/services.yaml
services:
    azure_blob_service_client:
        class: AzureOss\Storage\Blob\BlobServiceClient
        factory: ['AzureOss\Storage\Blob\BlobServiceClient', 'fromConnectionString']
        arguments:
            - '%env(AZURE_STORAGE_CONNECTION_STRING)%'
```

## Configure The Filesystem

Reference the service from a Flysystem storage that uses the `azure_oss` adapter:

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
                # visibility_handling: throw # or 'ignore'
                # public_container: false
            visibility: public
```

The `client` value is the Symfony service id, not an Azure connection string.

## Use The Filesystem

The Flysystem bundle exposes the configured storage for autowiring:

```php
<?php

use League\Flysystem\FilesystemOperator;

final class MyService
{
    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
    ) {
    }
}
```

The argument name corresponds to the storage name: `default.storage` becomes `$defaultStorage`.

## Configuration Reference

| Option | Required | Default | Description |
| --- | --- | --- | --- |
| `client` | yes | – | Service id of a configured `AzureOss\Storage\Blob\BlobServiceClient`. |
| `container` | yes | – | Azure Blob Storage container name. |
| `prefix` | no | `''` | Path prefix prepended to every blob name. |
| `mime_type_detector` | no | `null` | Service id of a `League\MimeTypeDetection\MimeTypeDetector`. Defaults to the adapter's `FinfoMimeTypeDetector`. |
| `visibility_handling` | no | `throw` | Behavior when `setVisibility()` is called. Use `throw` or `ignore`; Azure has no per-blob ACL. |
| `public_container` | no | `false` | Whether the container allows public access. This affects URL generation. |

For supported filesystem operations and Azure-specific behavior, continue to the [Flysystem quickstart](../3-storage-blob-flysystem/2-quickstart.md).
