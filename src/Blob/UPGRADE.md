# Upgrade Guide

## Upgrading from 1.x to 2.x

Version 2.0 drops support for PHP 8.1. Applications must run PHP 8.2 or newer before upgrading.

Update your Composer constraint:

```shell
composer require azure-oss/storage-blob:^2.0
```

This release also requires `azure-oss/storage-common:^2.0`. Composer will update that dependency automatically unless your application requires `azure-oss/storage-common` directly, in which case update that constraint too.

### Options arguments

Blob methods no longer accept `null` for optional options arguments. Omit the argument to use the default options, or pass an options object explicitly.

Before:

```php
$blobClient->upload($contents, null);
$containerClient->create(null);
```

After:

```php
$blobClient->upload($contents);
$containerClient->create();

// Or pass options explicitly:
$blobClient->upload($contents, new UploadBlobOptions);
$containerClient->create(new CreateContainerOptions);
```

### Blob service exceptions

Blob service errors are now represented by `BlobStorageException` and `BlobErrorCode` instead of one exception class per Azure error code.

Before:

```php
use AzureOss\Storage\Blob\Exceptions\BlobNotFoundException;

try {
    $blobClient->downloadStreaming();
} catch (BlobNotFoundException) {
    // Handle missing blob.
}
```

After:

```php
use AzureOss\Storage\Blob\Exceptions\BlobStorageException;
use AzureOss\Storage\Blob\Models\BlobErrorCode;

try {
    $blobClient->downloadStreaming();
} catch (BlobStorageException $e) {
    if ($e->errorCode === BlobErrorCode::BlobNotFound) {
        // Handle missing blob.
    }
}
```

The exception also exposes:

- `$errorCode`: a `?BlobErrorCode` enum for known Azure Blob service error codes.
- `$errorCodeValue`: the raw Azure error-code string, including unknown future service codes.
- `$requestId`: the Azure request id when returned by the service.
- `$statusCode`: the HTTP status code.

Removed Blob service exception classes:

- `AuthenticationFailedException`
- `AuthorizationFailedException`
- `BlobNotFoundException`
- `CannotVerifyCopySourceException`
- `ContainerAlreadyExistsException`
- `ContainerNotFoundException`
- `InvalidBlockListException`
- `NoPendingCopyOperationException`
- `TagsTooLargeException`

Client-side exceptions such as `InvalidBlobUriException`, `InvalidConnectionStringException`, `UnableToGenerateSasException`, and `DeserializationException` are unchanged.
