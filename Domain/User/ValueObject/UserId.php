<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Identity\Ulid;

class UserId extends Ulid
{
    /**
     * @throws TypeException
     */
    public static function fromString(string $userId): UserId
    {
        return new self(value: $userId);
    }
}
