<?php

declare(strict_types=1);

namespace App\Domain\Site\Model;

use App\Infrastructure\Persistence\Cache\SiteCachePsr16;
use App\Infrastructure\Persistence\Database;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use App\Shared\Services\Trait\HydratorAware;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use ReflectionException;
use stdClass;

use function Qubus\Security\Helpers\esc_html;
use function Qubus\Support\Helpers\convert_array_to_object;
use function Qubus\Support\Helpers\is_null__;

/**
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string $slug
 * @property string $domain
 * @property string $mapping
 * @property string $path
 * @property string $owner
 * @property string $status
 * @property string $registered
 * @property string $modified
 */
final class Site extends stdClass
{
    use HydratorAware;

    public function __construct(protected ?Database $dfdb = null)
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
        if ('' === $value) {
            return false;
        }

        $siteId = match ($field) {
            'id', 'ID' => $value,
            'key' => SimpleCacheObjectCacheFactory::make(namespace: 'sitekey')
                    ->get(md5($value), ''),
            'slug' => SimpleCacheObjectCacheFactory::make(namespace:'siteslug')
                    ->get(md5($value), ''),
            default => false,
        };

        $dbField = match ($field) {
            'id', 'ID' => 'site_id',
            'key' => 'site_key',
            'slug' => 'site_slug',
            default => false,
        };

        $site = null;

        if ('' !== $siteId) {
            if (
                    $data = SimpleCacheObjectCacheFactory::make(namespace: 'sites')
                            ->get(md5($siteId))
            ) {
                is_array($data) ? convert_array_to_object($data) : $data;
            }
        }

        if (
                !$data = $this->dfdb->getRow(
                    $this->dfdb->prepare(
                        "SELECT * 
                            FROM {$this->dfdb->basePrefix}site 
                            WHERE $dbField = ?",
                        [
                            $value
                        ]
                    ),
                    Database::ARRAY_A
                )
        ) {
            return false;
        }

        if (!is_null__($data)) {
            $site = $this->create($data);
            SiteCachePsr16::update($site);
        }

        if (is_array($site)) {
            $site = convert_array_to_object($site);
        }

        return $site;
    }

    /**
     * Create a new instance of Site. Optionally populating it
     * from a data array.
     *
     * @param array $data
     * @return Site
     */
    public function create(array $data = []): Site
    {
        $site = $this->__create();
        if ($data) {
            $site = $this->populate($site, $data);
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
        return new Site();
    }

    public function populate(Site $site, array $data = []): self
    {
        $site->id = esc_html(string: $data['site_id']) ?? null;
        $site->key = esc_html(string: $data['site_key']) ?? null;
        $site->name = esc_html(string: $data['site_name']) ?? null;
        $site->slug = esc_html(string: $data['site_slug']) ?? null;
        $site->domain = esc_html(string: $data['site_domain']) ?? null;
        $site->mapping = isset($data['site_mapping']) ? esc_html(string: $data['site_mapping']) : null;
        $site->path = esc_html(string: $data['site_path']) ?? null;
        $site->owner = esc_html(string: $data['site_owner']) ?? null;
        $site->status = esc_html(string: $data['site_status']) ?? null;
        $site->registered = esc_html(string: $data['site_registered']) ?? null;
        $site->modified = isset($data['site_modified']) ? esc_html(string: $data['site_modified']) : null;

        return $site;
    }

    /**
     * Magic method for checking the existence of property.
     *
     * @param string $key Site property to check if set.
     * @return bool Whether the given property is set.
     */
    public function __isset(string $key)
    {
        if (isset($this->{$key})) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve the value of a property.
     *
     * @param string $key Property
     * @return string
     */
    public function get(string $key): string
    {
        return $this->{$key} ?? '';
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
     * @return array Array representation.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
