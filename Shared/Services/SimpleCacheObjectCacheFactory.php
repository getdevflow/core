<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use Qubus\Cache\Psr16\SimpleCache;
use Qubus\Exception\Data\TypeException;
use ReflectionException;

use function Codefy\Framework\Helpers\config;

final class SimpleCacheObjectCacheFactory
{
    /**
     * @param string $namespace
     * @return CacheInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public static function make(string $namespace = 'default'): CacheInterface
    {
        return new SimpleCache(
            adapter: Registry::getInstance()->get('cacheAdapter'),
            ttl: config()->integer(key: 'cache.ttl'),
            namespace: $namespace
        );
    }
}
