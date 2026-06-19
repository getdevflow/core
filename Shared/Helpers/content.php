<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Application\Devflow;
use App\Domain\Content\Command\DeleteContentCommand;
use App\Domain\Content\Command\RemoveContentParentCommand;
use App\Domain\Content\Command\UpdateContentStatusCommand;
use App\Domain\Content\Model\Content;
use App\Domain\Content\Command\CreateContentCommand;
use App\Domain\Content\Command\UpdateContentCommand;
use App\Domain\Content\ContentError;
use App\Domain\Content\Query\FindContentByTypeAndIdQuery;
use App\Domain\Content\Query\FindContentQuery;
use App\Domain\Content\ValueObject\ContentId;
use App\Domain\ContentType\Model\ContentType;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Cache\ContentCachePsr16;
use App\Infrastructure\Services\Attribute\AttributeBag;
use App\Infrastructure\Services\AttributesFactory;
use App\Infrastructure\Services\Content\Event\ContentUpdated;
use App\Shared\Services\DateTime;
use App\Shared\Services\Sanitizer;
use App\Shared\Services\Utils;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Error\Error;
use Qubus\EventDispatcher\EventDispatcher;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;

use function array_map;
use function Codefy\Framework\Helpers\ask;
use function Codefy\Framework\Helpers\command;
use function Codefy\Framework\Helpers\logger;
use function Codefy\Framework\Helpers\trans_html;
use function is_array;
use function preg_split;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\unslash;
use function Qubus\Support\Helpers\concat_ws;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;
use function Qubus\Support\Helpers\php_like;
use function sprintf;
use function str_replace;

/**
 * Retrieve all the content regardless of status.
 *
 * @return array
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_content(): array
{
    return ask(new FindContentQuery());
}

/**
 * Retrieve all content or content based on filters.
 *
 * @file core/Shared/Helpers/content.php
 * @param string|null $contentTypeSlug Content type slug.
 * @param int $limit Number of content to show.
 * @param int|null $offset The offset of the first row to be returned.
 * @param string $status Returned unescaped content based on status (all, draft, published, pending, archived)
 * @return Content[] Array of published content or content by particular content type.
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_all_content_with_filters(
    ?string $contentTypeSlug = null,
    int $limit = 0,
    ?int $offset = null,
    string $status = 'all'
): array {
    $query = new FindContentQuery([
        'type' => $contentTypeSlug,
        'limit' => $limit,
        'offset' => $offset,
        'status' => $status,
    ]);

    return ask($query);
}

/**
 * Retrieves content by content type slug and content id.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentTypeSlug
 * @param string $contentId
 * @return false|object
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 */
function get_content_by_type_and_id(string $contentTypeSlug, string $contentId): false|object
{
    $query = new FindContentByTypeAndIdQuery([
        'type' => new StringLiteral($contentTypeSlug),
        'id' => ContentId::fromString($contentId),
    ]);

    $results = ask($query);

    if (is_null__($results) || is_false__($results)) {
        return false;
    }

    return $results;
}

/**
 * Retrieve content by a given field from the content table.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $field The field to retrieve the content with
 *                      (id = content_id, type = content_content_type, slug = content_slug).
 * @param string $value A value for $field (content_id, content_content_type, content_slug).
 * @return false|object
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_by(string $field, string $value): false|object
{
    /** @var Content $content */
    $content = Devflow::$PHP->make(name: Content::class);
    $contentdata = $content->findBy($field, $value);

    if (is_false__($contentdata)) {
        return false;
    }

    return $contentdata;
}

/**
 * Retrieve content by the content id.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId
 * @return false|object
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_by_id(string $contentId): object|false
{
    return get_content_by('id', $contentId);
}

/**
 * A function which retrieves content datetime.
 *
 * Purpose of this function is for the `get.content.datetime`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string|null $content
 * @return string Content datetime.
 * @throws Exception
 */
function get_content_datetime(?string $content = null): string
{
    $datetime = concat_ws(
        get_content_date('published', $content),
        get_content_time('published', $content),
        ' ',
    );
    /**
     * Filters the content's datetime.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $datetime  The content's datetime.
     * @param string $contentId Content id or content object.
     */
    return __observer()->filter->applyFilter('get.content.datetime', $datetime, $content);
}

/**
 * A function which retrieves content modified datetime.
 *
 * Purpose of this function is for the `get.content.modified`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content modified datetime or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_modified(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $format = get_user_datetime_format();

    $modified = get_user_datetime($content->modifiedGmt, $format);

    /**
     * Filters the content date.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $modified The content's modified datetime.
     * @param string $format   Format to return datetime string.
     * @param string $contentId Content id or content object.
     */
    return __observer()->filter->applyFilter('get.content.modified', $modified, $format, $content);
}

/**
 * A function which retrieves a content body.
 *
 * Purpose of this function is for the `get.content.body`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content body or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_body(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $body = $content->body;
    /**
     * Filters the content date.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $body    The content's body.
     * @param string $content Content object.
     */
    return __observer()->filter->applyFilter('get.content.body', $body, $content);
}

/**
 * A function which retrieves a content content_type name.
 *
 * Purpose of this function is for the `get.content.contenttype.name`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string|false Content type name or '' on failure.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 */
function get_content_contenttype_name(string $contentId): false|string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    /** @var ContentType $contentType */
    $contentType = get_content_type_by('slug', $content->type);
    $contentTypeName = $contentType->title;
    /**
     * Filters the content content_type name.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $contentTypeName The content's content_type name.
     * @param string $content         Content object.
     */
    return __observer()->filter->applyFilter('get.content.contenttype.name', $contentTypeName, $content);
}

/**
 * A function which retrieves a content content_type link.
 *
 * Purpose of this function is for the `get.content.contenttype.link`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content Type link.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_contenttype_link(string $contentId): string
{
    $link = site_url(get_content_contenttype($contentId) . '/');
    /**
     * Filters the content content_type link.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $link      The content's content_type link.
     * @param string $contentId Content id.
     */
    return __observer()->filter->applyFilter('get.content.contenttype.link', $link, $contentId);
}

/**
 * A function which retrieves a content title.
 *
 * Purpose of this function is for the `get.content.title`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content title or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_title(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $title = $content->title;
    /**
     * Filters the content title.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $title The content's title.
     * @param string $content  Content object.
     */
    return __observer()->filter->applyFilter('get.content.title', $title, $content);
}

/**
 * A function which retrieves a content slug.
 *
 * Purpose of this function is for the `get.content.slug`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content slug or ''.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_slug(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $slug = $content->slug;
    /**
     * Filters the content's slug.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $slug The content's slug.
     * @param string $content   Content object.
     */
    return __observer()->filter->applyFilter('get.content.slug', $slug, $content);
}

/**
 * A function which retrieves a content's relative url.
 *
 * Purpose of this function is for the `get.{$contenttype}.relative.url`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content relative url or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_relative_url(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $relativeUrl = $content->relativeUrl;
    /**
     * Filters the content's relative_url.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $relativeUrl The content's relative url.
     * @param string $content   The content object.
     */
    return __observer()->filter->applyFilter(
        "get.{$content->type}.relative.url",
        $relativeUrl,
        $content
    );
}

