<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Update;

use Closure;
use JsonException;
use RuntimeException;

use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\update_option;
use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function rename;
use function strlen;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const LOCK_EX;

/**
 * Persists the pre-update maintenance state so a later process can restore it
 * after recovering an interrupted filesystem transaction.
 */
final class PersistentMaintenanceMode
{
    private Closure $readMode;
    private Closure $writeMode;
    private ?int $previousMode = null;

    public function __construct(
        private readonly string $stateFile,
        ?callable $readMode = null,
        ?callable $writeMode = null,
    ) {
        $this->readMode = $readMode !== null
        ? Closure::fromCallable($readMode)
        : static fn (): int => (int) get_option('maintenance_mode', 0);
        $this->writeMode = $writeMode !== null
        ? Closure::fromCallable($writeMode)
        : static fn (int $mode): bool => update_option('maintenance_mode', $mode)
                || (int) get_option('maintenance_mode', 0) === $mode;
    }

    /**
     * @throws JsonException
     */
    public function __invoke(bool $enabled, string $version = ''): bool
    {
        return $enabled ? $this->enter() : $this->leave();
    }

    /**
     * @throws JsonException
     */
    public function enter(): bool
    {
        if ($this->previousMode === null) {
            $this->previousMode = $this->readPersistedMode() ?? $this->currentMode();
            $this->persistMode($this->previousMode);
        }

        if ($this->currentMode() === 1) {
            return true;
        }

        return $this->write(1);
    }

    /**
     * @throws JsonException
     */
    public function leave(): bool
    {
        if ($this->previousMode === null) {
            $this->previousMode = $this->readPersistedMode();
        }

        if ($this->previousMode === null) {
            return true;
        }

        if ($this->currentMode() !== $this->previousMode && !$this->write($this->previousMode)) {
            return false;
        }

        if (is_file($this->stateFile) && !unlink($this->stateFile)) {
            return false;
        }

        $this->previousMode = null;

        return true;
    }

    private function currentMode(): int
    {
        return (int) ($this->readMode)();
    }

    private function write(int $mode): bool
    {
        return ($this->writeMode)($mode) !== false && $this->currentMode() === $mode;
    }

    /**
     * @throws JsonException
     */
    private function readPersistedMode(): ?int
    {
        if (!is_file($this->stateFile)) {
            return null;
        }

        $json = file_get_contents($this->stateFile);

        if ($json === false) {
            throw new RuntimeException('Unable to read the persisted update maintenance state.');
        }

        $state = json_decode($json, true, 16, JSON_THROW_ON_ERROR);

        if (
            !is_array($state)
            || !isset($state['previous_mode'])
            || ($state['previous_mode'] !== 0 && $state['previous_mode'] !== 1)
        ) {
            throw new RuntimeException('The persisted update maintenance state is invalid.');
        }

        return $state['previous_mode'];
    }

    /**
     * @throws JsonException
     */
    private function persistMode(int $mode): void
    {
        $directory = dirname($this->stateFile);

        if (!is_dir($directory)) {
            throw new RuntimeException('The update maintenance-state directory does not exist.');
        }

        $json = json_encode(['previous_mode' => $mode], JSON_THROW_ON_ERROR);
        $temporary = $this->stateFile . '.tmp';

        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('Unable to persist the update maintenance state.');
        }

        chmod($temporary, 0600);

        if (!rename($temporary, $this->stateFile)) {
            unlink($temporary);
            throw new RuntimeException('Unable to publish the update maintenance state.');
        }
    }
}
