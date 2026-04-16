<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Domain\Product\Command\DeleteProductCommand;
use App\Domain\Product\Command\UpdateProductStatusCommand;
use App\Domain\Product\Query\FindProductsQuery;
use App\Domain\Product\Command\CreateProductCommand;
use App\Domain\Product\Command\UpdateProductCommand;
use App\Domain\Product\Model\Product;
use App\Domain\Product\ProductError;
use App\Domain\Product\ValueObject\ProductId;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Cache\ProductCachePsr16;
use App\Infrastructure\Services\Attribute\AttributeBag;
use App\Infrastructure\Services\AttributesFactory;
use App\Shared\Services\DateTime;
use App\Shared\Services\Sanitizer;
use App\Shared\Services\Utils;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\CommandBus\Exceptions\CommandCouldNotBeHandledException;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Factory\FileLoggerFactory;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Error\Error;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Money\Currency;
use Qubus\ValueObjects\Money\CurrencyCode;
use Qubus\ValueObjects\Money\Money;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;

use function array_map;
use function Codefy\Framework\Helpers\ask;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\config;
use function is_array;
use function preg_split;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\esc_html__;
use function Qubus\Security\Helpers\unslash;
use function Qubus\Support\Helpers\concat_ws;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function Qubus\Support\Helpers\php_like;
use function str_replace;

/**
 * Retrieve all the products regardless of status.
 *
 * @return array
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_products(): array
{
    return ask(new FindProductsQuery());
}

/**
 * Retrieve all products or a product based on filters.
 *
 * @file core/Shared/Helpers/product.php
 * @param string|null $productSku Product sku.
 * @param int $limit Number of products to show.
 * @param int|null $offset The offset of the first row to be returned.
 * @param string $status Returned unescaped product based on status (all, draft, published, pending, archived)
 * @return array Array of published products or product by particular sku.
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_all_products_with_filters(
    ?string $productSku = null,
    int $limit = 0,
    ?int $offset = null,
    string $status = 'all'
): array {
    $query = new FindProductsQuery([
        'sku' => $productSku,
        'limit' => $limit,
        'offset' => $offset,
        'status' => $status,
    ]);

    return ask($query);
}

/**
 * Retrieve product by a given field from the product table.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $field The field to retrieve the product with
 *                      (id = product_id, sku = product_sku, slug = product_slug).
 * @param string $value A value for $field (product_id, product_sku, product_slug).
 * @return false|object
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_by(string $field, string $value): false|object
{
    /** @var Product $product */
    $product = Devflow::$PHP->make(name: Product::class);
    $productdata = $product->findBy($field, $value);

    if (is_false__($productdata)) {
        return false;
    }

    return $productdata;
}

/**
 * Retrieve product by the product id.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId
 * @return false|object
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_by_id(string $productId): object|false
{
    return get_product_by('id', $productId);
}

/**
 * A function which retrieves product datetime.
 *
 * Purpose of this function is for the `product.datetime`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string|null $product
 * @return string Product datetime.
 * @throws Exception
 */
function get_product_datetime(?string $product = null): string
{
    $datetime = concat_ws(
        get_product_date('published', $product),
        get_product_time('published', $product),
        ' ',
    );
    /**
     * Filters the product's datetime.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $datetime  The product's datetime.
     * @param string $productId Product id or product object.
     */
    return __observer()->filter->applyFilter('product.datetime', $datetime, $product);
}

/**
 * A function which retrieves product modified datetime.
 *
 * Purpose of this function is for the `product.modified`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @return string Product modified datetime or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_modified(string $productId): string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $format = get_user_datetime_format();

    $modified = get_user_datetime($product->modifiedGmt, $format);

    /**
     * Filters the product date.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $modified The product's modified datetime.
     * @param string $format   Format to return datetime string.
     * @param string $productId Product id or product object.
     */
    return __observer()->filter->applyFilter('product.modified', $modified, $format, $product);
}

/**
 * A function which retrieves a product body.
 *
 * Purpose of this function is for the `product.body`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @return string Product body or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_body(string $productId): string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $body = $product->body;
    /**
     * Filters the product date.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $body The product's body content.
     * @param string $productId Product id or product object.
     */
    return __observer()->filter->applyFilter('product.body', $body, $productId);
}

/**
 * A function which retrieves a product product_type name.
 *
 * Purpose of this function is for the `product.sku`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @return string|false Product type name or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_sku(string $productId): false|string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $sku = $product->sku;
    /**
     * Filters the product product_sku name.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $sku The product's sku.
     * @param string $productId  Product id.
     */
    return __observer()->filter->applyFilter('product.sku', $sku, $productId);
}

/**
 * A function which retrieves a product title.
 *
 * Purpose of this function is for the `product.title`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @return string Product title or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_title(string $productId): string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $title = $product->title;
    /**
     * Filters the product title.
     *
     * @file core/Shared/Helpers/product.php
     * @param string   $title The product's title.
     * @param string $product  Product object.
     */
    return __observer()->filter->applyFilter('product.title', $title, $product);
}

/**
 * A function which retrieves a product slug.
 *
 * Purpose of this function is for the `product.slug`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @return string Product slug or ''.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_slug(string $productId): string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $slug = $product->slug;
    /**
     * Filters the product's slug.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $slug The product's slug.
     * @param string $product   Product object.
     */
    return __observer()->filter->applyFilter('product.slug', $slug, $product);
}

/**
 * Adds label to product's status.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $status
 * @return string Product status label.
 */
function product_status_label(string $status): string
{
    $label = [
        'published' => 'label-success',
        'scheduled' => 'label-primary',
        'draft' => 'label-warning',
        'pending' => 'label-default',
        'archived' => 'label-danger'
    ];

    return $label[$status];
}

