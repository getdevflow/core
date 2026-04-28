<?php

declare(strict_types=1);

namespace App\Domain\Content\Model;

use App\Infrastructure\Persistence\Cache\ContentCachePsr16;
use Qubus\Expressive\Database;
use App\Infrastructure\Services\AttributesFactory;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function Codefy\Framework\Helpers\config;
use function md5;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\purify_html;

final class Content
{
    public ?string $id = null;
    public ?string $title = null;
    public ?string $slug = null;
    public ?string $body = null;
    public ?string $author = null;
    public ?string $type = null;
    public ?string $parent = null;
    public ?string $sidebar = null;
    public ?string $showInMenu = null;
    public ?string $showInSearch = null;
    public ?string $relativeUrl = null;
    public ?string $featuredImage = null;
    public ?string $status = null;
    public ?string $created = null;
    public ?string $createdGmt = null;
    public ?string $published = null;
    public ?string $publishedGmt = null;
    public ?string $modified = null;
    public ?string $modifiedGmt = null;

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
     * Return only the main user fields.
     *
     * @param string $field The field to query against: 'id', 'ID', 'slug' or 'type'.
     * @param string $value The field value
     * @return Content|false Raw content object
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     */
    public function findBy(string $field, string $value): Content|false
    {
        if ($value === '') {
            return false;
        }

        $field = strtolower($field);

        $dbField = match ($field) {
            'id' => 'content_id',
            'owner', 'author' => 'content_author',
            'slug' => 'content_slug',
            'type' => 'content_type',
            default => null,
        };

        if ($dbField === null) {
            return false;
        }

        $contentId = $this->resolveCachedContentId($field, $value);
        if ($contentId !== null && $contentId !== '') {
            $cached = SimpleCacheObjectCacheFactory::make(
                    namespace: $this->dfdb->prefix . 'content'
            )->get(md5($contentId));

            if (is_array($cached)) {
                return $this->create($cached);
            }

            if ($cached instanceof self) {
                return $cached;
            }

            $dbField = 'content_id';
            $value = $contentId;
        }

        $data = $this->dfdb->getRow(
            $this->dfdb->prepare(
                sprintf(
                    "SELECT *
                     FROM {$this->dfdb->prefix}content
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

        $content = $this->create($data);

        ContentCachePsr16::update($content);

        return $content;
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
    private function resolveCachedContentId(string $field, string $value): ?string
    {
        return match ($field) {
            'id' => $value,

            'owner', 'author' => SimpleCacheObjectCacheFactory::make(
                    namespace: $this->dfdb->prefix . 'contentauthor'
            )->get(md5($value), null),

            'slug' => SimpleCacheObjectCacheFactory::make(
                    namespace: $this->dfdb->prefix . 'contentslug'
            )->get(md5($value), null),

            'type' => SimpleCacheObjectCacheFactory::make(
                    namespace: $this->dfdb->prefix . 'contenttype'
            )->get(md5($value), null),

            default => null,
        };
    }

    /**
     * Create a new instance of Content. Optionally populating it
     * from a data array.
     *
     * @param array $data
     * @return Content
     * @throws TypeException
     * @throws Exception
     */
    public function create(array $data = []): self
    {
        $content = $this->__create();

        if ($data !== []) {
            $content->populate($data);
        }

        return $content;
    }

    /**
     * Create a new Content object.
     *
     * @return Content
     */
    protected function __create(): Content
    {
        return new Content($this->dfdb);
    }

    /**
     * @param array<string, mixed> $data
     * @throws TypeException
     * @throws Exception
     */
    public function populate(array $data = []): self
    {
        $data = $this->normalizeData($data);

        $this->id = $this->clean($data['id']);
        $this->title = $this->clean($data['title']);
        $this->slug = $this->clean($data['slug']);
        $this->body = $data['body'] !== null ? purify_html((string) $data['body']) : null;
        $this->author = $this->clean($data['author']);
        $this->type = $this->clean($data['type']);
        $this->parent = $this->clean($data['parent']);
        $this->sidebar = $this->clean($data['sidebar']);
        $this->showInMenu = $this->clean($data['showInMenu']);
        $this->showInSearch = $this->clean($data['showInSearch']);
        $this->featuredImage = $this->clean($data['featuredImage']);
        $this->status = $this->clean($data['status']);
        $this->created = $this->clean($data['created']);
        $this->createdGmt = $this->clean($data['createdGmt']);
        $this->published = $this->clean($data['published']);
        $this->publishedGmt = $this->clean($data['publishedGmt']);
        $this->modified = $this->clean($data['modified']);
        $this->modifiedGmt = $this->clean($data['modifiedGmt']);
        $this->relativeUrl = $this->makeRelativeUrl();

        return $this;
    }

    /**
     * Accepts both database-shaped rows and cache/object-shaped arrays.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        return [
            'id' => $data['id'] ?? $data['content_id'] ?? null,
            'title' => $data['title'] ?? $data['content_title'] ?? null,
            'slug' => $data['slug'] ?? $data['content_slug'] ?? null,
            'body' => $data['body'] ?? $data['content_body'] ?? null,
            'author' => $data['author'] ?? $data['content_author'] ?? null,
            'type' => $data['type'] ?? $data['content_type'] ?? null,
            'parent' => $data['parent'] ?? $data['content_parent'] ?? null,
            'sidebar' => $data['sidebar'] ?? $data['content_sidebar'] ?? null,
            'showInMenu' => $data['showInMenu'] ?? $data['content_show_in_menu'] ?? null,
            'showInSearch' => $data['showInSearch'] ?? $data['content_show_in_search'] ?? null,
            'relativeUrl' => $data['relativeUrl'] ?? $data['content_relative_url'] ?? null,
            'featuredImage' => $data['featuredImage'] ?? $data['content_featured_image'] ?? null,
            'status' => $data['status'] ?? $data['content_status'] ?? null,
            'created' => $data['created'] ?? $data['content_created'] ?? null,
            'createdGmt' => $data['createdGmt'] ?? $data['content_created_gmt'] ?? null,
            'published' => $data['published'] ?? $data['content_published'] ?? null,
            'publishedGmt' => $data['publishedGmt'] ?? $data['content_published_gmt'] ?? null,
            'modified' => $data['modified'] ?? $data['content_modified'] ?? null,
            'modifiedGmt' => $data['modifiedGmt'] ?? $data['content_modified_gmt'] ?? null,
        ];
    }

    /**
     * @throws Exception
     */
    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return esc_html((string) $value);
    }

    /**
     * @throws Exception
     * @throws TypeException
     */
    private function makeRelativeUrl(): ?string
    {
        if ($this->slug === null || $this->slug === '') {
            return null;
        }

        if (config()->string(key: 'cms.relative_url') === 'contenttype' && $this->type !== null && $this->type !== '') {
            return esc_html($this->type . '/' . $this->slug . '/');
        }

        return esc_html($this->slug . '/');
    }

    /**
     * Magic method for checking the existence of a certain custom field.
     *
     * @param string $key Content attribute key to check if set.
     * @return bool Whether the given content attribute key is set.
     * @throws ContainerExceptionInterface
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

        return AttributesFactory::content()->exists($this->id, $key);
    }

    /**
     * Magic method for accessing custom fields.
     *
     * @param string $key Content attribute key to retrieve.
     * @return string Value of the given content attribute key (if set). If `$key` is 'id', the content ID.
     * @throws ContainerExceptionInterface
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

        $value = AttributesFactory::content()->get(
            id: $this->id,
            key: $key,
            default: null
        );

        return is_string($value) ? purify_html($value) : $value;
    }

    /**
     * Magic method for setting custom content fields.
     *
     * This method does not update custom fields in the content document. It only stores
     * the value on the Content instance.
     *
     * @param string $key   Content attribute key.
     * @param mixed  $value Content attribute value.
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
     * @param string $key Content attribute key to unset.
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
     * Retrieves from the content table.
     *
     * @param string $key Property
     * @return string
     * @throws ContainerExceptionInterface
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
     * Determine whether a property or attribute key is set
     *
     * Consults the content table.
     *
     * @param string $key Property
     * @return bool
     * @throws ContainerExceptionInterface
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
     * @return array Array representation.
     */
    public function toArray(bool $includeAttributes = true): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'author' => $this->author,
            'type' => $this->type,
            'parent' => $this->parent,
            'sidebar' => $this->sidebar,
            'showInMenu' => $this->showInMenu,
            'showInSearch' => $this->showInSearch,
            'relativeUrl' => $this->relativeUrl,
            'featuredImage' => $this->featuredImage,
            'status' => $this->status,
            'created' => $this->created,
            'createdGmt' => $this->createdGmt,
            'published' => $this->published,
            'publishedGmt' => $this->publishedGmt,
            'modified' => $this->modified,
            'modifiedGmt' => $this->modifiedGmt,
        ];

        if ($includeAttributes) {
            $data['attributes'] = $this->attributes;
        }

        return $data;
    }
}
