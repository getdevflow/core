<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

use function strtotime;

final class ContentPublishedWasChanged extends AggregateChanged
{
    private ContentId $id;

    private DateTimeInterface $published;

    public static function withData(
        ContentId $id,
        DateTimeInterface $published,
    ): ContentPublishedWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_published' => (string) $published,
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->published = $published;

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

    public function contentPublished(): DateTimeInterface
    {
        if (!isset($this->published)) {
            $this->published = QubusDateTimeImmutable::parse(
                strtotime($this->payload()['content_published'])
            );
        }

        return $this->published;
    }
}
