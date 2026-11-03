<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use App\Shared\Services\EventDateTime;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;

final class ContentModifiedWasChanged extends AggregateChanged
{
    private ContentId $id;

    private DateTimeInterface $modified;

    public static function withData(
        ContentId $id,
        DateTimeInterface $modified,
    ): ContentModifiedWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                ...EventDateTime::payload('content_modified', $modified),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->modified = $modified;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function contentId(): ContentId
    {
        if (!isset($this->id)) {
            $this->id = ContentId::fromString($this->aggregateId()->__toString());
        }

        return $this->id;
    }

    public function contentModified(): DateTimeInterface
    {
        if (!isset($this->modified)) {
            $this->modified = EventDateTime::parse($this->payload(), 'content_modified');
        }

        return $this->modified;
    }
}
