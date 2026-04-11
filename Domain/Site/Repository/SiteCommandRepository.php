<?php

declare(strict_types=1);

namespace App\Domain\Site\Repository;

use App\Domain\Site\Model\Site;
use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\ValueObject\UserId;

interface SiteCommandRepository
{
    public function save(Site $site): void;

    public function update(Site $site): void;

    public function attributeSiteUser(SiteId $siteId, UserId $authorId, UserId $assignId): void;

    public function remove(SiteId $siteId, UserId $userId): void;

    public function destroy(SiteId $id): void;

    public function updateOwner(Site $site): void;
}
