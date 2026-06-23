<?php

declare(strict_types=1);

namespace App\Domain\Content\Command;

use App\Domain\Content\ValueObject\ContentId;
use Codefy\CommandBus\PropertyCommand;
use DateTimeInterface;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

class RestoreRevisionCommand extends PropertyCommand
{
    public ContentId $id;

    public StringLiteral $title;

    public StringLiteral $slug;

    public StringLiteral $body;

    public DateTimeInterface $modified;

    public DateTimeInterface $modifiedGmt;
}
