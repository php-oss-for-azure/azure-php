<?php

declare(strict_types=1);

namespace AzureOss\Tests\Storage;

use PHPUnit\Framework\Assert;

trait RetryableAssertions
{
    /**
     * Retry a callback that is expected to complete without throwing.
     *
     * The final exception is rethrown when all attempts fail so callers retain the
     * Azure error code and request details needed to diagnose persistent failures.
     *
     * @param  callable(): mixed  $callback
     */
    protected static function assertEventuallySucceeds(
        callable $callback,
        int $maxAttempts = 10,
        int $delayMs = 1000,
    ): void {
        $lastException = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $callback();

                return;
            } catch (\Throwable $e) {
                $lastException = $e;
            }

            if ($attempt + 1 < $maxAttempts) {
                usleep($delayMs * 1000);
            }
        }

        throw $lastException ?? new \RuntimeException('The operation did not complete successfully.');
    }

    /**
     * Retry a callback until it returns true or timeout is reached
     *
     * @param  callable  $callback  The condition to check
     * @param  int  $maxAttempts  Maximum number of retry attempts
     * @param  int  $delayMs  Delay between attempts in milliseconds
     * @param  string|null  $message  Failure message
     */
    protected static function assertEventually(
        callable $callback,
        int $maxAttempts = 10,
        int $delayMs = 1000,
        ?string $message = null
    ): void {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $result = $callback();
            if ($result) {
                Assert::assertTrue($result);

                return;
            }
            usleep($delayMs * 1000);
            $attempt++;
        }

        $message = $message ?? sprintf(
            'Condition not met after %d attempts (%dms total)',
            $maxAttempts,
            $maxAttempts * $delayMs
        );

        Assert::fail($message);
    }
}
