---
sidebar_position: 2
title: Delete a container
---

## Delete A Container

```php
<?php

use AzureOss\Storage\Blob\BlobServiceClient;

$service = BlobServiceClient::fromConnectionString(getenv('AZURE_STORAGE_CONNECTION_STRING'));
$container = $service->getContainerClient('my-container');

$container->delete();
```

## Delete If Exists

Use this variant when you do not want an exception if the container does not exist:

```php
$container->deleteIfExists();
```

## Restore A Soft-Deleted Container

Container soft delete must be enabled for the storage account. First list deleted containers to obtain the service-generated deleted version, then restore the selected container:

```php
use AzureOss\Storage\Blob\Models\BlobContainerInclude;
use AzureOss\Storage\Blob\Models\GetBlobContainersOptions;

$options = new GetBlobContainersOptions(includes: [BlobContainerInclude::DELETED]);

foreach ($service->getBlobContainers(prefix: 'my-container', options: $options) as $item) {
    if ($item->name === 'my-container' && $item->isDeleted && $item->versionId !== null) {
        $container = $service->undeleteBlobContainer($item->name, $item->versionId);
        break;
    }
}
```

Restoration fails when an active container already uses the destination name, or when the deleted container is outside its retention period.
