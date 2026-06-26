# Upgrade Guide

## Upgrading from 1.x to 2.x

Version 2.0 drops support for PHP 8.1. Applications must run PHP 8.2 or newer before upgrading.

Update your Composer constraint:

```shell
composer require azure-oss/storage-common:^2.0
```

### Removed class aliases

The backwards-compatible identity aliases under `AzureOss\Storage\Common\Auth` have been removed. Import the identity package classes directly instead:

- `AzureOss\Storage\Common\Auth\AccessToken` -> `AzureOss\Identity\AccessToken`
- `AzureOss\Storage\Common\Auth\TokenCredential` -> `AzureOss\Identity\TokenCredential`
- `AzureOss\Storage\Common\Auth\ClientSecretCredential` -> `AzureOss\Identity\ClientSecretCredential`
