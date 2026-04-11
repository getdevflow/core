<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class ContentTitleWasChanged extends AggregateChanged
{
    private ContentId $id;

    private StringLiteral $title;

    public static function withData(
        ContentId $id,
        StringLiteral $title,
    ): ContentTitleWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_title' => $title->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->title = $title;

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

    public function contentTitle(): StringLiteral
    {
        if (!isset($this->title)) {
            $this->title = StringLiteral::fromNative($this->payload()['content_title']);
        }

        return $this->title;
    }
}
