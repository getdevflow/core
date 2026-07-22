<?php

declare(strict_types=1);

namespace App\Shared\ValueObject;

use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Identity\Uuid;
use Qubus\ValueObjects\ValueObject;

final class UuidIdentity extends Uuid implements ValueObject
{
    /**
     * @throws TypeException
     */
    public static function fromString(?string $uuid = null): self
    {
        return new self($uuid);
    }
}
