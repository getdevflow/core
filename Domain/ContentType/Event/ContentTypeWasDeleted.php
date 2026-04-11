<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Event;

use App\Domain\ContentType\ValueObject\ContentTypeId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

final class ContentTypeWasDeleted extends AggregateChanged
{
    private ContentTypeId $contentTypeId;

    public static function withData(
        ContentTypeId $contentTypeId,
    ): ContentTypeWasDeleted|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $contentTypeId,
            payload: [],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'contenttype',
            ],
        );

        $event->contentTypeId = $contentTypeId;

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
}
