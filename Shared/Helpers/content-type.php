<?php

declare(strict_types=1);

namespace App\Shared\Helpers;

use App\Domain\ContentType\Model\ContentType;
use App\Domain\ContentType\Query\FindContentTypeByIdQuery;
use App\Domain\ContentType\Query\FindContentTypesQuery;
use App\Domain\ContentType\Command\CreateContentTypeCommand;
use App\Domain\ContentType\Command\DeleteContentTypeCommand;
use App\Domain\ContentType\Command\UpdateContentTypeCommand;
use App\Domain\ContentType\ContentTypeError;
use App\Domain\ContentType\Query\FindContentTypeBySlugQuery;
use App\Domain\ContentType\ValueObject\ContentTypeId;
use App\Shared\Services\Sanitizer;
use Codefy\CommandBus\Exceptions\CommandPropertyNotFoundException;
use Codefy\CommandBus\Exceptions\UnresolvableCommandHandlerException;
use Codefy\Framework\Factory\FileLoggerFactory;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Error\Error;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\ValueObjects\StringLiteral\StringLiteral;
use ReflectionException;

use function Codefy\Framework\Helpers\ask;
use function Codefy\Framework\Helpers\command;
use function Qubus\Security\Helpers\__observer;
use function Qubus\Security\Helpers\esc_html__;
use function Qubus\Security\Helpers\esc_url;
use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;

/**
 * Retrieve content type by a given field from the content_type table.
 *
 * @file core/Shared/Helpers/content-type.php
 * @param string $field The field to retrieve the content_type with
 *                      (id = content_type_id, slug = content_type_slug).
 * @param string $value A value for $field.
 * @return false|object
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 */
function get_content_type_by(string $field, string $value): false|object
{
    $query = match ($field) {
        'id' => new FindContentTypeByIdQuery(['id' => ContentTypeId::fromString($value)]),
        'slug' => new FindContentTypeBySlugQuery(['slug' => new StringLiteral($value)]),
    };

    $results = ask($query);

    if (is_null__($results) || is_false__($results)) {
        return false;
    }

    return new ContentType((array) $results);
}

/**
 * A function which retrieves a content type title.
 *
 * Purpose of this function is for the `content.type.title`
 * filter.
 *
 * @file core/Shared/Helpers/content-type.php
 * @param string $contentTypeId The unique id of a content_type.
 * @return string Content Type title or '' on failure.
 * @throws CommandPropertyNotFoundException
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 * @throws Exception
 */
function get_content_type_title(string $contentTypeId): string
{
    /** @var ContentType $contentType */
    $contentType = get_content_type_by('id', $contentTypeId);

    if (!$contentType) {
        return '';
    }

    $title = $contentType->title;
    /**
     * Filters the content_type title.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string    $title The content_type's title.
     * @param string    $contentTypeId The content_type id.
     */
    return __observer()->filter->applyFilter('content.type.title', $title, $contentTypeId);
}

/**
 * A function which retrieves a content_type slug.
 *
 * Purpose of this function is for the `content.type.slug`
 * filter.
 *
 * @file core/Shared/Helpers/content-type.php
 * @param string $contentTypeId The unique id of a content_type.
 * @return string Content Type slug or '' on failure.
 * @throws CommandPropertyNotFoundException
 * @throws Exception
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 */
function get_content_type_slug(string $contentTypeId): string
{
    /** @var ContentType $contentType */
    $contentType = get_content_type_by('id', $contentTypeId);

    if (!$contentType) {
        return '';
    }

    $slug = $contentType->slug;
    /**
     * Filters the content_type's slug.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string    $slug The content_type's slug.
     * @param string    $contentTypeId The content_type id.
     */
    return __observer()->filter->applyFilter('content.type.slug', $slug, $contentTypeId);
}

