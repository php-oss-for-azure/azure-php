# Plan: Add Azure Files Service SAS Support Following the Blob Package Pattern

## Goal

Add Azure Files service SAS generation to `src/Storage/File/Share` using the same implementation style already used by the Blob package:

- shared-key-only signing
- a single mutable SAS builder with fluent setters
- small permission value objects that serialize to ordered permission strings
- `canGenerateSasUri()` and `generateSasUri()` on resource clients
- focused unit tests around builder output and permission ordering

This plan is intentionally shaped around the existing Blob implementation in this repository, not around a fresh .NET-style redesign.

## Scope

Included:
- Minimal Azure Files client hierarchy required to address shares, directories, and files
- `ShareClient::canGenerateSasUri()`
- `ShareClient::generateSasUri(ShareSasBuilder $builder)`
- `ShareDirectoryClient::canGenerateSasUri()`
- `ShareDirectoryClient::generateSasUri(ShareSasBuilder $builder)`
- `ShareFileClient::canGenerateSasUri()`
- `ShareFileClient::generateSasUri(ShareSasBuilder $builder)`
- `ShareSasBuilder`
- File Share SAS permission value objects
- Unit tests modeled on Blob SAS tests
- Feature tests for generated SAS URIs

Excluded:
- Convenience overloads that bypass the builder
- User delegation SAS
- Account SAS changes
- Laravel wrappers
- Flysystem integration
- General Azure Files CRUD beyond what is needed to construct clients and validate SAS generation

## First Principle

Do not invent a second SAS design.

Azure Files should follow the conventions already established by:

- `src/Storage/Blob/Sas/BlobSasBuilder.php`
- `src/Storage/Blob/Sas/BlobSasPermissions.php`
- `src/Storage/Blob/Sas/BlobContainerSasPermissions.php`
- `src/Storage/Blob/BlobContainerClient.php`
- `src/Storage/Blob/BlobClient.php`
- `tests/Storage/Blob/Unit/BlobSasBuilderTest.php`

Where Azure Files needs different signed resources or string-to-sign fields, keep those differences inside the Azure Files SAS builder. The external shape should still feel like the Blob package.

## Package Shape

The package already exists at:

- `src/Storage/File/Share`

Namespace:

- `AzureOss\Storage\File\Share`

Planned entry points:

- `ShareServiceClient`
- `ShareClient`
- `ShareDirectoryClient`
- `ShareFileClient`

This mirrors the naming style of `.NET`, but the PHP surface should still behave like the existing Blob clients in this repository.

## API Shape

Follow the Blob pattern directly.

### Client methods

Each resource client should expose:

```php
public function canGenerateSasUri(): bool

public function generateSasUri(ShareSasBuilder $shareSasBuilder): UriInterface
```

Do not add `generateSasUriFromPermissions(...)` overloads in the first implementation. The Blob package does not use that style, and introducing it here would create a second SAS API shape to support.

### Builder style

Follow `BlobSasBuilder`:

- `ShareSasBuilder::new()`
- fluent `set...()` methods
- mutable builder
- `build(StorageSharedKeyCredential $credential): string`
- returns the SAS query string without a leading `?`

### Permission style

Follow `BlobSasPermissions` and `BlobContainerSasPermissions`:

- permission classes are final value objects
- constructor booleans define granted operations
- `__toString()` emits the permissions in Azure's required order
- no parsing layer is needed in v1

## Proposed Files

`src/Storage/File/Share`
- `ShareServiceClient.php`
- `ShareClient.php`
- `ShareDirectoryClient.php`
- `ShareFileClient.php`

`src/Storage/File/Share/Sas`
- `ShareSasBuilder.php`
- `ShareSasPermissions.php`
- `ShareDirectorySasPermissions.php`
- `ShareFileSasPermissions.php`

`src/Storage/File/Share/Helpers`
- `ShareUriParserHelper.php`

`src/Storage/File/Share/Exceptions`
- `InvalidConnectionStringException.php`
- `InvalidShareUriException.php`
- `UnableToGenerateSasException.php`

