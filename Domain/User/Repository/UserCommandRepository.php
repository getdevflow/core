<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Model\User;
use App\Domain\User\ValueObject\UserId;

interface UserCommandRepository
{
    public function save(User $user): void;

    public function update(User $user): void;

    public function destroy(UserId $id): void;

    public function updatePassword(User $user): void;
}