/**
 * A function which retrieves a content_type description.
 *
 * Purpose of this function is for the `content.type.description`
 * filter.
 *
 * @file core/Shared/Helpers/content-type.php
 * @param string $contentTypeId The unique id of a content_type.
 * @return string Content Type description or '' on failure.
 * @throws CommandPropertyNotFoundException
 * @throws Exception
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 */
function get_content_type_description(string $contentTypeId): string
{
    /** @var ContentType $contentType */
    $contentType = get_content_type_by('id', $contentTypeId);

    if (!$contentType) {
        return '';
    }

    $description = $contentType->description;
    /**
     * Filters the content_type's description.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string    $description The content_type's description.
     * @param string    $contentTypeId The content_type id.
     */
    return __observer()->filter->applyFilter('content.type.description', $description, $contentTypeId);
}

/**
 * A function which retrieves a content_type's permalink.
 *
 * Purpose of this function is for the `content.type.permalink`
 * filter.
 *
 * @file core/Shared/Helpers/content-type.php
 * @param string $contentTypeId Content Type id.
 * @return string
 * @throws CommandPropertyNotFoundException
 * @throws Exception
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableQueryHandlerException
 */
function get_content_type_permalink(string $contentTypeId): string
{
    $link = esc_url(site_url(get_content_type_slug($contentTypeId) . '/'));
    /**
     * Filters the content_type's link.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string    $link The content_type's permalink.
     * @param string    $contentTypeId The content_type id.
     */
    return __observer()->filter->applyFilter('content.type.permalink', $link, $contentTypeId);
}

/**
 * Creates a unique content_type slug.
 *
 * @param string $originalSlug Original slug of content_type.
 * @param string $originalTitle Original title of content_type.
 * @param string|null $contentTypeId Unique content_type id.
 * @return string Unique content_type slug.
 * @throws Exception
 * @throws ReflectionException
 */
function cms_unique_content_type_slug(
    string $originalSlug,
    string $originalTitle,
    ?string $contentTypeId = null
): string {
    if (is_null__($contentTypeId)) {
        $contentTypeSlug = cms_slugify($originalTitle, 'content_type');
    } elseif (if_content_type_slug_exists($contentTypeId, $originalSlug)) {
        $contentTypeSlug = cms_slugify($originalTitle, 'content_type');
    } else {
        $contentTypeSlug = $originalSlug;
    }
    /**
     * Filters the unique content_type slug before returned.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string    $contentTypeSlug Unique content_type slug.
     * @param string    $originalSlug    The content_type's original slug.
     * @param string    $originalTitle   The content_type's original title before slugified.
     * @param string    $contentTypeId   The content_type's unique id.
     */
    return __observer()->filter->applyFilter(
        'cms.unique.content.type.slug',
        $contentTypeSlug,
        $originalSlug,
        $originalTitle,
        $contentTypeId
    );
}

/**
 * Insert or update a content_type.
 *
 * @file core/Shared/Helpers/content-type.php
 * @param array|ServerRequestInterface|ContentType $contentTypeData An array of data that is used for insert or update.
 * @return string|null|Error The newly created content_type's content_type_id, null or Error if the content_type could
 *                           not be created or updated.
 * @throws CommandPropertyNotFoundException
 * @throws Exception
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 * @throws UnresolvableQueryHandlerException
 */
