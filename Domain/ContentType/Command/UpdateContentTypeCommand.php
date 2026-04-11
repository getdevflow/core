<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Command;

use App\Domain\ContentType\ValueObject\ContentTypeId;
use Codefy\CommandBus\PropertyCommand;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

class UpdateContentTypeCommand extends PropertyCommand
{
    public ContentTypeId $id;

    public StringLiteral $title;

    public StringLiteral $slug;

    public StringLiteral $description;
}
