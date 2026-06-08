<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Domain\Page\Model\Page;
use App\Infrastructure\Services\Attribute\AttributeBag;
use App\Infrastructure\Services\AttributesFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use ReflectionException;

use function is_array;
use function Qubus\Support\Helpers\is_false__;

/**
 * @param string $field
 * @param mixed $value
 * @return Page|false
 * @throws Exception
 * @throws ReflectionException
 * @throws InvalidArgumentException
 */
function get_page_by(string $field, mixed $value): Page|false
{
    /** @var Page $page */
    $page = Devflow::$PHP->make(name: Page::class);
    $pageData = $page->findBy($field, $value);

    if (is_false__($pageData)) {
        return false;
    }

    return $pageData;
}

/**
 * @return array{object: Page}|false array
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 */
function get_pages(): array|false
{
    $dfdb = dfdb();
    /** @var Page $page */
    $page = Devflow::$PHP->make(name: Page::class);
    $pages = [];
    
    $locale = get_option(key: 'site_locale');
    $sql = $dfdb->getRow(
        $dfdb->prepare(
            "SELECT p.id, p.name, p.show_in_nav, p.nav_position, p.nav_type, p.data,
            t.locale, t.meta_title, t.meta_description, t.route 
            FROM {$dfdb->prefix}pages p
            LEFT JOIN {$dfdb->prefix}page_translations t 
            ON t.page_id = p.id
            WHERE t.locale = ?",
            [$locale]
        ),
        Database::ARRAY_A
    );

    if (! is_array($sql) || $sql === []) {
        return false;
    }

    foreach ($sql as $data) {
        $pages[] = $page->create($data);
    }

    return $pages;
}

/**
 * Retrieve content attribute field for a content.
 *
 * @file core/Shared/Helpers/page.php
 * @param int|string $pageId Content ID.
 * @param string $key Optional. The attribute key to retrieve.
 * @param bool $default Optional. Default value.
 * @return mixed
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws Exception
 */
function get_page_attribute(int|string $pageId, string $key, mixed $default = null): mixed
{
    return AttributesFactory::page()->get(id: (string) $pageId, key: $key, default: $default);
}

/**
 * Update content attribute field based on content ID.
 *
 * If the attribute field for the content does not exist, it will be added.
 *
 * @file core/Shared/Helpers/page.php
 * @param int|string $pageId Content ID.
 * @param string $key Attribute key.
 * @param mixed $value Attribute value.
 * @return AttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function update_page_attribute(
    int|string $pageId,
    string $key,
    mixed $value,
): AttributeBag {
    return AttributesFactory::page()->set(id: (string) $pageId, key: $key, value: $value);
}

/**
 * Add attribute data field to a content.
 *
 * @file core/Shared/Helpers/page.php
 * @param int|string $pageId Content ID.
 * @param string $key Attribute name.
 * @param mixed $value Attribute value. Must be serializable if non-scalar.
 * @return AttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function add_page_attribute(int|string $pageId, string $key, mixed $value): AttributeBag
{
    return AttributesFactory::page()->set(id: (string) $pageId, key: $key, value: $value);
}

/**
 * Remove attribute matching criteria from a content.
 *
 * @file core/Shared/Helpers/page.php
 * @param int|string $pageId Content ID.
 * @param string $key Attribute name.
 * @return AttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function delete_page_attribute(int|string $pageId, string $key): AttributeBag
{
    return AttributesFactory::page()->remove(id: (string) $pageId, key: $key);
}
