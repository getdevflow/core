<?php

declare(strict_types=1);

namespace App\Domain\Content\Event;

use App\Domain\Content\ValueObject\ContentId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Number\IntegerNumber;

final class ContentShowInSearchWasChanged extends AggregateChanged
{
    private ContentId $id;

    private IntegerNumber $showInSearch;

    public static function withData(
        ContentId $id,
        IntegerNumber $showInSearch,
    ): ContentShowInSearchWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'content_show_in_search' => $showInSearch->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'content',
            ]
        );

        $event->id = $id;
        $event->showInSearch = $showInSearch;

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

    /**
     * @throws TypeException
     */
    public function contentShowInSearch(): IntegerNumber
    {
        if (!isset($this->showInSearch)) {
            $this->showInSearch = IntegerNumber::fromNative($this->payload()['content_show_in_search']);
        }

        return $this->showInSearch;
    }
}
