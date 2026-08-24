<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Update;

use App\Shared\Services\Security\ArchivePath;
use JsonException;
use ParseError;
use PhpToken;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

use function array_reverse;
use function bin2hex;
use function chmod;
use function clearstatcache;
use function closedir;
use function dirname;
use function fclose;
use function feof;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function flock;
use function fopen;
use function fread;
use function fwrite;
use function glob;
use function hash;
use function hash_equals;
use function hash_file;
use function is_array;
use function is_dir;
use function is_file;
use function is_link;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function opendir;
use function random_bytes;
use function readdir;
use function realpath;
use function rename;
use function rmdir;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;
use const TOKEN_PARSE;

/**
 * Stages and publishes one update archive with a durable rollback journal.
 *
 * The live installation layout is intentionally left unchanged. Each file is
 * replaced atomically, and every affected pre-update file is copied to a
 * private workspace before the first live mutation is made.
 */
final class FilesystemUpdateTransaction
{
    private const int MAX_ARCHIVE_ENTRIES = 10000;
    private const int MAX_UNCOMPRESSED_BYTES = 536870912;
    private const string WORKSPACE_PREFIX = '.devflow-update-transaction-';
    private const string JOURNAL_FILE = 'journal.json';
    private const string LOCK_FILE = '.devflow-update.lock';

    private string $installDir;
    private string $tempDir;
    private ?string $workspace = null;
    private ?string $stageDir = null;
    private ?string $backupDir = null;

    /**
     * @var resource|null
     */
    private $lockHandle = null;

    /**
     * @var list<array{path:string, existed:bool, permissions:int, temporary:string}>
     */
    private array $journalEntries = [];

    /**
     * @var list<string>
     */
    private array $createdDirectories = [];

    /**
     * @var list<string>
     */
    private array $publishedPaths = [];

    /**
     * @var list<string>
     */
    private array $stagedDirectories = [];

    private string $phase = 'new';

