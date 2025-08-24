<?php

declare(strict_types=1);

namespace App\Domain\Site\Repository;

interface SitesQueryRepository
{
    public function findById(string $siteId): array|object;

    public function findByKey(string $siteKey): array|object;

    public function findBySlug(string $siteSlug): array|object;

    public function findByOwner(string $siteOwner): array|object;

    public function findAll(): array;
}
