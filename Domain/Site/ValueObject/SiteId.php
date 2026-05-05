<?php

declare(strict_types=1);

namespace App\Domain\Site\ValueObject;

use Qubus\Exception\Data\TypeException;
use Qubus\ValueObjects\Identity\Ulid;

class SiteId extends Ulid
{
    /**
     * @throws TypeException
     */
    public static function fromString(string $siteId): SiteId
    {
        return new self(value: $siteId);
    }
}
