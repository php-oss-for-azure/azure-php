<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue\Models;

use AzureOss\Storage\Common\ApiVersion;
use AzureOss\Storage\Common\Middleware\HttpClientOptions;

final readonly class QueueServiceClientOptions
{
    public function __construct(
        public HttpClientOptions $httpClientOptions = new HttpClientOptions,
        public ?ApiVersion $apiVersion = null,
    ) {}
}