/**
 * A function which retrieves a content's permalink.
 *
 * Purpose of this function is for the `get.{$contenttype}.link`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content permalink or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_permalink(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $link = home_url(get_content_relative_url($contentId));
    /**
     * Filters the content's link based on its content_type.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $link The content's link.
     * @param object $content Content object.
     */
    return __observer()->filter->applyFilter("get.{$content->type}.link", $link, $content);
}

/**
 * Wrapper function for `get_all_content_with_filters`.
 *
 * @file core/Shared/Helpers/content.php
 * @param string|null $contentType The content type.
 * @param int $limit Number of content to show.
 * @param int|null $offset The offset of the first row to be returned.
 * @param string $status Should it retrieve all statuses, published, draft, etc.?
 * @return array Content.
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_all_content(
    ?string $contentType = null,
    int $limit = 0,
    ?int $offset = null,
    string $status = 'all'
): array {
    return get_all_content_with_filters($contentType, $limit, $offset, $status);
}

/**
 * Adds label to content's status.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $status
 * @return string Content status label.
 */
function content_status_label(string $status): string
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
 * Retrieve content attribute field for a content.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content ID.
 * @param string $key Optional. The attribute key to retrieve.
 * @param bool $default Optional. Default value.
 * @return mixed
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function get_content_attribute(string $contentId, string $key, mixed $default = null): mixed
{
    return AttributesFactory::content()->get(id: $contentId, key: $key, default: $default);
}

/**
 * Update content attribute field based on content ID.
 *
 * If the attribute field for the content does not exist, it will be added.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content ID.
 * @param string $key Attribute key.
 * @param mixed $value Attribute value.
 * @return AttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function update_content_attribute(
    string $contentId,
    string $key,
    mixed $value,
): AttributeBag {
    return AttributesFactory::content()->set(id: $contentId, key: $key, value: $value);
}

/**
 * Add attribute data field to a content.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content ID.
 * @param string $key Attribute name.
 * @param mixed $value Attribute value. Must be serializable if non-scalar.
 * @return AttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function add_content_attribute(string $contentId, string $key, mixed $value): AttributeBag
{
    return AttributesFactory::content()->set(id: $contentId, key: $key, value: $value);
}

/**
 * Remove attribute matching criteria from a content.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content ID.
 * @param string $key Attribute name.
 * @return AttributeBag
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function delete_content_attribute(string $contentId, string $key): AttributeBag
{
    return AttributesFactory::content()->remove(id: $contentId, key: $key);
}

/**
 * A function which retrieves a content author id.
 *
 * Purpose of this function is for the `get.content.author.id`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return false|string Content author id or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_author_id(string $contentId): false|string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $authorId = $content->author;
    /**
     * Filters the content author id.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $authorId The content's author id.
     * @param object $content Content object.
     */
    return __observer()->filter->applyFilter('get.content.author.id', $authorId, $content);
}

/**
 * A function which retrieves a content author.
 *
 * Purpose of this function is for the `get.content.author`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Optional Content id or content object.
 * @param bool $reverse If first name should appear first or not. Default is false.
 * @return string|false Content author or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_author(string $contentId, bool $reverse = false): false|string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return false;
    }

    $author = get_name($content->author, $reverse);
    /**
     * Filters the content author.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $author The content's author.
     * @param object   $content Content object.
     */
    return __observer()->filter->applyFilter('get.content.author', $author, $content);
}

/**
 * A function which retrieves a content status.
 *
 * Purpose of this function is for the `get.content.status`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string|false Content status or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_status(string $contentId): false|string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return false;
    }

    $status = $content->status;
    /**
     * Filters the content status.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $status The content's status.
     * @param Content   $content Content object.
     */
    return __observer()->filter->applyFilter('get.content.status', $status, $content);
}

/**
 * A function which retrieves content date.
 *
 * Uses `call_user_func_array()` function to return appropriate content date function.
 * Dynamic part is the variable $type, which calls the date function you need.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $type Type of date to return: created, published, modified. Default: published.
 * @param string $contentId Content id.
 * @return string Content date.
 */
function get_content_date(string $type = 'published', string $contentId = ''): string
{
    return call_user_func_array("App\\Shared\\Helpers\\the_{$type}_date", [&$contentId, 'Y-m-d']);
}

/**
 * A function which retrieves content time.
 *
 * Uses `call_user_func_array()` function to return appropriate content time function.
 * Dynamic part is the variable $type, which calls the date function you need.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $type Type of date to return: created, published, modified. Default: published.
 * @param string $contentId Content id.
 * @return string Content time.
 */
function get_content_time(string $type = 'published', string $contentId = ''): string
{
    return call_user_func_array("App\\Shared\\Helpers\\the_{$type}_time", [&$contentId, 'h:i A']);
}

/**
 * Retrieves content created date.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the date the content was created.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted content created date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_created_date(
    string $contentId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theDate = get_user_datetime($content->createdGmt);
    } else {
        $theDate = $content->created;
    }

    $theDate = $date->db2Date($format, $theDate, $translate);

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the content created date.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $theDate The content's formatted date.
     * @param bool   $format Format to use for retrieving the date the content was written.
     *                       Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt    Whether to retrieve the GMT date. Default false.
     */
    return __observer()->filter->applyFilter('get.content.created.date', $theDate, $format, $gmt);
}

/**
 * Retrieves content created date.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the date the content was created.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default empty.
 * @return string Formatted content created date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_created_date(string $contentId, string $format = ''): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    if ('' === $format) {
        $theDate = get_content_created_date(
            $contentId,
            get_user_date_format(),
            true,
            true
        );
    } else {
        $theDate = get_content_created_date($contentId, $format, true, true);
    }

    /**
     * Filters the date the content was written.
     *
     * @file core/Shared/Helpers/content.php
     * @param string    $theDate The formatted date.
     * @param string    $format  Format to use for retrieving the date the content was written.
     *                           Accepts 'G', 'U', or php date format value specified
     *                           in 'date_format' option. Default empty.
     * @param Content  $content  Content object.
     */
    return __observer()->filter->applyFilter('content.created.date', $theDate, $format, $content);
}

/**
 * A function which retrieves content created time.
 *
 * Purpose of this function is for the `get.content.created.time`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the time the content was created.
 *                        Accepts 'G', 'U', or php date format value specified
 *                        in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted content created time string or Unix timestamp
 *                          if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_created_time(
    string $contentId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theTime = get_user_datetime($content->createdGmt);
    } else {
        $theTime = $content->created;
    }

    $theTime = $date->db2Date($format, $theTime, $translate);

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the content created time.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $theTime The content's formatted time.
     * @param bool   $format   Format to use for retrieving the time the content was written.
     *                         Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt      Whether to retrieve the GMT time. Default false.
     */
    return __observer()->filter->applyFilter('get.content.created.time', $theTime, $format, $gmt);
}

