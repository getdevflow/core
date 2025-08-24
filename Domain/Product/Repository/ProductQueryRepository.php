<?php

declare(strict_types=1);

namespace App\Domain\Product\Repository;

interface ProductQueryRepository
{
    public function findById(string $productId): array|object;

    public function findBySku(string $productSku): array|object;

    public function findBySlug(string $productSlug): array|object;

    public function findByFilters(
        ?string $productSku = null,
        int $limit = 0,
        ?int $offset = null,
        string $status = 'all'
    ): array;
}
