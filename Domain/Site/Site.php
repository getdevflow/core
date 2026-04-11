<?php

declare(strict_types=1);

namespace App\Domain\Site;

use App\Domain\Site\Event\SiteDomainWasChanged;
use App\Domain\Site\Event\SiteMappingWasChanged;
use App\Domain\Site\Event\SiteNameWasChanged;
use App\Domain\Site\Event\SiteOwnerWasChanged;
use App\Domain\Site\Event\SitePathWasChanged;
use App\Domain\Site\Event\SiteSlugWasChanged;
use App\Domain\Site\Event\SiteStatusWasChanged;
use App\Domain\Site\Event\SiteWasCreated;
use App\Domain\Site\Event\SiteWasDeleted;
use App\Domain\Site\Event\SiteWasModified;
use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\ValueObject\UserId;
use Codefy\Domain\Aggregate\AggregateId;
use Codefy\Domain\Aggregate\AggregateRoot;
use Codefy\Domain\Aggregate\EventSourcedAggregate;
use DateTimeInterface;
use Exception;
use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

use function Qubus\Security\Helpers\t__;
use function Qubus\Support\Helpers\is_null__;

final class Site extends EventSourcedAggregate implements AggregateRoot
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

    private DateTimeInterface $modified;

    public static function createSite(
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
    ): Site {
        $site = self::root($id);

        $site->recordApplyAndPublishThat(
            SiteWasCreated::withData(
                $id,
                $key,
                $name,
                $slug,
                $domain,
                $mapping,
                $path,
                $owner,
                $status,
                $registered,
            )
        );

        return $site;
    }

    public static function fromNative(SiteId $siteId): Site
    {
        return self::root($siteId);
    }

    public function siteId(): SiteId|AggregateId
    {
        return $this->id;
    }

    public function siteKey(): StringLiteral
    {
        return $this->key;
    }

    public function siteName(): StringLiteral
    {
        return $this->name;
    }

    public function siteSlug(): StringLiteral
    {
        return $this->slug;
    }

    public function siteDomain(): StringLiteral
    {
        return $this->domain;
    }

    public function siteMapping(): StringLiteral
    {
        return $this->mapping;
    }

    public function sitePath(): StringLiteral
    {
        return $this->path;
    }

    public function siteOwner(): UserId
    {
        return $this->owner;
    }

    public function siteStatus(): StringLiteral
    {
        return $this->status;
    }

    public function siteRegistered(): DateTimeInterface
    {
        return $this->registered;
    }

    public function siteModified(): DateTimeInterface
    {
        return $this->modified;
    }

    /**
     * @throws Exception
     */
    public function changeSiteName(StringLiteral $siteName): void
    {
        if ($siteName->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Site name cannot be empty.', domain: 'devflow'));
        }

        if ($siteName->equals($this->name)) {
            return;
        }

        $this->recordApplyAndPublishThat(
            SiteNameWasChanged::withData($this->id, $siteName)
        );
    }

    /**
     * @throws Exception
     */
    public function changeSiteSlug(StringLiteral $siteSlug): void
    {
        if ($siteSlug->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Site slug cannot be empty.', domain: 'devflow'));
        }

        if ($siteSlug->equals($this->slug)) {
            return;
        }

        $this->recordApplyAndPublishThat(SiteSlugWasChanged::withData($this->id, $siteSlug));
    }

    /**
     * @throws Exception
     */
    public function changeSiteDomain(StringLiteral $siteDomain): void
    {
        if ($siteDomain->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Site domain cannot be empty.', domain: 'devflow'));
        }

        if ($siteDomain->equals($this->domain)) {
            return;
        }

        $this->recordApplyAndPublishThat(SiteDomainWasChanged::withData($this->id, $siteDomain));
    }

    /**
     * @throws Exception
     */
    public function changeSiteMapping(StringLiteral $siteMapping): void
    {
        if ($siteMapping->equals($this->mapping)) {
            return;
        }

        $this->recordApplyAndPublishThat(SiteMappingWasChanged::withData($this->id, $siteMapping));
    }

    /**
     * @throws Exception
     */
    public function changeSitePath(StringLiteral $sitePath): void
    {
        if ($sitePath->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Site path cannot be empty.', domain: 'devflow'));
        }

        if ($sitePath->equals($this->path)) {
            return;
        }

        $this->recordApplyAndPublishThat(SitePathWasChanged::withData($this->id, $sitePath));
    }

    /**
     * @throws Exception
     */
    public function changeSiteOwner(UserId $siteOwner): void
    {
        if ($siteOwner->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Owner cannot be empty.', domain: 'devflow'));
        }

        if ($siteOwner->equals($this->owner)) {
            return;
        }

        $this->recordApplyAndPublishThat(SiteOwnerWasChanged::withData($this->id, $siteOwner));
    }

    /**
     * @throws Exception
     */
    public function changeSiteStatus(StringLiteral $siteStatus): void
    {
        if ($siteStatus->isEmpty()) {
            throw new Exception(message: t__(msgid: 'Site status cannot be empty.', domain: 'devflow'));
        }

        if ($siteStatus->equals($this->status)) {
            return;
        }

        $this->recordApplyAndPublishThat(SiteStatusWasChanged::withData($this->id, $siteStatus));
    }

    /**
     * @throws Exception
     */
    public function changeSiteModified(DateTimeInterface $siteModified): void
    {
        if (empty($this->modified)) {
            return;
        }

        if ($this->modified->getTimestamp() === $siteModified->getTimestamp()) {
            return;
        }

        $this->recordApplyAndPublishThat(SiteWasModified::withData($this->id, $siteModified));
    }

    /**
     * @throws Exception
     */
    public function changeSiteDeleted(SiteId $siteId): void
    {
        if (is_null__($siteId)) {
            throw new Exception(message: t__(msgid: 'Site id cannot be null.', domain: 'devflow'));
        }

        if (!$siteId->equals($this->id)) {
            return;
        }

        $this->recordApplyAndPublishThat(SiteWasDeleted::withData($this->id));
    }

    /**
     * @throws TypeException
     */
    public function whenSiteWasCreated(SiteWasCreated $event): void
    {
        $this->id = $event->siteId();
        $this->key = $event->siteKey();
        $this->name = $event->siteName();
        $this->slug = $event->siteSlug();
        $this->domain = $event->siteDomain();
        $this->mapping = $event->siteMapping();
        $this->path = $event->sitePath();
        $this->owner = $event->siteOwner();
        $this->status = $event->siteStatus();
        $this->registered = $event->siteRegistered();
    }

    /**
     * @throws TypeException
     */
    public function whenSiteNameWasChanged(SiteNameWasChanged $event): void
    {
        $this->id = $event->siteId();
        $this->name = $event->siteName();
    }

    /**
     * @throws TypeException
     */
    public function whenSiteSlugWasChanged(SiteSlugWasChanged $event): void
    {
        $this->id = $event->siteId();
        $this->slug = $event->siteSlug();
    }

    /**
     * @throws TypeException
     */
    public function whenSiteDomainWasChanged(SiteDomainWasChanged $event): void
    {
        $this->id = $event->siteId();
        $this->domain = $event->siteDomain();
    }

    /**
     * @throws TypeException
     */
    public function whenSiteMappingWasChanged(SiteMappingWasChanged $event): void
    {
        $this->id = $event->siteId();
        $this->mapping = $event->siteMapping();
    }

    /**
     * @throws TypeException
     */
    public function whenSitePathWasChanged(SitePathWasChanged $event): void
    {
        $this->id = $event->siteId();
        $this->path = $event->sitePath();
    }

    /**
     * @throws TypeException
     */
    public function whenSiteOwnerWasChanged(SiteOwnerWasChanged $event): void
    {
        $this->id = $event->siteId();
        $this->owner = $event->siteOwner();
    }

    /**
     * @throws TypeException
     */
    public function whenSiteStatusWasChanged(SiteStatusWasChanged $event): void
    {
        $this->id = $event->siteId();
        $this->status = $event->siteStatus();
    }

    /**
     * @throws TypeException
     */
    public function whenSiteWasModified(SiteWasModified $event): void
    {
        $this->id = $event->siteId();
        $this->modified = $event->siteModified();
    }

    /**
     * @throws TypeException
     */
    public function whenSiteWasDeleted(SiteWasDeleted $event): void
    {
        $this->id = $event->siteId();
    }
}
