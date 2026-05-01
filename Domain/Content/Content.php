<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Content\Event\ContentAuthorWasChanged;
use App\Domain\Content\Event\ContentBodyWasChanged;
use App\Domain\Content\Event\ContentFeaturedImageWasChanged;
use App\Domain\Content\Event\ContentAttributeWasChanged;
use App\Domain\Content\Event\ContentModifiedGmtWasChanged;
use App\Domain\Content\Event\ContentModifiedWasChanged;
use App\Domain\Content\Event\ContentParentWasChanged;
use App\Domain\Content\Event\ContentParentWasRemoved;
use App\Domain\Content\Event\ContentPublishedGmtWasChanged;
use App\Domain\Content\Event\ContentPublishedWasChanged;
use App\Domain\Content\Event\ContentShowInMenuWasChanged;
use App\Domain\Content\Event\ContentShowInSearchWasChanged;
use App\Domain\Content\Event\ContentSidebarWasChanged;
use App\Domain\Content\Event\ContentSlugWasChanged;
use App\Domain\Content\Event\ContentStatusWasChanged;
use App\Domain\Content\Event\ContentTitleWasChanged;
use App\Domain\Content\Event\ContentTypeWasChanged;
use App\Domain\Content\Event\ContentWasCreated;
use App\Domain\Content\Event\ContentWasDeleted;
use App\Domain\Content\ValueObject\ContentId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Domain\Aggregate\AggregateRoot;
use Codefy\Domain\Aggregate\EventSourcedAggregate;
use DateTimeInterface;
use Exception;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

use function Codefy\Framework\Helpers\trans;
use function Qubus\Support\Helpers\is_null__;

final class Content extends EventSourcedAggregate implements AggregateRoot
{
    private ContentId $id;

    private StringLiteral $title;

    private StringLiteral $slug;

    private StringLiteral $body;

    private UserId $author;

    private StringLiteral $type;

    private ?ContentId $parent = null;

    private IntegerNumber $sidebar;

    private IntegerNumber $showInMenu;

    private IntegerNumber $showInSearch;

    private ?StringLiteral $featuredImage = null;

    private ArrayLiteral $attribute;

    private StringLiteral $status;

    private DateTimeInterface $created;

    private DateTimeInterface $createdGmt;

    private DateTimeInterface $published;

    private DateTimeInterface $publishedGmt;

    private ?DateTimeInterface $modified = null;

    private ?DateTimeInterface $modifiedGmt = null;

    public static function createContent(
        ContentId $id,
        StringLiteral $title,
        StringLiteral $slug,
        StringLiteral $body,
        UserId $author,
        StringLiteral $type,
        IntegerNumber $sidebar,
        IntegerNumber $showInMenu,
        IntegerNumber $showInSearch,
        StringLiteral $featuredImage,
        StringLiteral $status,
        DateTimeInterface $created,
        DateTimeInterface $createdGmt,
        DateTimeInterface $published,
        DateTimeInterface $publishedGmt,
        ArrayLiteral $attribute,
        ?ContentId $parent = null,
    ): Content {
        $content = self::root(aggregateId: $id);

        $content->recordApplyAndPublishThat(
            ContentWasCreated::withData(
                id: $id,
                title: $title,
                slug: $slug,
                body: $body,
                author: $author,
                type: $type,
                sidebar: $sidebar,
                showInMenu: $showInMenu,
                showInSearch: $showInSearch,
                featuredImage: $featuredImage,
                status: $status,
                created: $created,
                createdGmt: $createdGmt,
                published: $published,
                publishedGmt: $publishedGmt,
                attribute: $attribute,
                parent: $parent,
            )
        );

        return $content;
    }

    /**
     * @throws TypeException
     */
    public static function fromNative(string $contentId): Content
    {
        return self::root(aggregateId: ContentId::fromString($contentId));
    }

    public function contentId(): ContentId
    {
        return $this->id;
    }

    public function contentTitle(): StringLiteral
    {
        return $this->title;
    }

    public function contentSlug(): StringLiteral
    {
        return $this->slug;
    }

    public function contentBody(): StringLiteral
    {
        return $this->body;
    }

