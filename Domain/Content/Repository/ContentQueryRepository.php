<?php

declare(strict_types=1);

namespace App\Domain\Content\Repository;

interface ContentQueryRepository
{
    public function findById(string $contentId): array|object;

    public function findBySlug(string $contentSlug): array|object;

    public function findByStatus(string $contentStatus): array;

    public function findByTypeAndId(string $contentTypeSlug, string $contentId): array|object;

    public function findByType(string $contentTypeSlug): array|object;

    public function findByFilters(
        ?string $contentTypeSlug = null,
        int $limit = 0,
        ?int $offset = null,
        string $status = 'all'
    ): array;
}
