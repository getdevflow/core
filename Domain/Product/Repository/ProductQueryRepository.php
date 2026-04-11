<?php

declare(strict_types=1);

namespace App\Domain\Product\Repository;

interface ProductQueryRepository
{
    public function findById(string $id): array|object;

    public function findBySku(string $sku): array|object;

    public function findBySlug(string $slug): array|object;

    public function findByFilters(
        ?string $sku = null,
        int $limit = 0,
        ?int $offset = null,
        string $status = 'all'
    ): array;
}
