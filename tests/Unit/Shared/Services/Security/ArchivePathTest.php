<?php

declare(strict_types=1);

use App\Shared\Services\Security\ArchivePath;

it('normalizes safe archive paths', function (string $input, string $expected): void {
    expect(ArchivePath::normalize($input))->toBe($expected);
})->with([
    ['Application/update.php', 'Application/update.php'],
    ['public\\themes\\clean\\theme.php', 'public/themes/clean/theme.php'],
    ['storage/cache/', 'storage/cache/'],
]);

it('rejects archive traversal and absolute paths', function (string $entry): void {
    expect(ArchivePath::normalize($entry))->toBeNull()
        ->and(ArchivePath::isSafe($entry))->toBeFalse();
})->with([
    '../outside.php',
    'safe/../../outside.php',
    '/etc/passwd',
    'C:\\Windows\\system.ini',
    '\\\\server\\share\\file',
    "safe/evil\0.php",
    'safe//file.php',
    './file.php',
]);
