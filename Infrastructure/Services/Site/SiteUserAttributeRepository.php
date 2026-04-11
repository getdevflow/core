<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Site;

interface SiteUserAttributeRepository
{
    public function find(string $siteId, string $userId): ?SiteUserAttribute;

    public function get(string $siteId, string $userId): SiteUserAttribute;

    public function exists(string $siteId, string $userId): bool;

    public function create(SiteUserAttribute $attributes): void;

    public function save(SiteUserAttribute $attributes): void;

    public function delete(string $siteId, string $userId): void;
}
