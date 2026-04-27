<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\User;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

use function is_string;
use function json_decode;
use function md5;

final readonly class UserAttributeManager
{
    public function __construct(
        private UserAttributeRepository $repository,
        private CacheInterface $cache
    ) {
    }

    public function get(string $siteId, string $userId, string $key, mixed $default = null): mixed
    {
        return $this->load($siteId, $userId)?->getPath($key, $default);
    }

    public function all(string $siteId, string $userId): string
    {
        return $this->load($siteId, $userId)->toJson();
    }

    public function bag(string $siteId, string $userId): UserAttributeBag
    {
        return $this->load($siteId, $userId);
    }

    public function exists(string $siteId, string $userId, string $key): bool
    {
        try {
            $cached = $this->cache->get(md5($siteId . $userId));
            $decodedValue = json_decode(json: $cached, associative: true)[$key];

            if (is_string($cached) && $cached !== '' && isset($decodedValue)) {
                return true;
            }
        } catch (Throwable) {
        }

        return $this->repository->exists($siteId, $userId);
    }

    public function create(UserAttributeBag $attribute): void
    {
        $this->repository->create($attribute);

        $this->warmWith($attribute);
    }

    public function createIfMissing(string $siteId, string $userId): ?UserAttributeBag
    {
        $existing = $this->repository->exists($siteId, $userId);

        if (!$existing) {
            $attribute = UserAttributeBag::empty($siteId, $userId);

            $this->repository->create($attribute);
            $this->warmWith($attribute);

            return $attribute;
        }

        return null;
    }

    public function set(string $siteId, string $userId, string $key, mixed $value): UserAttributeBag
    {
        $updated = $this->repository->patch(
            $siteId,
            $userId,
            static fn (UserAttributeBag $attribute): UserAttributeBag => $attribute->setPath($key, $value),
        );
        $this->warmWith($updated);

        return $updated;
    }

    public function remove(string $siteId, string $userId, string $key): UserAttributeBag
    {
        $updated = $this->load($siteId, $userId)->removePath($key);

        $this->repository->save($updated);
        $this->warmWith($updated);

        return $updated;
    }

    public function delete(string $siteId, string $userId): void
    {
        $this->repository->delete($siteId, $userId);
        $this->forget($siteId, $userId);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function merge(string $siteId, string $userId, array $values): UserAttributeBag
    {
        $updated = $this->load($siteId, $userId)->merge($values);

        $this->repository->save($updated);
        $this->warmWith($updated);

        return $updated;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function mergeRecursive(string $siteId, string $userId, array $values): UserAttributeBag
    {
        $updated = $this->load($siteId, $userId)->mergeRecursive($values);

        $this->repository->save($updated);
        $this->warmWith($updated);

        return $updated;
    }

    /**
     * @param string $siteId
     * @param string $userId
     * @param string $value
     * @return UserAttributeBag
     */
    public function replaceAll(string $siteId, string $userId, string $value): UserAttributeBag
    {
        $attribute = UserAttributeBag::fromJson($siteId, $userId, $value);

        if ($this->repository->exists($siteId, $userId)) {
            $this->repository->save($attribute);
        } else {
            $this->repository->create($attribute);
        }

        $this->warmWith($attribute);

        return $attribute;
    }

    public function warm(string $siteId, string $userId): UserAttributeBag
    {
        $attribute = $this->repository->find($siteId, $userId);
        $this->warmWith($attribute);

        return $attribute;
    }

    /**
     * @param list<array{site_id:string,user_id:string}> $pairs
     * @return array<string, UserAttributeBag>
     */
    public function warmMany(array $pairs): array
    {
        $result = [];

        foreach ($pairs as $pair) {
            $attribute = $this->warm($pair['site_id'], $pair['user_id']);
            $result[$pair['site_id'] . '.' . $pair['user_id']] = $attribute;
        }

        return $result;
    }

    public function forget(string $siteId, string $userId): void
    {
        try {
            $this->cache->delete(md5($siteId . $userId));
        } catch (InvalidArgumentException) {
        }
    }

    private function load(string $siteId, string $userId): ?UserAttributeBag
    {
        try {
            $cached = $this->cache->get(md5($siteId.$userId));

            if (is_string($cached) && $cached !== '') {
                return UserAttributeBag::fromJson($siteId, $userId, $cached);
            }
        } catch (Throwable) {
        }

        $attributes = $this->repository->find($siteId, $userId);
        $this->warmWith($attributes ?? new UserAttributeBag($siteId, $userId));

        return $attributes;
    }

    private function warmWith(UserAttributeBag $attribute): void
    {
        try {
            $this->cache->set(
                md5($attribute->siteId() . $attribute->userId()),
                $attribute->toJson(),
            );
        } catch (InvalidArgumentException) {
            // Ignore cache failures.
        }
    }
}
