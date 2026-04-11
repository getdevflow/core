<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

class ContentAttributeWasChanged extends AggregateChanged
{
    private ContentId $id;

    private ArrayLiteral $attribute;

    public static function withData(
        ContentId $id,
        ArrayLiteral $attribute
    ): ContentAttributeWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_attribute' => $attribute->toNative()
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
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

    public function contentAttribute(): ArrayLiteral
    {
        if (!isset($this->attribute)) {
            $this->attribute = ArrayLiteral::fromNative($this->payload()['content_attribute']);
        }

        return $this->attribute;
    }
}
