<?php

declare(strict_types=1);

namespace App\Domain\Site\Model;

use App\Infrastructure\Persistence\Cache\SiteCachePsr16;
use App\Infrastructure\Services\Trait\CleanAware;
use Qubus\Exception\Data\TypeException;
use Qubus\Expressive\Database;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use ReflectionException;

final class Site
{
    use CleanAware;

    public ?string $id = null;
    public ?string $key = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $domain = null;
    public ?string $mapping = null;
    public ?string $path = null;
    public ?string $owner = null;
    public ?string $status = null;
    public ?string $registered = null;
    public ?string $modified = null;

    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     */
    public function findBy(string $field, string $value): Site|false
    {
        if ($value === '') {
            return false;
        }

        $field = strtolower($field);

        $dbField = match ($field) {
            'id' => 'site_id',
            'key' => 'site_key',
            'slug' => 'site_slug',
            default => null,
        };

        if ($dbField === null) {
            return false;
        }

        $siteId = $this->resolveCachedSiteId($field, $value);
        if ($siteId !== null && $siteId !== '') {
            $cached = SimpleCacheObjectCacheFactory::make(namespace: 'sites')
                ->get(md5($siteId));

            if (is_array($cached)) {
                return $this->create($cached);
            }

            if ($cached instanceof self) {
                return $cached;
            }

            $dbField = 'site_id';
            $value = $siteId;
        }

        $data = $this->dfdb->getRow(
            $this->dfdb->prepare(
                sprintf(
                    "SELECT *
                     FROM {$this->dfdb->basePrefix}site
                     WHERE %s = ?",
                    $dbField
                ),
                [$value]
            ),
            Database::ARRAY_A
        );

        if (! is_array($data) || $data === []) {
            return false;
        }

        $site = $this->create($data);

        SiteCachePsr16::update($site);

        return $site;
    }

    /**
     * @param string $field
     * @param string $value
     * @return string|null
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    private function resolveCachedSiteId(string $field, string $value): ?string
    {
        return match ($field) {
            'id' => $value,

            'key' => SimpleCacheObjectCacheFactory::make(namespace: 'sitekey')
                    ->get(md5($value), null),

            'slug' => SimpleCacheObjectCacheFactory::make(namespace: 'siteslug')
                    ->get(md5($value), null),

            default => null,
        };
    }

    /**
     * Create a new instance of Site. Optionally populating it
     * from a data array.
     *
     * @param array<string, mixed> $data
     * @return Site
     * @throws Exception
     */
    public function create(array $data = []): Site
    {
        $site = $this->__create();
        if ($data !== []) {
            $site->populate($data);
        }

        return $site;
    }

    /**
     * Create a new Site object.
     *
     * @return Site
     */
    protected function __create(): Site
    {
        return new Site($this->dfdb);
    }

    /**
     * @param array<string, mixed> $data
     * @throws Exception
     */
    public function populate(array $data = []): self
    {
        $data = $this->normalizeData($data);

        $this->id = $this->clean($data['id']);
        $this->key = $this->clean($data['key']);
        $this->name = $this->clean($data['name']);
        $this->slug = $this->clean($data['slug']);
        $this->domain = $this->clean($data['domain']);
        $this->mapping = $this->clean($data['mapping']);
        $this->path = $this->clean($data['path']);
        $this->owner = $this->clean($data['owner']);
        $this->status = $this->clean($data['status']);
        $this->registered = $this->clean($data['registered']);
        $this->modified = $this->clean($data['modified']);

        return $this;
    }

    /**
     * Accepts both DB-shaped rows and cache/object-shaped arrays.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        return [
            'id' => $data['id'] ?? $data['site_id'] ?? null,
            'key' => $data['key'] ?? $data['site_key'] ?? null,
            'name' => $data['name'] ?? $data['site_name'] ?? null,
            'slug' => $data['slug'] ?? $data['site_slug'] ?? null,
            'domain' => $data['domain'] ?? $data['site_domain'] ?? null,
            'mapping' => $data['mapping'] ?? $data['site_mapping'] ?? null,
            'path' => $data['path'] ?? $data['site_path'] ?? null,
            'owner' => $data['owner'] ?? $data['site_owner'] ?? null,
            'status' => $data['status'] ?? $data['site_status'] ?? null,
            'registered' => $data['registered'] ?? $data['site_registered'] ?? null,
            'modified' => $data['modified'] ?? $data['site_modified'] ?? null,
        ];
    }

    /**
     * Magic method for checking the existence of property.
     *
     * @param string $key Site property to check if set.
     * @return bool Whether the given property is set.
     */
    public function __isset(string $key)
    {
        return property_exists($this, $key) && $this->{$key} !== null;
    }

    /**
     * Retrieve the value of a property.
     *
     * @param string $key Property
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = ''): mixed
    {
        if (! property_exists($this, $key)) {
            return $default;
        }

        return $this->{$key} ?? $default;
    }

    /**
     * Determine whether a property is set.
     *
     * @param string $key Property
     * @return bool
     */
    public function hasProp(string $key): bool
    {
        return $this->__isset($key);
    }

    /**
     * Return an array representation.
     *
     * @return array{
     *     id:string,
     *     key:string,
     *     name:string,
     *     domain:string,
     *     mapping:string,
     *     path:string,
     *     owner:string,
     *     status:string,
     *     registered:string,
     *     modified:string
     * } Array representation.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'mapping' => $this->mapping,
            'path' => $this->path,
            'owner' => $this->owner,
            'status' => $this->status,
            'registered' => $this->registered,
            'modified' => $this->modified,
        ];
    }
}