function cms_insert_content_type(array|ServerRequestInterface|ContentType $contentTypeData): string|Error|null
{
    if ($contentTypeData instanceof ServerRequestInterface) {
        $contentTypeData = $contentTypeData->getParsedBody();
    } elseif ($contentTypeData instanceof ContentType) {
        $contentTypeData = $contentTypeData->toArray();
    }

    // Are we updating or creating?
    if (!empty($contentTypeData['id']) && !is_false__(get_content_type_by('id', $contentTypeData['id']))) {
        $update = true;
        $contentTypeId = ContentTypeId::fromString($contentTypeData['id']);

        /** @var ContentType $contentTypeBefore */
        $contentTypeBefore = get_content_type_by('id', $contentTypeId->toNative());

        if (is_null__($contentTypeBefore)) {
            return new ContentTypeError(message: esc_html__(string: 'Invalid content_type id.', domain: 'devflow'));
        }

        $previousSlug = get_content_type_slug($contentTypeId->toNative());
        /**
         * Fires immediately before a content_type is inserted into the content_type table.
         *
         * @file core/Shared/Helpers/content-type.php
         * @param string  $previousSlug   Slug of the content before it was created.
         *                                or updated.
         * @param string  $contentTypeId  The content_type's content_type_id.
         * @param bool    $update         Whether this is an existing content_type or a new content_type.
         */
        __observer()->action->doAction('content_type_previous_slug', $previousSlug, $contentTypeId, $update);

        /**
         * Create new content_type object.
         */
        $contentType = new ContentType();
        $contentType->id = Sanitizer::item($contentTypeId->toNative());
        $contentType->title = Sanitizer::item($contentTypeData['title']);
        $contentType->slug = Sanitizer::item($contentTypeData['slug']);
        $contentType->description = Sanitizer::item($contentTypeData['description']);
    } else {
        $update = false;
        $previousSlug = null;
        $contentTypeId = new ContentTypeId();
        /**
         * Fires immediately before a content_type is inserted into the content_type table.
         *
         * @file core/Shared/Helpers/content-type.php
         * @param string  $previousSlug   Slug of the content_type before it is created.
         *                                or updated.
         * @param string  $contentTypeId  The content_type's content_type_id.
         * @param bool    $update         Whether this is an existing content_type or a new content_type.
         */
        __observer()->action->doAction(
            'content_type_previous_slug',
            $previousSlug,
            $contentTypeId->toNative(),
            $update
        );

        /**
         * Create new content_type object.
         */
        $contentType = new ContentType();
        $contentType->id = Sanitizer::item($contentTypeId->toNative());
        $contentType->title = Sanitizer::item($contentTypeData['title']);
        $contentType->slug = Sanitizer::item($contentTypeData['slug']);
        $contentType->description = Sanitizer::item($contentTypeData['description']);
    }

    if (isset($contentTypeData['slug'])) {
        /**
         * cms_unique_content_type_slug will take the original slug supplied and check
         * to make sure that it is unique. If not unique, it will make it unique
         * by adding a number at the end.
         */
        $contentTypeSlug = cms_unique_content_type_slug(
            $contentType->slug,
            $contentType->title,
            $contentType->id
        );
    } else {
        /**
         * For an update, don't modify the post_slug if it
         * wasn't supplied as an argument.
         */
        $contentTypeSlug = $contentTypeBefore->slug;
    }

    if ($update) {
        $command = new UpdateContentTypeCommand([
            'id' => ContentTypeId::fromString($contentType->id),
            'title' => new StringLiteral($contentType->title),
            'slug' => new StringLiteral($contentTypeSlug),
            'description' => new StringLiteral($contentType->description),
        ]);

        command($command);

    } else {
        $command = new CreateContentTypeCommand([
            'id' => ContentTypeId::fromString($contentType->id),
            'title' => new StringLiteral($contentType->title),
            'slug' => new StringLiteral($contentTypeSlug),
            'description' => new StringLiteral($contentType->description),
        ]);

        command($command);
    }

    $contentType = get_content_type_by('id', $contentTypeId->toNative());

    if ($update) {
        /**
         * Action hook triggered after existing content_type has been updated.
         *
         * @file core/Shared/Helpers/content-type.php
         * @param string   $contentTypeId    Content Type id.
         * @param object   $contentType      Content Type object.
         */
        __observer()->action->doAction('update_content_type', $contentTypeId->toNative(), $contentType);
        /** @var ContentType $contentTypeAfter */
        $contentTypeAfter = get_content_type_by('id', $contentTypeId->toNative());

        /**
         * Action hook triggered after existing content type has been updated.
         *
         * @file core/Shared/Helpers/content-type.php
         * @param string    $contentTypeId      Content Type id.
         * @param object    $contentTypeAfter   Content Type object following the update.
         * @param object    $contentTypeBefore  Content Type object before the update.
         */
        __observer()->action->doAction(
            'content_type_updated',
            $contentTypeId->toNative(),
            $contentTypeAfter,
            $contentTypeBefore
        );
    }

    /**
     * Action hook triggered after content_type has been saved.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string $contentTypeId  The content_type's id.
     * @param array  $contentType    Content Type object.
     * @param bool   $update         Whether this is an existing content_type or a new content_type.
     */
    __observer()->action->doAction(
        'cms_after_insert_content_type_data',
        $contentTypeId->toNative(),
        $contentType,
        $update
    );

    return $contentTypeId->toNative();
}