/**
 * Retrieve product attribute for a product.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product ID.
 * @param string $key The attribute key to retrieve.
 * @param mixed $default Optional. Whether to return a single value. Default false.
 * @return mixed Attribute value.
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_attribute(string $productId, string $key, mixed $default = null): mixed
{
    return AttributesFactory::product()->get(id: $productId, key: $key, default: $default);
}

/**
 * Update product attribute based on product ID.
 *
 * If the attribute for the product does not exist, it will be added.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product ID.
 * @param string $key Attribute key.
 * @param mixed $value Attribute value. Must be serializable if non-scalar.
 * @return AttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function update_product_attribute(
    string $productId,
    string $key,
    mixed $value,
): AttributeBag {
    return AttributesFactory::product()->set(id: $productId, key: $key, value: $value);
}

/**
 * Add attribute to a product.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product ID.
 * @param string $key Attribute name.
 * @param mixed $value Attribute value. Must be serializable if non-scalar.
 * @return AttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function add_product_attribute(string $productId, string $key, mixed $value): AttributeBag
{
    return AttributesFactory::product()->set(id: $productId, key: $key, value: $value);
}

/**
 * Remove attribute matching criteria from a product.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product ID.
 * @param string $key Attribute name.
 * @return AttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function delete_product_attribute(string $productId, string $key): AttributeBag
{
    return AttributesFactory::product()->remove(id: $productId, key: $key);
}

/**
 * A function which retrieves a product author id.
 *
 * Purpose of this function is for the `product.author.id`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @return false|string Product author id or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_author_id(string $productId): false|string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $authorId = $product->author;
    /**
     * Filters the product author id.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $authorId The product's author id.
     * @param object $product Product object.
     */
    return __observer()->filter->applyFilter('product.author.id', $authorId, $product);
}

/**
 * A function which retrieves a product author.
 *
 * Purpose of this function is for the `product.author`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Optional Product id or product object.
 * @param bool $reverse If first name should appear first or not. Default is false.
 * @return string|false Product author or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_author(string $productId, bool $reverse = false): false|string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return false;
    }

    $author = get_name($product->author, $reverse);
    /**
     * Filters the product author.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $author The product's author.
     * @param object $product Product object.
     */
    return __observer()->filter->applyFilter('product.author', $author, $product);
}

/**
 * A function which retrieves a product status.
 *
 * Purpose of this function is for the `product.status`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @return string|false Product status or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_status(string $productId): false|string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return false;
    }

    $status = $product->status;
    /**
     * Filters the product status.
     *
     * @file core/Shared/Helpers/product.php
     * @param string  $status The product's status.
     * @param Product $product Product object.
     */
    return __observer()->filter->applyFilter('product.status', $status, $product);
}

/**
 * A function which retrieves product date.
 *
 * Uses `call_user_func_array()` function to return appropriate product date function.
 * Dynamic part is the variable $type, which calls the date function you need.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $type Type of date to return: created, published, modified. Default: published.
 * @param string $productId Product id.
 * @return string Product date.
 */
function get_product_date(string $type = 'published', string $productId = ''): string
{
    return call_user_func_array("App\\Shared\\Helpers\\the_product_{$type}_date", ['Y-m-d',&$productId]);
}

/**
 * A function which retrieves product time.
 *
 * Uses `call_user_func_array()` function to return appropriate product time function.
 * Dynamic part is the variable $type, which calls the date function you need.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $type Type of date to return: created, published, modified. Default: published.
 * @param string $productId Product id.
 * @return string Product time.
 */
function get_product_time(string $type = 'published', string $productId = ''): string
{
    return call_user_func_array("App\\Shared\\Helpers\\the_product_{$type}_time", ['h:i A',&$productId]);
}

/**
 * Retrieves product created date.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the date the product was created.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted product created date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_created_date(
    string $productId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theDate = get_user_datetime($product->createdGmt);
    } else {
        $theDate = $product->created;
    }

    $theDate = $date->db2Date($format, $theDate, $translate);

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the product created date.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $theDate The product's formatted date.
     * @param bool   $format Format to use for retrieving the date the product was written.
     *                       Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt    Whether to retrieve the GMT date. Default false.
     */
    return __observer()->filter->applyFilter('get.product.created.date', $theDate, $format, $gmt);
}

/**
 * Retrieves product created date.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the date the product was created.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default empty.
 * @return string Formatted product created date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_product_created_date(string $productId, string $format = ''): string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    if ('' === $format) {
        $theDate = get_product_created_date(
            $productId,
            get_user_date_format(),
            true,
            true
        );
    } else {
        $theDate = get_product_created_date($productId, $format, true, true);
    }

    /**
     * Filters the date the product was written.
     *
     * @file core/Shared/Helpers/product.php
     * @param string    $theDate The formatted date.
     * @param string    $format  Format to use for retrieving the date the product was written.
     *                           Accepts 'G', 'U', or php date format value specified
     *                           in 'date_format' option. Default empty.
     * @param Product  $product  Product object.
     */
    return __observer()->filter->applyFilter('product.created.date', $theDate, $format, $product);
}

/**
 * A function which retrieves product created time.
 *
 * Purpose of this function is for the `get.product.created.time`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the time the product was created.
 *                        Accepts 'G', 'U', or php date format value specified
 *                        in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted product created time string or Unix timestamp
 *                          if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_created_time(
    string $productId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theTime = get_user_datetime($product->createdGmt);
    } else {
        $theTime = $product->created;
    }

    $theTime = $date->db2Date($format, $theTime, $translate);

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the product created time.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $theTime The product's formatted time.
     * @param bool   $format   Format to use for retrieving the time the product was written.
     *                         Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt      Whether to retrieve the GMT time. Default false.
     */
    return __observer()->filter->applyFilter('get.product.created.time', $theTime, $format, $gmt);
}

