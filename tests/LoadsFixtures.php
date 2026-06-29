<?php

declare(strict_types=1);

namespace AzureOss\Tests;

use PHPUnit\Framework\TestCase;

/** @mixin TestCase */
trait LoadsFixtures
{
    protected function fixturePath(string $file): string
    {
        return __DIR__.'/fixtures/'.$file;
    }

    protected function fixtureContents(string $file): string
    {
        $contents = file_get_contents($this->fixturePath($file));

        if ($contents === false) {
            throw new \RuntimeException("Failed to read fixture: {$file}");
        }

        return $contents;
    }
}
