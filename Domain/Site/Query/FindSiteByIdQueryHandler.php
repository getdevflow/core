<?php

declare(strict_types=1);

namespace App\Domain\Site\Query;

use App\Domain\Site\Repository\SitesQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

class FindSiteByIdQueryHandler implements QueryHandler
{
    public function __construct(protected SitesQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @param FindSiteByIdQuery|Query $query
     * @return array|object
     */
    public function handle(FindSiteByIdQuery|Query $query): array|object
    {
        return $this->repository->findById($query->siteId->toNative());
    }
}