`src/Storage/File/Share/Models`
- `ShareServiceClientOptions.php`
- `ShareClientOptions.php`
- `ShareDirectoryClientOptions.php`
- `ShareFileClientOptions.php`

`tests/Storage/File/Share/Unit`
- `ShareSasBuilderTest.php`
- permission tests matching the Blob pattern

`tests/Storage/File/Share/Feature`
- SAS URI generation tests

## Client Method Pattern

Match the Blob clients closely.

### `canGenerateSasUri()`

Return `true` only when the client credential is a `StorageSharedKeyCredential`.

### `generateSasUri()`

Behavior should match the Blob package:

1. Throw `UnableToGenerateSasException` if the client cannot sign.
2. If the client URI is a development URI, relax the protocol to `https,http`, following the Blob behavior.
3. Stamp client-specific context onto the builder.
4. Call `build(...)` on the builder.
5. Merge the generated SAS query parameters with any existing query parameters from the client URI.

Planned context stamping:

- `ShareClient` sets `shareName`
- `ShareDirectoryClient` sets `shareName` and `directoryPath`
- `ShareFileClient` sets `shareName`, `filePath`, and parent directory context as needed

This keeps the builder responsible for signing and the clients responsible for resource context, exactly like Blob.

## Builder Design

`ShareSasBuilder` should look structurally similar to `BlobSasBuilder`.

### Required state

- `shareName`
- `expiresOn`

### Optional state

- `path` or separate `directoryPath` / `filePath`
- `startsOn`
- `permissions`
- `identifier`
- `cacheControl`
- `contentDisposition`
- `contentEncoding`
- `contentLanguage`
- `contentType`
- `ipRange`
- `protocol`
- `version`
- `directoryDepth`

### Builder setters

The naming should mirror Blob unless Azure Files has a strong reason not to:

- `setShareName(string $value)`
- `setPath(string $value)` or resource-specific setters if needed
- `setExpiresOn(\DateTimeInterface $value)`
- `setPermissions(string|ShareSasPermissions|ShareDirectorySasPermissions|ShareFileSasPermissions $value)`
- `setIdentifier(string $value)`
- `setStartsOn(\DateTimeInterface $value)`
- `setCacheControl(string $value)`
- `setContentDisposition(string $value)`
- `setContentEncoding(string $value)`
- `setContentLanguage(string $value)`
- `setContentType(string $value)`
- `setIPRange(SasIpRange $value)`
- `setProtocol(SasProtocol $value)`
- `setVersion(string $value)`
- `setDirectoryDepth(int $value)` if directory-scoped SAS requires it

### Resource selection

Like `BlobSasBuilder`, the builder should derive the signed resource from the stamped context rather than forcing callers to set raw `sr` values.

Planned mapping:

- share SAS => `sr=s`
- directory SAS => `sr=d`
- file SAS => `sr=f`

## String-To-Sign

Azure Files needs its own implementation, but the Blob builder is still the template:

- normalize values up front
- build the canonicalized resource in one helper
- assemble the string-to-sign as an ordered list
- `urldecode()` each value before joining with `\n`
- sign through `StorageSharedKeyCredential::computeHMACSHA256(...)`
- emit query parameters through `GuzzleHttp\Psr7\Query::build(...)`

Keep the Azure Files differences local to:

- canonicalized resource format
- signed resource value
- any Azure Files-specific fields such as directory depth

Do not create a cross-service SAS abstraction unless duplication becomes materially painful.

## Canonicalized Resource

Follow the Blob builder pattern of computing this internally from the account name and stamped client state.

Planned form:

- share: `/file/{account}/{share}`
- directory: `/file/{account}/{share}/{directoryPath}`
- file: `/file/{account}/{share}/{filePath}`

If Azure Files requires different canonicalization for directories versus files, keep that distinction inside the builder.

## Development URI Handling

Copy the Blob behavior unless Azure Files proves it needs a different rule.

That means:

- use the existing `StorageUriParserHelper::isDevelopmentUri(...)` if it applies cleanly
- otherwise introduce the smallest possible helper addition needed for Azure Files endpoints
- when the URI is a development URI, set `SasProtocol::HTTPS_AND_HTTP` before signing

This should be driven by the same reasoning as Blob, not by a Files-specific redesign.

