<?php

declare(strict_types=1);

namespace App\Domain\User\Query;

use App\Domain\User\Repository\UserQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

final class FindUserByLoginQueryHandler implements QueryHandler
{
    public function __construct(protected UserQueryRepository $repository)
    {
    }

    public function handle(FindUserByLoginQuery|Query $query): array|null|object
    {
        return $this->repository->findByLogin($query->userLogin->toNative());
    }
}