/**
 * Retrieves content created time.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the time the content was written.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'time_format' option. Default empty.
 * @return string Formatted content created time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_created_time(string $contentId, string $format = ''): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    if ('' === $format) {
        $theTime = get_content_created_time(
            $contentId,
            get_user_time_format(),
            true,
            true
        );
    } else {
        $theTime = get_content_created_time($contentId, $format, true, true);
    }

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the time the content was written.
     *
     * @file core/Shared/Helpers/content.php
     * @param string    $theTime The formatted time.
     * @param string    $format  Format to use for retrieving the time the content was written.
     *                           Accepts 'G', 'U', or php date format value specified
     *                           in 'time_format' option. Default empty.
     * @param object    $content Content object.
     */
    return __observer()->filter->applyFilter('content.created.time', $theTime, $format, $content);
}

/**
 * A function which retrieves content published date.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the date the content was published.
 *                        Accepts 'G', 'U', or php date format value specified
 *                        in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted content published date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_published_date(
    string $contentId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theDate = get_user_datetime($content->publishedGmt);
    } else {
        $theDate = $content->published;
    }

    $theDate = $date->db2Date($format, $theDate, $translate);

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the content published date.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $theDate The content's formatted date.
     * @param bool $format Format to use for retrieving the date the content was published.
     *                     Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt  Whether to retrieve the GMT date. Default false.
     */
    return __observer()->filter->applyFilter('get.content.published.date', $theDate, $format, $gmt);
}

/**
 * Retrieves content published date.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the date the content was published.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default empty.
 * @return string Formatted content published date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_published_date(string $contentId, string $format = ''): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    if ('' === $format) {
        $theDate = get_content_published_date(
            $contentId,
            get_user_date_format(),
            true,
            true
        );
    } else {
        $theDate = get_content_published_date($contentId, $format, true, true);
    }

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the time the content was written.
     *
     * @file core/Shared/Helpers/content.php
     * @param string    $theDate The formatted date.
     * @param string    $format   Format to use for retrieving the date the content was published.
     *                            Accepts 'G', 'U', or php date format value specified
     *                            in 'date_format' option. Default empty.
     * @param object    $content  Content object.
     */
    return __observer()->filter->applyFilter('content.published.date', $theDate, $format, $content);
}

/**
 * A function which retrieves content published time.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the time the content was published.
 *                        Accepts 'G', 'U', or php date format value specified
 *                        in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted content published time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_published_time(
    string $contentId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theTime = get_user_datetime($content->publishedGmt);
    } else {
        $theTime = $content->published;
    }

    $theTime = $date->db2Date($format, $theTime, $translate);

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the content published time.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $theTime The content's formatted time.
     * @param bool   $format   Format to use for retrieving the time the content was written.
     *                         Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt      Whether to retrieve the GMT time. Default false.
     */
    return __observer()->filter->applyFilter('get.content.published.time', $theTime, $format, $gmt);
}

/**
 * Retrieves content published time.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the time the content was published.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'time_format' option. Default empty.
 * @return string Formatted content published time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_published_time(string $contentId, string $format = ''): string
{
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    if ('' === $format) {
        $theTime = get_content_published_time(
            $contentId,
            get_user_time_format(),
            true,
            true
        );
    } else {
        $theTime = get_content_published_time($contentId, $format, true, true);
    }

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the time the content was published.
     *
     * @file core/Shared/Helpers/content.php
     * @param string    $theTime  The formatted time.
     * @param string    $format   Format to use for retrieving the time the content was published.
     *                            Accepts 'G', 'U', or php date format value specified
     *                            in 'time_format' option. Default empty.
     * @param object    $content  Content object.
     */
    return __observer()->filter->applyFilter('content.published.time', $theTime, $format, $content);
}

/**
 * A function which retrieves content modified date.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the date the content was modified.
 *                        Accepts 'G', 'U', or php date format value specified
 *                        in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted content modified date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_modified_date(
    string $contentId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theDate = get_user_datetime($content->modifiedGmt);
    } else {
        $theDate = $content->modified;
    }

    $theDate = $date->db2Date($format, $theDate, $translate);

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the content modified date.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $theDate The content's formatted date.
     * @param bool   $format  Format to use for retrieving the date the content was published.
     *                        Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt     Whether to retrieve the GMT date. Default false.
     */
    return __observer()->filter->applyFilter('get.content.modified.date', $theDate, $format, $gmt);
}

/**
 * Retrieves content published date.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the date the content was published.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default empty.
 * @return string Formatted content modified date string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_modified_date(string $contentId, string $format = ''): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    if ('' === $format) {
        $theDate = get_content_modified_date(
            $contentId,
            get_user_date_format(),
            true,
            true
        );
    } else {
        $theDate = get_content_modified_date($contentId, $format, true, true);
    }

    if (is_false__($theDate)) {
        return '';
    }

    /**
     * Filters the date the content was modified.
     *
     * @file core/Shared/Helpers/content.php
     * @param string    $theDate The formatted date.
     * @param string    $format  Format to use for retrieving the date the content was modified.
     *                           Accepts 'G', 'U', or php date format value specified
     *                           in 'date_format' option. Default empty.
     * @param object    $content Content object.
     */
    return __observer()->filter->applyFilter('content.modified.date', $theDate, $format, $content);
}

/**
 * A function which retrieves content modified time.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the time the content was modified.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'date_format' option. Default 'U'.
 * @param bool $gmt Whether to use GMT. Default false.
 * @param bool $translate Whether the returned string should be translated. Default false.
 * @return string Formatted content modified time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_modified_time(
    string $contentId,
    string $format = 'U',
    bool $gmt = false,
    bool $translate = false
): string {
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $date = new DateTime();

    if ($gmt) {
        $theTime = get_user_datetime($content->modifiedGmt);
    } else {
        $theTime = $content->modified;
    }

    $theTime = $date->db2Date($format, $theTime, $translate);

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the content modified time.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $theTime The content's formatted time.
     * @param bool   $format   Format to use for retrieving the time the content was modified.
     *                         Accepts 'G', 'U', or php date format. Default 'U'.
     * @param bool   $gmt      Whether to retrieve the GMT time. Default false.
     */
    return __observer()->filter->applyFilter('get.content.modified.time', $theTime, $format, $gmt);
}

/**
 * Retrieves content modified time.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @param string $format Format to use for retrieving the time the content was modified.
 *                       Accepts 'G', 'U', or php date format value specified
 *                       in 'time_format' option. Default empty.
 * @return string Formatted content modified time string or Unix timestamp
 *                if $format is 'U' or 'G'. '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_modified_time(string $contentId, string $format = ''): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    if ('' === $format) {
        $theTime = get_content_modified_time(
            $contentId,
            get_user_time_format(),
            true,
            true
        );
    } else {
        $theTime = get_content_modified_time($contentId, $format, true, true);
    }

    if (is_false__($theTime)) {
        return '';
    }

    /**
     * Filters the time the content was modified.
     *
     * @file core/Shared/Helpers/content.php
     * @param string    $theTime The formatted time.
     * @param string    $format  Format to use for retrieving the time the content was modified.
     *                           Accepts 'G', 'U', or php date format value specified
     *                           in 'time_format' option. Default empty.
     * @param object    $content Content object.
     */
    return __observer()->filter->applyFilter('content.modified.time', $theTime, $format, $content);
}

/**
 * A function which retrieves content content_type id.
 *
 * Purpose of this function is for the `get.content.content.type.id`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content Type id or '' on failure.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 */
