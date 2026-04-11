<?php

declare(strict_types=1);

namespace App\Domain\Content\Command;

use App\Domain\Content\ValueObject\ContentId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\CommandBus\PropertyCommand;
use DateTimeInterface;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

class UpdateContentCommand extends PropertyCommand
{
    public ContentId $id;

    public StringLiteral $title;

    public StringLiteral $slug;

    public StringLiteral $body;

    public UserId $author;

    public StringLiteral $type;

    public ?ContentId $parent = null;

    public IntegerNumber $sidebar;

    public IntegerNumber $showInMenu;

    public IntegerNumber $showInSearch;

    public StringLiteral $featuredImage;

    public ArrayLiteral $attribute;

    public StringLiteral $status;

    public DateTimeInterface $published;

    public DateTimeInterface $publishedGmt;

    public DateTimeInterface $modified;

    public DateTimeInterface $modifiedGmt;
}
