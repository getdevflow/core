<?php

declare(strict_types=1);

namespace App\Domain\Site\Query;

use App\Domain\Site\Repository\SitesQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

class FindSiteByKeyQueryHandler implements QueryHandler
{
    public function __construct(protected SitesQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @param FindSiteByKeyQuery|Query $query
     * @return array|object
     */
    public function handle(FindSiteByKeyQuery|Query $query): array|object
    {
        return $this->repository->findByKey($query->siteKey->toNative());
    }
}
