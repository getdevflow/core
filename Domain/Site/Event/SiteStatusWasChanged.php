<?php

declare(strict_types=1);

namespace App\Domain\Site\Event;

use App\Domain\Site\ValueObject\SiteId;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class SiteStatusWasChanged extends AggregateChanged
{
    private SiteId $id;

    private StringLiteral $status;

    public static function withData(
        SiteId $id,
        StringLiteral $status
    ): SiteStatusWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'site_status' => $status->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'site'
            ],
        );

        $event->id = $id;
        $event->status = $status;

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

    public function siteStatus(): StringLiteral
    {
        if (!isset($this->status)) {
            $this->status = StringLiteral::fromNative($this->payload()['site_status']);
        }

        return $this->status;
    }
}
