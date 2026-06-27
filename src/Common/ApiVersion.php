<?php

declare(strict_types=1);

namespace AzureOss\Storage\Common;

enum ApiVersion: string
{
    case V2026_06_06 = '2026-06-06';
    case V2026_04_06 = '2026-04-06';
    case V2026_02_06 = '2026-02-06';
    case V2025_11_05 = '2025-11-05';
    case V2025_07_05 = '2025-07-05';
    case V2025_05_05 = '2025-05-05';
    case V2025_01_05 = '2025-01-05';
    case V2024_11_04 = '2024-11-04';
    case V2024_08_04 = '2024-08-04';

    public static function latestGA(): self
    {
        return self::V2026_06_06;
    }

    public static function latestAzurite(): self
    {
        return self::V2025_11_05;
    }
}
