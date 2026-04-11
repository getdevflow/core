<?php

declare(strict_types=1);

namespace App\Domain\User\Query;

use App\Domain\User\Repository\UserQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

final class FindUserByTokenQueryHandler implements QueryHandler
{
    public function __construct(protected UserQueryRepository $repository)
    {
    }

    public function handle(FindUserByTokenQuery|Query $query): array|null|object
    {
        /** @var FindUserByTokenQuery $query */

        return $this->repository->findByToken($query->token->toNative());
    }
}
