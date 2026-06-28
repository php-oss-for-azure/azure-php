# AGENTS.md

## Overview

This repository is the PHP monorepo for the community-maintained Azure SDKs under the `azure-oss` namespace. SDK packages are organized by service inside `src/`, with Storage packages under `src/Storage`, Identity under `src/Identity`, and the documentation website under `docs/`.

At the root you will find:

- `composer.json`: the monorepo-level package, shared dependencies, and root autoload setup.
- `src/`: all SDK package source code, grouped by Azure service.
- `tests/`: the test suites, grouped by package.
- `docs/`: the Docusaurus documentation website, split to `Azure-OSS/azure-oss.github.io`.
- `infra/`: Azure Bicep templates for provisioning storage resources used during development or validation.
- `.github/`: repository automation and helper scripts such as subtree/package sync tooling.

## Source Layout

### `src/Storage/BlobFlysystemBundle`

Symfony bridge for the Azure Blob Storage Flysystem adapter.

### `src/Storage/File/Share`

Azure Storage File Share SDK (Under construction).

### `src/Storage/Common`

Shared primitives used across the storage packages.

- `Auth/`: authentication helpers such as shared key credentials.
- `Middleware/`: Guzzle middleware, client factory logic, retry/auth header wiring, and HTTP options.
- `Sas/`: account-level SAS builders, permissions, protocols, and related value objects.
- `Exceptions/` and `Helpers/`: reusable helpers and cross-package error handling.
- `aliases.php`: backwards-compatibility aliases for moved or renamed public types.

This is the lowest-level package in the repo. Blob and queue code depend on it.

### `src/Storage/Blob`

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

### `src/Storage/Queue`

The Azure Storage Queue SDK.

- Top-level clients:
  - `QueueServiceClient.php`
  - `QueueClient.php`
- `Models/`: queue/message models and client option types.
- `Requests/` and `Responses/`: XML/body mapping classes for queue operations.
- `Exceptions/` and `Helpers/`: queue-specific exceptions and metadata helpers.

Use this package for queue CRUD, message send/receive/delete, and queue client behavior.

### `src/Storage/BlobFlysystem`

Flysystem integration for Azure Blob Storage.

- `AzureBlobStorageAdapter.php`: the main Flysystem adapter.
- `Support/`: adapter-specific config parsing and support utilities.
- `aliases.php`: backwards-compatibility aliases for older Flysystem integration class names.

This layer depends on the Blob SDK and should stay thin: most storage behavior belongs in `src/Storage/Blob`, not here.

### `src/Storage/BlobLaravel`

Laravel filesystem integration built on top of the Flysystem adapter.

- `AzureStorageBlobServiceProvider.php`: registers the Laravel driver.
- `AzureStorageBlobAdapter.php`: bridges Laravel filesystem expectations to the Flysystem adapter.
- `AzureStorageBlobDiskConfig.php`: configuration parsing and validation.

Changes here should focus on Laravel service registration, config handling, and framework integration.

### `src/Storage/QueueLaravel`

Laravel queue integration for Azure Storage Queues.

- `AzureStorageQueueServiceProvider.php`: registers the queue connector.
- `AzureStorageQueueConnector.php`: constructs queue connections from Laravel config.
- `AzureStorageQueue.php`: Laravel queue driver implementation.
- `AzureStorageQueueJob.php`: wraps received queue messages as Laravel jobs.
- `AzureStorageQueueConfig.php`: config parsing and normalization.

Changes here should stay Laravel-specific rather than duplicating queue SDK behavior.

### `src/Identity`

Azure Identity SDK, published as `azure-oss/identity` and split to `Azure-OSS/azure-identity-php`.

### `docs`

Docusaurus documentation website, split to `Azure-OSS/azure-oss.github.io`.

## Dependency Direction

The packages are layered roughly like this:

`Storage/Common` -> `Storage/Blob` / `Storage/Queue` / `Storage/File/Share` -> `Storage/BlobFlysystem` -> `Storage/BlobLaravel` / `Storage/BlobFlysystemBundle`

