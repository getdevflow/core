<?php

declare(strict_types=1);

use App\Shared\Services\PhpFileParser;

afterEach(function (): void {
    if (isset($this->phpFile) && is_file($this->phpFile)) {
        unlink($this->phpFile);
    }
});

it('finds named types in modern PHP files', function (string $source, string $expected): void {
    $this->phpFile = tempnam(sys_get_temp_dir(), 'devflow-php-');
    file_put_contents($this->phpFile, $source);

    expect(PhpFileParser::classFullNameFromFile($this->phpFile))->toBe($expected);
})->with([
    ['<?php namespace Example\\Package; final class Handler {}', 'Example\\Package\\Handler'],
    ['<?php namespace Example\\Package { enum State: string { case Ready = "ready"; } }', 'Example\\Package\\State'],
    ['<?php class GlobalHandler {}', 'GlobalHandler'],
    ['<?php $value = new class {}; class NamedHandler {}', 'NamedHandler'],
]);

it('fails clearly when a file cannot be read', function (): void {
    PhpFileParser::classFullNameFromFile('/path/that/does/not/exist.php');
})->throws(RuntimeException::class, 'Unable to read PHP file');
