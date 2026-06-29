<?php

declare(strict_types=1);

namespace AzureOss\Tests;

use PHPUnit\Framework\TestCase;

/** @mixin TestCase */
trait RequiresEnvironmentVariables
{
    protected static function getRequiredEnvironmentVariable(string $name): string
    {
        $value = getenv($name);

        if (! is_string($value) || $value === '') {
            self::markTestSkipped('Missing environment variables: '.$name);
        }

        return $value;
    }

    /**
     * @param  list<string>  $names
     */
    protected static function getFirstAvailableEnvironmentVariable(array $names): string
    {
        foreach ($names as $name) {
            $value = getenv($name);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        self::markTestSkipped('Missing environment variables: one of '.implode(', ', $names));
    }
}
