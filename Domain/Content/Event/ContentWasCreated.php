<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\Services\DateTime;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

use function Qubus\Support\Helpers\is_null__;

class ContentWasCreated extends AggregateChanged
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

    public static function withData(
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
    ): ContentWasCreated|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_id' => $id->toNative(),
                'content_title' => $title->toNative(),
                'content_slug' => $slug->toNative(),
                'content_body' => $body->toNative(),
                'content_attribute' => $attribute->toNative(),
                'content_author' => $author->toNative(),
                'content_type' => $type->toNative(),
                'content_parent' => $parent?->toNative(),
                'content_sidebar' => $sidebar->toNative(),
                'content_show_in_menu' => $showInMenu->toNative(),
                'content_show_in_search' => $showInSearch->toNative(),
                'content_featured_image' => $featuredImage->toNative(),
                'content_status' => $status->toNative(),
                'content_created' => $created->format('Y-m-d H:i:s'),
                'content_created_gmt' => $createdGmt->format('Y-m-d H:i:s'),
                'content_published' => $published->format('Y-m-d H:i:s'),
                'content_published_gmt' => $publishedGmt->format('Y-m-d H:i:s'),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ],
        );

        $event->id = $id;
        $event->title = $title;
        $event->slug = $slug;
        $event->body = $body;
        $event->author = $author;
        $event->type = $type;
        $event->sidebar = $sidebar;
        $event->showInMenu = $showInMenu;
        $event->showInSearch = $showInSearch;
        $event->featuredImage = $featuredImage;
        $event->status = $status;
        $event->created = $created;
        $event->createdGmt = $createdGmt;
        $event->published = $published;
        $event->publishedGmt = $publishedGmt;
        $event->parent = $parent;
        $event->attribute = $attribute;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function contentId(): ContentId|AggregateId
    {
        if (!isset($this->id)) {
            $this->id = ContentId::fromString(contentId: $this->aggregateId()->__toString());
        }

        return $this->id;
    }

    public function contentTitle(): StringLiteral
    {
        if (!isset($this->title)) {
            $this->title = StringLiteral::fromNative($this->payload()['content_title']);
        }

        return $this->title;
    }

    public function contentSlug(): StringLiteral
    {
        if (!isset($this->slug)) {
            $this->slug = StringLiteral::fromNative($this->payload()['content_slug']);
        }

        return $this->slug;
    }

    public function contentBody(): StringLiteral
    {
        if (!isset($this->body)) {
            $this->body = StringLiteral::fromNative($this->payload()['content_body']);
        }

        return $this->body;
    }

    /**
     * @throws TypeException
     */
    public function contentAuthor(): UserId
    {
        if (!isset($this->author)) {
            $this->author = UserId::fromString($this->payload()['content_author']);
        }

        return $this->author;
    }

    public function contentTypeSlug(): StringLiteral
    {
        if (!isset($this->type)) {
            $this->type = StringLiteral::fromNative($this->payload()['content_type']);
        }

        return $this->type;
    }

    /**
     * @throws TypeException
     */
    public function contentParent(): ?ContentId
    {
        if (is_null__($this->parent)) {
            $this->parent = null;
        } else {
            $this->parent = ContentId::fromString($this->payload()['content_parent']);
        }

        return $this->parent;
    }

    /**
     * @throws TypeException
     */
    public function contentSidebar(): IntegerNumber
    {
        if (!isset($this->sidebar)) {
            $this->sidebar = IntegerNumber::fromNative($this->payload()['content_sidebar']);
        }

        return $this->sidebar;
    }

    /**
     * @throws TypeException
     */
    public function contentShowInMenu(): IntegerNumber
    {
        if (!isset($this->showInMenu)) {
            $this->showInMenu = IntegerNumber::fromNative($this->payload()['content_show_in_menu']);
        }

        return $this->showInMenu;
    }

    /**
     * @throws TypeException
     */
    public function contentShowInSearch(): IntegerNumber
    {
        if (!isset($this->showInSearch)) {
            $this->showInSearch = IntegerNumber::fromNative($this->payload()['content_show_in_search']);
        }

        return $this->showInSearch;
    }

    public function contentFeaturedImage(): StringLiteral
    {
        if (!isset($this->featuredImage)) {
            $this->featuredImage = StringLiteral::fromNative($this->payload()['content_featured_image']);
        }

        return $this->featuredImage;
    }

    public function contentStatus(): StringLiteral
    {
        if (!isset($this->status)) {
            $this->status = StringLiteral::fromNative($this->payload()['content_status']);
        }

        return $this->status;
    }

    public function contentCreated(): DateTimeInterface
    {
        if (!isset($this->created)) {
            $this->created = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['content_created'])->getDateTime()
            );
        }

        return $this->created;
    }

    public function contentCreatedGmt(): DateTimeInterface
    {
        if (!isset($this->createdGmt)) {
            $this->createdGmt = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['content_created_gmt'])->getDateTime()
            );
        }

        return $this->createdGmt;
    }

    public function contentPublished(): DateTimeInterface
    {
        if (!isset($this->published)) {
            $this->published = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['content_published'])->getDateTime()
            );
        }

        return $this->published;
    }

    public function contentPublishedGmt(): DateTimeInterface
    {
        if (!isset($this->publishedGmt)) {
            $this->publishedGmt = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['content_published_gmt'])->getDateTime()
            );
        }

        return $this->publishedGmt;
    }

    public function contentAttribute(): ArrayLiteral
    {
        if (!isset($this->attribute)) {
            $this->attribute = ArrayLiteral::fromNative($this->payload()['content_attribute']);
        }

        return $this->attribute;
    }
}
