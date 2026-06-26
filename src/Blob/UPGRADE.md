# Upgrade Guide

## Upgrading from 1.x to 2.x

Version 2.0 drops support for PHP 8.1. Applications must run PHP 8.2 or newer before upgrading.

Update your Composer constraint:

```shell
composer require azure-oss/storage-blob:^2.0
```

This release also requires `azure-oss/storage-common:^2.0`. Composer will update that dependency automatically unless your application requires `azure-oss/storage-common` directly, in which case update that constraint too.

### Removed class aliases

The backwards-compatible options aliases under `AzureOss\Storage\Blob\Options` have been removed. Import the model classes directly instead:

- `AzureOss\Storage\Blob\Options\BlobClientOptions` -> `AzureOss\Storage\Blob\Models\BlobClientOptions`
- `AzureOss\Storage\Blob\Options\BlobContainerClientOptions` -> `AzureOss\Storage\Blob\Models\BlobContainerClientOptions`
- `AzureOss\Storage\Blob\Options\BlobServiceClientOptions` -> `AzureOss\Storage\Blob\Models\BlobServiceClientOptions`
- `AzureOss\Storage\Blob\Options\BlockBlobClientOptions` -> `AzureOss\Storage\Blob\Models\BlockBlobClientOptions`

### Removed deprecated client members

The `sharedKeyCredentials` properties have been removed from `BlobServiceClient`, `BlobContainerClient`, and `BlobClient`. Use the `credential` property instead.

`BlobClient::copyFromUri()` has been removed. Use `syncCopyFromUri()` for synchronous server-side copies, or `startCopyFromUri()` for asynchronous copies.

### Result model constructors

Result models returned by the SDK are now created internally through factory methods. Their constructors are private:

- `Blob`
- `BlobContainer`
- `BlobContainerProperties`
- `BlobDownloadStreamingResult`
- `BlobPrefix`
- `BlobProperties`
- `TaggedBlob`

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

The `$contentType` parameter has been removed from the `UploadBlobOptions` constructor. Set the content type on the `BlobHttpHeaders` object instead:

Before:

```php
new UploadBlobOptions(contentType: 'text/plain');
```

After:

```php
new UploadBlobOptions(httpHeaders: new BlobHttpHeaders(contentType: 'text/plain'));
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
- `UnableToUploadBlobException`

Client-side exceptions such as `InvalidBlobUriException`, `InvalidConnectionStringException`, `UnableToGenerateSasException`, and `DeserializationException` are unchanged.