/**
 * Retrieves product created time.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the time the product was written.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'time_format' option. Default empty.
 * @return string Formatted product created time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_product_created_time(string $productId, string $format = ''): string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    if ('' === $format) {
        $theTime = get_product_created_time(
            $productId,
            get_user_time_format(),
            true,
            true
        );
    } else {
        $theTime = get_product_created_time($productId, $format, true, true);
    }

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the time the product was written.
     *
     * @file core/Shared/Helpers/product.php
     * @param string    $theTime The formatted time.
     * @param string    $format  Format to use for retrieving the time the product was written.
     *                           Accepts 'G', 'U', or php date format value specified
     *                           in 'time_format' option. Default empty.
     * @param object    $product Product object.
     */
    return __observer()->filter->applyFilter('product.created.time', $theTime, $format, $product);
}

/**
 * A function which retrieves product published date.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the date the product was published.
 *                        Accepts 'G', 'U', or php date format value specified
 *                        in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted product published date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_published_date(
    string $productId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theDate = get_user_datetime($product->publishedGmt);
    } else {
        $theDate = $product->published;
    }

    $theDate = $date->db2Date($format, $theDate, $translate);

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the product published date.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $theDate The product's formatted date.
     * @param bool $format Format to use for retrieving the date the product was published.
     *                     Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt  Whether to retrieve the GMT date. Default false.
     */
    return __observer()->filter->applyFilter('get.product.published.date', $theDate, $format, $gmt);
}

/**
 * Retrieves product published date.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the date the product was published.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default empty.
 * @return string Formatted product published date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_product_published_date(string $productId, string $format = ''): string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    if ('' === $format) {
        $theDate = get_product_published_date(
            $productId,
            get_user_date_format(),
            true,
            true
        );
    } else {
        $theDate = get_product_published_date($productId, $format, true, true);
    }

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the time the product was written.
     *
     * @file core/Shared/Helpers/product.php
     * @param string    $theDate The formatted date.
     * @param string    $format   Format to use for retrieving the date the product was published.
     *                            Accepts 'G', 'U', or php date format value specified
     *                            in 'date_format' option. Default empty.
     * @param object    $product  Product object.
     */
    return __observer()->filter->applyFilter('product.published.date', $theDate, $format, $product);
}

/**
 * A function which retrieves product published time.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the time the product was published.
 *                        Accepts 'G', 'U', or php date format value specified
 *                        in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted product published time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_published_time(
    string $productId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theTime = get_user_datetime($product->publishedGmt);
    } else {
        $theTime = $product->published;
    }

    $theTime = $date->db2Date($format, $theTime, $translate);

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the product published time.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $theTime The product's formatted time.
     * @param bool   $format   Format to use for retrieving the time the product was written.
     *                         Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt      Whether to retrieve the GMT time. Default false.
     */
    return __observer()->filter->applyFilter('get.product.published.time', $theTime, $format, $gmt);
}

/**
 * Retrieves product published time.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the time the product was published.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'time_format' option. Default empty.
 * @return string Formatted product published time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_product_published_time(string $productId, string $format = ''): string
{
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    if ('' === $format) {
        $theTime = get_product_published_time(
            $productId,
            get_user_time_format(),
            true,
            true
        );
    } else {
        $theTime = get_product_published_time($productId, $format, true, true);
    }

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the time the product was published.
     *
     * @file core/Shared/Helpers/product.php
     * @param string    $theTime  The formatted time.
     * @param string    $format   Format to use for retrieving the time the product was published.
     *                            Accepts 'G', 'U', or php date format value specified
     *                            in 'time_format' option. Default empty.
     * @param object    $product  Product object.
     */
    return __observer()->filter->applyFilter('product.published.time', $theTime, $format, $product);
}

/**
 * A function which retrieves product modified date.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the date the product was modified.
 *                        Accepts 'G', 'U', or php date format value specified
 *                        in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted product modified date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_modified_date(
    string $productId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theDate = get_user_datetime($product->modifiedGmt);
    } else {
        $theDate = $product->modified;
    }

    $theDate = $date->db2Date($format, $theDate, $translate);

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the product modified date.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $theDate The product's formatted date.
     * @param bool   $format  Format to use for retrieving the date the product was published.
     *                        Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt     Whether to retrieve the GMT date. Default false.
     */
    return __observer()->filter->applyFilter('get.product.modified.date', $theDate, $format, $gmt);
}

/**
 * Retrieves product published date.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the date the product was published.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default empty.
 * @return string Formatted product modified date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_product_modified_date(string $productId, string $format = ''): string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    if ('' === $format) {
        $theDate = get_product_modified_date(
            $productId,
            get_user_date_format(),
            true,
            true
        );
    } else {
        $theDate = get_product_modified_date($productId, $format, true, true);
    }

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the date the product was modified.
     *
     * @file core/Shared/Helpers/product.php
     * @param string    $theDate The formatted date.
     * @param string    $format  Format to use for retrieving the date the product was modified.
     *                           Accepts 'G', 'U', or php date format value specified
     *                           in 'date_format' option. Default empty.
     * @param object    $product Product object.
     */
    return __observer()->filter->applyFilter('product.modified.date', $theDate, $format, $product);
}

/**
 * A function which retrieves product modified time.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the time the product was modified.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted product modified time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_modified_time(
    string $productId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theTime = get_user_datetime($product->modifiedGmt);
    } else {
        $theTime = $product->modified;
    }

    $theTime = $date->db2Date($format, $theTime, $translate);

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the product modified time.
     *
     * @file core/Shared/Helpers/product.php
     * @param string $theTime The product's formatted time.
     * @param bool   $format   Format to use for retrieving the time the product was modified.
     *                         Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt      Whether to retrieve the GMT time. Default false.
     */
    return __observer()->filter->applyFilter('get.product.modified.time', $theTime, $format, $gmt);
}

