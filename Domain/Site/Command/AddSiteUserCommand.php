<?php

declare(strict_types=1);

namespace App\Domain\Site\Command;

use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\ValueObject\UserId;
use Codefy\CommandBus\PropertyCommand;

final class AddSiteUserCommand extends PropertyCommand
{
    public SiteId $siteId;

    public UserId $userId;
}
