<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\User;

interface UserAttributeRepository
{
    public function find(string $siteId, string $userId): ?UserAttributeBag;

    public function get(string $siteId, string $userId, string $key, mixed $default = null): mixed;

    public function exists(string $siteId, string $userId): bool;

    public function patch(string $siteId, string $userId, callable $callback): UserAttributeBag;

    public function create(UserAttributeBag $attribute): void;

    public function save(UserAttributeBag $attribute): void;

    public function delete(string $siteId, string $userId): void;
}
