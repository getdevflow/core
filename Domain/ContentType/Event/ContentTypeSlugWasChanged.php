<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Event;

use App\Domain\ContentType\ValueObject\ContentTypeId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ContentTypeSlugWasChanged extends AggregateChanged
{
    private ContentTypeId $contentTypeId;

    private StringLiteral $contentTypeSlug;

    public static function withData(
        ContentTypeId $contentTypeId,
        StringLiteral $contentTypeSlug
    ): ContentTypeSlugWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $contentTypeId,
            payload: [
                'content_type_slug' => $contentTypeSlug->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'contenttype',
            ],
        );

        $event->contentTypeId = $contentTypeId;
        $event->contentTypeSlug = $contentTypeSlug;

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

    public function contentTypeSlug(): StringLiteral
    {
        if (!isset($this->contentTypeSlug)) {
            $this->contentTypeSlug = StringLiteral::fromNative($this->payload()['content_type_slug']);
        }

        return $this->contentTypeSlug;
    }
}
