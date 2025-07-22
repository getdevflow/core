<?php

declare(strict_types=1);

namespace App\Domain\Product\Command;

use App\Domain\Product\ValueObject\ProductId;
use Codefy\CommandBus\PropertyCommand;
use DateTimeInterface;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

class UpdateProductStatusCommand extends PropertyCommand
{
    public ?ProductId $productId = null;

    public ?StringLiteral $productStatus = null;

    public ?DateTimeInterface $productModified = null;

    public ?DateTimeInterface $productModifiedGmt = null;
}
