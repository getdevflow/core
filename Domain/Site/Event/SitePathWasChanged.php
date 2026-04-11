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

final class SitePathWasChanged extends AggregateChanged
{
    private SiteId $id;

    private StringLiteral $path;

    public static function withData(
        SiteId $id,
        StringLiteral $path
    ): SitePathWasChanged|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'site_path' => $path->toNative(),
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'site'
            ],
        );

        $event->id = $id;
        $event->path = $path;

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

    public function sitePath(): StringLiteral
    {
        if (!isset($this->path)) {
            $this->path = StringLiteral::fromNative($this->payload()['site_path']);
        }

        return $this->path;
    }
}