function get_content_content_type_id(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    /** @var ContentType $contentType */
    $contentType = get_content_type_by('slug', $content->type);
    /**
     * Filters the content content_type id.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $contentTypeId The content's content_type id.
     * @param string $contentId  The content ID.
     */
    return __observer()->filter->applyFilter('get.content.content.type.id', $contentType->id, $contentId);
}

/**
 * A function which retrieves content content_type.
 *
 * Purpose of this function is for the `get.content.content.type`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content Type or '' on failure
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_contenttype(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $contenttype = $content->type;
    /**
     * Filters the content content_type.
     *
     * @file core/Shared/Helpers/content.php
     * @param string   $contenttype  The content's content_type.
     * @param string   $contentId    The content ID.
     */
    return __observer()->filter->applyFilter('get.content.content.type', $contenttype, $contentId);
}

/**
 * A function which retrieves a content's parent id.
 *
 * Purpose of this function is for the `get.content.parent.id`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content parent id or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function get_content_parent_id(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    /**
     * Check that parent id is properly formatted.
     */
    $parentId = ContentId::fromString($content->parent)->toNative();
    /**
     * Filters the content parent id.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $parentId  The content's parent id.
     * @param string $contentId The content ID.
     */
    return __observer()->filter->applyFilter('get.content.parent.id', $parentId, $contentId);
}

/**
 * A function which retrieves content parent.
 *
 * Purpose of this function is for the `get.content.parent`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return string Content parent or '' on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_parent(string $contentId): string
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return '';
    }

    $parent = $content->parent;
    /**
     * Filters the content parent.
     *
     * @file core/Shared/Helpers/content.php
     * @param string $parent    The content's parent.
     * @param string $contentId The content ID.
     */
    return __observer()->filter->applyFilter('get.content.parent', $parent, $contentId);
}

/**
 * A function which retrieves content sidebar.
 *
 * Purpose of this function is for the `get.content.sidebar`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return int Content sidebar integer or 0 on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_sidebar(string $contentId): int
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return (int) 0;
    }

    $sidebar = $content->sidebar;
    /**
     * Filters the content sidebar.
     *
     * @file core/Shared/Helpers/content.php
     * @param int    $sidebar   The content's sidebar option.
     * @param string $contentId The content ID.
     */
    return __observer()->filter->applyFilter('get.content.sidebar', (int) $sidebar, $contentId);
}

/**
 * A function which retrieves content show in menu.
 *
 * Purpose of this function is for the `get.content.show.in.menu`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return int Content show in menu integer or 0 on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_show_in_menu(string $contentId): int
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return (int) 0;
    }

    $menu = $content->showInMenu;
    /**
     * Filters the content show in menu.
     *
     * @file core/Shared/Helpers/content.php
     * @param int    $menu      The content's show in menu option.
     * @param string $contentId The content ID.
     */
    return __observer()->filter->applyFilter('get.content.show.in.menu', (int) $menu, $contentId);
}

/**
 * A function which retrieves content show in search.
 *
 * Purpose of this function is for the `get.content.show.in.search`
 * filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id.
 * @return int Content show in search integer or 0 on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_show_in_search(string $contentId): int
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return (int) 0;
    }

    $search = $content->showInSearch;
    /**
     * Filters the content show in search.
     *
     * @file core/Shared/Helpers/content.php
     * @param int    $search    The content's show in search option.
     * @param string $contentId The content ID.
     */
    return __observer()->filter->applyFilter('get.content.show.in.search', (int) $search, $contentId);
}

/**
 * Creates a unique content slug.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $originalSlug Original slug of content.
 * @param string $originalTitle Original title of content.
 * @param string|null $contentId Unique content id or null.
 * @param string|null $contentType Content type of content.
 * @return string Unique content slug.
 * @throws Exception
 * @throws ReflectionException
 * @throws TypeException
 */
function cms_unique_content_slug(
    string $originalSlug,
    string $originalTitle,
    ?string $contentId = null,
    ?string $contentType = null
): string {
    if (is_null__($contentId)) {
        $contentSlug = cms_slugify($originalTitle, 'content');
    } elseif (if_content_slug_exists($contentId, $originalSlug, $contentType)) {
        $contentSlug = cms_slugify($originalTitle, 'content');
    } else {
        $contentSlug = $originalSlug;
    }
    /**
     * Filters the unique content slug before returned.
     *
     * @file core/Shared/Helpers/content.php
     * @param string    $contentSlug   Unique content slug.
     * @param string    $originalSlug  The content's original slug.
     * @param string    $originalTitle The content's original title before slugified.
     * @param string    $contentId     The content's unique id.
     * @param string    $contentType   The content's content type.
     */
    return __observer()->filter->applyFilter(
        'cms.unique.content.slug',
        $contentSlug,
        $originalSlug,
        $originalTitle,
        $contentId,
        $contentType
    );
}

