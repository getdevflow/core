<?php

declare(strict_types=1);

namespace App\Shared\Services;

use DateTimeInterface;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

final class EventDateTime
{
    public static function payload(string $key, DateTimeInterface $date): array
    {
        // An offset also distinguishes the two occurrences of a clock time when DST ends.
        return [$key => $date->format('Y-m-d H:i:s'), $key . '_timezone' => $date->format('P')];
    }

    public static function parse(array $payload, string $key): DateTimeInterface
    {
        // Historical local dates have no offset; retain their default-timezone interpretation.
        $timezone = $payload[$key . '_timezone'] ?? (str_ends_with($key, '_gmt') ? 'GMT' : null);

        return QubusDateTimeImmutable::parse($payload[$key], $timezone);
    }
}
