<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Models;

use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Middleware\HttpClientOptions;

/**
 * Configures blob batch client options.
 */
final readonly class BlobBatchClientOptions
{
    /**
     * @param  HttpClientOptions  $httpClientOptions  Client transport options such as timeouts.
     * @param  ApiVersion|null  $apiVersion  Storage service version to send, or null for the default.
     */
    public function __construct(
        public HttpClientOptions $httpClientOptions = new HttpClientOptions,
        public ?ApiVersion $apiVersion = null,
    ) {}
}