/**
 * Insert or update a content.
 *
 * All the `$contentdata` array fields have filters associated with the values. The filters
 * have the prefix 'pre.' followed by the field name. An example using 'content_status' would have
 * the filter called, 'pre.content.status' that can be hooked into.
 *
 * @file core/Shared/Helpers/content.php
 * @internal Should only be used for rest API's.
 * @param array|ServerRequestInterface|Content $contentdata An array of data that is used for insert or update.
 *
 *      @type string $title The content's title.
 *      @type string $body The content's body.
 *      @type string $slug The content's slug.
 *      @type string $author The content's author.
 *      @type string $type The content's contenttype.
 *      @type string $parent The content's parent.
 *      @type string $sidebar The content's sidebar.
 *      @type string $showInMenu Whether to show content in menu.
 *      @type string $showInSearch Whether to show content in search.
 *      @type string $relativeUrl The content's relative url.
 *      @type string $featuredImage THe content's featured image.
 *      @type string $status THe content's status.
 *      @type string $published Timestamp describing the moment when the content
 *                              was published. Defaults to Y-m-d h:i A.
 * @return Error|string|null The newly created content's content_id or throws an error or returns null
 *                     if the content could not be created or updated.
 * @throws CommandPropertyNotFoundException
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 */
function cms_insert_content(array|ServerRequestInterface|Content $contentdata): Error|string|null
{
    $userId = get_current_user_id();

    $defaults = [
        'title' => '',
        'body' => '',
        'author' => $userId,
        'type' => '',
        'parent' => '',
        'sidebar' => '0',
        'showInMenu' => '0',
        'showInSearch' => '0',
        'featuredImage' => '',
        'status' => 'draft'
    ];

    if ($contentdata instanceof ServerRequestInterface) {
        $contentdata = $contentdata->getParsedBody();
    } elseif ($contentdata instanceof Content) {
        $contentdata = $contentdata->toArray();
    }

    $contentdata = Utils::parseArgs($contentdata, $defaults);

    // Are we updating or creating?
    if (!empty($contentdata['id']) && !is_false__(get_content_by_id($contentdata['id']))) {
        $update = true;
        $contentId = new ContentId($contentdata['id']);
        /** @var Content $contentBefore */
        $contentBefore = get_content_by('id', $contentId->toNative());

        if (is_false__($contentBefore)) {
            return new ContentError(message: trans_html('Invalid content id.'));
        }

        $previousStatus = get_content_status($contentId->toNative());
        /**
         * Fires immediately before content is inserted into the content document.
         *
         * @param string $previousStatus Status of the content before it is created or updated.
         * @param string $contentId      The content's content_id.
         * @param bool   $update         Whether this is an existing content or a new content.
         */
        __observer()->action->doAction(
            'content_previous_status',
            $previousStatus,
            $contentId->toNative(),
            $update
        );

        /**
         * Create new content object.
         * @var Content $content
         */
        $content = Devflow::$PHP->make(name: Content::class);
        $content->id = $contentId->toNative();
    } else {
        $update = false;
        $contentId = new ContentId();
        $previousStatus = 'new';
        /**
         * Fires immediately before a content is inserted into the content document.
         *
         * @param string $previousStatus Status of the content before it is created or updated.
         * @param string $contentId      The content's content_id.
         * @param bool   $update         Whether this is an existing content or a new content.
         */
        __observer()->action->doAction(
            'content_previous_status',
            $previousStatus,
            $contentId->toNative(),
            $update
        );

        /**
         * Create new content object.
         *
         * @var Content $content
         */
        $content = Devflow::$PHP->make(name: Content::class);
        $content->id = $contentId->toNative();
    }

    if (isset($contentdata['title'])) {
        $contentTitle = $contentdata['title'];
    } else {
        /**
         * For an update, don't modify the title if it
         * wasn't supplied as an argument.
         */
        $contentTitle = $contentBefore->title;
    }

    $rawContentType = $contentdata['type'];
    $sanitizedContentType = Sanitizer::item($rawContentType);
    /**
     * Filters a content's type before the content is created or updated.
     *
     * @param string $sanitizedContentType Content type after it has been sanitized.
     * @param string $rawContentType The content's content type.
     */
    $contentType = __observer()->filter->applyFilter(
        'pre.content.type',
        $sanitizedContentType,
        $rawContentType
    );
    $content->type = $contentType;

    $rawContentTitle = $contentTitle;
    $sanitizedContentTitle = Sanitizer::item($rawContentTitle);
    /**
     * Filters a content's title before created/updated.
     *
     * @param string $sanitizedContentTitle Content title after it has been sanitized.
     * @param string $rawContentTitle The content's title.
     */
    $contentTitle = __observer()->filter->applyFilter(
        'pre.content.title',
        (string) $sanitizedContentTitle,
        (string) $rawContentTitle
    );
    $content->title = $contentTitle;

    if (isset($contentdata['slug'])) {
        /**
         * cms_unique_content_slug will take the original slug supplied and check
         * to make sure that it is unique. If not unique, it will make it unique
         * by adding a number at the end.
         */
        $contentSlug = cms_unique_content_slug(
            $contentdata['slug'],
            $contentTitle,
            $contentId->toNative(),
            $contentType
        );
    } else {
        /**
         * For an update, don't modify the slug if it
         * wasn't supplied as an argument.
         */
        $contentSlug = $contentBefore->slug;
    }

    $rawContentSlug = $contentSlug;
    $sanitizedContentSlug = Sanitizer::item($rawContentSlug);
    /**
     * Filters a content's slug before created/updated.
     *
     * @param string $sanitizedContentSlug Content slug after it has been sanitized.
     * @param string $rawContentSlug The content's slug.
     */
    $contentSlug = __observer()->filter->applyFilter(
        'pre.content.slug',
        (string) $sanitizedContentSlug,
        (string) $rawContentSlug
    );
    $content->slug = $contentSlug;

    $rawContentBody = $contentdata['body'];
    /**
     * Filters a content's body before created/updated.
     *
     * @param string $rawContentSlug The content's slug.
     */
    $contentBody = __observer()->filter->applyFilter(
        'pre.content.body',
        $rawContentBody
    );
    $content->body = cms_compress_internal_urls($contentBody);

    /**
     * Check for content author
     *
     * @param string $contentAuthor Content author id.
     */
    $contentAuthor = $contentdata['author'];

    if ($contentAuthor === '' || $contentAuthor === null) {
        return new ContentError(
            message: trans_html(string: 'Content author cannot be null or empty.')
        );
    }

    $content->author = $contentAuthor;

    $rawContentParent = $contentdata['parent'];
    $sanitizedContentParent = Sanitizer::item($rawContentParent);
    /**
     * Filters a content's parent before the content is created or updated.
     *
     * @param string $sanitizedContentParent Content parent after it has been sanitized.
     * @param string $rawContentParent The content's parent.
     */
    $contentParent = __observer()->filter->applyFilter(
        'pre.content.parent',
        $sanitizedContentParent,
        $rawContentParent
    );
    $content->parent = $contentParent;

    $rawContentSidebar = $contentdata['sidebar'];
    $sanitizedContentSidebar = Sanitizer::item($rawContentSidebar, 'int');
    /**
     * Filters a content's sidebar before the content is created or updated.
     *
     * @param int $sanitizedContentSidebar Content sidebar after it has been sanitized.
     * @param int $rawContentSidebar The content's sidebar.
     */
    $contentSidebar = __observer()->filter->applyFilter(
        'pre.content.sidebar',
        $sanitizedContentSidebar,
        $rawContentSidebar
    );
    $content->sidebar = $contentSidebar;

    $rawContentShowInMenu = $contentdata['showInMenu'];
    $sanitizedContentShowInMenu = Sanitizer::item($rawContentShowInMenu, 'int');
    /**
     * Filters a content's show in menu before the content is created or updated.
     *
     * @param string $sanitizedContentShowInMenu Content show in menu after it has been sanitized.
     * @param int $rawContentShowInMenu The content's show in menu.
     */
    $contentShowInMenu = __observer()->filter->applyFilter(
        'pre.content.show.in.menu',
        $sanitizedContentShowInMenu,
        $rawContentShowInMenu
    );
    $content->showInMenu = $contentShowInMenu;

    $rawContentShowInSearch = $contentdata['showInSearch'];
    $sanitizedContentShowInSearch = Sanitizer::item($rawContentShowInSearch, 'int');
    /**
     * Filters a content's show in search before the content is created or updated.
     *
     * @param int $sanitizedContentShowInSearch Content show in search after it has been sanitized.
     * @param int $rawContentShowInSearch The content's show in search.
     */
    $contentShowInSearch = __observer()->filter->applyFilter(
        'pre.content.show.in.search',
        $sanitizedContentShowInSearch,
        $rawContentShowInSearch
    );
    $content->showInSearch = $contentShowInSearch;

    $rawContentFeaturedImage = cms_optimized_image_upload($contentdata['featuredImage']);
    $sanitizedContentFeaturedImage = Sanitizer::item($rawContentFeaturedImage);
    /**
     * Filters a content's featured image before the content is created or updated.
     *
     * @param string $sanitizedContentFeaturedImage Content featured image after it has been sanitized.
     * @param string $rawContentFeaturedImage The content's featured image.
     */
    $contentFeaturedImage = __observer()->filter->applyFilter(
        'pre.content.featured.image',
        (string) $sanitizedContentFeaturedImage,
        (string) $rawContentFeaturedImage
    );
    $content->featuredImage = $contentFeaturedImage;

    $rawContentStatus = $contentdata['status'];
    $sanitizedContentStatus = Sanitizer::item($rawContentStatus);
    /**
     * Filters a content's status before the content is created or updated.
     *
     * @param string $sanitizedContentStatus Content status after it has been sanitized.
     * @param string $rawContentStatus The content's status.
     */
    $contentStatus = __observer()->filter->applyFilter(
        'pre.content.status',
        (string) $sanitizedContentStatus,
        (string) $rawContentStatus
    );
    $content->status = $contentStatus;

    /*
     * Filters whether the content is null.
     *
     * @param bool  $maybe_empty Whether the content should be considered "null".
     * @param array $contentdata   Array of content data.
     */
    $maybeNull = !$contentTitle && !$contentBody;
    if (__observer()->filter->applyFilter('cms.insert.empty.content', $maybeNull, $contentdata)) {
        return new ContentError(message: trans_html(string: 'The title and content are null.'));
    }

    if (!$update) {
        if (empty($contentdata['published']) || php_like('%0000-00-00 00:00', $contentdata['published'])) {
            $contentPublished = new DateTime('now', get_user_timezone())->getDateTime();
            $contentPublishedGmt = new DateTime('now', 'GMT')->getDateTime();
            $contentCreated = $contentPublished;
            $contentCreatedGmt = $contentPublishedGmt;
        } else {
            $contentPublished = new DateTime(
                str_replace(['AM', 'PM'], '', $contentdata['published']),
                get_user_timezone()
            )->getDateTime();
            $contentPublishedGmt = new DateTime(
                $contentdata['publishedGmt'] ?? 'now',
                'GMT'
            )->getDateTime();
            $contentCreated = $contentPublished;
            $contentCreatedGmt = $contentPublishedGmt;
        }
    } else {
        $contentPublished = new DateTime(
            str_replace(['AM', 'PM'], '', $contentdata['published']),
            get_user_timezone()
        )->getDateTime();
        $contentPublishedGmt = new DateTime(
            $contentdata['publishedGmt'] ?? str_replace(['AM', 'PM'], '', $contentdata['published']),
            'GMT'
        )->getDateTime();
        $contentCreated = $contentPublished;
        $contentCreatedGmt = $contentPublishedGmt;
        $contentModified = new DateTime(QubusDateTimeImmutable::now(get_user_timezone())->toDateTimeString())
                ->getDateTime();
        $contentModifiedGmt = new DateTime(QubusDateTimeImmutable::now('GMT')->toDateTimeString())->getDateTime();
    }

    if (
        $contentStatus !== 'scheduled' &&
            ($contentPublished->format('Y-m-d H:i:s') >
                    new DateTime('now', get_user_timezone())->format())
    ) {
        $contentStatus = 'scheduled';
        $content->status = $contentStatus;
    }

    $contentDataArray = [
        'id' => $contentId->toNative(),
        'slug' => $contentSlug,
        'body' => $contentBody,
        'author' => $contentAuthor,
        'type' => $contentType,
        'parent' => $contentParent,
        'sidebar' => (string) $contentSidebar,
        'showInMenu' => (string) $contentShowInMenu,
        'showInSearch' => (string) $contentShowInSearch,
        'featuredImage' => $contentFeaturedImage,
        'status' => $contentStatus,
        'created' => $contentCreated->format('Y-m-d H:i:s'),
        'createdGmt' => $contentCreatedGmt->format('Y-m-d H:i:s'),
        'published' => $contentPublished->format('Y-m-d H:i:s'),
        'publishedGmt' => $contentPublishedGmt->format('Y-m-d H:i:s'),
    ];
    $contentData = unslash($contentDataArray);

    // Content custom fields.
    $attributeFields = $contentdata['content_field'] ?? [];

    /**
     * Filters content data before the record is created or updated.
     *
     * It only includes data in the content table, not any content attribute.
     *
     * @param array $contentData
     *     Values and keys for the user.
     *
     *      @type string $contentTitle         The content's title.
     *      @type string $contentBody          The content's body.
     *      @type string $contentSlug          The content's slug.
     *      @type string $contentAuthor        The content's author.
     *      @type string $contentType          The content's type.
     *      @type string $contentParent        The content's parent.
     *      @type string $contentSidebar       The content's sidebar.
     *      @type string $contentShowInMenu    Whether to show content in menu.
     *      @type string $contentShowInSearch  Whether to show content in search.
     *      @type string $contentFeaturedImage The content's featured image.
     *      @type string $contentStatus        The content's status.
     *      @type string $contentCreated       Timestamp of when the content was created.
     *                                         Defaults to Y-m-d H:i:s A.
     *      @type string $contentCreatedGmt    Timestamp of when the content was created
     *                                         in GMT. Defaults to Y-m-d H:i:s A.
     *      @type string $contentPublished     Timestamp describing the moment when the content
     *                                         was published. Defaults to Y-m-d H:i:s A.
     *      @type string $contentPublishedGmt  Timestamp describing the moment when the content
     *                                         was published in GMT. Defaults to Y-m-d H:i:s A.
     *      @type string $contentModified      Timestamp of when the content was modified.
     *                                         Defaults to Y-m-d H:i:s A.
     *      @type string $contentModifiedGmt   Timestamp of when the content was modified
     *                                         in GMT. Defaults to Y-m-d H:i:s A.
     *
     * @param bool     $update Whether the content is being updated rather than created.
     * @param string|null $id  ID of the content to be updated, or NULL if the content is being created.
     */
    __observer()->filter->applyFilter(
        'cms.before.insert.content.data',
        $contentData,
        $update,
        $update ? $contentBefore->id : $contentId,
    );

    if (!$update) {
        /**
         * Fires immediately before a content is inserted into the content document.
         *
         * @param Content $content Content object.
         */
        __observer()->action->doAction('pre_content_insert', $content);

        try {
            $command = new CreateContentCommand([
                'id' => ContentId::fromString($contentId->toNative()),
                'title' => new StringLiteral($contentTitle),
                'slug' => new StringLiteral($contentSlug),
                'body' => new StringLiteral($contentBody ?? ''),
                'attribute' => new ArrayLiteral($attributeFields),
                'author' => UserId::fromString($contentAuthor),
                'type' => new StringLiteral($contentType),
                'parent' => 'NULL' !== $contentParent ? ContentId::fromString($contentParent) : null,
                'sidebar' => new IntegerNumber($contentSidebar),
                'showInMenu' => new IntegerNumber($contentShowInMenu),
                'showInSearch' => new IntegerNumber($contentShowInSearch),
                'featuredImage' => new StringLiteral($contentFeaturedImage),
                'status' => new StringLiteral($contentStatus),
                'created' => $contentCreated,
                'createdGmt' => $contentCreatedGmt,
                'published' => $contentPublished,
                'publishedGmt' => $contentPublishedGmt,
            ]);

            command($command);
        } catch (PDOException $ex) {
            logger(
                'error',
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Content Function' => 'cms_insert_content'
                ]
            );

            return new ContentError(message: trans_html(
                string: 'Could not insert content into the content table.',
            ));
        }
    } else {
        /**
         * Fires immediately before existing content is updated in the content document.
         *
         * @param string  $contentId Content id.
         * @param content $content   Content object.
         */
        __observer()->action->doAction('pre_content_update', $contentId, $content);

        try {
            $command = new UpdateContentCommand([
                'id' => ContentId::fromString($contentId->toNative()),
                'title' => new StringLiteral($contentTitle),
                'slug' => new StringLiteral($contentSlug),
                'body' => new StringLiteral($contentBody),
                'attribute' => new ArrayLiteral($attributeFields),
                'author' => UserId::fromString($contentAuthor),
                'type' => new StringLiteral($contentType),
                'parent' => 'NULL' !== $contentParent ? ContentId::fromString($contentParent) : null,
                'sidebar' => new IntegerNumber($contentSidebar),
                'showInMenu' => new IntegerNumber($contentShowInMenu),
                'showInSearch' => new IntegerNumber($contentShowInSearch),
                'featuredImage' => new StringLiteral($contentFeaturedImage),
                'status' => new StringLiteral($contentStatus),
                'published' => $contentPublished,
                'publishedGmt' => $contentPublishedGmt,
                'modified' => $contentModified,
                'modifiedGmt' => $contentModifiedGmt,
            ]);

            command($command);
        } catch (PDOException $ex) {
            logger(
                'error',
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Content Function' => 'cms_insert_content'
                ]
            );

            return new ContentError(message: trans_html(
                string: 'Could not update content within the content table.',
            ));
        }
    }

    /** @var Content $content */
    $content = get_content_by_id($contentId->toNative());

    ContentCachePsr16::clean($content);

    if ($update) {
        /**
         * Action hook triggered after existing content has been updated.
         *
         * @param string $contentId Content id.
         * @param array  $content   Content object.
         */
        __observer()->action->doAction('update_content', $contentId, $content);
        /** @var Content $contentAfter */
        $contentAfter = get_content_by_id($contentId->toNative());
        /**
         * Action hook triggered after existing content has been updated.
         *
         * @param string $contentId      Content id.
         * @param object $contentAfter   Content object following the update.
         * @param object $contentBefore  Content object before the update.
         */
        __observer()->action->doAction('content_updated', $contentId->toNative(), $contentAfter, $contentBefore);
    } else {
        /**
         * Action hook triggered after content is created.
         *
         * @param array $content Content object.
         */
        __observer()->action->doAction('create_content', $content);
    }

    /**
     * Action hook triggered after content has been saved.
     *
     * The dynamic portion of this hook, `$contentType`, is the content's
     * content type.
     *
     * @param string $contentId The content's id.
     * @param array $content    Content object.
     * @param bool  $update     Whether this is an existing content or a new content.
     */
    __observer()->action->doAction("save_content_{$contentType}", $contentId->toNative(), $content, $update);

    /**
     * Action hook triggered after content has been saved.
     *
     * The dynamic portions of this hook, `$contentType` and `$contentStatus`,
     * are the content's content type and status.
     *
     * @param string $contentId The content's id.
     * @param array  $content   Content object.
     * @param bool   $update    Whether this is existing content or new content.
     */
    __observer()->action->doAction(
        "save_content_{$contentType}_{$contentStatus}",
        $contentId->toNative(),
        $content,
        $update
    );

    /**
     * Action hook triggered after content has been saved.
     *
     * @param string $contentId The content's id.
     * @param object $content   Content object.
     * @param bool   $update    Whether this is existing content or new content.
     */
    __observer()->action->doAction('cms_after_insert_content_data', $contentId->toNative(), $content, $update);

    return $contentId->toNative();
}

