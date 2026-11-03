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

final class ContentPublishedGmtWasChanged extends AggregateChanged
{
    private ContentId $id;

    private DateTimeInterface $publishedGmt;

    public static function withData(
        ContentId $id,
        DateTimeInterface $publishedGmt
    ): ContentPublishedGmtWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                ...EventDateTime::payload('content_published_gmt', $publishedGmt)
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->publishedGmt = $publishedGmt;

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

    public function contentPublishedGmt(): DateTimeInterface
    {
        if (!isset($this->publishedGmt)) {
            $this->publishedGmt = EventDateTime::parse($this->payload(), 'content_published_gmt');
        }

        return $this->publishedGmt;
    }
}
