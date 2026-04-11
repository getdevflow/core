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

final class ContentModifiedGmtWasChanged extends AggregateChanged
{
    private ContentId $id;

    private DateTimeInterface $modifiedGmt;

    public static function withData(
        ContentId $id,
        DateTimeInterface $modifiedGmt
    ): ContentModifiedGmtWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_modified_gmt' => (string) $modifiedGmt
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->modifiedGmt = $modifiedGmt;

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

    public function contentModifiedGmt(): DateTimeInterface
    {
        if (!isset($this->modifiedGmt)) {
            $this->modifiedGmt = QubusDateTimeImmutable::parse(
                strtotime($this->payload()['content_modified_gmt']),
                'GMT'
            );
        }

        return $this->modifiedGmt;
    }
}