## Permission Types

Model the permission classes directly on the Blob permission objects.

### `ShareSasPermissions`

Represents share-scoped permissions and serializes them in Azure's required order.

### `ShareDirectorySasPermissions`

Represents directory-scoped permissions and serializes them in Azure's required order.

### `ShareFileSasPermissions`

Represents file-scoped permissions and serializes them in Azure's required order.

Implementation guidance:

- use promoted boolean properties
- keep the constructor flat
- implement only `__toString()`
- do not add fluent permission toggles in v1

If two scopes have identical permission sets, still prefer separate types if that matches the Blob package's clarity around scope.

## Minimal Client Hierarchy

Only build enough Azure Files client surface to support SAS generation and tests.

### `ShareServiceClient`

Should provide:

- `fromConnectionString(...)`
- `getShareClient(string $shareName): ShareClient`

### `ShareClient`

Should hold:

- endpoint URI
- share name
- credential
- HTTP client/options following Blob and Queue patterns

Should provide:

- `getDirectoryClient(string $directoryPath): ShareDirectoryClient`
- `getFileClient(string $filePath): ShareFileClient`
- `canGenerateSasUri()`
- `generateSasUri(...)`

### `ShareDirectoryClient`

Should provide:

- `getDirectoryClient(string $directoryPath): ShareDirectoryClient`
- `getFileClient(string $fileName): ShareFileClient`
- `canGenerateSasUri()`
- `generateSasUri(...)`

### `ShareFileClient`

Should provide:

- `canGenerateSasUri()`
- `generateSasUri(...)`

This is enough to follow the Blob navigation pattern and sign resource-specific SAS URIs.

## Tests

Mirror the Blob testing strategy.

### Unit tests

Add focused tests around:

- permission ordering and serialization
- signed resource selection (`s`, `d`, `f`)
- canonicalized resource construction
- inclusion of optional fields such as IP range
- response-header override query parameters
- directory depth behavior for `sr=d`
- failure when neither identifier nor permissions are set, if Azure Files follows the same rule as Blob
- failure when the client credential cannot sign

The unit tests should be small and builder-centric, similar to `BlobSasBuilderTest`.

### Feature tests

Add feature tests around:

- `canGenerateSasUri()` on share, directory, and file clients
- `generateSasUri()` stamping the right resource context
- generated URIs containing the expected `sr`
- a signed SAS URI working against real Azure Files operations where the current test environment supports it

Keep feature coverage narrow. Blob already proves that most SAS correctness belongs in unit tests.

## Docs

Documentation should match the Blob package style:

- docs examples using `ShareSasBuilder::new()`
- clear note that SAS generation requires a shared-key credential
- no convenience-overload examples unless those methods actually exist
- no wrapper/framework guidance in this phase

## Implementation Phases

### Phase 1: Minimal package wiring

- Keep `src/Storage/File/Share/composer.json` aligned with the other Storage packages
- Add the Azure Files source directories and namespace layout
- Add a package README section for SAS generation scope

### Phase 2: Minimal client surface

- Add `ShareServiceClient`
- Add `ShareClient`
- Add `ShareDirectoryClient`
- Add `ShareFileClient`
- Reuse the existing Storage client construction patterns from Blob and Queue

### Phase 3: SAS types

- Add `ShareSasBuilder`
- Add the three permission value objects
- Add `UnableToGenerateSasException`

### Phase 4: SAS signing

- Implement Azure Files canonicalized resource construction
- Implement Azure Files string-to-sign logic
- Implement `canGenerateSasUri()` and `generateSasUri()` on the resource clients

### Phase 5: Tests

- Add builder and permission unit tests first
- Add narrow feature tests second

### Phase 6: Docs

- Add README examples and changelog entry

## Decisions To Lock Before Coding

1. Keep the Blob-style builder API as the only SAS API in v1.
2. Keep Azure Files SAS logic in `src/Storage/File/Share/Sas` rather than pushing abstractions into `Common`.
3. Add only the minimal client hierarchy required to stamp resource context and generate SAS URIs.
4. Mirror Blob tests and naming closely unless Azure Files semantics require a concrete difference.
