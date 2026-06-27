<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Helpers\ConnectionStringHelper;
use AzureOss\Storage\Queue\Exceptions\InvalidConnectionStringException;
use AzureOss\Storage\Queue\Models\QueueClientOptions;
use AzureOss\Storage\Queue\Models\QueueServiceClientOptions;
use Psr\Http\Message\UriInterface;

final class QueueServiceClient
{
    public function __construct(
        public UriInterface $uri,
        public readonly StorageSharedKeyCredential|TokenCredential|null $credential = null,
        private readonly QueueServiceClientOptions $options = new QueueServiceClientOptions,
    ) {
        // must always include the forward slash (/) to separate the host name from the path and query portions of the URI.
        $this->uri = $uri->withPath(rtrim($uri->getPath(), '/').'/');
    }

    public static function fromConnectionString(string $connectionString, QueueServiceClientOptions $options = new QueueServiceClientOptions): self
    {
        $uri = ConnectionStringHelper::getQueueEndpoint($connectionString);
        if ($uri === null) {
            throw new InvalidConnectionStringException;
        }

        $sas = ConnectionStringHelper::getSas($connectionString);
        if ($sas !== null) {
            return new self($uri->withQuery($sas), options: $options);
        }

        $accountName = ConnectionStringHelper::getAccountName($connectionString);
        $accountKey = ConnectionStringHelper::getAccountKey($connectionString);
        if ($accountName !== null && $accountKey !== null) {
            return new self($uri, new StorageSharedKeyCredential($accountName, $accountKey), $options);
        }

        throw new InvalidConnectionStringException;
    }

    public function getQueueClient(string $queueName): QueueClient
    {
        return new QueueClient(
            $this->uri->withPath($this->uri->getPath().$queueName),
            $this->credential,
            new QueueClientOptions($this->options->httpClientOptions, $this->options->apiVersion),
        );
    }
}
