# AGENTS.md

## Overview

This repository is a PHP monorepo for the Azure Storage SDKs maintained under the `azure-oss` namespace. The codebase is organized by package inside `src/`, with shared infrastructure extracted into `src/Common` and feature-specific SDKs and integrations layered on top of it. A lightweight meta package also lives in `meta/` and is used for the umbrella `azure-oss/storage` distribution.

At the root you will find:

- `composer.json`: the monorepo-level package, shared dependencies, and root autoload setup.
- `src/`: all package source code.
- `tests/`: the test suites, grouped by package.
- `infra/`: Azure Bicep templates for provisioning storage resources used during development or validation.
- `.github/`: repository automation and helper scripts such as subtree/package sync tooling.

## Source Layout

### `src/BlobSymfony`

Symfony bridge for the Azure Blob Storage Flysystem adapter.

### `src/File/Share`

Azure Storage File Share SDK (Under construction).

### `src/Common`

Shared primitives used across the storage packages.

- `Auth/`: authentication helpers such as shared key credentials.
- `Middleware/`: Guzzle middleware, client factory logic, retry/auth header wiring, and HTTP options.
- `Sas/`: account-level SAS builders, permissions, protocols, and related value objects.
- `Exceptions/` and `Helpers/`: reusable helpers and cross-package error handling.
- `aliases.php`: backwards-compatibility aliases for moved or renamed public types.

This is the lowest-level package in the repo. Blob and queue code depend on it.

### `meta`

The umbrella Composer package published as `azure-oss/storage`.

- Contains package metadata only; there is no runtime source code here.
- Requires the Blob, Queue and File Share SDK packages so consumers can install a single package.
- This is the package that should be subtree-split to the main `storage` repository.

Use this package for umbrella package metadata, dependency aggregation, and split-repo docs.

### `src/Blob`

The core Azure Blob Storage SDK, published as `azure-oss/storage-blob`.

- Top-level clients:
  - `BlobServiceClient.php`
  - `BlobContainerClient.php`
  - `BlobClient.php`
- `Specialized/`: specialized blob clients such as block blob support.
- `Models/`: request option objects, result objects, and domain models.
- `Requests/` and `Responses/`: request/response payload mapping types.
- `Sas/`: blob- and container-specific SAS builders and permission types.
- `Exceptions/` and `Helpers/`: blob-specific parsing, metadata, streams, dates, and error translation.
- `aliases.php`: backwards-compatibility aliases for older blob package class names.

Use this package for changes to the Blob SDK itself.

### `src/Queue`

The Azure Storage Queue SDK.

- Top-level clients:
  - `QueueServiceClient.php`
  - `QueueClient.php`
- `Models/`: queue/message models and client option types.
- `Requests/` and `Responses/`: XML/body mapping classes for queue operations.
- `Exceptions/` and `Helpers/`: queue-specific exceptions and metadata helpers.

Use this package for queue CRUD, message send/receive/delete, and queue client behavior.

### `src/BlobFlysystem`

Flysystem integration for Azure Blob Storage.

- `AzureBlobStorageAdapter.php`: the main Flysystem adapter.
- `Support/`: adapter-specific config parsing and support utilities.
- `aliases.php`: backwards-compatibility aliases for older Flysystem integration class names.

This layer depends on the Blob SDK and should stay thin: most storage behavior belongs in `src/Blob`, not here.

### `src/BlobLaravel`

Laravel filesystem integration built on top of the Flysystem adapter.

- `AzureStorageBlobServiceProvider.php`: registers the Laravel driver.
- `AzureStorageBlobAdapter.php`: bridges Laravel filesystem expectations to the Flysystem adapter.
- `AzureStorageBlobDiskConfig.php`: configuration parsing and validation.

Changes here should focus on Laravel service registration, config handling, and framework integration.

### `src/QueueLaravel`

Laravel queue integration for Azure Storage Queues.

- `AzureStorageQueueServiceProvider.php`: registers the queue connector.
- `AzureStorageQueueConnector.php`: constructs queue connections from Laravel config.
- `AzureStorageQueue.php`: Laravel queue driver implementation.
- `AzureStorageQueueJob.php`: wraps received queue messages as Laravel jobs.
- `AzureStorageQueueConfig.php`: config parsing and normalization.

Changes here should stay Laravel-specific rather than duplicating queue SDK behavior.

## Dependency Direction

The packages are layered roughly like this:

`Common` -> `Blob` / `Queue` / `File/Share` -> `BlobFlysystem` -> `BlobLaravel` / `BlobSymfony`

`Common` -> `Blob` -> `QueueLaravel`

`meta` -> `Blob` / `Queue` / `File/Share`

In practice:

- Put reusable HTTP/auth/SAS utilities in `src/Common`.
- Put Azure Blob API behavior in `src/Blob`.
- Put Azure Queue API behavior in `src/Queue`.
- Put Azure File Share API behavior in `src/File/Share`.
- Put umbrella Composer-package wiring in `meta/`.
- Put Flysystem-specific behavior in `src/BlobFlysystem`.
- Put Laravel-specific filesystem behavior in `src/BlobLaravel`.
- Put Laravel-specific queue behavior in `src/QueueLaravel`.
- Put Symfony-specific behavior in `src/BlobSymfony`.

## Tests

Tests mirror the source packages under `tests/`.

- `tests/Common`
- `tests/Blob`
- `tests/Queue`
- `tests/File/Share`
- `tests/BlobFlysystem`
- `tests/BlobLaravel`
- `tests/QueueLaravel`
- `tests/BlobSymfony`

There are also shared test helpers at the top level of `tests/`, such as temporary resource creation traits and retry assertions.

When editing a package, start by checking its matching test directory. Feature tests cover end-to-end client behavior; unit tests cover helpers, permissions, parsers, and aliases.

## Contributor Notes

- Root autoloading maps `AzureOss\\Storage\\` to `src/`, while individual subpackages also ship their own `composer.json` files where applicable.
- `aliases.php` files are there for backwards compatibility and legacy class-name support, not as a primary extension mechanism.
- Package READMEs live beside the package code in `src/<Package>/README.md`, with the umbrella package README in `meta/README.md`.
- The `.github/sync-package.php` script scans package manifests in `src/` and `meta/` and maintains subtree-split metadata for publishable packages.
- `infra/*.bicep` contains deployment templates, not runtime application code.
- Code style is enforced with Laravel Pint via `vendor/bin/pint`, using the rules in `pint.json`.
- Static analysis is enforced with PHPStan via `vendor/bin/phpstan --no-progress --memory-limit=2G`, configured in `phpstan.neon` at level 10 over `src/` and `tests/`.
- Both Pint and PHPStan are also run in CI under `.github/workflows/`.

## Good First Navigation Paths

If you are new to the repo, these are the fastest entry points:

- Blob SDK: start at `src/Blob/BlobServiceClient.php`
- Queue SDK: start at `src/Queue/QueueServiceClient.php`
- Shared HTTP/auth stack: start at `src/Common/Middleware/ClientFactory.php`
- Laravel filesystem integration: start at `src/BlobLaravel/AzureStorageBlobServiceProvider.php`
- Laravel queue integration: start at `src/QueueLaravel/AzureStorageQueueServiceProvider.php`