    public function __construct(
        string $installDir,
        string $tempDir,
        private readonly LoggerInterface $logger,
        private readonly int $directoryPermissions = 0755,
    ) {
        $resolvedInstallDir = realpath($installDir);
        $resolvedTempDir = realpath($tempDir);

        if (!is_string($resolvedInstallDir) || !is_dir($resolvedInstallDir)) {
            throw new RuntimeException(sprintf('Install directory "%s" does not exist.', $installDir));
        }

        if (!is_string($resolvedTempDir) || !is_dir($resolvedTempDir)) {
            throw new RuntimeException(sprintf('Temporary directory "%s" does not exist.', $tempDir));
        }

        $this->installDir = rtrim($resolvedInstallDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->tempDir = rtrim($resolvedTempDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Acquire the cross-process update lock and recover an interrupted update.
     * @throws RandomException
     * @throws JsonException
     */
    public function begin(?callable $afterRecovery = null): void
    {
        if (is_resource($this->lockHandle)) {
            throw new RuntimeException('The update transaction has already begun.');
        }

        $lockHandle = fopen($this->tempDir . self::LOCK_FILE, 'c+b');

        if (!is_resource($lockHandle)) {
            throw new RuntimeException('Unable to open the update lock file.');
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            throw new RuntimeException('Another Devflow update is already running.');
        }

        $this->lockHandle = $lockHandle;
        $recovered = $this->recoverInterruptedTransactions();

        if ($recovered && $afterRecovery !== null && $afterRecovery() === false) {
            throw new RuntimeException('The interrupted update was restored, but recovery cleanup failed.');
        }

        $token = bin2hex(random_bytes(16));
        $this->workspace = $this->tempDir . self::WORKSPACE_PREFIX . $token;
        $this->stageDir = $this->workspace . DIRECTORY_SEPARATOR . 'stage';
        $this->backupDir = $this->workspace . DIRECTORY_SEPARATOR . 'backup';

        if (
            !mkdir($this->stageDir, 0700, true)
            || !mkdir($this->backupDir, 0700, true)
        ) {
            throw new RuntimeException('Unable to create the private update workspace.');
        }

        $this->phase = 'staging';
        $this->writeJournal();
    }

    /**
     * Fully inspect and extract an archive into the private staging directory.
     *
     * @return list<string> Normalized file paths, excluding directory entries.
     */
    public function stage(string $archive): array
    {
        $this->assertBegun();

        $zip = new ZipArchive();
        $resource = $zip->open($archive, ZipArchive::CHECKCONS);

        if ($resource !== true) {
            throw new RuntimeException(sprintf('Unable to open update archive "%s".', $archive));
        }

        try {
            $entries = $this->inspectArchive($zip);
            $writtenBytes = 0;

            foreach ($entries as $entry) {
                $destination = $this->stagePath($entry['path']);

                if ($entry['directory']) {
                    $this->createDirectory($destination, 0700);
                    $this->stagedDirectories[] = rtrim($entry['path'], '/');
                    continue;
                }

                $this->createDirectory(dirname($destination), 0700);
                $source = $zip->getStream($entry['archive_name']);

                if (!is_resource($source)) {
                    throw new RuntimeException(sprintf(
                        'Unable to read update archive entry "%s".',
                        $entry['archive_name']
                    ));
                }

                $target = fopen($destination, 'xb');

                if (!is_resource($target)) {
                    fclose($source);
                    throw new RuntimeException(sprintf('Unable to stage update file "%s".', $entry['path']));
                }

                try {
                    while (!feof($source)) {
                        $chunk = fread($source, 8192);

                        if ($chunk === false) {
                            throw new RuntimeException(sprintf(
                                'Unable to read update archive entry "%s".',
                                $entry['archive_name']
                            ));
                        }

                        if ($chunk === '') {
                            continue;
                        }

                        $writtenBytes += strlen($chunk);

                        if ($writtenBytes > self::MAX_UNCOMPRESSED_BYTES) {
                            throw new RuntimeException('Update archive exceeds the extraction size limit.');
                        }

                        if (fwrite($target, $chunk) !== strlen($chunk)) {
                            throw new RuntimeException(sprintf('Unable to stage update file "%s".', $entry['path']));
                        }
                    }
                } finally {
                    fclose($source);
                    fclose($target);
                }

                chmod($destination, 0600);
                $this->validatePhpSyntax($destination, $entry['path']);
            }

            $this->phase = 'staged';
            $this->writeJournal();

            return array_values(array_map(
                static fn (array $entry): string => $entry['path'],
                array_filter($entries, static fn (array $entry): bool => !$entry['directory'])
            ));
        } finally {
            $zip->close();
        }
    }

    /**
     * Publish every staged file using an atomic same-directory rename.
     *
     * @param list<string> $paths
     * @throws JsonException
     */
    public function publish(array $paths): void
    {
        $this->assertBegun();

        if ($this->phase !== 'staged') {
            throw new RuntimeException('The update archive must be staged before it can be published.');
        }

        $this->phase = 'publishing';
        $this->writeJournal();

        foreach ($this->stagedDirectories as $directory) {
            $this->assertDestinationIsSafe($directory . '/placeholder');
            $this->ensureInstallDirectory($directory);
        }

        foreach ($paths as $path) {
            if (!$this->isSafeFilePath($path)) {
                throw new RuntimeException('The staged update contains an invalid file path.');
            }

            $source = $this->stagePath($path);
            $target = $this->installPath($path);

            if (!is_file($source) || is_link($source)) {
                throw new RuntimeException(sprintf('Staged update file "%s" is missing.', $path));
            }

            $this->assertDestinationIsSafe($path);
            $this->ensureInstallDirectory(dirname($path));

            $existed = file_exists($target) || is_link($target);
            $existingPermissions = $existed && !is_link($target) ? fileperms($target) : false;
            $permissions = is_int($existingPermissions) ? ($existingPermissions & 0777) : 0644;

            if ($existed) {
                if (!is_file($target) || is_link($target)) {
                    throw new RuntimeException(sprintf('Update target "%s" is not a regular file.', $path));
                }

                $backup = $this->backupPath($path);
                $this->createDirectory(dirname($backup), 0700);

                if (!copy($target, $backup)) {
                    throw new RuntimeException(sprintf('Unable to back up update target "%s".', $path));
                }

                chmod($backup, 0600);
            }

            $temporary = $this->temporaryPathFor($path);
            $this->journalEntries[] = [
                'path' => $path,
                'existed' => $existed,
                'permissions' => $permissions,
                'temporary' => $temporary,
            ];
            $this->writeJournal();

            $this->atomicCopy($source, $target, $temporary, $permissions);
            $this->publishedPaths[] = $path;
        }

        $this->phase = 'published';
        $this->writeJournal();
    }

    public function publishedPath(string $path): string
    {
        if (!$this->isSafeFilePath($path)) {
            throw new RuntimeException('Invalid published update path.');
        }

        return $this->installPath($path);
    }

    /**
     * Verify that every live file still matches its staged copy.
     *
     * @param list<string> $excludedPaths
     */
    public function verifyPublishedFiles(array $excludedPaths = []): bool
    {
        foreach ($this->publishedPaths as $path) {
            if (in_array($path, $excludedPaths, true)) {
                continue;
            }

            $stagedHash = hash_file('sha256', $this->stagePath($path));
            $publishedHash = hash_file('sha256', $this->installPath($path));

            if (
                !is_string($stagedHash)
                || !is_string($publishedHash)
                || !hash_equals($stagedHash, $publishedHash)
            ) {
                return false;
            }
        }

        return true;
    }

    public function removePublishedFile(string $path): void
    {
        $target = $this->publishedPath($path);

        if (is_file($target) && !unlink($target)) {
            throw new RuntimeException(sprintf('Unable to remove update script "%s".', $path));
        }
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function commit(): void
    {
        $this->assertBegun();
        $this->phase = 'committed';
        $this->writeJournal();
        $this->removeTree($this->workspace);
        $this->workspace = null;
        $this->stageDir = null;
        $this->backupDir = null;
    }

    /**
     * Restore every live path recorded in the journal.
     */
    public function rollback(): bool
    {
        if ($this->workspace === null) {
            return true;
        }

        $success = $this->restoreJournal(
            $this->workspace,
            $this->journalEntries,
            $this->createdDirectories
        );

        if ($success) {
            $this->removeTree($this->workspace);
            $this->workspace = null;
            $this->stageDir = null;
            $this->backupDir = null;
        } else {
            $this->logger->critical(sprintf(
                'Update rollback was incomplete. Recovery data remains at "%s".',
                $this->workspace
            ));
        }

        return $success;
    }

    public function close(): void
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return list<array{archive_name:string, path:string, directory:bool}>
     */
    private function inspectArchive(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            throw new RuntimeException('Update archive contains too many entries.');
        }

        $entries = [];
        $seen = [];
        $declaredBytes = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stats = $zip->statIndex($index);

            if (!is_array($stats)) {
                throw new RuntimeException(sprintf('Unable to inspect update archive entry %d.', $index));
            }

            $path = ArchivePath::normalize((string) $stats['name']);
            $declaredBytes += (int) $stats['size'];

            if (
                $path === null
                || $this->isSymbolicLink($zip, $index)
                || $declaredBytes > self::MAX_UNCOMPRESSED_BYTES
            ) {
                throw new RuntimeException(sprintf('Unsafe update archive entry "%s".', $stats['name']));
            }

            $comparisonPath = strtolower(rtrim($path, '/'));

            if (isset($seen[$comparisonPath])) {
                throw new RuntimeException(sprintf('Duplicate update archive path "%s".', $path));
            }

            $seen[$comparisonPath] = true;
            $entries[] = [
                'archive_name' => (string) $stats['name'],
                'path' => $path,
                'directory' => str_ends_with($path, '/'),
            ];
        }

        return $entries;
    }

    private function validatePhpSyntax(string $filename, string $relativePath): void
    {
        if (!str_ends_with(strtolower($relativePath), '.php')) {
            return;
        }

        $source = file_get_contents($filename);

        if (!is_string($source)) {
            throw new RuntimeException(sprintf('Unable to validate staged PHP file "%s".', $relativePath));
        }

        try {
            PhpToken::tokenize($source, TOKEN_PARSE);
        } catch (ParseError $exception) {
            throw new RuntimeException(
                sprintf('Staged PHP file "%s" contains a syntax error.', $relativePath),
                0,
                $exception
            );
        }
    }

    private function assertDestinationIsSafe(string $path): void
    {
        $segments = explode('/', $path);
        array_pop($segments);
        $current = rtrim($this->installDir, '/\\');

        foreach ($segments as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($current)) {
                throw new RuntimeException(sprintf(
                    'Refusing to update through symbolic-link directory "%s".',
                    $path
                ));
            }

            if (file_exists($current) && !is_dir($current)) {
                throw new RuntimeException(sprintf('Update path "%s" has a non-directory parent.', $path));
            }
        }
    }

    /**
     * @throws JsonException
     */
    private function ensureInstallDirectory(string $relativeDirectory): void
    {
        if ($relativeDirectory === '.' || $relativeDirectory === '') {
            return;
        }

        $segments = explode('/', $relativeDirectory);
        $currentRelative = '';

        foreach ($segments as $segment) {
            $currentRelative = $currentRelative === '' ? $segment : $currentRelative . '/' . $segment;
            $directory = $this->installPath($currentRelative);

            if (is_dir($directory)) {
                continue;
            }

            if (file_exists($directory) || is_link($directory)) {
                throw new RuntimeException(sprintf('Unable to create update directory "%s".', $currentRelative));
            }

            if (!mkdir($directory, $this->directoryPermissions)) {
                throw new RuntimeException(sprintf('Unable to create update directory "%s".', $currentRelative));
            }

            $this->createdDirectories[] = $currentRelative;
            $this->writeJournal();
        }
    }

    private function atomicCopy(
        string $source,
        string $target,
        string $temporaryRelative,
        int $permissions
    ): void {
        $temporary = $this->installPath($temporaryRelative);

        if (file_exists($temporary) || is_link($temporary)) {
            throw new RuntimeException(sprintf('Temporary update path "%s" already exists.', $temporaryRelative));
        }

        if (!copy($source, $temporary)) {
            throw new RuntimeException(sprintf('Unable to prepare atomic replacement for "%s".', $target));
        }

        chmod($temporary, $permissions);

        if (!rename($temporary, $target)) {
            unlink($temporary);
            throw new RuntimeException(sprintf('Unable to atomically replace "%s".', $target));
        }

        clearstatcache(true, $target);
    }

    /**
     * @param list<array{path:string, existed:bool, permissions:int, temporary:string}> $entries
     * @param list<string> $createdDirectories
     */
    private function restoreJournal(string $workspace, array $entries, array $createdDirectories): bool
    {
        $success = true;
        $backupDir = $workspace . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR;

        foreach (array_reverse($entries) as $entry) {
            if (!$this->isSafeFilePath($entry['path']) || !$this->isSafeFilePath($entry['temporary'])) {
                $success = false;
                continue;
            }

            $target = $this->installPath($entry['path']);
            $temporary = $this->installPath($entry['temporary']);

            if ((is_file($temporary) || is_link($temporary)) && !unlink($temporary)) {
                $success = false;
            }

            if ($entry['existed']) {
                $backup = $backupDir . str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);

                if (!is_file($backup)) {
                    $success = false;
                    continue;
                }

                try {
                    $this->atomicCopy($backup, $target, $entry['temporary'], $entry['permissions']);
                } catch (Throwable $exception) {
                    $this->logger->error($exception->getMessage(), ['exception' => $exception]);
                    $success = false;
                }
            } elseif ((is_file($target) || is_link($target)) && !unlink($target)) {
                $success = false;
            }
        }

        foreach (array_reverse($createdDirectories) as $directory) {
            if (ArchivePath::normalize($directory) !== $directory) {
                $success = false;
                continue;
            }

            $absolute = $this->installPath($directory);

            if (is_dir($absolute) && !$this->directoryIsEmpty($absolute)) {
                continue;
            }

            if (is_dir($absolute) && !rmdir($absolute)) {
                $success = false;
            }
        }

        return $success;
    }

