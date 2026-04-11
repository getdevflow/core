<?php

declare(strict_types=1);

namespace App\Domain\Site\Repository;

interface SitesQueryRepository
{
    public function findById(string $id): array|object;

    public function findByKey(string $key): array|object;

    public function findBySlug(string $slug): array|object;

    public function findByOwner(string $owner): array|object;

    public function findAll(): array;
}
