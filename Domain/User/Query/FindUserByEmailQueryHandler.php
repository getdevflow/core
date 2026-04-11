<?php

declare(strict_types=1);

namespace App\Domain\User\Query;

use App\Domain\User\Repository\UserQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

final class FindUserByEmailQueryHandler implements QueryHandler
{
    public function __construct(protected UserQueryRepository $repository)
    {
    }

    public function handle(FindUserByEmailQuery|Query $query): array|null|object
    {
        /** @var FindUserByEmailQuery $query */

        return $this->repository->findByEmail($query->email->toNative());
    }
}
