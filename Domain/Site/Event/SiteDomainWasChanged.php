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

final class SiteDomainWasChanged extends AggregateChanged
{
    private SiteId $id;

    private StringLiteral $domain;

    public static function withData(
        SiteId $id,
        StringLiteral $domain
    ): SiteDomainWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'site_domain' => $domain->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'site'
            ],
        );

        $event->id = $id;
        $event->domain = $domain;

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

    public function siteDomain(): StringLiteral
    {
        if (!isset($this->domain)) {
            $this->domain = StringLiteral::fromNative($this->payload()['site_domain']);
        }

        return $this->domain;
    }
}