/**
 * Retrieves product modified time.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @param string $format Format to use for retrieving the time the product was modified.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'time_format' option. Default empty.
 * @return string Formatted product modified time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_product_modified_time(string $productId, string $format = ''): string
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return '';
    }

    if ('' === $format) {
        $theTime = get_product_modified_time(
            $productId,
            get_user_time_format(),
            true,
            true
        );
    } else {
        $theTime = get_product_modified_time($productId, $format, true, true);
    }

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the time the product was modified.
     *
     * @file core/Shared/Helpers/product.php
     * @param string    $theTime The formatted time.
     * @param string    $format  Format to use for retrieving the time the product was modified.
     *                           Accepts 'G', 'U', or php date format value specified
     *                           in 'time_format' option. Default empty.
     * @param object    $product Product object.
     */
    return __observer()->filter->applyFilter('product.modified.time', $theTime, $format, $product);
}

/**
 * A function which retrieves product show in menu.
 *
 * Purpose of this function is for the `product.show.in.menu`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @return int Product show in menu integer or 0 on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_show_in_menu(string $productId): int
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return (int) 0;
    }

    $menu = $product->showInMenu;
    /**
     * Filters the product show in menu.
     *
     * @file core/Shared/Helpers/product.php
     * @param int    $menu      The product's show in menu option.
     * @param string $productId The product ID.
     */
    return __observer()->filter->applyFilter('product.show.in.menu', (int) $menu, $productId);
}

/**
 * A function which retrieves product show in search.
 *
 * Purpose of this function is for the `product.show.in.search`
 * filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id.
 * @return int Product show in search integer or 0 on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_show_in_search(string $productId): int
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return (int) 0;
    }

    $search = $product->showInSearch;
    /**
     * Filters the product show in search.
     *
     * @file core/Shared/Helpers/product.php
     * @param int    $search    The product's show in search option.
     * @param string $productId The product ID.
     */
    return __observer()->filter->applyFilter('product.show.in.search', (int) $search, $productId);
}

/**
 * Creates a unique product slug.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $originalSlug Original slug of product.
 * @param string $originalTitle Original title of product.
 * @param string|null $productId Unique product id or null.
 * @return string Unique product slug.
 * @throws Exception
 * @throws ReflectionException
 */
function cms_unique_product_slug(
    string $originalSlug,
    string $originalTitle,
    ?string $productId = null,
): string {
    if (is_null__($productId)) {
        $productSlug = cms_slugify($originalTitle, 'product');
    } elseif (if_product_slug_exists($productId, $originalSlug)) {
        $productSlug = cms_slugify($originalTitle, 'product');
    } else {
        $productSlug = $originalSlug;
    }
    /**
     * Filters the unique product slug before returned.
     *
     * @file core/Shared/Helpers/product.php
     * @param string    $productSlug   Unique product slug.
     * @param string    $originalSlug  The product's original slug.
     * @param string    $originalTitle The product's original title before slugified.
     * @param string    $productId     The product's unique id.
     */
    return __observer()->filter->applyFilter(
        'cms.unique.product.slug',
        $productSlug,
        $originalSlug,
        $originalTitle,
        $productId,
    );
}

