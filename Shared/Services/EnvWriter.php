<?php

declare(strict_types=1);

namespace App\Shared\Services;

use RuntimeException;

use function fclose;
use function fflush;
use function flock;
use function fopen;
use function ftruncate;
use function fwrite;
use function preg_match;
use function rewind;
use function stream_get_contents;
use function strlen;

use const LOCK_EX;
use const LOCK_UN;

final readonly class EnvWriter
{
    public function __construct(private string $path)
    {
    }

    public function set(string $key, string $value): void
    {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $key) !== 1) {
            throw new RuntimeException(sprintf('Invalid environment variable name: %s', $key));
        }

        if (! file_exists($this->path)) {
            throw new RuntimeException(sprintf('Environment file does not exist: %s', $this->path));
        }

        $handle = fopen($this->path, 'r+');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Environment file is not writable: %s', $this->path));
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException(sprintf('Unable to lock environment file: %s', $this->path));
            }

            rewind($handle);
            $contents = stream_get_contents($handle);

            if ($contents === false) {
                throw new RuntimeException(sprintf('Environment file is not readable: %s', $this->path));
            }

            $value = $this->normalizeValue($value);

            if (preg_match('/^' . preg_quote($key, '/') . '=/m', $contents) === 1) {
                $updated = preg_replace_callback(
                    '/^' . preg_quote($key, '/') . '=.*$/m',
                    static fn (): string => $key . '=' . $value,
                    $contents
                );

                if ($updated === null) {
                    throw new RuntimeException(sprintf('Unable to update environment variable: %s', $key));
                }

                $contents = $updated;
            } else {
                $contents = rtrim($contents) . PHP_EOL . $key . '=' . $value . PHP_EOL;
            }

            rewind($handle);

            if (! ftruncate($handle, 0)) {
                throw new RuntimeException(sprintf('Unable to truncate environment file: %s', $this->path));
            }

            $written = fwrite($handle, $contents);

            if ($written === false || $written !== strlen($contents) || ! fflush($handle)) {
                throw new RuntimeException(sprintf('Unable to write environment file: %s', $this->path));
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function normalizeValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/\A[A-Za-z0-9_\.\/:@+\-]+\z/D', $value) === 1) {
            return $value;
        }

        return '"' . addcslashes($value, "\\\"\n\r\t\$") . '"';
    }
}
