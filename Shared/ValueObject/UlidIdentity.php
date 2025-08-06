<?php

declare(strict_types=1);

namespace App\Shared\ValueObject;

use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Identity\Ulid;
use Qubus\ValueObjects\ValueObject;

final class UlidIdentity extends Ulid implements ValueObject
{
    /**
     * @throws TypeException
     */
    public static function fromString(?string $ulid): self
    {
        return new self($ulid);
    }
}
