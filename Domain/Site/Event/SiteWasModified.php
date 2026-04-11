<?php

declare(strict_types=1);

namespace App\Domain\Site\Event;

use App\Domain\Site\ValueObject\SiteId;
use App\Shared\Services\DateTime;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

final class SiteWasModified extends AggregateChanged
{
    private SiteId $id;

    private DateTimeInterface $modified;

    public static function withData(
        SiteId $id,
        DateTimeInterface $modified,
    ): SiteWasModified|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'site_modified' => (string) $modified,
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'site'
            ],
        );

        $event->id = $id;
        $event->modified = $modified;

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

    public function siteModified(): DateTimeInterface
    {
        if (!isset($this->modified)) {
            $this->modified = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['site_modified'])->getDateTime()
            );
        }

        return $this->modified;
    }
}
