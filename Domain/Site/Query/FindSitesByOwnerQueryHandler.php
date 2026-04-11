<?php

declare(strict_types=1);

namespace App\Domain\Site\Query;

use App\Domain\Site\Repository\SitesQueryRepository;
use Codefy\QueryBus\Query;
use Codefy\QueryBus\QueryHandler;

class FindSitesByOwnerQueryHandler implements QueryHandler
{
    public function __construct(protected SitesQueryRepository $repository)
    {
    }

    /**
     * @inheritDoc
     */
    public function handle(FindSitesByOwnerQuery|Query $query): array|object
    {
        return $this->repository->findByOwner($query->owner->toNative());
    }
}
