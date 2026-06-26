<?php

declare(strict_types=1);

namespace AzureOss\Storage\Queue;

use AzureOss\Identity\TokenCredential;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Middleware\ClientFactory;
use AzureOss\Storage\Queue\Exceptions\QueueStorageException;
use AzureOss\Storage\Queue\Exceptions\QueueStorageExceptionDeserializer;
use AzureOss\Storage\Queue\Models\QueueClientOptions;
use AzureOss\Storage\Queue\Models\QueueErrorCode;
use AzureOss\Storage\Queue\Models\QueueMessage;
use AzureOss\Storage\Queue\Models\QueueProperties;
use AzureOss\Storage\Queue\Models\SendReceipt;
use AzureOss\Storage\Queue\Models\UpdateReceipt;
use AzureOss\Storage\Queue\Requests\QueueMessageRequestBody;
use AzureOss\Storage\Queue\Responses\ReceiveMessagesResponseBody;
use AzureOss\Storage\Queue\Responses\SendMessageResponseBody;
use AzureOss\Storage\Queue\Responses\UpdateMessageResponseBody;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\UriInterface;

final class QueueClient
{
    private readonly Client $client;

    public readonly string $queueName;

    public function __construct(
        public UriInterface $uri,
        public readonly StorageSharedKeyCredential|TokenCredential|null $credential = null,
        private readonly QueueClientOptions $options = new QueueClientOptions,
    ) {
        // must always include the forward slash (/) to separate the host name from the path and query portions of the URI.
        $this->uri = $uri->withPath(rtrim($uri->getPath(), '/'));
        $this->queueName = basename($this->uri->getPath());
        $this->client = (new ClientFactory)->create($this->uri, $credential, new QueueStorageExceptionDeserializer, $this->options->httpClientOptions);
    }

    public function create(): void
    {
        $this->createAsync()->wait();
    }

    public function createAsync(): PromiseInterface
    {
        return $this->client->putAsync($this->uri);
    }

    public function createIfNotExists(): void
    {
        $this->createIfNotExistsAsync()->wait();
    }

    public function createIfNotExistsAsync(): PromiseInterface
    {
        return $this->createAsync()
            ->otherwise(function (\Throwable $e) {
                if ($e instanceof QueueStorageException && $e->errorCode === QueueErrorCode::QueueAlreadyExists) {
                    return;
                }

                throw $e;
            });
    }

    public function delete(): void
    {
        $this->deleteAsync()->wait();
    }

    public function deleteAsync(): PromiseInterface
    {
        return $this->client->deleteAsync($this->uri);
    }

    public function deleteIfExists(): void
    {
        $this->deleteIfExistsAsync()->wait();
    }

    public function deleteIfExistsAsync(): PromiseInterface
    {
        return $this->deleteAsync()
            ->otherwise(function (\Throwable $e) {
                if ($e instanceof QueueStorageException && $e->errorCode === QueueErrorCode::QueueNotFound) {
                    return;
                }

                throw $e;
            });
    }

    public function exists(): bool
    {
        /** @phpstan-ignore-next-line */
        return $this->existsAsync()->wait();
    }

    public function existsAsync(): PromiseInterface
    {
        return $this->client
            ->headAsync($this->uri, [
                RequestOptions::QUERY => [
                    'comp' => 'metadata',
                ],
            ])
            ->then(fn () => true)
            ->otherwise(function (\Throwable $e) {
                if ($e instanceof QueueStorageException && $e->errorCode === QueueErrorCode::QueueNotFound) {
                    return false;
                }

                throw $e;
            });
    }

    public function getProperties(): QueueProperties
    {
        /** @phpstan-ignore-next-line */
        return $this->getPropertiesAsync()->wait();
    }

    public function getPropertiesAsync(): PromiseInterface
    {
        return $this->client
            ->getAsync($this->uri, [
                RequestOptions::QUERY => [
                    'comp' => 'metadata',
                ],
            ])
            ->then(QueueProperties::fromResponseHeaders(...));
    }

    public function clearMessages(): void
    {
        $this->clearMessagesAsync()->wait();
    }

