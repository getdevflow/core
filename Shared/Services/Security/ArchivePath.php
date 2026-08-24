<?php

declare(strict_types=1);

namespace App\Shared\Services\Security;

/**
 * Validates untrusted archive entry names before they are joined to a local
 * installation path.
 */
final class ArchivePath
{
    public static function normalize(string $entry): ?string
    {
        if ($entry === '' || str_contains($entry, "\0") || preg_match('/[\x00-\x1F\x7F]/', $entry) === 1) {
            return null;
        }

        $entry = str_replace('\\', '/', $entry);

        if (
            str_starts_with($entry, '/')
            || str_starts_with($entry, '//')
            || preg_match('/\A[A-Za-z]:\//', $entry) === 1
        ) {
            return null;
        }

        $directory = str_ends_with($entry, '/');
        $segments = explode('/', rtrim($entry, '/'));

        if (array_any($segments, fn($segment) => $segment==='' || $segment==='.' || $segment==='..')) {
            return null;
        }

        $normalized = implode('/', $segments);

        return $directory ? $normalized . '/' : $normalized;
    }

    public static function isSafe(string $entry): bool
    {
        return self::normalize($entry) !== null;
    }
}
