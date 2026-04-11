<?php

declare(strict_types=1);

namespace App\Domain\Content\Repository;

interface ContentQueryRepository
{
    public function findById(string $id): array|object;

    public function findBySlug(string $slug): array|object;

    public function findByStatus(string $status): array;

    public function findByTypeAndId(string $type, string $id): array|object;

    public function findByType(string $type): array|object;

    public function findByFilters(
        ?string $type = null,
        int $limit = 0,
        ?int $offset = null,
        string $status = 'all'
    ): array;
}
