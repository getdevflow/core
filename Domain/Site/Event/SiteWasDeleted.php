<?php

declare(strict_types=1);

namespace App\Domain\Site\Event;

use App\Domain\Site\ValueObject\SiteId;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;

final class SiteWasDeleted extends AggregateChanged
{
    private SiteId $id;

    public static function withData(
        SiteId $id,
    ): SiteWasDeleted|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'site'
            ],
        );

        $event->id = $id;

        return $event;
    }

    /**
     * @throws TypeException
     */
    public function siteId(): SiteId|AggregateId
    {
        if (!isset($this->id)) {
            $this->id = SiteId::fromString(siteId: $this->aggregateId()->__toString());
        }

        return $this->id;
    }
}