    public function clearMessagesAsync(): PromiseInterface
    {
        return $this->client->deleteAsync($this->messagesUri());
    }

    public function sendMessage(string $messageText, ?int $visibilityTimeout = null, ?int $timeToLive = null): SendReceipt
    {
        /** @phpstan-ignore-next-line */
        return $this->sendMessageAsync($messageText, $visibilityTimeout, $timeToLive)->wait();
    }

    public function sendMessageAsync(string $messageText, ?int $visibilityTimeout = null, ?int $timeToLive = null): PromiseInterface
    {
        $query = [];
        if ($visibilityTimeout !== null) {
            $query['visibilitytimeout'] = $visibilityTimeout;
        }
        if ($timeToLive !== null) {
            $query['messagettl'] = $timeToLive;
        }

        return $this->client
            ->postAsync($this->messagesUri(), [
                RequestOptions::QUERY => $query,
                RequestOptions::BODY => (new QueueMessageRequestBody($messageText))->toXml()->asXML(),
            ])
            ->then(SendMessageResponseBody::fromResponse(...));
    }

    public function updateMessage(string $messageId, string $popReceipt, int $visibilityTimeout, ?string $messageText = null): UpdateReceipt
    {
        /** @phpstan-ignore-next-line */
        return $this->updateMessageAsync($messageId, $popReceipt, $visibilityTimeout, $messageText)->wait();
    }

    public function updateMessageAsync(string $messageId, string $popReceipt, int $visibilityTimeout, ?string $messageText = null): PromiseInterface
    {
        $options = [
            RequestOptions::QUERY => [
                'popreceipt' => $popReceipt,
                'visibilitytimeout' => $visibilityTimeout,
            ],
        ];

        if ($messageText !== null) {
            $options[RequestOptions::BODY] = (new QueueMessageRequestBody($messageText))->toXml()->asXML();
        }

        return $this->client
            ->putAsync($this->messageUri($messageId), $options)
            ->then(UpdateMessageResponseBody::fromResponse(...));
    }

    public function deleteMessage(string $messageId, string $popReceipt): void
    {
        $this->deleteMessageAsync($messageId, $popReceipt)->wait();
    }

    public function deleteMessageAsync(string $messageId, string $popReceipt): PromiseInterface
    {
        return $this->client->deleteAsync($this->messageUri($messageId), [
            RequestOptions::QUERY => [
                'popreceipt' => $popReceipt,
            ],
        ]);
    }

    public function receiveMessage(?int $visibilityTimeout = null): ?QueueMessage
    {
        /** @phpstan-ignore-next-line */
        return $this->receiveMessageAsync($visibilityTimeout)->wait();
    }

    public function receiveMessageAsync(?int $visibilityTimeout = null): PromiseInterface
    {
        return $this->receiveMessagesAsync(1, $visibilityTimeout)
            ->then(fn (array $messages) => $messages[0] ?? null);
    }

    /**
     * @return QueueMessage[]
     */
    public function receiveMessages(?int $maxMessages = null, ?int $visibilityTimeout = null): array
    {
        /** @phpstan-ignore-next-line  */
        return $this->receiveMessagesAsync($maxMessages, $visibilityTimeout)->wait();
    }

    public function receiveMessagesAsync(?int $maxMessages = null, ?int $visibilityTimeout = null): PromiseInterface
    {
        $query = [];
        if ($maxMessages !== null) {
            $query['numofmessages'] = $maxMessages;
        }
        if ($visibilityTimeout !== null) {
            $query['visibilitytimeout'] = $visibilityTimeout;
        }

        return $this->client
            ->getAsync($this->messagesUri(), [
                RequestOptions::QUERY => $query,
            ])
            ->then(ReceiveMessagesResponseBody::fromResponse(...));
    }

    private function messagesUri(): UriInterface
    {
        return $this->uri->withPath($this->uri->getPath().'/messages');
    }

    private function messageUri(string $messageId): UriInterface
    {
        return $this->uri->withPath($this->uri->getPath().'/messages/'.$messageId);
    }
}