/**
 * Update a content in the content document.
 *
 * See {@see cms_insert_content()} For what fields can be set in $contentdata.
 *
 * @file core/Shared/Helpers/content.php
 * @internal Should only be used for rest API's.
 * @param array|ServerRequestInterface|Content $contentdata An array of content data or a content object.
 * @return string|Error The updated content's id or return Error if content could not be updated.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 */
function cms_update_content(array|ServerRequestInterface|Content $contentdata): string|Error
{
    if ($contentdata instanceof ServerRequestInterface) {
        $contentdata = $contentdata->getParsedBody();
    } elseif ($contentdata instanceof Content) {
        $contentdata = $contentdata->toArray();
    }

    // First, get all the original fields.
    /** @var Content $content */
    $content = get_content_by_id($contentdata['id']);

    if (is_null__($content->id) || '' === $content->id) {
        return new ContentError(message: trans_html(string: 'Invalid content id.'));
    }

    // Merge old and new fields with new fields overwriting old ones.
    $contentdata = array_merge($content->toArray(), $contentdata);

    return cms_insert_content($contentdata);
}

/**
 * Deletes content from the content document.
 *
 * @file core/Shared/Helpers/content.php
 * @internal Should only be used for rest API's.
 * @param string $contentId The id of the content to delete.
 * @return bool|Content Content on success or false on failure.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 * @throws CommandPropertyNotFoundException
 */
