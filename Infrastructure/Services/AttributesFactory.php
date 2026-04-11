<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Devflow;
use App\Infrastructure\Services\Attribute\PdoAttributeDataRepository;
use App\Infrastructure\Services\Attribute\AttributeManager;
use App\Infrastructure\Services\User\PdoUserAttributeDataRepository;
use App\Infrastructure\Services\User\UserAttributeManager;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

use function App\Shared\Helpers\dfdb;

final class AttributesFactory
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
    public static function product(): AttributeManager
    {
        $dfdb = dfdb();

        return new AttributeManager(
            type: 'product',
            repository: new PdoAttributeDataRepository($dfdb),
            cache: SimpleCacheObjectCacheFactory::make(namespace: $dfdb->prefix . 'product_attribute')
        );
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    public static function content(): AttributeManager
    {
        $dfdb = dfdb();

        return new AttributeManager(
            type: 'content',
            repository: new PdoAttributeDataRepository($dfdb),
            cache: SimpleCacheObjectCacheFactory::make(namespace: $dfdb->prefix . 'content_attribute')
        );
    }

    /**
     * @return UserAttributeManager
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public static function user(): UserAttributeManager
    {
        return new UserAttributeManager(
            repository: Devflow::$PHP->make(name: PdoUserAttributeDataRepository::class),
            cache: SimpleCacheObjectCacheFactory::make(namespace: dfdb()->prefix . 'user_attribute')
        );
    }
}
