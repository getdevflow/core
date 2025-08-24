<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Repository;

interface ContentTypeQueryRepository
{
    public function findById(string $contentTypeId): array|null|object;

    public function findBySlug(string $contentTypeSlug): array|null|object;

    public function findAll(): array;
}