/**
 * Insert or update a product.
 *
 * All the `$productdata` array fields have filters associated with the values. The filters
 * have the prefix 'pre.' followed by the field name. An example using 'product_status' would have
 * the filter called, 'pre.product.status' that can be hooked into.
 *
 * @file core/Shared/Helpers/product.php
 * @param array|ServerRequestInterface|Product $productdata An array of data that is used for insert or update.
 *
 *      @type string $productTitle The product's title.
 *      @type string $productBody The product's body.
 *      @type string $productSlug The product's slug.
 *      @type string $productAuthor The product's author.
 *      @type string $productSku The product's parent.
 *      @type string $productPrice The product's price.
 *      @type string $productCurrency The product's currency.
 *      @type string $productPurchaseUrl The product's purchase url.
 *      @type string $productShowInMenu Whether to show product in menu.
 *      @type string $productShowInSearch Whether to show product in search.
 *      @type string $productRelativeUrl The product's relative url.
 *      @type string $productFeaturedImage THe product's featured image.
 *      @type string $productStatus THe product's status.
 *      @type string $productPublished Timestamp describing the moment when the product
 *                                     was published. Defaults to Y-m-d h:i A.
 * @return Error|string|null The newly created product's product_id or throws an error or returns null
 *                     if the product could not be created or updated.
 * @throws CommandCouldNotBeHandledException
 * @throws CommandPropertyNotFoundException
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 * @throws UnresolvableQueryHandlerException
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 */
function cms_insert_product(array|ServerRequestInterface|Product $productdata): Error|string|null
{
    $userId = get_current_user_id();

    $defaults = [
        'title' => '',
        'body' => '',
        'author' => $userId,
        'sku' => '',
        'showInMenu' => '0',
        'showInSearch' => '0',
        'featuredImage' => '',
        'status' => 'draft'
    ];

    if ($productdata instanceof ServerRequestInterface) {
        $productdata = $productdata->getParsedBody();
    } elseif ($productdata instanceof Product) {
        $productdata = $productdata->toArray();
    }

    $productdata = Utils::parseArgs($productdata, $defaults);

    // Are we updating or creating?
    if (!empty($productdata['id']) && !is_false__(get_product_by_id($productdata['id']))) {
        $update = true;
        $productId = new ProductId($productdata['id']);
        /** @var Product $productBefore */
        $productBefore = get_product_by('id', $productId->toNative());

        if (is_false__($productBefore)) {
            return new ProductError(message: esc_html__(string: 'Invalid product id.', domain: 'devflow'));
        }

        $previousStatus = get_product_status($productId->toNative());
        /**
         * Fires immediately before product is inserted into the product document.
         *
         * @param string $previousStatus Status of the product before it is created or updated.
         * @param string $productId      The product's product_id.
         * @param bool   $update         Whether this is an existing product or a new product.
         */
        __observer()->action->doAction('product_previous_status', $previousStatus, $productId->toNative(), $update);

        /**
         * Create new product object.
         *
         * @var Product $product
         */
        $product = Devflow::$PHP->make(name: Product::class);
        $product->id = $productId->toNative();
    } else {
        $update = false;
        $productId = new ProductId();
        $previousStatus = 'new';
        /**
         * Fires immediately before a product is inserted into the product document.
         *
         * @param string $previousStatus Status of the product before it is created or updated.
         * @param string $productId      The product's product_id.
         * @param bool   $update         Whether this is an existing product or a new product.
         */
        __observer()->action->doAction('product_previous_status', $previousStatus, $productId->toNative(), $update);

        /**
         * Create new product object.
         *
         * @var Product $product
         */
        $product = Devflow::$PHP->make(name: Product::class);
        $product->id = $productId->toNative();
    }

    if (isset($productdata['title'])) {
        $productTitle = $productdata['title'];
    } else {
        /**
         * For an update, don't modify the title if it
         * wasn't supplied as an argument.
         */
        $productTitle = $productBefore->title;
    }

    $rawProductSku = $productdata['sku'];
    $sanitizedProductSku = Sanitizer::item($rawProductSku);
    /**
     * Filters a product's type before the product is created or updated.
     *
     * @param string $sanitizedProductType Product type after it has been sanitized.
     * @param string $rawProductType The product's product type.
     */
    $productSku = __observer()->filter->applyFilter(
        'pre.product.sku',
        $sanitizedProductSku,
        $rawProductSku
    );
    $product->sku = $productSku;

    $rawProductTitle = $productTitle;
    $sanitizedProductTitle = Sanitizer::item($rawProductTitle);
    /**
     * Filters a product's title before created/updated.
     *
     * @param string $sanitizedProductTitle Product title after it has been sanitized.
     * @param string $rawProductTitle The product's title.
     */
    $productTitle = __observer()->filter->applyFilter(
        'pre.product.title',
        (string) $sanitizedProductTitle,
        (string) $rawProductTitle
    );
    $product->title = $productTitle;

    if (isset($productdata['slug'])) {
        /**
         * cms_unique_product_slug will take the original slug supplied and check
         * to make sure that it is unique. If not unique, it will make it unique
         * by adding a number at the end.
         */
        $productSlug = cms_unique_product_slug(
            $productdata['slug'],
            $productTitle,
            $productId->toNative(),
        );
    } else {
        /**
         * For an update, don't modify the slug if it
         * wasn't supplied as an argument.
         */
        $productSlug = $productBefore->slug;
    }

    $rawProductSlug = $productSlug;
    $sanitizedProductSlug = Sanitizer::item($rawProductSlug);
    /**
     * Filters a product's slug before created/updated.
     *
     * @param string $sanitizedProductSlug Product slug after it has been sanitized.
     * @param string $rawProductSlug The product's slug.
     */
    $productSlug = __observer()->filter->applyFilter(
        'pre.product.slug',
        (string) $sanitizedProductSlug,
        (string) $rawProductSlug
    );
    $product->slug = $productSlug;

    $rawProductBody = $productdata['body'];
    /**
     * Filters a product's body before created/updated.
     *
     * @param string $rawProductSlug The product's slug.
     */
    $productBody = __observer()->filter->applyFilter(
        'pre.product.body',
        $rawProductBody
    );
    $product->body = $productBody;

    /**
     * Check for product author
     *
     * @param string $productAuthor Product author id.
     */
    $productAuthor = $productdata['author'];

    if ($productAuthor === '' || $productAuthor === null) {
        return new ProductError(
            message: esc_html__(string: 'Product author cannot be null or empty.', domain: 'devflow')
        );
    }

    $product->author = $productAuthor;

    $rawProductSku = $productdata['sku'];
    $sanitizedProductSku = Sanitizer::item($rawProductSku);
    /**
     * Filters a product's sku before the product is created or updated.
     *
     * @param string $sanitizedProductSku Product sku after it has been sanitized.
     * @param string $rawProductSku The product's sku.
     */
    $productSku = __observer()->filter->applyFilter(
        'pre.product.sku',
        $sanitizedProductSku,
        $rawProductSku
    );
    $product->sku = $productSku;

    $rawProductPrice = $productdata['price'];
    $sanitizedProductPrice = Sanitizer::item($rawProductPrice, 'float');
    /**
     * Filters a product's price before the product is created or updated.
     *
     * @param string $sanitizedProductPrice Product price after it has been sanitized.
     * @param string $rawProductPrice The product's price.
     */
    $productPrice = __observer()->filter->applyFilter(
        'pre.product.price',
        (int) $sanitizedProductPrice,
        (int) $rawProductPrice
    );
    $product->price = $productPrice;

    $rawProductCurrency = $productdata['currency'];
    $sanitizedProductCurrency = Sanitizer::item($rawProductCurrency);
    /**
     * Filters a product's currency before the product is created or updated.
     *
     * @param string $sanitizedProductCurrency Product currency after it has been sanitized.
     * @param string $rawProductCurrency The product's currency.
     */
    $productCurrency = __observer()->filter->applyFilter(
        'pre.product.currency',
        $sanitizedProductCurrency,
        $rawProductCurrency
    );
    $product->currency = $productCurrency;

    $rawProductPurchaseUrl = $productdata['purchaseUrl'];
    $sanitizedProductPurchaseUrl = Sanitizer::item($rawProductPurchaseUrl);
    /**
     * Filters a product's purchase url before the product is created or updated.
     *
     * @param string $sanitizedProductPurchaseUrl Product purchase url after it has been sanitized.
     * @param string $rawProductPurchaseUrl The product's purchase url.
     */
    $productPurchaseUrl = __observer()->filter->applyFilter(
        'pre.product.purchase.url',
        $sanitizedProductPurchaseUrl,
        $rawProductPurchaseUrl
    );
    $product->purchaseUrl = $productPurchaseUrl;

    $rawProductShowInMenu = $productdata['showInMenu'];
    $sanitizedProductShowInMenu = Sanitizer::item($rawProductShowInMenu, 'int');
    /**
     * Filters a product's show in menu before the product is created or updated.
     *
     * @param string $sanitizedProductShowInMenu Product show in menu after it has been sanitized.
     * @param int $rawProductShowInMenu The product's show in menu.
     */
    $productShowInMenu = __observer()->filter->applyFilter(
        'pre.product.show.in.menu',
        (int) $sanitizedProductShowInMenu,
        (int) $rawProductShowInMenu
    );
    $product->showInMenu = $productShowInMenu;

    $rawProductShowInSearch = $productdata['showInSearch'];
    $sanitizedProductShowInSearch = Sanitizer::item($rawProductShowInSearch, 'int');
    /**
     * Filters a product's show in search before the product is created or updated.
     *
     * @param int $sanitizedProductShowInSearch Product show in search after it has been sanitized.
     * @param int $rawProductShowInSearch The product's show in search.
     */
    $productShowInSearch = __observer()->filter->applyFilter(
        'pre.product.show.in.search',
        (int) $sanitizedProductShowInSearch,
        (int) $rawProductShowInSearch
    );
    $product->showInSearch = $productShowInSearch;

    $rawProductFeaturedImage = cms_optimized_image_upload($productdata['featuredImage']);
    $sanitizedProductFeaturedImage = Sanitizer::item($rawProductFeaturedImage);
    /**
     * Filters a product's featured image before the product is created or updated.
     *
     * @param string $sanitizedProductFeaturedImage Product featured image url after it has been sanitized.
     * @param string $rawProductFeaturedImage The product's featured image url.
     */
    $productFeaturedImage = __observer()->filter->applyFilter(
        'pre.product.featured.image',
        (string) $sanitizedProductFeaturedImage,
        (string) $rawProductFeaturedImage
    );
    $product->featuredImage = $productFeaturedImage;

    $rawProductStatus = $productdata['status'];
    $sanitizedProductStatus = Sanitizer::item($rawProductStatus);
    /**
     * Filters a product's status before the product is created or updated.
     *
     * @param string $sanitizedProductStatus Product status after it has been sanitized.
     * @param string $rawProductStatus The product's status.
     */
    $productStatus = __observer()->filter->applyFilter(
        'pre.product.status',
        (string) $sanitizedProductStatus,
        (string) $rawProductStatus
    );
    $product->status = $productStatus;

    /*
     * Filters whether the product is null.
     *
     * @param bool  $maybe_empty Whether the product should be considered "null".
     * @param array $productdata   Array of product data.
     */
    $maybeNull = !$productTitle && !$productBody;
    if (__observer()->filter->applyFilter('cms.insert.empty.product', $maybeNull, $productdata)) {
        return new ProductError(
            message: esc_html__(
                string: 'The title and body should not be null.',
                domain: 'devflow'
            )
        );
    }

    if (!$update) {
        if (empty($productdata['published']) || php_like('%0000-00-00 00:00', $productdata['published'])) {
            $productPublished = new DateTime('now', get_user_timezone())->getDateTime();
            $productPublishedGmt = new DateTime('now', 'GMT')->getDateTime();
            $productCreated = $productPublished;
            $productCreatedGmt = $productPublishedGmt;
        } else {
            $productPublished = new DateTime(
                str_replace(['AM', 'PM'], '', $productdata['published']),
                get_user_timezone()
            )->getDateTime();
            $productPublishedGmt = new DateTime($productdata['publishedGmt'] ?? 'now', 'GMT')->getDateTime();
            $productCreated = $productPublished;
            $productCreatedGmt = $productPublishedGmt;
        }
    } else {
        $productPublished = new DateTime(
            str_replace(['AM', 'PM'], '', $productdata['published']),
            get_user_timezone()
        )->getDateTime();
        $productPublishedGmt = new DateTime(
            $productdata['publishedGmt'] ?? str_replace(['AM', 'PM'], '', $productdata['published']),
            'GMT'
        )->getDateTime();
        $productCreated = $productPublished;
        $productCreatedGmt = $productPublishedGmt;
        $productModified = new DateTime(QubusDateTimeImmutable::now(get_user_timezone())->toDateTimeString())
                ->getDateTime();
        $productModifiedGmt = new DateTime(QubusDateTimeImmutable::now('GMT')->toDateTimeString())->getDateTime();
    }

    if (
        $productStatus !== 'scheduled' &&
            ($productPublished->format('Y-m-d H:i:s') >
                    new DateTime('now', get_user_timezone())->format())
    ) {
        $productStatus = 'scheduled';
        $product->status = $productStatus;
    }

    $dataArray = [
        'id' => $productId->toNative(),
        'slug' => $productSlug,
        'body' => $productBody,
        'author' => $productAuthor,
        'sku' => $productSku,
        'price' => (string) $productPrice,
        'currency' => $productCurrency,
        'purchaseUrl' => $productPurchaseUrl,
        'showInMenu' => (string) $productShowInMenu,
        'showInSearch' => (string) $productShowInSearch,
        'featuredImage' => $productFeaturedImage,
        'status' => $productStatus,
        'created' => (string) $productCreated,
        'createdGmt' => (string) $productCreatedGmt,
        'published' => (string) $productPublished,
        'publishedGmt' => (string) $productPublishedGmt,
    ];
    $productDataArray = unslash($dataArray);

    // Product custom fields.
    $attributeFields = $productdata['product_field'] ?? [];

    /**
     * Filters product data before the record is created or updated.
     *
     * It only includes data in the product table, not any product attributes.
     *
     * @param array $productDataArray
     *     Values and keys for the user.
     *
     *      @type string $productTitle         The product's title.
     *      @type string $productBody          The product's body.
     *      @type string $productSlug          The product's slug.
     *      @type string $productAuthor        The product's author.
     *      @type string $productSku           The product's sku.
     *      @type string $productPrice         The product's price.
     *      @type string $productCurrency      The product's currency.
     *      @type string $productPurchaseUrl   The product's purchaseUrl.
     *      @type string $productShowInMenu    Whether to show product in menu.
     *      @type string $productShowInSearch  Whether to show product in search.
     *      @type string $productFeaturedImage The product's featured image.
     *      @type string $productStatus        The product's status.
     *      @type string $productCreated       Timestamp of when the product was created.
     *                                         Defaults to Y-m-d H:i:s A.
     *      @type string $productCreatedGmt    Timestamp of when the product was created
     *                                         in GMT. Defaults to Y-m-d H:i:s A.
     *      @type string $productPublished     Timestamp describing the moment when the product
     *                                         was published. Defaults to Y-m-d H:i:s A.
     *      @type string $productPublishedGmt  Timestamp describing the moment when the product
     *                                         was published in GMT. Defaults to Y-m-d H:i:s A.
     *      @type string $productModified      Timestamp of when the product was modified.
     *                                         Defaults to Y-m-d H:i:s A.
     *      @type string $productModifiedGmt   Timestamp of when the product was modified
     *                                         in GMT. Defaults to Y-m-d H:i:s A.
     *
     * @param bool     $update Whether the product is being updated rather than created.
     * @param string|null $id  ID of the product to be updated, or NULL if the product is being created.
     */
    __observer()->filter->applyFilter(
        'cms.before.insert.product.data',
        $productDataArray,
        $update,
        $update ? $productBefore->id : $productId,
    );

    if (!$update) {
        /**
         * Fires immediately before a product is inserted into the product document.
         *
         * @param Product $product Product object.
         */
        __observer()->action->doAction('pre_product_insert', $product);

        try {
            $command = new CreateProductCommand([
                'id' => ProductId::fromString($productId->toNative()),
                'title' => new StringLiteral($productTitle),
                'slug' => new StringLiteral($productSlug),
                'body' => new StringLiteral($productBody ?? ''),
                'attribute' => new ArrayLiteral($attributeFields),
                'author' => UserId::fromString($productAuthor),
                'sku' => new StringLiteral($productSku),
                'price' => new Money(new IntegerNumber($productPrice), new Currency(CurrencyCode::$productCurrency())),
                'purchaseUrl' => new StringLiteral($productPurchaseUrl ?? ''),
                'showInMenu' => new IntegerNumber($productShowInMenu),
                'showInSearch' => new IntegerNumber($productShowInSearch),
                'featuredImage' => new StringLiteral($productFeaturedImage ?? ''),
                'status' => new StringLiteral($productStatus),
                'created' => $productCreated,
                'createdGmt' => $productCreatedGmt,
                'published' => $productPublished,
                'publishedGmt' => $productPublishedGmt,
            ]);

            command($command);
        } catch (PDOException $ex) {
            FileLoggerFactory::getLogger()->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Product Function' => 'cms_insert_product'
                ]
            );

            return new ProductError(message: esc_html__(
                string: 'Could not insert product into the product table.',
                domain: 'devflow'
            ));
        }

    } else {
        /**
         * Fires immediately before existing product is updated in the product document.
         *
         * @param string  $productId Product id.
         * @param product $product   Product object.
         */
        __observer()->action->doAction('pre_product_update', $productId, $product);

        try {
            $command = new UpdateProductCommand([
                'id' => ProductId::fromString($productId->toNative()),
                'title' => new StringLiteral($productTitle),
                'slug' => new StringLiteral($productSlug),
                'body' => new StringLiteral($productBody),
                'attribute' => new ArrayLiteral($attributeFields),
                'author' => UserId::fromString($productAuthor),
                'sku' => new StringLiteral($productSku),
                'price' => new Money(new IntegerNumber($productPrice), new Currency(CurrencyCode::$productCurrency())),
                'purchaseUrl' => new StringLiteral($productPurchaseUrl ?? ''),
                'showInMenu' => new IntegerNumber($productShowInMenu),
                'showInSearch' => new IntegerNumber($productShowInSearch),
                'featuredImage' => new StringLiteral($productFeaturedImage ?? ''),
                'status' => new StringLiteral($productStatus),
                'published' => $productPublished,
                'publishedGmt' => $productPublishedGmt,
                'modified' => $productModified,
                'modifiedGmt' => $productModifiedGmt,
            ]);

            command($command);
        } catch (PDOException $ex) {
            FileLoggerFactory::getLogger()->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Product Function' => 'cms_insert_product'
                ]
            );

            return new ProductError(message: esc_html__(
                string: 'Could not update product within the product table.',
                domain: 'devflow'
            ));
        }
    }

    /** @var Product $product */
    $product = get_product_by_id($productId->toNative());

    ProductCachePsr16::clean($product);

    if ($update) {
        /**
         * Action hook triggered after existing product has been updated.
         *
         * @param string $productId Product id.
         * @param array  $product   Product object.
         */
        __observer()->action->doAction('update_product', $productId, $product);
        /** @var Product $productAfter */
        $productAfter = get_product_by_id($productId->toNative());
        /**
         * Action hook triggered after existing product has been updated.
         *
         * @param string $productId      Product id.
         * @param object $productAfter   Product object following the update.
         * @param object $productBefore  Product object before the update.
         */
        __observer()->action->doAction('product_updated', $productId->toNative(), $productAfter, $productBefore);
    } else {
        /**
         * Action hook triggered after product is created.
         *
         * @param array $product Product object.
         */
        __observer()->action->doAction('create_product', $product);
    }

    /**
     * Action hook triggered after product has been saved.
     *
     * The dynamic portion of this hook, `$productSku`, is the product's
     * sku.
     *
     * @param string $productId The product's id.
     * @param array $product    Product object.
     * @param bool  $update     Whether this is an existing product or a new product.
     */
    __observer()->action->doAction("save_product_{$productSku}", $productId->toNative(), $product, $update);

    /**
     * Action hook triggered after product has been saved.
     *
     * The dynamic portions of this hook, `$productSku` and `$productStatus`,
     * are the product's sku and status.
     *
     * @param string $productId The product's id.
     * @param array  $product   Product object.
     * @param bool   $update    Whether this is existing product or new product.
     */
    __observer()->action->doAction(
        "save_product_{$productSku}_{$productStatus}",
        $productId->toNative(),
        $product,
        $update
    );

    /**
     * Action hook triggered after product has been saved.
     *
     * @param string $productId The product's id.
     * @param object $product   Product object.
     * @param bool   $update    Whether this is existing product or new product.
     */
    __observer()->action->doAction('cms_after_insert_product_data', $productId->toNative(), $product, $update);

    return $productId->toNative();
}