/**
 * Update a Content Type in the content_type table.
 *
 * See {@see cms_insert_content_type()} For what fields can be set in $contentTypeData.
 *
 * @file core/Shared/Helpers/content-type.php
 * @param array|ServerRequestInterface|ContentType $contentTypeData An array of content_type data
 *                                                                  or a content_type object.
 * @return string|null|Error The updated content_type's id, Exception or return null if
 *                           content_type could not be updated.
 * @throws CommandPropertyNotFoundException
 * @throws ContainerExceptionInterface
 * @throws Exception
 * @throws NotFoundExceptionInterface
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 * @throws UnresolvableQueryHandlerException
 */
function cms_update_content_type(array|ServerRequestInterface|ContentType $contentTypeData): Error|string|null
{
    if ($contentTypeData instanceof ServerRequestInterface) {
        $contentTypeData = $contentTypeData->getParsedBody();
    } elseif ($contentTypeData instanceof ContentType) {
        $contentTypeData = $contentTypeData->toArray();
    }

    // First, get all the original fields.
    /** @var ContentType $contenttype */
    $contenttype = get_content_type_by('id', $contentTypeData['id']);

    if (is_false__($contenttype)) {
        return new ContentTypeError(message: esc_html__(string: 'Invalid content type id.', domain: 'devflow'));
    }

    // Merge old and new fields with new fields overwriting old ones.
    $_contenttypedata = array_merge($contenttype->toArray(), $contentTypeData);

    return cms_insert_content_type($_contenttypedata);
}

/**
 * Deletes a content_type from the content_type document.
 *
 * @file core/Shared/Helpers/content-type.php
 * @param string $contentTypeId The id of the content_type to delete.
 * @return false|string|Error ContentType id or false|Error on failure.
 * @throws CommandPropertyNotFoundException
 * @throws Exception
 * @throws ReflectionException
 * @throws TypeException
 * @throws UnresolvableCommandHandlerException
 * @throws UnresolvableQueryHandlerException
 */
function cms_delete_content_type(string $contentTypeId): false|string|Error
{
    /** @var ContentType $contenttype */
    $contenttype = get_content_type_by('id', $contentTypeId);

    if (is_false__($contenttype)) {
        return false;
    }

    /**
     * Action hook fires before a content_type is deleted.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string $contentTypeId ContentType id.
     */
    __observer()->action->doAction('before_delete_content_type', $contentTypeId);

    /**
     * Action hook fires immediately before a content_type is deleted from the
     * content_type table.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string $contentTypeId ContentType ID.
     */
    __observer()->action->doAction('delete_content_type', $contentTypeId);

    try {
        $command = new DeleteContentTypeCommand([
            'id' => ContentTypeId::fromString($contentTypeId),
        ]);

        command($command);
    } catch (PDOException $e) {
        FileLoggerFactory::getLogger()->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Content Type Function' => 'cms_delete_content_type'
            ]
        );

        return new ContentTypeError(
            message: esc_html__(
                string: 'The system was not able to delete the content type.',
                domain: 'devflow'
            )
        );
    }

    /**
     * Action hook fires immediately after a content_type is deleted from the content_type table.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string $contentTypeId ContentType id.
     */
    __observer()->action->doAction('deleted_content_type', $contentTypeId);

    /**
     * Action hook fires after a content_type is deleted.
     *
     * @file core/Shared/Helpers/content-type.php
     * @param string $contentTypeId ContentType id.
     */
    __observer()->action->doAction('after_delete_contenttype', $contentTypeId);

    return $contenttype->id;
}

/**
 * Returns all content types.
 *
 * @file core/Shared/Helpers/content-type.php
 * @return array
 * @throws ReflectionException
 * @throws UnresolvableQueryHandlerException
 */
function get_all_content_types(): array
{
    return ask(new FindContentTypesQuery());
}
