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
                'content_published_gmt' => $publishedGmt->format('Y-m-d H:i:s')
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
            $this->publishedGmt = QubusDateTimeImmutable::parse(
                $this->payload()['content_published_gmt'],
                'GMT'
            );
        }

        return $this->publishedGmt;
    }
}