/**
 * Update a product in the product document.
 *
 * See {@see cms_insert_product()} For what fields can be set in $productdata.
 *
 * @file core/Shared/Helpers/product.php
 * @param array|ServerRequestInterface|Product $productdata An array of product data or a product object.
 * @return string|Error The updated product's id or return Error if product could not be updated.
 * @throws CommandCouldNotBeHandledException
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 * @throws UnresolvableQueryHandlerException
 */
function cms_update_product(array|ServerRequestInterface|Product $productdata): string|Error
{
    if ($productdata instanceof ServerRequestInterface) {
        $productdata = $productdata->getParsedBody();
    } elseif ($productdata instanceof Product) {
        $productdata = $productdata->toArray();
    }

    // First, get all the original fields.
    /** @var Product $product */
    $product = get_product_by_id($productdata['id']);

    if (is_null__($product->id) || '' === $product->id) {
        return new ProductError(message: esc_html__(string: 'Invalid product id.', domain: 'devflow'));
    }

    // Merge old and new fields with new fields overwriting old ones.
    $productdata = array_merge($product->toArray(), $productdata);

    return cms_insert_product($productdata);
}

/**
 * Deletes product from the product document.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId The id of the product to delete.
 * @return bool|Product Product on success or false on failure.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 */
function cms_delete_product(string $productId): Product|bool
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    if (is_false__($product)) {
        return false;
    }

    /**
     * Action hook fires before a product is deleted.
     *
     * @param string $productId Product id.
     */
    __observer()->action->doAction('before_delete_product', $productId);

    /**
     * Action hook fires immediately before a product is deleted from the
     * product document.
     *
     * @param string $productId Product ID.
     */
    __observer()->action->doAction('delete_product', $productId);

    try {
        command(
            new DeleteProductCommand([
                'id' => ProductId::fromString($product->id),
            ])
        );
    } catch (PDOException $ex) {
        FileLoggerFactory::getLogger()->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Product Function' => 'cms_delete_product'
            ]
        );
    }

    /**
     * Action hook fires immediately after a product is deleted from the product document.
     *
     * @param string $productId Product id.
     */
    __observer()->action->doAction('deleted_product', $productId);

    return $product;
}