`Identity` -> `Storage/Common`

`Storage/Common` -> `Storage/Queue` -> `Storage/QueueLaravel`

In practice:

- Put reusable Storage HTTP/auth/SAS utilities in `src/Storage/Common`.
- Put Azure Blob API behavior in `src/Storage/Blob`.
- Put Azure Queue API behavior in `src/Storage/Queue`.
- Put Azure File Share API behavior in `src/Storage/File/Share`.
- Put identity and token credential behavior in `src/Identity`.
- Put Flysystem-specific behavior in `src/Storage/BlobFlysystem`.
- Put Laravel-specific filesystem behavior in `src/Storage/BlobLaravel`.
- Put Laravel-specific queue behavior in `src/Storage/QueueLaravel`.
- Put Symfony Flysystem bundle behavior in `src/Storage/BlobFlysystemBundle`.
- Put documentation website behavior and content in `docs/`.

## Tests

Tests mirror the source packages under `tests/`.

- `tests/Storage/Common`
- `tests/Storage/Blob`
- `tests/Storage/Queue`
- `tests/Storage/File/Share`
- `tests/Storage/BlobFlysystem`
- `tests/Storage/BlobLaravel`
- `tests/Storage/QueueLaravel`
- `tests/Storage/BlobFlysystemBundle`
- `tests/Identity`

There are also shared test helpers at the top level of `tests/`, such as temporary resource creation traits and retry assertions.

When editing a package, start by checking its matching test directory. Feature tests cover end-to-end client behavior; unit tests cover helpers, permissions, parsers, and aliases.

## Contributor Notes

- Root autoloading maps `AzureOss\\Storage\\` to `src/Storage/` and `AzureOss\\Identity\\` to `src/Identity/`, while individual subpackages also ship their own `composer.json` files where applicable.
- `aliases.php` files are there for backwards compatibility and legacy class-name support, not as a primary extension mechanism.
- Package READMEs live beside the package code in `src/<Service>/<Package>/README.md`.
- `infra/*.bicep` contains deployment templates, not runtime application code.
- Code style is enforced with Laravel Pint via `vendor/bin/pint`, using the rules in `pint.json`.
- Static analysis is enforced with PHPStan via `vendor/bin/phpstan --no-progress --memory-limit=2G`, configured in `phpstan.neon` at level 10 over `src/` and `tests/`.
- Both Pint and PHPStan are also run in CI under `.github/workflows/`.

## Changelogs and Documentation

- Add useful PHPDoc docblocks for all new functionality and whenever existing functionality is updated. Document public classes, methods, properties, parameters, return shapes, exceptions, asynchronous behavior, and non-obvious Azure service semantics where applicable; avoid comments that merely repeat the type signature.
- Use the terminology and behavioral descriptions from the [Azure Storage client libraries for .NET](https://learn.microsoft.com/en-us/dotnet/api/overview/azure/storage?view=azure-dotnet) as the baseline for Storage API docblocks, adapted to the PHP API and its actual behavior.
- Update the relevant package README and/or pages under `docs/docs/` whenever a change affects public APIs, behavior, configuration, authentication, setup, or integration.
- Update the affected package's `CHANGELOG.md` under `Unreleased` whenever package code changes. Replace any "No user-facing changes" placeholder with an appropriate structured section.
- Update the affected package's `UPGRADE.md` whenever a change is breaking, including clear migration instructions and before-and-after examples where useful.

## Good First Navigation Paths

If you are new to the repo, these are the fastest entry points:

- Blob SDK: start at `src/Storage/Blob/BlobServiceClient.php`
- Queue SDK: start at `src/Storage/Queue/QueueServiceClient.php`
- Shared HTTP/auth stack: start at `src/Storage/Common/Middleware/ClientFactory.php`
- Identity SDK: start at `src/Identity/DefaultAzureCredential.php`
- Laravel filesystem integration: start at `src/Storage/BlobLaravel/AzureStorageBlobServiceProvider.php`
- Laravel queue integration: start at `src/Storage/QueueLaravel/AzureStorageQueueServiceProvider.php`