    public function contentAuthor(): UserId
    {
        return $this->author;
    }

    public function contentTypeSlug(): StringLiteral
    {
        return $this->type;
    }

    public function contentParent(): ?ContentId
    {
        return $this->parent;
    }

    public function contentSidebar(): IntegerNumber
    {
        return $this->sidebar;
    }

    public function contentShowInMenu(): IntegerNumber
    {
        return $this->showInMenu;
    }

    public function contentShowInSearch(): IntegerNumber
    {
        return $this->showInSearch;
    }

    public function contentFeaturedImage(): StringLiteral
    {
        return $this->featuredImage;
    }
    public function contentStatus(): StringLiteral
    {
        return $this->status;
    }

    public function contentCreated(): DateTimeInterface
    {
        return $this->created;
    }

    public function contentCreatedGmt(): DateTimeInterface
    {
        return $this->createdGmt;
    }

    public function contentPublished(): DateTimeInterface
    {
        return $this->published;
    }

    public function contentPublishedGmt(): DateTimeInterface
    {
        return $this->publishedGmt;
    }

    public function contentModified(): DateTimeInterface
    {
        return $this->modified;
    }

    public function contentModifiedGmt(): DateTimeInterface
    {
        return $this->modifiedGmt;
    }

    public function contentAttribute(): ArrayLiteral
    {
        return $this->attribute;
    }

    /**
     * @throws Exception
     */
    public function changeContentTitle(StringLiteral $contentTitle): void
    {
        if ($contentTitle->isEmpty()) {
            throw new Exception(message: trans('Content title cannot be empty.'));
        }
        if ($contentTitle->equals($this->title)) {
            return;
        }
        $this->recordApplyAndPublishThat(
            event: ContentTitleWasChanged::withData(id: $this->id, title: $contentTitle)
        );
    }

    /**
     * @throws Exception
     */
    public function changeContentSlug(StringLiteral $contentSlug): void
    {
        if ($contentSlug->isEmpty()) {
            throw new Exception(message: trans('Content slug cannot be empty.'));
        }
        if ($contentSlug->equals($this->slug)) {
            return;
        }
        $this->recordApplyAndPublishThat(
            event: ContentSlugWasChanged::withData(id: $this->id, slug: $contentSlug)
        );
    }

