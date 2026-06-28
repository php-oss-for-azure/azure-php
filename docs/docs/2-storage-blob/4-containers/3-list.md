---
sidebar_position: 3
title: List containers
---

## List All Containers

```php
<?php

use AzureOss\Storage\Blob\BlobServiceClient;

$service = BlobServiceClient::fromConnectionString(getenv('AZURE_STORAGE_CONNECTION_STRING'));

foreach ($service->getBlobContainers() as $container) {
    echo $container->name.PHP_EOL;
}
```

## List Containers By Prefix

```php
foreach ($service->getBlobContainers('project-') as $container) {
    echo $container->name.PHP_EOL;
}
```

## Include Deleted Containers

```php
use AzureOss\Storage\Blob\Models\BlobContainerInclude;
use AzureOss\Storage\Blob\Models\GetBlobContainersOptions;

$options = new GetBlobContainersOptions(
    pageSize: 100,
    includes: [
        BlobContainerInclude::METADATA,
        BlobContainerInclude::DELETED,
    ],
);

foreach ($service->getBlobContainers(options: $options) as $container) {
    if ($container->isDeleted) {
        echo "{$container->name}: {$container->versionId}".PHP_EOL;
        echo $container->properties->remainingRetentionDays.PHP_EOL;
    }
}
```

`BlobContainerInclude::SYSTEM` can also be used to include service-created containers. Deleted containers expose the version required for restoration, their deletion time, and their remaining retention days.
