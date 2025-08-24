<?php

declare(strict_types=1);

namespace App\Domain\User\Query;

use App\Domain\User\Repository\UserQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

final class FindUserByIdQueryHandler implements QueryHandler
{
    public function __construct(protected UserQueryRepository $repository)
    {
    }

    public function handle(FindUserByIdQuery|Query $query): array|object
    {
        return $this->repository->findById($query->userId->toNative());
    }
}
