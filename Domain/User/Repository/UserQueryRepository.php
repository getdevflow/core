<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

interface UserQueryRepository
{
    public function findById(string $userId): array|object;

    public function findUnique(): array|null|object;

    public function findByEmail(string $userEmail): array|null|object;

    public function findByLogin(string $userLogin): array|null|object;

    public function findByToken(string $userToken): array|null|object;

    public function findAll(): array;
}