    /**
     * @throws Exception
     */
    public function changeContentBody(StringLiteral $contentBody): void
    {
        if ($contentBody->equals($this->body)) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentBodyWasChanged::withData($this->id, $contentBody));
    }

    /**
     * @throws Exception
     */
    public function changeContentAuthor(UserId $contentAuthor): void
    {
        if ($contentAuthor->isEmpty()) {
            throw new Exception(message: trans('Content author cannot be empty.'));
        }
        if ($contentAuthor->equals($this->author)) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentAuthorWasChanged::withData($this->id, $contentAuthor));
    }

    /**
     * @throws Exception
     */
    public function changeContentType(StringLiteral $contentTypeSlug): void
    {
        if ($contentTypeSlug->isEmpty()) {
            throw new Exception(message: trans('Content type cannot be empty.'));
        }
        if ($contentTypeSlug->equals($this->type)) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentTypeWasChanged::withData($this->id, $contentTypeSlug));
    }

    /**
     * @throws Exception
     */
    public function changeContentParent(ContentId $contentParent): void
    {
        if ($contentParent->isEmpty()) {
            return;
        }

        if (
                (!$contentParent->isEmpty() && !is_null__($this->parent)) &&
                $contentParent->equals($this->parent)
        ) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentParentWasChanged::withData($this->id, $contentParent));
    }

    /**
     * @throws Exception
     */
    public function changeContentParentWasRemoved(?ContentId $contentParent = null): void
    {
        if (
                (!is_null__($this->parent) && !is_null__($contentParent)) &&
                (!$contentParent->equals($this->parent))
        ) {
            return;
        }

        $this->recordApplyAndPublishThat(ContentParentWasRemoved::withData($this->id, $contentParent));
    }

    /**
     * @throws Exception
     */
    public function changeContentSidebar(IntegerNumber $contentSidebar): void
    {
        if ($contentSidebar->toInteger()->toNative() < 0) {
            throw new Exception(message: trans('Content sidebar must be an absolute integer.'));
        }
        if ($contentSidebar->equals($this->sidebar)) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentSidebarWasChanged::withData($this->id, $contentSidebar));
    }

    /**
     * @throws Exception
     */
    public function changeContentShowInMenu(IntegerNumber $showInMenu): void
    {
        if ($showInMenu->toInteger()->toNative() < 0) {
            throw new Exception(message: trans('Show in menu must be an absolute integer.'));
        }
        if ($showInMenu->equals($this->showInMenu)) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentShowInMenuWasChanged::withData($this->id, $showInMenu));
    }

    /**
     * @throws Exception
     */
    public function changeContentShowInSearch(IntegerNumber $showInSearch): void
    {
        if ($showInSearch->toInteger()->toNative() < 0) {
            throw new Exception(message: trans('Show in search must be an absolute integer.'));
        }
        if ($showInSearch->equals($this->showInSearch)) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentShowInSearchWasChanged::withData($this->id, $showInSearch));
    }

    /**
     * @throws Exception
     */
    public function changeContentFeaturedImage(StringLiteral $contentFeaturedImage): void
    {
        if ($contentFeaturedImage->equals($this->featuredImage)) {
            return;
        }
        $this->recordApplyAndPublishThat(
            ContentFeaturedImageWasChanged::withData($this->id, $contentFeaturedImage)
        );
    }

    /**
     * @throws Exception
     */
    public function changeContentStatus(StringLiteral $contentStatus): void
    {
        if ($contentStatus->isEmpty()) {
            throw new Exception(message: trans('Content status cannot be empty.'));
        }
        if ($contentStatus->equals($this->status)) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentStatusWasChanged::withData($this->id, $contentStatus));
    }

    /**
     * @throws Exception
     */
    public function changeContentPublished(DateTimeInterface $contentPublished): void
    {
        if (empty($this->published)) {
            throw new Exception(message: trans('Content published cannot be empty.'));
        }
        if ($this->published->getTimestamp() === $contentPublished->getTimestamp()) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentPublishedWasChanged::withData($this->id, $contentPublished));
    }

    /**
     * @throws Exception
     */
    public function changeContentPublishedGmt(DateTimeInterface $contentPublishedGmt): void
    {
        if (empty($this->publishedGmt)) {
            throw new Exception(message: trans('Content published gmt cannot be empty.'));
        }
        if ($this->publishedGmt->getTimestamp() === $contentPublishedGmt->getTimestamp()) {
            return;
        }
        $this->recordApplyAndPublishThat(
            ContentPublishedGmtWasChanged::withData($this->id, $contentPublishedGmt)
        );
    }

    /**
     * @throws Exception
     */
    public function changeContentModified(DateTimeInterface $contentModified): void
    {
        if (
                !is_null__($this->modified) &&
                ($this->modified->getTimestamp() === $contentModified->getTimestamp())
        ) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentModifiedWasChanged::withData($this->id, $contentModified));
    }

    /**
     * @throws Exception
     */
    public function changeContentModifiedGmt(DateTimeInterface $contentModifiedGmt): void
    {
        if (
                !is_null__($this->modifiedGmt) &&
                ($this->modifiedGmt->getTimestamp() === $contentModifiedGmt->getTimestamp())
        ) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentModifiedGmtWasChanged::withData($this->id, $contentModifiedGmt));
    }

    /**
     * @param ContentId $contentId
     * @return void
     * @throws Exception
     */
    public function changeContentDeleted(ContentId $contentId): void
    {
        if ($contentId->isEmpty()) {
            throw new Exception(message: trans('Content id cannot be null.'));
        }
        if (!$contentId->equals($this->id)) {
            return;
        }
        $this->recordApplyAndPublishThat(ContentWasDeleted::withData($this->id));
    }

    public function changeContentAttribute(ArrayLiteral $attribute): void
    {
        if ($attribute->equals($this->attribute)) {
            return;
        }

        $this->recordApplyAndPublishThat(ContentAttributeWasChanged::withData($this->id, $attribute));
    }

    /**
     * @throws TypeException
     */
    public function whenContentWasCreated(ContentWasCreated $event): void
    {
        $this->id = $event->contentId();
        $this->title = $event->contentTitle();
        $this->slug = $event->contentSlug();
        $this->body = $event->contentBody();
        $this->author = $event->contentAuthor();
        $this->type = $event->contentTypeSlug();
        $this->sidebar = $event->contentSidebar();
        $this->showInMenu = $event->contentShowInMenu();
        $this->showInSearch = $event->contentShowInSearch();
        $this->featuredImage = $event->contentFeaturedImage();
        $this->status = $event->contentStatus();
        $this->created = $event->contentCreated();
        $this->createdGmt = $event->contentCreatedGmt();
        $this->published = $event->contentPublished();
        $this->publishedGmt = $event->contentPublishedGmt();
        $this->attribute = $event->contentAttribute();
        $this->parent = $event->contentParent();
    }

    /**
     * @throws TypeException
     */
    public function whenContentTitleWasChanged(ContentTitleWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->title = $event->contentTitle();
    }

    /**
     * @throws TypeException
     */
    public function whenContentSlugWasChanged(ContentSlugWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->slug = $event->contentSlug();
    }

    /**
     * @throws TypeException
     */
    public function whenContentBodyWasChanged(ContentBodyWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->body = $event->contentBody();
    }

    /**
     * @throws TypeException
     */
    public function whenContentAuthorWasChanged(ContentAuthorWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->author = $event->contentAuthor();
    }

    /**
     * @throws TypeException
     */
    public function whenContentTypeWasChanged(ContentTypeWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->type = $event->contentTypeSlug();
    }

    /**
     * @throws TypeException
     */
    public function whenContentParentWasChanged(ContentParentWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->parent = $event->contentParent();
    }

    /**
     * @throws TypeException
     */
    public function whenContentSidebarWasChanged(ContentSidebarWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->sidebar = $event->contentSidebar();
    }

    /**
     * @throws TypeException
     */
    public function whenContentShowInMenuWasChanged(ContentShowInMenuWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->showInMenu = $event->contentShowInMenu();
    }

    /**
     * @throws TypeException
     */
    public function whenContentShowInSearchWasChanged(ContentShowInSearchWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->showInSearch = $event->contentShowInSearch();
    }

    /**
     * @throws TypeException
     */
    public function whenContentFeaturedImageWasChanged(ContentFeaturedImageWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->featuredImage = $event->contentFeaturedImage();
    }

    /**
     * @throws TypeException
     */
    public function whenContentAttributesWasChanged(ContentAttributeWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->attribute = $event->contentAttribute();
    }

    /**
     * @throws TypeException
     */
    public function whenContentStatusWasChanged(ContentStatusWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->status = $event->contentStatus();
    }

    /**
     * @throws TypeException
     */
    public function whenContentPublishedWasChanged(ContentPublishedWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->published = $event->contentPublished();
    }

    /**
     * @throws TypeException
     */
    public function whenContentPublishedGmtWasChanged(ContentPublishedGmtWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->publishedGmt = $event->contentPublishedGmt();
    }

    /**
     * @throws TypeException
     */
    public function whenContentModifiedWasChanged(ContentModifiedWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->modified = $event->contentModified();
    }

    /**
     * @throws TypeException
     */
    public function whenContentModifiedGmtWasChanged(ContentModifiedGmtWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->modifiedGmt = $event->contentModifiedGmt();
    }

    /**
     * @throws TypeException
     */
    public function whenContentParentWasRemoved(ContentParentWasRemoved $event): void
    {
        $this->id = $event->contentId();
        $this->parent = $event->contentParent();
    }

    /**
     * @throws TypeException
     */
    public function whenContentAttributeWasChanged(ContentAttributeWasChanged $event): void
    {
        $this->id = $event->contentId();
        $this->attribute = $event->contentAttribute();
    }

    /**
     * @throws TypeException
     */
    public function whenContentWasDeleted(ContentWasDeleted $event): void
    {
        $this->id = $event->contentId();
    }
}
