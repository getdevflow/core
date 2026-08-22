<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Update;

use RuntimeException;

use function chmod;
use function fclose;
use function flock;
use function fopen;
use function is_resource;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

final class UpdateLock
{
    /**
     * @var resource|null
     */
    private $handle = null;

    public function __construct(private readonly string $filename)
    {
    }

    public function acquire(): bool
    {
        if (is_resource($this->handle)) {
            throw new RuntimeException('The update lock is already acquired.');
        }

        $handle = fopen($this->filename, 'c+b');

        if (!is_resource($handle)) {
            throw new RuntimeException('Unable to open the update process lock.');
        }

        chmod($this->filename, 0600);

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
