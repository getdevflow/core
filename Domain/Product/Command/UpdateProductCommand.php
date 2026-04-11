<?php

declare(strict_types=1);

namespace App\Domain\Product\Command;

use App\Domain\Product\ValueObject\ProductId;
use App\Domain\User\ValueObject\UserId;
use App\Shared\ValueObject\ArrayLiteral;
use Codefy\CommandBus\PropertyCommand;
use DateTimeInterface;
use Qubus\ValueObjects\Money\Money;
use Qubus\ValueObjects\Number\IntegerNumber;
use Qubus\ValueObjects\StringLiteral\StringLiteral;

class UpdateProductCommand extends PropertyCommand
{
    public ProductId $id;

    public StringLiteral $title;

    public StringLiteral $slug;

    public StringLiteral $body;

    public UserId $author;

    public StringLiteral $sku;

    public Money $price;

    public StringLiteral $purchaseUrl;

    public IntegerNumber $showInMenu;

    public IntegerNumber $showInSearch;

    public StringLiteral $featuredImage;

    public StringLiteral $status;

    public ArrayLiteral $attribute;

    public DateTimeInterface $published;

    public DateTimeInterface $publishedGmt;

    public DateTimeInterface $modified;

    public DateTimeInterface $modifiedGmt;
}
