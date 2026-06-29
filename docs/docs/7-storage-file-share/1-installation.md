---
sidebar_position: 1
title: Installation
---

`azure-oss/storage-file-share` is the Azure Files package for PHP.

## Requirements

- PHP 8.2+
- PHP extensions:
  - `curl`
  - `json`
  - `simplexml`

## Install With Composer

```bash
composer require azure-oss/storage-file-share
```

## Intended usage

This package is not intended for normal mounted-share file I/O.

For ordinary file and directory operations, mount Azure Files over SMB or NFS and use standard filesystem APIs from PHP or your framework.

The SDK is intended for Azure Files capabilities that are outside that mounted-share workflow.

## Next Step

Continue to [Mounted Share Boundary](./working-with-mounted-shares) for the package scope and the current repository test model.
