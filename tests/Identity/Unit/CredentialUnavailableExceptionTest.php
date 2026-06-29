<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Unit;

use AzureOss\Identity\AggregateException;
use AzureOss\Identity\CredentialUnavailableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CredentialUnavailableExceptionTest extends TestCase
{
    #[Test]
    public function create_aggregate_exception_returns_single_exception_as_is(): void
    {
        $original = new CredentialUnavailableException('only');

        $result = CredentialUnavailableException::createAggregateException('base', [$original]);

        self::assertSame($original, $result);
    }

    #[Test]
    public function create_aggregate_exception_builds_bulleted_message_and_sets_aggregate_previous(): void
    {
        $a = new CredentialUnavailableException('a');
        $b = new CredentialUnavailableException('b');

        $result = CredentialUnavailableException::createAggregateException('base', [$a, $b]);

        self::assertSame("base\n- a\n- b", $result->getMessage());
        self::assertInstanceOf(AggregateException::class, $result->getPrevious());

        /** @var AggregateException $previous */
        $previous = $result->getPrevious();
        self::assertCount(2, $previous->exceptions);
        self::assertSame($a, $previous->exceptions[0]);
        self::assertSame($b, $previous->exceptions[1]);
    }
}
