<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Shared\Services\SimpleCacheObjectCacheFactory;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use ReflectionException;
use RuntimeException;

use function array_column;
use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;


final class ExtensionRepository
{
    private CacheInterface $cache;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function __construct(
        private readonly string $composerLockPath,
        private readonly string $registryBaseUrl = 'https://api.devflowcmf.com/v1',
        private readonly int $ttl = 21600
    ) {
        $this->cache = SimpleCacheObjectCacheFactory::make(namespace: 'devflow_extensions');
    }

    /**
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function plugins(): array
    {
        return $this->forInstaller('plugins');
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function themes(): array
    {
        return $this->forInstaller('themes');
    }

    /**
     * @param string $kind
     * @return array
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function forInstaller(string $kind): array
    {
        $cacheKey = sprintf('installer.%s', $kind);
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $available = $this->available($kind);
        $installed = $this->installedPackages();

        $extensions = [];

        foreach ($available as $extension) {
            if (! is_array($extension)) {
                continue;
            }

            $package = (string) ($extension['name'] ?? $extension['package'] ?? '');

            if ($package === '') {
                continue;
            }

            $extensions[] = [
                'name' => $package,
                'vendor' => $package,
                'description' => (string) ($extension['description'] ?? ''),
                'version' => (string) ($extension['version'] ?? $extension['latest_version'] ?? ''),
                'url' => $extension['url'] ?? null,
                'installed' => isset($installed[$package]),
            ];
        }

        $this->cache->set($cacheKey, $extensions, $this->ttl);

        return $extensions;
    }

    /**
     * @param string $kind
     * @return array
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function available(string $kind): array
    {
        $cacheKey = sprintf('available.%s', $kind);
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $url = sprintf(
            '%s/%s.json',
            rtrim($this->registryBaseUrl, '/'),
            $kind
        );

        try {
            $json = $this->fetchJson($url);
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        $results = match (true) {
            isset($decoded['packages']) && is_array($decoded['packages']) => $decoded['packages'],
            isset($decoded['results']) && is_array($decoded['results']) => $decoded['results'],
            isset($decoded['data']) && is_array($decoded['data']) => $decoded['data'],
            isset($decoded[$kind]) && is_array($decoded[$kind]) => $decoded[$kind],
            array_is_list($decoded) => $decoded,
            default => [],
        };

        $results = array_values(array_filter(
            $results,
            static fn (mixed $extension): bool => is_array($extension)
        ));

        $this->cache->set($cacheKey, $results, $this->ttl);

        return $results;
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function installedPackages(): array
    {
        if (! file_exists($this->composerLockPath)) {
            return [];
        }

        $hash = md5_file($this->composerLockPath);

        $cacheKey = 'installed.packages.' . ($hash ?: 'missing');
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        if (! file_exists($this->composerLockPath)) {
            return [];
        }

        $json = file_get_contents($this->composerLockPath);

        if ($json === false) {
            return [];
        }

        $lock = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $packages = [
            ...($lock['packages'] ?? []),
            ...($lock['packages-dev'] ?? []),
        ];

        $installed = array_column($packages, null, 'name');

        $this->cache->set($cacheKey, $installed, $this->ttl);

        return $installed;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clearCache(): void
    {
        $this->cache->delete('available.plugins');
        $this->cache->delete('available.themes');
        $this->cache->delete('installer.plugins');
        $this->cache->delete('installer.themes');
    }

    private function fetchJson(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: DevflowCMF/1.0',
                    'Accept: application/json',
                ],
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $json = file_get_contents($url, false, $context);

        $statusLine = $http_response_header[0] ?? '';

        if ($json === false || ! str_contains($statusLine, '200')) {
            throw new RuntimeException(sprintf(
                'Unable to fetch registry URL [%s]. Response: %s',
                $url,
                $statusLine
            ));
        }

        return $json;
    }
}
