<?php

declare(strict_types=1);

namespace App\Domain\Site\Command;

use App\Domain\Site\ValueObject\SiteId;
use App\Domain\User\ValueObject\UserId;
use Codefy\CommandBus\PropertyCommand;
use DateTimeInterface;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

final class CreateSiteCommand extends PropertyCommand
{
    public SiteId $id;

    public StringLiteral $key;

    public StringLiteral $name;

    public StringLiteral $slug;

    public StringLiteral $domain;

    public StringLiteral $mapping;

    public StringLiteral $path;

    public UserId $owner;

    public StringLiteral $status;

    public DateTimeInterface $registered;
}
