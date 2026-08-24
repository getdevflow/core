<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Devflow;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;
use RuntimeException;
use Throwable;

use function App\Shared\Helpers\updater_server_url;
use function Codefy\Framework\Helpers\base_path;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\trans_html;
use function date;
use function fclose;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_resource;
use function json_decode;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function stream_get_contents;
use function stream_set_blocking;
use function time;
use function trim;
use function usleep;

use const JSON_THROW_ON_ERROR;

final class UpdateManager
{
    /**
     * @param bool $forceRefresh
     * @return array
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function overview(bool $forceRefresh = false): array
    {
        $cache = SimpleCacheObjectCacheFactory::make(namespace: 'devflow_updates_screen');
        $cacheKey = 'updates-overview';

        if (!$forceRefresh) {
            $cached = $cache->get($cacheKey);

            if (is_array($cached)) {
                return $cached + ['stale' => true];
            }
        }

        try {
            $overview = [
                'core' => $this->coreUpdate(),
                'plugins' => $this->packageUpdates('devflow-plugin'),
                'themes' => $this->packageUpdates('devflow-theme'),
                'stale' => false,
                'checked_at' => date('Y-m-d H:i:s'),
            ];

            $cache->set($cacheKey, $overview, 1800);

            return $overview;
        } catch (Throwable $e) {
            logger(level: 'error', message: $e->getMessage(), context: ['UpdateManager' => 'overview']);

            $cached = $cache->get($cacheKey);

            if (is_array($cached)) {
                return $cached + [
                    'stale' => true,
                    'error' => trans_html('Could not refresh updates. Showing cached results.'),
                ];
            }

            return [
                'core' => ['available' => false, 'current' => Devflow::release(), 'latest' => null],
                'plugins' => [],
                'themes' => [],
                'stale' => true,
                'error' => trans_html('Could not check for updates right now.'),
            ];
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function coreUpdate(): array
    {
        $updater = new Updater();

        $publicKey = UpdateSigningKey::get();

        if ($publicKey === '') {
            throw new RuntimeException(
                'Devflow update signing public key is not configured.'
            );
        }

        $updater->setSigningPublicKey($publicKey);
        $updater->setCurrentVersion(Devflow::release());
        $updater->setUpdateUrl(updater_server_url() . '/update-check');

        $result = $updater->checkUpdate(timeout: 3);

        return [
            'current' => Devflow::release(),
            'latest' => $updater->latestVersion,
            'available' => $result !== false && $updater->newVersionAvailable(),
        ];
    }

    /**
     * @param string $type
     * @return array
     * @throws Exception
     * @throws JsonException
     */
    public function packageUpdates(string $type): array
    {
        $installed = $this->installedPackagesByType($type);
        $outdated = $this->composerJson(['composer', 'show', '--outdated', '--direct', '--format=json']);

        $updates = [];

        foreach (($outdated['installed'] ?? []) as $package) {
            $name = $package['name'] ?? '';

            if (!isset($installed[$name])) {
                continue;
            }

            $updates[] = [
                'name' => $name,
                'description' => $installed[$name]['description'] ?? '',
                'current' => $package['version'] ?? $installed[$name]['version'] ?? '',
                'latest' => $package['latest'] ?? '',
                'type' => $type,
            ];
        }

        return $updates;
    }

    /**
     * @param string $package
     * @param string $expectedType
     * @return array
     * @throws Exception
     * @throws JsonException
     */
    public function updatePackage(string $package, string $expectedType): array
    {
        $installed = $this->installedPackagesByType($expectedType);

        if (!isset($installed[$package])) {
            throw new RuntimeException(sprintf(trans_html('Package is not an installed %s type.'), $expectedType));
        }

        return $this->composerRun(
            ['composer', 'update', $package, '--with-dependencies', '--no-interaction'],
            300
        );
    }

    /**
     * @param string $expectedType
     * @return array
     * @throws Exception
     * @throws JsonException
     */
    public function updateAll(string $expectedType): array
    {
        $updates = $this->packageUpdates($expectedType);

        if ($updates === []) {
            return [
                'success' => true,
                'output' => trans_html('No updates available.'),
            ];
        }

        $packages = array_column($updates, 'name');

        return $this->composerRun(
            ['composer', 'update', ...$packages, '--with-dependencies', '--no-interaction'],
            300
        );
    }

    /**
     * @param string $type
     * @return array
     * @throws JsonException
     */
    private function installedPackagesByType(string $type): array
    {
        $lockFile = base_path('composer.lock');

        if (!file_exists($lockFile)) {
            return [];
        }

        $lock = json_decode(
            file_get_contents($lockFile),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $packages = [];

        foreach (($lock['packages'] ?? []) as $package) {
            if (($package['type'] ?? '') !== $type) {
                continue;
            }

            $packages[$package['name']] = $package;
        }

        return $packages;
    }

    /**
     * @param list<string> $command
     * @return array
     * @throws Exception
     * @throws JsonException
     */
    private function composerJson(array $command): array
    {
        $result = $this->composerRun($command);

        if (!$result['success']) {
            return [];
        }

        return json_decode($result['output'], true, 512, JSON_THROW_ON_ERROR) ?: [];
    }

    /**
     * @param list<string> $command
     * @param int $timeout
     * @return array
     * @throws Exception
     */
    private function composerRun(array $command, int $timeout = 3): array
    {
        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptor, $pipes, base_path());

        if (!is_resource($process)) {
            return ['success' => false, 'output' => trans_html('Unable to start Composer process.')];
        }

        $start = time();

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $output = '';

        $exitCode = null;

        while (true) {
            $status = proc_get_status($process);

            $output .= stream_get_contents($pipes[1]);
            $output .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }

            if ((time() - $start) >= $timeout) {
                proc_terminate($process);

                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }

                proc_close($process);

                return [
                    'success' => false,
                    'timeout' => true,
                    'output' => trans_html('Composer update check timed out.'),
                ];
            }

            usleep(100000);
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $closeCode = proc_close($process);
        $code = $exitCode >= 0 ? $exitCode : $closeCode;

        return [
            'success' => $code === 0,
            'timeout' => false,
            'output' => trim($output),
        ];
    }
}
