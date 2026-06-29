<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share\Models;

use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Middleware\HttpClientOptions;

/**
 * Configures file share client options.
 */
final readonly class ShareClientOptions
{
    public function __construct(
        public HttpClientOptions $httpClientOptions = new HttpClientOptions,
        public ?ApiVersion $apiVersion = null,
    ) {}
}