function cms_delete_content(string $contentId): Content|bool
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    if (is_false__($content)) {
        return false;
    }

    /**
     * Action hook fires before a content is deleted.
     *
     * @param string $contentId Content id.
     */
    __observer()->action->doAction('before_delete_content', $contentId);

    if (is_content_parent($contentId)) {
        foreach (is_content_parent($contentId) as $children) {
            try {
                command(
                    new RemoveContentParentCommand([
                        'id' => ContentId::fromString($contentId),
                        'parent' => ContentId::fromString($children['content_id']),
                    ])
                );

                ContentCachePsr16::clean((array) $children);
            } catch (PDOException | \InvalidArgumentException $ex) {
                logger(
                    'error',
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    ),
                    [
                        'Content Function' => 'cms_delete_content'
                    ]
                );
                return false;
            }
        }
    }

    /**
     * Action hook fires immediately before a content is deleted from the
     * content table.
     *
     * @param string $contentId Content ID.
     */
    __observer()->action->doAction('delete_content', $contentId);

    try {
        command(
            new DeleteContentCommand([
                'id' => ContentId::fromString($content->id),
            ])
        );

        ContentCachePsr16::clean($content->toArray());
    } catch (PDOException | \InvalidArgumentException $ex) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Content Function' => 'cms_delete_content'
            ]
        );
    }

    /**
     * Action hook fires immediately after a content is deleted from the database.
     *
     * @param string $contentId Content id.
     */
    __observer()->action->doAction('deleted_content', $contentId);

    return $content;
}

/**
 * Returns the number of content rows within a given content type.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $slug Content type slug.
 * @return int Number of content rows based on content type.
 */
function number_content_by_type(string $slug): int
{
    $dfdb = dfdb();

    try {
        $count = $dfdb->getVar(
            $dfdb->prepare("SELECT COUNT(*) FROM {$dfdb->prefix}content WHERE content_type = ?", [$slug])
        );

        return (int) $count;
    } catch (PDOException | TypeException $ex) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Content Function' => 'number_content_per_type'
            ]
        );
    }
    return (int) 0;
}