    private function recoverInterruptedTransactions(): bool
    {
        $workspaces = glob($this->tempDir . self::WORKSPACE_PREFIX . '*', GLOB_ONLYDIR);

        if (!is_array($workspaces)) {
            return false;
        }

        $recovered = false;

        foreach ($workspaces as $workspace) {
            $journalFile = $workspace . DIRECTORY_SEPARATOR . self::JOURNAL_FILE;
            $json = file_get_contents($journalFile);

            if (!is_string($json)) {
                throw new RuntimeException(sprintf(
                    'Interrupted update workspace "%s" has no readable journal; manual recovery is required.',
                    $workspace
                ));
            }

            try {
                $journal = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    sprintf('Interrupted update workspace "%s" has an invalid journal.', $workspace),
                    0,
                    $exception
                );
            }

            if (
                !is_array($journal)
                || ($journal['install_dir'] ?? null) !== $this->installDir
                || !is_array($journal['entries'] ?? null)
                || !is_array($journal['created_directories'] ?? null)
            ) {
                throw new RuntimeException(sprintf(
                    'Interrupted update workspace "%s" does not match this installation.',
                    $workspace
                ));
            }

            if (($journal['phase'] ?? '') === 'committed') {
                $this->removeTree($workspace);
                continue;
            }

            if (($journal['phase'] ?? '') === 'staging' || ($journal['phase'] ?? '') === 'staged') {
                $this->removeTree($workspace);
                continue;
            }

            $this->logger->warning(sprintf('Recovering interrupted update from "%s".', $workspace));

            if (!$this->restoreJournal($workspace, $journal['entries'], $journal['created_directories'])) {
                throw new RuntimeException(sprintf(
                    'Unable to recover interrupted update from "%s".',
                    $workspace
                ));
            }

            $this->removeTree($workspace);
            $recovered = true;
        }

