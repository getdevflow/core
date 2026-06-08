<?php

declare(strict_types=1);

namespace App\Domain\Page\Model;

use App\Infrastructure\Services\AttributesFactory;
use App\Infrastructure\Services\Trait\CleanAware;
use Qubus\Exception\Data\TypeException;
use Qubus\Expressive\Database;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\get_option;
use function array_key_exists;
use function is_string;
use function property_exists;
use function Qubus\Security\Helpers\purify_html;

final class Page
{
    use CleanAware;

    public ?int $id = null;
    public ?string $locale = null;
    public ?string $name = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $route = null;
    public ?string $relativeUrl = null;
    public ?string $show = null;
    public ?int $position = null;
    public ?string $type = null;
    public ?string $data = null;
    /**
     * Runtime-only custom values.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    public function __construct(protected Database $dfdb)
    {
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     */
    public function findBy(string $field, mixed $value): Page|false
    {
        if ($value === '') {
            return false;
        }

        $locale = get_option(key: 'site_locale');
        $dbField = match ($field) {
            'id' => 'p.id',
            'slug', => 't.route',
            default => null,
        };

        $data = $this->dfdb->getRow(
            $this->dfdb->prepare(
                "SELECT p.id, p.name, p.show_in_nav, p.nav_position, p.nav_type, p.data,
                t.locale, t.meta_title, t.meta_description, t.route 
                FROM {$this->dfdb->prefix}pages p
                LEFT JOIN {$this->dfdb->prefix}page_translations t 
                ON t.page_id = p.id
                WHERE {$dbField} = ? 
                AND t.locale = ?",
                [$value, $locale]
            ),
            Database::ARRAY_A
        );

        if (! is_array($data) || $data === []) {
            return false;
        }

        return $this->create($data);
    }

    /**
     * Create a new instance of Page. Optionally populating it
     * from a data array.
     *
     * @param array<string, mixed> $data
     * @return Page
     * @throws Exception
     */
    public function create(array $data = []): Page
    {
        $site = $this->__create();
        if ($data !== []) {
            $site->populate($data);
        }

        return $site;
    }

    /**
     * Create a new Page object.
     *
     * @return Page
     */
    protected function __create(): Page
    {
        return new Page($this->dfdb);
    }

    /**
     * @param array<string, mixed> $data
     * @throws Exception
     */
    public function populate(array $data = []): self
    {
        $data = $this->normalizeData($data);

        $this->id = $this->clean($data['id']);
        $this->locale = $this->clean($data['locale']);
        $this->name = $this->clean($data['name']);
        $this->title = $this->clean($data['title']);
        $this->description = $this->clean($data['description']);
        $this->route = $this->clean($data['route']);
        $this->relativeUrl = $this->clean($data['route']);
        $this->show = $this->clean($data['show']);
        $this->position = $this->clean($data['position']);
        $this->type = $this->clean($data['type']);
        $this->data = purify_html($data['data']);

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
            'id' => $data['id'] ?? null,
            'locale' => $data['locale'] ?? null,
            'name' => $data['name'] ?? null,
            'title' => $data['title'] ?? $data['meta_title'] ?? null,
            'description' => $data['description'] ?? $data['meta_description'] ?? null,
            'route' => $data['route'] ?? null,
            'relativeUrl' => $data['route'] ?? null,
            'show' => $data['show'] ?? $data['show_in_nav'] ?? null,
            'position' => $data['position'] ?? $data['nav_position'] ?? null,
            'type' => $data['type'] ?? $data['nav_type'] ?? null,
            'data' => $data['data'] ?? null,
        ];
    }

    /**
     * Magic method for checking the existence of property.
     *
     * @param string $key Page property to check if set.
     * @return bool Whether the given property is set.
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function __isset(string $key)
    {
        if (property_exists($this, $key) && $this->{$key} !== null) {
            return true;
        }

        if (array_key_exists($key, $this->attributes)) {
            return true;
        }

        if ($this->id === null) {
            return false;
        }

        return AttributesFactory::page()->exists((string) $this->id, $key);
    }

    /**
     * Retrieve the value of a property.
     *
     * @param string $key Property
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function __get(string $key): mixed
    {
        if (property_exists($this, $key)) {
            return $this->{$key};
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if ($this->id === null) {
            return null;
        }

        $value = AttributesFactory::page()->get(
            id: (string) $this->id,
            key: $key,
            default: null
        );

        return is_string($value) ? purify_html($value) : $value;
    }

    /**
     * Magic method for setting custom page fields.
     *
     * This method does not update custom fields in the page table. It only stores
     * the value on the Page instance.
     *
     * @param string $key   Page attribute key.
     * @param mixed  $value Page attribute value.
     */
    public function __set(string $key, mixed $value): void
    {
        if (property_exists($this, $key)) {
            $this->{$key} = $value === null ? null : (string) $value;
            return;
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Magic method for unsetting a certain custom field.
     *
     * @param string $key Page attribute key to unset.
     */
    public function __unset(string $key)
    {
        if (property_exists($this, $key)) {
            $this->{$key} = null;
            return;
        }

        unset($this->attributes[$key]);
    }

    /**
     * Retrieve the value of a property or attribute key.
     *
     * Retrieves from the page table.
     *
     * @param string $key Property
     * @param mixed|null $default
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->__get($key);

        return $value ?? $default;
    }

    /**
     * Determine whether a property is set.
     *
     * @param string $key Property
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function hasProp(string $key): bool
    {
        return $this->__isset($key);
    }

    /**
     * Return an array representation.
     *
     * @return array{
     *     id:int,
     *     locale:string,
     *     name:string,
     *     title:string,
     *     description:string,
     *     route:string,
     *     show:string,
     *     position:int,
     *     type:string,
     *     data:string
     * } Array representation.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'locale' => $this->locale,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'route' => $this->route,
            'relativeUrl' => $this->route,
            'show' => $this->show,
            'position' => $this->position,
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
