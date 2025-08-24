<?php

declare(strict_types=1);

namespace App\Domain\Site\Query;

use App\Domain\Site\Repository\SitesQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

class FindSiteBySlugQueryHandler implements QueryHandler
{
    public function __construct(protected SitesQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     * @param FindSiteBySlugQuery|Query $query
     * @return array|object
     */
    public function handle(FindSiteBySlugQuery|Query $query): array|object
    {
        return $this->repository->findBySlug($query->siteSlug->toNative());
    }
}