/**
 * Retrieves an array of css class names.
 *
 * @file core/Shared/Helpers/product.php
 * @param string $productId Product id of current product.
 * @param string|array $class One or more css class names to add to element list.
 * @return array An array of css class names.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_product_class(string $productId, string|array $class = ''): array
{
    /** @var Product $product */
    $product = get_product_by_id($productId);

    $classes = [];

    if ($class) {
        if (!is_array($class)) {
            $class = preg_split('#\s+#', $class);
        }
        $classes = array_map('\Qubus\Security\Helpers\esc_attr', $class);
    } else {
        $class = [];
    }

    if (!$product) {
        return $classes;
    }

    $classes[] = 'product-' . $product->id;

    $classes = array_map('\Qubus\Security\Helpers\esc_attr', $classes);
    /**
     * Filters the list of CSS class names for the current product.
     *
     * @param array $classes An array of css class names.
     * @param array $class   An array of additional css class names.
     * @param string $productId Product id of the current product.
     */
    $classes = __observer()->filter->applyFilter('product.class', $classes, $class, $product->id);

    return array_unique($classes);
}

/**
 * Retrieves and displays product attribute value.
 *
 * Uses `the.product.attribute` filter.
 *
 * @file core/Shared/Helpers/product.php
 * @param string|Product|ProductId $product Product object or id.
 * @param string $key Product attribute key.
 * @return string Product attribute value.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_product_attribute(string|Product|ProductId $product, string $key): string
{
    if ($product instanceof Product) {
        $product = $product->id;
    }

    if ($product instanceof ProductId) {
        $product = $product->toNative();
    }

    $theAttribute = get_product_attribute(productId: $product, key: $key);
    /**
     * Filters product attribute.
     *
     * @file core/Shared/Helpers/product.php
     * @param mixed  $theAttribute Product attribute value.
     * @param string $key     Product attribute key.
     */
    return __observer()->filter->applyFilter('the.product.attribute', $theAttribute, $key);
}

