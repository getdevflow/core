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

class ContentMetaWasChanged extends AggregateChanged
{
    private ContentId $id;

    private ArrayLiteral $meta;

    public static function withData(
        ContentId $id,
        ArrayLiteral $meta
    ): ContentMetaWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'meta' => $meta->toNative()
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->meta = $meta;

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

    public function contentmeta(): ArrayLiteral
    {
        if (!isset($this->meta)) {
            $this->meta = ArrayLiteral::fromNative($this->payload()['meta']);
        }

        return $this->meta;
    }
}
