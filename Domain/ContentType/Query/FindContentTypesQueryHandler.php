<?php

declare(strict_types=1);

namespace App\Domain\ContentType\Query;

use App\Domain\ContentType\Repository\ContentTypeQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

class FindContentTypesQueryHandler implements QueryHandler
{
    public function __construct(protected ContentTypeQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @param FindContentTypesQuery|Query $query
     * @return array
     */
    public function handle(FindContentTypesQuery|Query $query): array
    {
        return $this->repository->findAll();
    }
}