/**
 * Currency dropdown options.
 *
 * @param string|null $active Currency selected.
 * @return void
 * @throws TypeException
 */
function currency_option(?string $active = null): void
{
    foreach (config()->array(key: 'currency') as $code => $currency) {
        echo '<option value="' . $code . '"' . selected($code, $active, false) . '>' . $code . '</option>' . "\r\n";
    }
}

/**
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 * @throws UnresolvableQueryHandlerException
 */
function publish_scheduled_product(): void
{
    $products = get_all_products_with_filters();
    $now = new DateTime('now', get_user_timezone())->getDateTime();

    try {
        foreach ($products as $product) {
            if (
                $product['status'] === 'scheduled' &&
                ($now->format('Y-m-d H:i:s') >= new DateTime($product['published'], get_user_timezone())->format())
            ) {
                $command = new UpdateProductStatusCommand([
                    'id' => ProductId::fromString($product['id']),
                    'status' => new StringLiteral(value: 'published'),
                    'modified' => $now,
                    'modifiedGmt' => new DateTime(time: 'now', timezone: 'GMT')->getDateTime(),
                ]);

                command($command);
            }
        }
    } catch (PDOException $ex) {
        FileLoggerFactory::getLogger()->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Product Function' => 'publish_scheduled_product'
            ]
        );
    }
}
