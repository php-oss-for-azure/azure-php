---
name: release-planner
description: Update Azure PHP monorepo package changelogs from `Unreleased` to concrete release versions and produce the exact `git tag` / `git push origin <tag>` command sequence for releasing those packages. Use when Codex is asked to plan or prepare a release for `/src/Identity`, `/src/Storage/*`, or `docs/`, especially when it must infer or apply version bumps, use prefixed monorepo tags, and avoid re-reading GitHub Actions for release conventions.
---

# Release Planner

Work from the repository root.

Treat this skill as the source of truth for this monorepo's release flow. Do not re-open GitHub Actions unless the user explicitly asks to verify or change the release process.

## Release Targets

Use this package map and tag prefixes:

| Path | Package | Tag prefix |
| --- | --- | --- |
| `src/Identity` | `azure-oss/identity` | `identity` |
| `src/Storage/Common` | `azure-oss/storage-common` | `storage-common` |
| `src/Storage/Blob` | `azure-oss/storage-blob` | `storage-blob` |
| `src/Storage/BlobFlysystem` | `azure-oss/storage-blob-flysystem` | `storage-blob-flysystem` |
| `src/Storage/BlobLaravel` | `azure-oss/storage-blob-laravel` | `storage-blob-laravel` |
| `src/Storage/BlobFlysystemBundle` | `azure-oss/storage-blob-flysystem-bundle` | `storage-blob-flysystem-bundle` |
| `src/Storage/Queue` | `azure-oss/storage-queue` | `storage-queue` |
| `src/Storage/QueueLaravel` | `azure-oss/storage-queue-laravel` | `storage-queue-laravel` |
| `src/Storage/File/Share` | `azure-oss/storage-file-share` | `storage-file-share` |
| `docs` | docs site | `docs` |

Use prefixed monorepo tags such as `storage-common-2.1.1`, `storage-blob-2.2.2`, or `docs-1.2.3`.

## Decide What To Release

Inspect each package changelog.

Release a package when its changelog has a `## Unreleased` section with real content. Skip a package when:

- it has no `## Unreleased` section
- its `## Unreleased` section only says `No user-facing changes since ...`
- the user explicitly excludes it

Do not invent releases for unchanged packages just to keep versions aligned.

## Choose Versions

If the user gives explicit versions, use them.

Otherwise infer the next version from the latest released version in that package's changelog:

- bump `major` when the unreleased notes describe a breaking change, BC break, removal, incompatible default, or an `UPGRADE.md` change
- bump `minor` when the unreleased notes add public API, new features, new supported integrations, or new configuration surface
- bump `patch` when the unreleased notes are fixes, internal behavior corrections, documentation-only user-visible clarifications, or compatibility adjustments without new public surface

When the unreleased notes are ambiguous, state the assumption before editing.

## Rewrite Changelogs

For every package being released, replace the current unreleased section with this structure:

```md
## Unreleased

No user-facing changes since `<new-version>`.

## <new-version>

...existing unreleased subsections and bullets...
```

Preserve the unreleased bullets exactly unless the user asked for editorial cleanup.

Keep the new version section directly below `## Unreleased`.

Do not touch older release sections.

Do not create a changelog entry for `docs`; documentation releases use tags only unless the user explicitly wants docs release notes somewhere else.

## Release Order

When producing the release command list, preserve this dependency-safe order and filter it down to only the packages being released:

1. `identity`
2. `storage-common`
3. `storage-blob`
4. `storage-blob-flysystem`
5. `storage-blob-laravel`
6. `storage-blob-flysystem-bundle`
7. `storage-queue`
8. `storage-queue-laravel`
9. `storage-file-share`
10. `docs`

This order keeps lower-level packages ahead of dependents. If only one package is releasing, output only that package.

## Command Output

After determining the release set and versions, produce the exact shell commands in release order.

Output one tag command followed immediately by its push command:

```bash
git tag <prefix>-<version>
git push origin <prefix>-<version>
```

Example:

```bash
git tag storage-common-2.1.1
git push origin storage-common-2.1.1
git tag storage-blob-2.2.2
git push origin storage-blob-2.2.2
```

Do not collapse them into a single multi-tag push unless the user asks.

## Response Shape

When using this skill, do the work in this order:

1. Identify releasable packages and proposed versions.
2. Update the matching changelog files.
3. Report which changelogs were changed.
4. Print the ordered `git tag` and `git push origin ...` commands.
5. Mention any assumptions, especially inferred version bumps.

If no packages have releasable unreleased notes, say so plainly and do not emit tag commands.
