---
sidebar_position: 1
title: Installation
---

`azure-oss/storage-blob-laravel` integrates Azure Blob Storage with Laravel's filesystem.

## Requirements

- PHP 8.1+
- Laravel Filesystem (`illuminate/filesystem`) 10.x, 11.x, 12.x, or 13.x

## Install With Composer

```bash
composer require azure-oss/storage-blob-laravel
```

## Configure `config/filesystems.php`

### Authentication

This driver supports:

- Shared key (connection string)
- Microsoft Entra ID (token-based)

When not using `connection_string`, you must set `credential` to one of:

- `shared_key`
- `client_secret`
- `client_certificate`
- `workload_identity`
- `managed_identity`

#### Shared key (connection string)

```php
'azure' => [
    'driver' => 'azure-storage-blob',
    'connection_string' => env('AZURE_STORAGE_CONNECTION_STRING'),
    'container' => env('AZURE_STORAGE_CONTAINER'),
],
```

#### Shared key (account key)

```php
'azure' => [
    'driver' => 'azure-storage-blob',
    'credential' => 'shared_key',
    'account_name' => env('AZURE_STORAGE_ACCOUNT_NAME'),
    'account_key' => env('AZURE_STORAGE_ACCOUNT_KEY'),
    // optional: 'endpoint_suffix' => env('AZURE_STORAGE_ENDPOINT_SUFFIX', 'core.windows.net'),
    // or: 'endpoint' => env('AZURE_STORAGE_ENDPOINT'),
    'container' => env('AZURE_STORAGE_CONTAINER'),
],
```

#### Entra ID (token-based)

Token-based credentials require either `endpoint` **or** `account_name` (and optionally `endpoint_suffix`).

##### Client Secret

```php
'azure' => [
    'driver' => 'azure-storage-blob',
    'credential' => 'client_secret',
    'endpoint' => env('AZURE_STORAGE_ENDPOINT'),
    // or: 'account_name' => env('AZURE_STORAGE_ACCOUNT_NAME')
    'tenant_id' => env('AZURE_TENANT_ID'),
    'client_id' => env('AZURE_CLIENT_ID'),
    'client_secret' => env('AZURE_CLIENT_SECRET'),
    'container' => env('AZURE_STORAGE_CONTAINER'),
],
```

##### Service client certificate

```php
'azure' => [
    'driver' => 'azure-storage-blob',
    'credential' => 'client_certificate',
    'endpoint' => env('AZURE_STORAGE_ENDPOINT'),
    'tenant_id' => env('AZURE_TENANT_ID'),
    'client_id' => env('AZURE_CLIENT_ID'),
    'client_certificate_path' => storage_path('certs/azure.pfx'), // PEM or PFX/PKCS#12
    // 'client_certificate_password' => env('AZURE_CLIENT_CERT_PASSWORD'),
    'container' => env('AZURE_STORAGE_CONTAINER'),
],
```

##### Workload identity (federated credentials)

```php
'azure' => [
    'driver' => 'azure-storage-blob',
    'credential' => 'workload_identity',
    'endpoint' => env('AZURE_STORAGE_ENDPOINT'),
    // You can omit these to use AZURE_TENANT_ID/AZURE_CLIENT_ID/AZURE_FEDERATED_TOKEN_FILE from the environment.
    // 'tenant_id' => env('AZURE_TENANT_ID'),
    // 'client_id' => env('AZURE_CLIENT_ID'),
    // 'federated_token_file' => env('AZURE_FEDERATED_TOKEN_FILE'),
    'container' => env('AZURE_STORAGE_CONTAINER'),
],
```

##### Managed identity (experimental)

```php
'azure' => [
    'driver' => 'azure-storage-blob',
    'credential' => 'managed_identity',
    'endpoint' => env('AZURE_STORAGE_ENDPOINT'),
    // Optional for user-assigned managed identity:
    // 'client_id' => env('AZURE_CLIENT_ID'),
    'container' => env('AZURE_STORAGE_CONTAINER'),
],
```

#### Notes

- Token-based credentials do not support SAS URL generation in this driver, so `temporaryUrl()` is not available (`providesTemporaryUrls()` returns `false`).
- To override the cloud authority (e.g. sovereign clouds), set `authority_host` (defaults to `login.microsoftonline.com`).

### Custom domains and Azure Front Door

To generate public URLs through a custom domain or Azure Front Door, set `url` in the disk config:

```php
'azure' => [
    'driver' => 'azure-storage-blob',
    'connection_string' => env('AZURE_STORAGE_CONNECTION_STRING'),
    'container' => env('AZURE_STORAGE_CONTAINER'),
    'url' => env('AZURE_STORAGE_URL'), // e.g. https://assets.example.com
],
```

`url` is used only by `Storage::url()`. The configured disk `prefix` or `root` and the requested path are appended to it. The URL must therefore point to a Front Door route that maps to the configured container, or include the container path itself.

Set `temporary_url` to serve signed download and upload URLs through a different origin:

```php
'azure' => [
    'driver' => 'azure-storage-blob',
    'connection_string' => env('AZURE_STORAGE_CONNECTION_STRING'),
    'container' => env('AZURE_STORAGE_CONTAINER'),
    'temporary_url' => env('AZURE_STORAGE_TEMPORARY_URL'), // e.g. https://secure-assets.example.com
],
```

`temporary_url` replaces only the scheme, host, and port of generated SAS URLs. The complete Blob path and SAS query string are preserved. When using Azure Front Door, configure the route to forward the SAS query string to Blob Storage and include the relevant query parameters in the cache key so one caller cannot receive content authorized for another.

## Next Step

Continue to [Quickstart](./quickstart) for common file operations with `Storage::disk('azure')`.
