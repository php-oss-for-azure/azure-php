<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

/**
 * Configures blob container listing options.
 */
final class GetBlobContainersOptions
{
    /**
     * @param  BlobContainerInclude[]  $includes  Optional datasets to include for every page of the listing.
     */
    public function __construct(
        public ?int $pageSize = null,
        public array $includes = [],
    ) {}
}
