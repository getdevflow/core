<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Attribute;

use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionException;

use function App\Shared\Helpers\dfdb;
use function Codefy\Framework\Helpers\logger;
use function md5;

final readonly class AttributeManager
{
    public function __construct(
        private string $type,
        private AttributeRepository $repository,
        private CacheInterface $cache
    ) {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
    public static function factory(string $type, string $namespace = 'attributes'): self
    {
        return new self($type, new PdoAttributeDataRepository(dfdb()), SimpleCacheObjectCacheFactory::make($namespace));
    }

    /**
     * Retrieve attributes for the specified array.
     *
     * @param string $id ID of the array attribute is for.
     * @param string $key Attribute key.
     * @param mixed $default Value to return if no result is found.
     * @return mixed Value.
     */
    public function get(string $id, string $key, mixed $default = null): mixed
    {
        return $this->load($id)->getPath($key, $default);
    }

    public function all(string $id): string
    {
        return $this->load($id)->toJson();
    }

    public function bag(string $id): AttributeBag
    {
        return $this->load($id);
    }

    public function set(string $id, string $key, mixed $value): AttributeBag
    {
        $updated = $this->repository->patchAttribute(
            $this->type,
            $id,
            static fn (AttributeBag $attribute): AttributeBag => $attribute->setPath($key, $value),
        );

        $this->storeWarmCache($id, $updated);

        return $updated;
    }

    public function remove(string $id, string $key): AttributeBag
    {
        $updated = $this->repository->patchAttribute(
            $this->type,
            $id,
            static fn (AttributeBag $attribute): AttributeBag => $attribute->removePath($key),
        );

        $this->storeWarmCache($id, $updated);

        return $updated;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function replaceAll(string $id, array $values): AttributeBag
    {
        $attribute = AttributeBag::fromArray($values);

        $this->repository->saveAttribute($this->type, $id, $attribute);
        $this->storeWarmCache($id, $attribute);

        return $attribute;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function merge(string $id, array $values): AttributeBag
    {
        $updated = $this->repository->patchAttribute(
            $this->type,
            $id,
            static fn (AttributeBag $attribute): AttributeBag => $attribute->merge($values),
        );

        $this->storeWarmCache($id, $updated);

        return $updated;
    }

    public function mergeRecursive(string $id, array $values): AttributeBag
    {
        $updated = $this->repository->patchAttribute(
            $this->type,
            $id,
            static fn (AttributeBag $attribute): AttributeBag => $attribute->mergeRecursive($values),
        );

        $this->storeWarmCache($id, $updated);

        return $updated;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function forget(string $id): void
    {
        $this->cache->delete(md5($id));
    }

    public function warm(string $id): AttributeBag
    {
        $attribute = $this->repository->getAttribute($this->type, $id);
        $this->storeWarmCache($id, $attribute);

        return $attribute;
    }

    /**
     * @param list<string> $ids
     * @return array<string, AttributeBag>
     */
    public function warmMany(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $result[$id] = $this->warm($id);
        }

        return $result;
    }

    public function exists(string $id, string $key): bool
    {
        return !empty($this->get($id, $key));
    }

    /**
     * Warms the cache.
     *
     * @param string $id
     * @return AttributeBag
     */
    private function load(string $id): AttributeBag
    {
        try {
            $cached = $this->cache->get(md5($id));

            if (is_string($cached) && $cached !== '') {
                return AttributeBag::fromJson($cached);
            }
        } catch (InvalidArgumentException $e) {
            logger(level: 'error', message: $e->getMessage());
        }

        $attribute = $this->repository->getAttribute($this->type, $id);
        $this->storeWarmCache($id, $attribute);

        return $attribute;
    }

    private function storeWarmCache(string $id, AttributeBag $attribute): void
    {
        try {
            $this->cache->set(
                md5($id),
                $attribute->toJson(),
            );
        } catch (InvalidArgumentException $e) {
            logger(level: 'error', message: $e->getMessage());
        }
    }
}
