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

final class SiteMappingWasChanged extends AggregateChanged
{
    private SiteId $id;

    private StringLiteral $mapping;

    public static function withData(
        SiteId $id,
        StringLiteral $mapping
    ): SiteMappingWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'site_mapping' => $mapping->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'site'
            ],
        );

        $event->id = $id;
        $event->mapping = $mapping;

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

    public function siteMapping(): StringLiteral
    {
        if (!isset($this->mapping)) {
            $this->mapping = StringLiteral::fromNative($this->payload()['site_mapping']);
        }

        return $this->mapping;
    }
}