/**
 * Retrieves all posts
 *
 * @file core/Shared/Helpers/content.php
 * @access private
 * @param string|null $parentId Content parent id
 * @param string $contentId Content id.
 * @return void
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_content_parent_dropdown_list(?string $parentId = null, string $contentId = ''): void
{
    try {
        $content = ask(new FindContentQuery());

        foreach ($content as $value) {
            if ($contentId !== $value['id']) {
                echo '<option value="' . $value['id'] . '"' .
                selected($parentId, $value['id'], false) . '>' .
                $value['title'] . '</option>';
            }
        }
    } catch (PDOException $ex) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Content Function' => 'get_content_parent_dropdown_list'
            ]
        );
    }
}

/**
 * Retrieves an array of CSS class names.
 *
 * @file core/Shared/Helpers/content.php
 * @param string $contentId Content id of current content.
 * @param string|array $class One or more css class names to add to html element list.
 * @return array An array of css class names.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function get_content_class(string $contentId, string|array $class = ''): array
{
    /** @var Content $content */
    $content = get_content_by_id($contentId);

    $classes = [];

    if ($class) {
        if (!is_array($class)) {
            $class = preg_split('#\s+#', $class);
        }
        $classes = array_map('\Qubus\Security\Helpers\esc_attr', $class);
    } else {
        $class = [];
    }

    if (!$content) {
        return $classes;
    }

    $classes[] = 'content-' . $content->id;
    $classes[] = 'contenttype-' . $content->type;

    $classes = array_map('\Qubus\Security\Helpers\esc_attr', $classes);
    /**
     * Filters the list of CSS class names for the current content.
     *
     * @param array $classes An array of css class names.
     * @param array $class   An array of additional css class names.
     * @param string $contentId Content id of the current content.
     */
    $classes = __observer()->filter->applyFilter('content.class', $classes, $class, $content->id);

    return array_unique($classes);
}

/**
 * Displays the permalink for the current content.
 *
 * Uses `the.permalink` filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string|Content|ContentId $content Content object or content id.
 * @return string Content permalink.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_permalink(string|Content|ContentId $content): string
{
    if ($content instanceof Content) {
        $content = $content->id;
    }

    if ($content instanceof ContentId) {
        $content = $content->toNative();
    }

    /**
     * Filters the display of the permalink for the current content.
     *
     * @file core/Shared/Helpers/content.php
     * @param string         $permalink The permalink for the current content.
     * @param string|Content $content   Content object or id.
     */
    return __observer()->filter->applyFilter('the.permalink', get_permalink($content), $content);
}

/**
 * The cms content filter.
 *
 * Uses `the.body` filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string|Content|ContentId $content Content object or content id.
 * @return string Content body.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_body(string|Content|ContentId $content): string
{
    if ($content instanceof Content) {
        $content = $content->id;
    }

    if ($content instanceof ContentId) {
        $content = $content->toNative();
    }

    $contentBody = get_content_body($content);
    $contentBody = __observer()->filter->applyFilter('the.body', $contentBody, $content);
    $contentBody = str_replace(']]>', ']]&gt;', $contentBody);
    return $contentBody;
}

/**
 * Retrieves and displays content attribute value.
 *
 * Uses `the.attribute` filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string|Content|ContentId $content Content object or id.
 * @param string $key Content attribute key.
 * @return string Content attribute value.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_attribute(string|Content|ContentId $content, string $key): string
{
    if ($content instanceof Content) {
        $content = $content->id;
    }

    if ($content instanceof ContentId) {
        $content = $content->toNative();
    }

    $theAttribute = get_content_attribute(contentId: $content, key: $key);
    /**
     * Filters content attribute.
     *
     * @file core/Shared/Helpers/content.php
     * @param mixed  $theAttribute Content attribute value.
     * @param string $key          Content attribute key.
     * @param string $content      Content id.
     */
    return __observer()->filter->applyFilter('the.attribute', $theAttribute, $key, $content);
}

/**
 * Retrieves and displays content title.
 *
 * Uses `the.title` filter.
 *
 * @file core/Shared/Helpers/content.php
 * @param string|Content|ContentId $content Content object or id.
 * @return string Content attribute value.
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 */
function the_title(string|Content|ContentId $content): string
{
    if ($content instanceof Content) {
        $content = $content->id;
    }

    if ($content instanceof ContentId) {
        $content = $content->toNative();
    }

    $theTitle = get_content_title($content);
    /**
     * Filters content attribute.
     *
     * @file core/Shared/Helpers/content.php
     * @param mixed  $theTitle Content title.
     * @param string $content  Content id.
     */
    return __observer()->filter->applyFilter('the.title', $theTitle, $content);
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
function publish_scheduled_content(): void
{
    $contents = get_all_content();
    $now = new DateTime('now', get_user_timezone())->getDateTime();
    /** @var EventDispatcher $event */
    $event = Devflow::$PHP->make(name: EventDispatcher::class);

    try {
        foreach ($contents as $content) {
            if (
                $content['status'] === 'scheduled' &&
                ($now->format('Y-m-d H:i:s') >= new DateTime($content['published'], get_user_timezone())->format())
            ) {
                $command = new UpdateContentStatusCommand([
                    'id' => ContentId::fromString($content['id']),
                    'status' => new StringLiteral('published'),
                    'modified' => $now,
                    'modifiedGmt' => new DateTime('now', 'GMT')->getDateTime(),
                ]);

                command($command);
            }

            $event->dispatch(new ContentUpdated((array) $content));
        }
    } catch (PDOException $ex) {
        logger(
            'error',
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Content Function' => 'publish_scheduled_content'
            ]
        );
    }
}

/**
 * @param string $status
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function user_can_set_content_status(string $status): bool
{
    $caps = content_status_capabilities();

    if (! array_key_exists($status, $caps)) {
        return false;
    }

    $cap = $caps[$status];

    return $cap === null || current_user_can(perm: $cap);
}

/**
 * @param string|null $publishedGmt
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function user_can_schedule_content_for(?string $publishedGmt): bool
{
    if (false === current_user_can(perm: 'schedule:content')) {
        return false;
    }

    if ($publishedGmt === null || $publishedGmt === '') {
        return false;
    }

    return strtotime($publishedGmt) > time();
}

/**
 * @param string $fromStatus
 * @param string $toStatus
 * @param string|null $publishedGmt
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function content_status_transition_allowed(
    string $fromStatus,
    string $toStatus,
    ?string $publishedGmt = null
): bool {
    if (! user_can_set_content_status($toStatus)) {
        return false;
    }

    $transitions = __observer()->filter->applyFilter('content.status.transitions', [
        'new' => ['draft', 'pending', 'scheduled', 'published'],
        'draft' => ['draft', 'pending', 'scheduled', 'published', 'archived'],
        'pending' => ['pending', 'draft', 'scheduled', 'published', 'archived'],
        'scheduled' => ['scheduled', 'draft', 'published', 'archived'],
        'published' => ['published', 'archived'],
        'archived' => ['archived', 'draft'],
    ]);

    if (! isset($transitions[$fromStatus]) || ! in_array($toStatus, $transitions[$fromStatus], true)) {
        return false;
    }

    if ($toStatus === 'scheduled') {
        return user_can_schedule_content_for($publishedGmt);
    }

    return true;
}

/**
 * @param string $toStatus
 * @param string|null $publishedGmt
 * @return bool
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws InvalidArgumentException
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 */
function content_status_create_allowed(
    string $toStatus,
    ?string $publishedGmt = null
): bool {
    return content_status_transition_allowed(
        fromStatus: 'new',
        toStatus: $toStatus,
        publishedGmt: $publishedGmt
    );
}
