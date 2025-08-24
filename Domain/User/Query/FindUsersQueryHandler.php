<?php

declare(strict_types=1);

namespace App\Domain\User\Query;

use App\Domain\User\Repository\UserQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

final class FindUsersQueryHandler implements QueryHandler
{
    public function __construct(protected UserQueryRepository $repository)
    {
    }

    public function handle(FindUsersQuery|Query $query): array
    {
        return $this->repository->findAll();
    }
}
