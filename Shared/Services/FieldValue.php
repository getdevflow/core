<?php

declare(strict_types=1);

namespace App\Shared\Services;

final readonly class FieldValue
{
    public function __construct(
        public bool $exists,
        public mixed $value,
    ) {
    }
}
