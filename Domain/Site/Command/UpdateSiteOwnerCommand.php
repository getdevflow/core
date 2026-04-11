<?php

declare(strict_types=1);

namespace App\Domain\Site\Command;

use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\ValueObject\UserId;
use Codefy\CommandBus\PropertyCommand;
use DateTimeInterface;

final class UpdateSiteOwnerCommand extends PropertyCommand
{
    public SiteId $id;

    public UserId $owner;

    public DateTimeInterface $modified;
}
