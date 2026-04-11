<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

interface UserQueryRepository
{
    public function findById(string $id): array|object;

    public function findUnique(): array|null|object;

    public function findByEmail(string $email): array|null|object;

    public function findByLogin(string $login): array|null|object;

    public function findByToken(string $token): array|null|object;

    public function findAll(): array;
}
