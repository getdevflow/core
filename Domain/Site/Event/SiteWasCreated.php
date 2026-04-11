<?php

declare(strict_types=1);

namespace App\Domain\Site\Event;

use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\Services\DateTime;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\EventSourcing\AggregateChanged;
use Codefy\Domain\EventSourcing\DomainEvent;
use Codefy\Domain\Metadata;
use DateTimeInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Support\DateTime\QubusDateTimeImmutable;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class SiteWasCreated extends AggregateChanged
{
    private SiteId $id;

    private StringLiteral $key;

    private StringLiteral $name;

    private StringLiteral $slug;

    private StringLiteral $domain;

    private StringLiteral $mapping;

    private StringLiteral $path;

    private UserId $owner;

    private StringLiteral $status;

    private DateTimeInterface $registered;

    public static function withData(
        SiteId $id,
        StringLiteral $key,
        StringLiteral $name,
        StringLiteral $slug,
        StringLiteral $domain,
        StringLiteral $mapping,
        StringLiteral $path,
        UserId $owner,
        StringLiteral $status,
        DateTimeInterface $registered,
    ): SiteWasCreated|DomainEvent|AggregateChanged {
        $event = self::occur(
            aggregateId: $id,
            payload: [
                'site_id' => $id->toNative(),
                'site_key' => $key->toNative(),
                'site_name' => $name->toNative(),
                'site_slug' => $slug->toNative(),
                'site_domain' => $domain->toNative(),
                'site_mapping' => $mapping->toNative(),
                'site_path' => $path->toNative(),
                'site_owner' => $owner->toNative(),
                'site_status' => $status->toNative(),
                'site_registered' => (string) $registered,
            ],
            metadata: [
                Metadata::AGGREGATE_TYPE => 'site',
            ],
        );

        $event->id = $id;
        $event->key = $key;
        $event->name = $name;
        $event->slug = $slug;
        $event->domain = $domain;
        $event->mapping = $mapping;
        $event->path = $path;
        $event->owner = $owner;
        $event->status = $status;
        $event->registered = $registered;

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

    public function siteKey(): StringLiteral
    {
        if (!isset($this->key)) {
            $this->key = StringLiteral::fromNative($this->payload()['site_key']);
        }

        return $this->key;
    }

    public function siteName(): StringLiteral
    {
        if (!isset($this->name)) {
            $this->name = StringLiteral::fromNative($this->payload()['site_name']);
        }

        return $this->name;
    }

    public function siteSlug(): StringLiteral
    {
        if (!isset($this->slug)) {
            $this->slug = StringLiteral::fromNative($this->payload()['site_slug']);
        }

        return $this->slug;
    }

    public function siteDomain(): StringLiteral
    {
        if (!isset($this->domain)) {
            $this->domain = StringLiteral::fromNative($this->payload()['site_domain']);
        }

        return $this->domain;
    }

    public function siteMapping(): StringLiteral
    {
        if (!isset($this->mapping)) {
            $this->mapping = StringLiteral::fromNative($this->payload()['site_mapping']);
        }

        return $this->mapping;
    }

    public function sitePath(): StringLiteral
    {
        if (!isset($this->path)) {
            $this->path = StringLiteral::fromNative($this->payload()['site_path']);
        }

        return $this->path;
    }

    /**
     * @throws TypeException
     */
    public function siteOwner(): UserId
    {
        if (!isset($this->owner)) {
            $this->owner = UserId::fromString($this->payload()['site_owner']);
        }

        return $this->owner;
    }

    public function siteStatus(): StringLiteral
    {
        if (!isset($this->status)) {
            $this->status = StringLiteral::fromNative($this->payload()['site_status']);
        }

        return $this->status;
    }

    public function siteRegistered(): DateTimeInterface
    {
        if (!isset($this->registered)) {
            $this->registered = QubusDateTimeImmutable::createFromInterface(
                new DateTime($this->payload()['site_registered'])->getDateTime()
            );
        }

        return $this->registered;
    }
}
