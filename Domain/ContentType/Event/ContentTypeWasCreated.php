<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Event;

use App\Domain\ContentType\ValueObject\ContentTypeId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ContentTypeWasCreated extends AggregateChanged
{
    private ContentTypeId $contentTypeId;

    private StringLiteral $contentTypeTitle;

    private StringLiteral $contentTypeSlug;

    private StringLiteral $contentTypeDescription;

    public static function withData(
        ContentTypeId $contentTypeId,
        StringLiteral $contentTypeTitle,
        StringLiteral $contentTypeSlug,
        StringLiteral $contentTypeDescription
    ): ContentTypeWasCreated|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $contentTypeId,
            payload: [
                'content_type_id' => $contentTypeId->toNative(),
                'content_type_title' => $contentTypeTitle->toNative(),
                'content_type_slug' => $contentTypeSlug->toNative(),
                'content_type_description' => $contentTypeDescription->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'contenttype'
            ]
        );

        $event->contentTypeId = $contentTypeId;
        $event->contentTypeTitle = $contentTypeTitle;
        $event->contentTypeSlug = $contentTypeSlug;
        $event->contentTypeDescription = $contentTypeDescription;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function contentTypeId(): ContentTypeId
    {
        if (!isset($this->contentTypeId)) {
            $this->contentTypeId = ContentTypeId::fromString($this->aggregateId()->__toString());
        }

        return $this->contentTypeId;
    }

    public function contentTypeTitle(): StringLiteral
    {
        if (!isset($this->contentTypeTitle)) {
            $this->contentTypeTitle = StringLiteral::fromNative($this->payload()['content_type_title']);
        }

        return $this->contentTypeTitle;
    }

    public function contentTypeSlug(): StringLiteral
    {
        if (!isset($this->contentTypeSlug)) {
            $this->contentTypeSlug = StringLiteral::fromNative($this->payload()['content_type_slug']);
        }

        return $this->contentTypeSlug;
    }

    public function contentTypeDescription(): StringLiteral
    {
        if (!isset($this->contentTypeDescription)) {
            $this->contentTypeDescription = StringLiteral::fromNative($this->payload()['content_type_description']);
        }

        return $this->contentTypeDescription;
    }
}
