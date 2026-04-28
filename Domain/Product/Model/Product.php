<?php

declare(strict_types=1);

namespace App\Domain\Product\Model;

use App\Infrastructure\Persistence\Cache\ProductCachePsr16;
use App\Infrastructure\Services\AttributesFactory;
use Qubus\Exception\Data\TypeException;
use Qubus\Expressive\Database;
use App\Shared\Services\SimpleCacheObjectCacheFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Exception;
use ReflectionException;
use stdClass;

use function md5;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\purify_html;
use function Qubus\Support\Helpers\convert_array_to_object;
use function Qubus\Support\Helpers\is_null__;

final class Product extends stdClass
{
    public ?string $id = null;
    public ?string $title = null;
    public ?string $slug = null;
    public ?string $body = null;
    public ?string $author = null;
    public ?string $sku = null;
    public ?string $price = null;
    public ?string $currency = null;
    public ?string $purchaseUrl = null;
    public ?string $showInMenu = null;
    public ?string $showInSearch = null;
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
     * Return only the main product fields.
     *
     * @param string $field The field to query against: 'id', 'owner|author', 'slug' or 'sku'.
     * @param string $value The field value
     * @return Product|false Raw product object
     * @throws Exception
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     */
    public function findBy(string $field, string $value): Product|false
    {
        if ($value === '') {
            return false;
        }

        $field = strtolower($field);

        $dbField = match ($field) {
            'id' => 'product_id',
            'owner', 'author' => 'product_author',
            'slug' => 'product_slug',
            'sku' => 'product_sku',
            default => null,
        };

        if ($dbField === null) {
            return false;
        }

        $productId = $this->resolveCachedProductId($field, $value);

        if ($productId !== null && $productId !== '') {
            $cached = SimpleCacheObjectCacheFactory::make(
                    namespace: $this->dfdb->prefix . 'products'
            )->get(md5($productId));

            if (is_array($cached)) {
                return $this->create($cached);
            }

            if ($cached instanceof self) {
                return $cached;
            }

            $dbField = 'product_id';
            $value = $productId;
        }

        $data = $this->dfdb->getRow(
            $this->dfdb->prepare(
                sprintf(
                    "SELECT *
                     FROM {$this->dfdb->prefix}product
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

        $product = $this->create($data);

        ProductCachePsr16::update($product);

        return $product;
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
    private function resolveCachedProductId(string $field, string $value): ?string
    {
        return match ($field) {
            'id' => $value,

            'owner', 'author' => SimpleCacheObjectCacheFactory::make(
                    namespace: $this->dfdb->prefix . 'productauthor'
            )->get(md5($value), null),

            'slug' => SimpleCacheObjectCacheFactory::make(
                    namespace: $this->dfdb->prefix . 'productslug'
            )->get(md5($value), null),

            'sku' => SimpleCacheObjectCacheFactory::make(
                    namespace: $this->dfdb->prefix . 'productsku'
            )->get(md5($value), null),

            default => null,
        };
    }

    /**
     * Create a new instance of Content. Optionally populating it
     * from a data array.
     *
     * @param array<string, mixed> $data
     * @return Product
     * @throws Exception
     */
    public function create(array $data = []): Product
    {
        $product = $this->__create();

        if ($data !== []) {
            $product->populate($data);
        }

        return $product;
    }

    /**
     * Create a new Content object.
     *
     * @return Product
     */
    protected function __create(): Product
    {
        return new Product($this->dfdb);
    }

    /**
     * @param array<string, mixed> $data
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
        $this->sku = $this->clean($data['sku']);
        $this->price = $this->clean($data['price']);
        $this->currency = $this->clean($data['currency']);
        $this->purchaseUrl = $this->clean($data['purchaseUrl']);
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
            'id' => $data['id'] ?? $data['product_id'] ?? null,
            'title' => $data['title'] ?? $data['product_title'] ?? null,
            'slug' => $data['slug'] ?? $data['product_slug'] ?? null,
            'body' => $data['body'] ?? $data['product_body'] ?? null,
            'author' => $data['author'] ?? $data['product_author'] ?? null,
            'sku' => $data['sku'] ?? $data['product_sku'] ?? null,
            'price' => $data['price'] ?? $data['product_price'] ?? null,
            'currency' => $data['currency'] ?? $data['product_currency'] ?? null,
            'purchaseUrl' => $data['purchaseUrl'] ?? $data['product_purchase_url'] ?? null,
            'showInMenu' => $data['showInMenu'] ?? $data['product_show_in_menu'] ?? null,
            'showInSearch' => $data['showInSearch'] ?? $data['product_show_in_search'] ?? null,
            'featuredImage' => $data['featuredImage'] ?? $data['product_featured_image'] ?? null,
            'status' => $data['status'] ?? $data['product_status'] ?? null,
            'created' => $data['created'] ?? $data['product_created'] ?? null,
            'createdGmt' => $data['createdGmt'] ?? $data['product_created_gmt'] ?? null,
            'published' => $data['published'] ?? $data['product_published'] ?? null,
            'publishedGmt' => $data['publishedGmt'] ?? $data['product_published_gmt'] ?? null,
            'modified' => $data['modified'] ?? $data['product_modified'] ?? null,
            'modifiedGmt' => $data['modifiedGmt'] ?? $data['product_modified_gmt'] ?? null,
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
     * Magic method for checking the existence of a certain custom field.
     *
     * @param string $key Content attribute key to check if set.
     * @return bool Whether the given product attribute key is set.
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

        return AttributesFactory::product()->exists($this->id, $key);
    }

    /**
     * Magic method for accessing custom fields.
     *
     * @param string $key Content attribute key to retrieve.
     * @return string Value of the given product attribute key (if set). If `$key` is 'id', the product ID.
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

        $value = AttributesFactory::product()->get(
            id: $this->id,
            key: $key,
            default: null
        );

        return is_string($value) ? purify_html($value) : $value;
    }

    /**
     * Magic method for setting custom product fields.
     *
     * This method does not update custom fields in the product table. It only stores
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
     * Retrieves from the product table.
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
     * Determine whether a property or attribute key is set.
     *
     * @param string $key Property
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function hasProp(string $key): bool
    {
        return $this->__isset($key);
    }

    /**
     * Return an array representation.
     *
     * @return array<string, mixed> Array representation.
     */
    public function toArray(bool $includeAttributes = true): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'author' => $this->author,
            'sku' => $this->sku,
            'price' => $this->price,
            'currency' => $this->currency,
            'purchaseUrl' => $this->purchaseUrl,
            'showInMenu' => $this->showInMenu,
            'showInSearch' => $this->showInSearch,
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