        return $recovered;
    }

    /**
     * @throws JsonException
     */
    private function writeJournal(): void
    {
        if ($this->workspace === null) {
            return;
        }

        $journal = json_encode([
            'version' => 1,
            'install_dir' => $this->installDir,
            'phase' => $this->phase,
            'entries' => $this->journalEntries,
            'created_directories' => $this->createdDirectories,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $temporary = $this->workspace . DIRECTORY_SEPARATOR . self::JOURNAL_FILE . '.tmp';
        $filename = $this->workspace . DIRECTORY_SEPARATOR . self::JOURNAL_FILE;

        if (file_put_contents($temporary, $journal, LOCK_EX) !== strlen($journal)) {
            throw new RuntimeException('Unable to write the update rollback journal.');
        }

        chmod($temporary, 0600);

        if (!rename($temporary, $filename)) {
            unlink($temporary);
            throw new RuntimeException('Unable to publish the update rollback journal.');
        }
    }

    private function temporaryPathFor(string $path): string
    {
        $directory = dirname($path);
        $prefix = $directory === '.' ? '' : $directory . '/';

        return $prefix . '.devflow-update-' . substr(hash('sha256', (string) $this->workspace . $path), 0, 24) . '.tmp';
    }

    private function stagePath(string $path): string
    {
        if ($this->stageDir === null) {
            throw new RuntimeException('The update transaction has no staging directory.');
        }

        return $this->stageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim($path, '/'));
    }

    private function backupPath(string $path): string
    {
        if ($this->backupDir === null) {
            throw new RuntimeException('The update transaction has no backup directory.');
        }

        return $this->backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function installPath(string $path): string
    {
        return $this->installDir . str_replace('/', DIRECTORY_SEPARATOR, rtrim($path, '/'));
    }

    private function isSafeFilePath(string $path): bool
    {
        return $path !== ''
        && !str_ends_with($path, '/')
        && ArchivePath::normalize($path) === $path;
    }

    private function assertBegun(): void
    {
        if (!is_resource($this->lockHandle) || $this->workspace === null) {
            throw new RuntimeException('The update transaction has not begun.');
        }
    }

    private function createDirectory(string $directory, int $permissions): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, $permissions, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory "%s".', $directory));
        }
    }

    private function directoryIsEmpty(string $directory): bool
    {
        $handle = opendir($directory);

        if ($handle === false) {
            return false;
        }

        try {
            while (($entry = readdir($handle)) !== false) {
                if ($entry !== '.' && $entry !== '..') {
                    return false;
                }
            }

            return true;
        } finally {
            closedir($handle);
        }
    }

    private function removeTree(?string $directory = null): void
    {
        if ($directory === null || !is_dir($directory)) {
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

    private function isSymbolicLink(ZipArchive $zip, int $index): bool
    {
        $operatingSystem = 0;
        $attributes = 0;

        if (!$zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
            return false;
        }

        return (($attributes >> 16) & 0170000) === 0120000;
    }
}
