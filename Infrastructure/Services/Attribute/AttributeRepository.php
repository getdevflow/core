<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Attribute;

interface AttributeRepository
{
    public function getAttribute(string $type, string $id): AttributeBag;

    public function saveAttribute(string $type, string $id, AttributeBag $attribute): void;

    public function patchAttribute(string $type, string $id, callable $callback): AttributeBag;
}
