<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

final class UpdateSigningKey
{
    public const string PUBLIC_KEY = 'M5ut1f6ZRdv8o9dY2B03OPwOkUlLG2yWMAAT/LixIyw=';

    public static function get(): string
    {
        return self::PUBLIC_KEY;
    }
}
