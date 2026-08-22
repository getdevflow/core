<?php

declare(strict_types=1);

use App\Infrastructure\Services\Update\FilesystemUpdateTransaction;
use App\Infrastructure\Services\Update\PersistentMaintenanceMode;
use App\Infrastructure\Services\Update\UpdateLock;
use App\Infrastructure\Services\Updater;
use Psr\Log\NullLogger;

function updateTransactionRemoveTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}

/**
 * @param array<string, string> $files
 */
function updateTransactionZip(string $filename, array $files): void
{
    $zip = new ZipArchive();

    expect($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    foreach ($files as $path => $contents) {
        if (str_ends_with($path, '/')) {
            expect($zip->addEmptyDir(rtrim($path, '/')))->toBeTrue();
            continue;
        }

        expect($zip->addFromString($path, $contents))->toBeTrue();
    }

    expect($zip->close())->toBeTrue();
}

/**
 * @param array<string, mixed> $properties
 */
function updateTransactionUpdater(array $properties): Updater
{
    $reflection = new ReflectionClass(Updater::class);
    $updater = $reflection->newInstanceWithoutConstructor();

    foreach ($properties as $name => $value) {
        $property = $reflection->getProperty($name);
        $property->setValue($updater, $value);
    }

    return $updater;
}

beforeEach(function (): void {
    $this->transactionRoot = sys_get_temp_dir() . '/devflow-update-test-' . bin2hex(random_bytes(12));
    $this->installDir = $this->transactionRoot . '/install';
    $this->tempDir = $this->transactionRoot . '/temp';

    mkdir($this->installDir, 0700, true);
    mkdir($this->tempDir, 0700, true);
});

afterEach(function (): void {
    unset($GLOBALS['devflow_update_test_migrated']);
    updateTransactionRemoveTree($this->transactionRoot);
});

it('stages without mutating live files and commits atomically', function (): void {
    file_put_contents($this->installDir . '/existing.php', '<?php return "old";');
    $archive = $this->transactionRoot . '/update.zip';
    updateTransactionZip($archive, [
        'existing.php' => '<?php return "new";',
        'nested/new.txt' => 'new file',
        'empty-directory/' => '',
    ]);

    $transaction = new FilesystemUpdateTransaction(
        $this->installDir,
        $this->tempDir,
        new NullLogger()
    );

    $transaction->begin();
    $paths = $transaction->stage($archive);

    expect(file_get_contents($this->installDir . '/existing.php'))->toContain('old')
        ->and(file_exists($this->installDir . '/nested/new.txt'))->toBeFalse();

    $transaction->publish($paths);

    expect($transaction->verifyPublishedFiles())->toBeTrue()
        ->and(file_get_contents($this->installDir . '/existing.php'))->toContain('new')
        ->and(file_get_contents($this->installDir . '/nested/new.txt'))->toBe('new file')
        ->and(is_dir($this->installDir . '/empty-directory'))->toBeTrue();

    $transaction->commit();
    $transaction->close();
});

it('restores replaced files and removes newly-created paths on rollback', function (): void {
    file_put_contents($this->installDir . '/existing.txt', 'before');
    $archive = $this->transactionRoot . '/update.zip';
    updateTransactionZip($archive, [
        'existing.txt' => 'after',
        'created/during-update.txt' => 'temporary',
    ]);

    $transaction = new FilesystemUpdateTransaction(
        $this->installDir,
        $this->tempDir,
        new NullLogger()
    );
    $transaction->begin();
    $paths = $transaction->stage($archive);
    $transaction->publish($paths);

    expect($transaction->rollback())->toBeTrue()
        ->and(file_get_contents($this->installDir . '/existing.txt'))->toBe('before')
        ->and(file_exists($this->installDir . '/created/during-update.txt'))->toBeFalse()
        ->and(is_dir($this->installDir . '/created'))->toBeFalse();

    $transaction->close();
});

it('rejects PHP syntax errors before touching the live installation', function (): void {
    file_put_contents($this->installDir . '/existing.php', '<?php return "old";');
    $archive = $this->transactionRoot . '/invalid.zip';
    updateTransactionZip($archive, ['existing.php' => '<?php function broken( {']);

    $transaction = new FilesystemUpdateTransaction(
        $this->installDir,
        $this->tempDir,
        new NullLogger()
    );
    $transaction->begin();

    expect(fn () => $transaction->stage($archive))->toThrow(RuntimeException::class)
        ->and(file_get_contents($this->installDir . '/existing.php'))->toContain('old')
        ->and($transaction->rollback())->toBeTrue();

    $transaction->close();
});

it('refuses to publish through a symbolic-link directory', function (): void {
    $outside = $this->transactionRoot . '/outside';
    mkdir($outside, 0700);
    file_put_contents($outside . '/target.txt', 'outside');
    symlink($outside, $this->installDir . '/linked');
    $archive = $this->transactionRoot . '/symlink-target.zip';
    updateTransactionZip($archive, ['linked/target.txt' => 'overwritten']);

    $transaction = new FilesystemUpdateTransaction(
        $this->installDir,
        $this->tempDir,
        new NullLogger()
    );
    $transaction->begin();
    $paths = $transaction->stage($archive);

    expect(fn () => $transaction->publish($paths))->toThrow(RuntimeException::class)
        ->and(file_get_contents($outside . '/target.txt'))->toBe('outside')
        ->and($transaction->rollback())->toBeTrue();

    $transaction->close();
});

it('prevents concurrent update transactions', function (): void {
    $first = new FilesystemUpdateTransaction($this->installDir, $this->tempDir, new NullLogger());
    $second = new FilesystemUpdateTransaction($this->installDir, $this->tempDir, new NullLogger());

    $first->begin();

    expect(fn () => $second->begin())->toThrow(
        RuntimeException::class,
        'Another Devflow update is already running.'
    );

    $first->rollback();
    $first->close();
});

it('holds the process lock until the complete update workflow releases it', function (): void {
    $filename = $this->tempDir . '/process.lock';
    $first = new UpdateLock($filename);
    $second = new UpdateLock($filename);

    expect($first->acquire())->toBeTrue()
        ->and($second->acquire())->toBeFalse();

    $first->release();

    expect($second->acquire())->toBeTrue();

    $second->release();
});

it('restores persisted maintenance state from a later process', function (): void {
    $mode = 0;
    $stateFile = $this->tempDir . '/maintenance.json';
    $reader = static function () use (&$mode): int {
        return $mode;
    };
    $writer = static function (int $newMode) use (&$mode): bool {
        $mode = $newMode;

        return true;
    };
    $firstProcess = new PersistentMaintenanceMode($stateFile, $reader, $writer);

    expect($firstProcess->enter())->toBeTrue()
        ->and($mode)->toBe(1)
        ->and(is_file($stateFile))->toBeTrue();

    $nextProcess = new PersistentMaintenanceMode($stateFile, $reader, $writer);

    expect($nextProcess->leave())->toBeTrue()
        ->and($mode)->toBe(0)
        ->and(is_file($stateFile))->toBeFalse();
});

it('recovers a published transaction left behind by an interrupted process', function (): void {
    file_put_contents($this->installDir . '/existing.txt', 'before');
    $archive = $this->transactionRoot . '/update.zip';
    updateTransactionZip($archive, [
        'existing.txt' => 'after',
        'new.txt' => 'created',
    ]);

    $interrupted = new FilesystemUpdateTransaction($this->installDir, $this->tempDir, new NullLogger());
    $interrupted->begin();
    $interrupted->publish($interrupted->stage($archive));
    $interrupted->close();

    expect(file_get_contents($this->installDir . '/existing.txt'))->toBe('after');

    $recovery = new FilesystemUpdateTransaction($this->installDir, $this->tempDir, new NullLogger());
    $recoveryCallbackRan = false;
    $recovery->begin(static function () use (&$recoveryCallbackRan): bool {
        $recoveryCallbackRan = true;

        return true;
    });

    expect(file_get_contents($this->installDir . '/existing.txt'))->toBe('before')
        ->and(file_exists($this->installDir . '/new.txt'))->toBeFalse()
        ->and($recoveryCallbackRan)->toBeTrue();

    $recovery->rollback();
    $recovery->close();
});

it('rolls files back when a post-migration health check fails', function (): void {
    file_put_contents($this->installDir . '/existing.txt', 'before');
    $archive = $this->transactionRoot . '/update.zip';
    updateTransactionZip($archive, [
        'existing.txt' => 'after',
        'new.txt' => 'created',
    ]);
    $maintenanceStates = [];
    $updater = updateTransactionUpdater([
        'installDir' => $this->installDir . '/',
        'tempDir' => $this->tempDir . '/',
        'log' => new NullLogger(),
        'maintenanceModeHandler' => static function (bool $enabled) use (&$maintenanceStates): bool {
            $maintenanceStates[] = $enabled;

            return true;
        },
        'healthChecks' => [static fn (): bool => false],
    ]);
    $method = new ReflectionMethod(Updater::class, 'extractAndInstall');

    $result = $method->invoke($updater, $archive, false, '1.2.3');

    expect($result)->toBe(Updater::ERROR_TRANSACTION)
        ->and(file_get_contents($this->installDir . '/existing.txt'))->toBe('before')
        ->and(file_exists($this->installDir . '/new.txt'))->toBeFalse()
        ->and($maintenanceStates)->toBe([true, false]);
});

it('runs an upgrade script through the configured migration transaction', function (): void {
    $archive = $this->transactionRoot . '/update.zip';
    updateTransactionZip($archive, [
        'release.txt' => 'installed',
        '_upgrade.php' => '<?php $GLOBALS["devflow_update_test_migrated"] = true;',
    ]);
    $migrationWrapped = false;
    $updater = updateTransactionUpdater([
        'installDir' => $this->installDir . '/',
        'tempDir' => $this->tempDir . '/',
        'log' => new NullLogger(),
        'migrationTransaction' => static function (Closure $migration) use (&$migrationWrapped): mixed {
            $migrationWrapped = true;

            return $migration();
        },
    ]);
    $method = new ReflectionMethod(Updater::class, 'extractAndInstall');

    $result = $method->invoke($updater, $archive, false, '1.2.3');

    expect($result)->toBeTrue()
        ->and($migrationWrapped)->toBeTrue()
        ->and($GLOBALS['devflow_update_test_migrated'])->toBeTrue()
        ->and(file_get_contents($this->installDir . '/release.txt'))->toBe('installed')
        ->and(file_exists($this->installDir . '/_upgrade.php'))->toBeFalse();
});

it('rolls published files back when the migration transaction throws', function (): void {
    file_put_contents($this->installDir . '/release.txt', 'previous');
    $archive = $this->transactionRoot . '/update.zip';
    updateTransactionZip($archive, [
        'release.txt' => 'next',
        '_upgrade.php' => '<?php throw new RuntimeException("migration failed");',
    ]);
    $updater = updateTransactionUpdater([
        'installDir' => $this->installDir . '/',
        'tempDir' => $this->tempDir . '/',
        'log' => new NullLogger(),
        'migrationTransaction' => static fn (Closure $migration): mixed => $migration(),
    ]);
    $method = new ReflectionMethod(Updater::class, 'extractAndInstall');

    $result = $method->invoke($updater, $archive, false, '1.2.3');

    expect($result)->toBe(Updater::ERROR_TRANSACTION)
        ->and(file_get_contents($this->installDir . '/release.txt'))->toBe('previous')
        ->and(file_exists($this->installDir . '/_upgrade.php'))->toBeFalse();
});
