<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

/**
 * Configures get blobs options.
 */
final class GetBlobsOptions
{
    /**
     * @param  BlobInclude[]  $includes  Optional datasets to include for every page of the listing.
     */
    public function __construct(
        public ?int $pageSize = null,
        public array $includes = [],
    ) {}
}
