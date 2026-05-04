<?php

declare(strict_types=1);

namespace App\Shared\Services;

use RuntimeException;

final readonly class EnvWriter
{
    public function __construct(private string $path)
    {
    }

    public function set(string $key, string $value): void
    {
        if (! file_exists($this->path)) {
            throw new RuntimeException(sprintf('Environment file does not exist: %s', $this->path));
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Environment file is not readable: %s', $this->path));
        }

        $value = $this->normalizeValue($value);

        if (preg_match('/^' . preg_quote($key, '/') . '=/m', $contents) === 1) {
            $contents = preg_replace(
                '/^' . preg_quote($key, '/') . '=.*$/m',
                $key . '=' . $value,
                $contents
            );
        } else {
            $contents = rtrim($contents) . PHP_EOL . $key . '=' . $value . PHP_EOL;
        }

        file_put_contents($this->path, $contents, LOCK_EX);
    }

    private function normalizeValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (str_contains($value, ' ') || str_contains($value, '#')) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }
}
